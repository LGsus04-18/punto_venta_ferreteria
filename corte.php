<?php
    require_once "config/conexion.php";

    if (!isset($_SESSION['usuario'])) {
        header("Location: login.php");
        exit();
    }
    $mensaje_corte = "";
    $tipo_mensaje = "";
    // ========================================================
    // 1. OBTENER EL FONDO INICIAL (Último registro guardado)
    // ========================================================
    $sql_ultimo_fondo = "SELECT monto_inicial_siguiente_dia FROM cortes_caja ORDER BY id_corte DESC LIMIT 1";
    $res_fondo = $conexion->query($sql_ultimo_fondo);
    if ($res_fondo && $res_fondo->num_rows > 0) {
        $fila_fondo = $res_fondo->fetch_assoc();
        $monto_inicial_caja = floatval($fila_fondo['monto_inicial_siguiente_dia']);
    } else {
        $monto_inicial_caja = 1000.00; 
    }
    // ========================================================
    // 2. CALCULAR LAS VENTAS ACTIVAS DESDE EL SERVIDOR
    // ========================================================
    $sql_ventas = "SELECT SUM(total) as total_ventas FROM ventas"; 
    $res_ventas = $conexion->query($sql_ventas);
    $fila_ventas = $res_ventas->fetch_assoc();
    $ventas_del_dia = isset($fila_ventas['total_ventas']) ? floatval($fila_ventas['total_ventas']) : 0.00;
    $monto_teorico_final = $monto_inicial_caja + $ventas_del_dia;
    // ========================================================
    // 3. PROCESAR EL FORMULARIO (PURO PHP)
    // ========================================================
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['btn_hacer_corte'])) {
        if ($ventas_del_dia <= 0) {
            $mensaje_corte = "No se puede realizar el corte porque no hay ventas registradas en el turno actual.";
            $tipo_mensaje = "error";
        } else {
            $id_user = $_SESSION['id_usuario'] ?? 1; 
            $monto_siguiente_dia = isset($_POST['monto_siguiente_dia']) ? floatval($_POST['monto_siguiente_dia']) : 0.00;
            // Capturar cantidades físicas de monedas y billetes
            $b1000 = (int)($_POST['b1000'] ?? 0);
            $b500  = (int)($_POST['b500'] ?? 0);
            $b200  = (int)($_POST['b200'] ?? 0);
            $b100  = (int)($_POST['b100'] ?? 0);
            $b50   = (int)($_POST['b50'] ?? 0);
            $b20   = (int)($_POST['b20'] ?? 0);
            $m10   = (int)($_POST['m10'] ?? 0);
            $m5    = (int)($_POST['m5'] ?? 0);
            $m2    = (int)($_POST['m2'] ?? 0);
            $m1    = (int)($_POST['m1'] ?? 0);
            $m05   = (int)($_POST['m05'] ?? 0);
            // PHP hace el cálculo matemático del dinero real contado en el servidor
            $efectivo_real = ($b1000 * 1000) + ($b500 * 500) + ($b200 * 200) + ($b100 * 100) + ($b50 * 50) + ($b20 * 20) + ($m10 * 10) + ($m5 * 5) + ($m2 * 2) + ($m1 * 1) + ($m05 * 0.5);
            // Calcular la diferencia (Faltante / Sobrante)
            $diferencia = $efectivo_real - $monto_teorico_final;
            $conexion->begin_transaction();
            try {
                // A) Insertar reporte en cortes_caja
                $sql_guardar_corte = "INSERT INTO cortes_caja (monto_inicial, total_ventas_efectivo, efectivo_real_contado, diferencia, monto_inicial_siguiente_dia, id_usuario, fecha_hora) 
                                    VALUES (?, ?, ?, ?, ?, ?, NOW())";
                $stmt = $conexion->prepare($sql_guardar_corte);
                $stmt->bind_param("dddddi", $monto_inicial_caja, $ventas_del_dia, $efectivo_real, $diferencia, $monto_siguiente_dia, $id_user);
                $stmt->execute();
                $id_corte_generado = $conexion->insert_id;
                // B) Clonar los registros detallados de hoy al historial_ventas
                $sql_respaldo = "INSERT INTO historial_ventas (id_usuario, id_venta, total, fecha, id_corte) 
                                SELECT id_usuario, id_venta, total, fecha_hora, ? FROM ventas";
                $stmt_respaldo = $conexion->prepare($sql_respaldo);
                $stmt_respaldo->bind_param("i", $id_corte_generado);
                $stmt_respaldo->execute();
                // C) Vaciar la tabla ventas activa
                $sql_limpiar_ventas = "DELETE FROM ventas";
                $conexion->query($sql_limpiar_ventas);
                $conexion->commit();
                // Redireccionar al archivo encargado de escupir las descargas
                header("Location: procesar_reportes.php?id_corte=" . $id_corte_generado);
                exit();
            } catch (Exception $e) {
                $conexion->rollback();
                $mensaje_corte = "Error al procesar el cierre: " . $e->getMessage();
                $tipo_mensaje = "error";
            }
        }
    }
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Los Chicarcas - Desglose de Caja</title>
    <link rel="stylesheet" href="css/estilos.css">
    <style>
        .contenedor-principal { display: flex; min-height: calc(100vh - 60px); width: 100%; position: relative; overflow-y: auto; }
        .contenido-pagina { flex-grow: 1; padding: 30px; background-color: #f1f5f9; height: auto; overflow: visible; }
        .contenedor-corte { background: #ffffff; padding: 25px; border-radius: 12px; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); margin-bottom: 40px; }
        .seccion-denominaciones { display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin-bottom: 25px; }
        .columna-dinero h3 { margin-top: 0; padding-bottom: 8px; border-bottom: 2px solid #e0532b; color: #1e293b; font-size: 16px; }
        .fila-denominacion { display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px; }
        .fila-denominacion label { font-weight: 600; color: #475569; font-size: 14px; width: 120px; }
        .input-cantidad { width: 100px; padding: 8px; border: 1px solid #cbd5e1; border-radius: 6px; text-align: center; font-size: 15px; }
        .input-cantidad:focus { border-color: #e0532b; outline: none; }
        .input-siguiente { width: 100%; padding: 12px; border: 2px solid #fcd34d; border-radius: 8px; box-sizing: border-box; font-size: 16px; }
        .btn-enviar-corte { background-color: #e0532b; color: white; border: none; padding: 16px; width: 100%; border-radius: 8px; font-size: 16px; font-weight: bold; cursor: pointer; margin-top: 15px; }
        .btn-enviar-corte:hover { background-color: #c4411d; }
        .alerta.error { background-color: #fee2e2; color: #991b1b; padding: 15px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #fca5a5; font-weight: 500; }
    </style>
</head>
<body>
    <input type="checkbox" id="toggle-sidebar-check" style="display: none;">
        <label for="toggle-sidebar-check" class="btn-hamburguesa-toggle" style="position: fixed; top: 22px; left: 25px; z-index: 2000; font-size: 26px; color: #FFFFFF !important; cursor: pointer; user-select: none;">☰</label> 


    <input type="checkbox" id="toggle-sidebar-check" style="display: none;">
    <?php include "componentes/header.php"; ?>
    <div class="contenedor-principal">
        <?php include "componentes/sidebar.php"; ?>
        <main class="contenido-pagina">
            <div style="max-width: 750px; margin: 0 auto;">
                <?php if (!empty($mensaje_corte)): ?>
                    <div class="alerta <?php echo $tipo_mensaje; ?>">
                        <?php echo $mensaje_corte; ?>
                    </div>
                <?php endif; ?>
                <div class="contenedor-corte">
                    <h2 style="margin-top: 0; margin-bottom: 25px; color: #1e293b; text-align: center;">📟 Arqueo y Corte de Caja</h2>
                    <form action="" method="POST">
                        <div class="seccion-denominaciones">
                            <!-- COLUMNA BILLETES -->
                            <div class="columna-dinero">
                                <h3 style="text-align: center;">Billetes</h3>
                                <div class="fila-denominacion"><label>$1,000 MXN:</label><input type="number" min="0" name="b1000" class="input-cantidad" placeholder="0"></div>
                                <div class="fila-denominacion"><label>$500 MXN:</label><input type="number" min="0" name="b500" class="input-cantidad" placeholder="0"></div>
                                <div class="fila-denominacion"><label>$200 MXN:</label><input type="number" min="0" name="b200" class="input-cantidad" placeholder="0"></div>
                                <div class="fila-denominacion"><label>$100 MXN:</label><input type="number" min="0" name="b100" class="input-cantidad" placeholder="0"></div>
                                <div class="fila-denominacion"><label>$50 MXN:</label><input type="number" min="0" name="b50" class="input-cantidad" placeholder="0"></div>
                                <div class="fila-denominacion"><label>$20 MXN:</label><input type="number" min="0" name="b20" class="input-cantidad" placeholder="0"></div>
                            </div>
                            <!-- COLUMNA MONEDAS -->
                            <div class="columna-dinero">
                                <h3 style="text-align: center;">Monedas</h3>
                                <div class="fila-denominacion"><label>$10 MXN:</label><input type="number" min="0" name="m10" class="input-cantidad" placeholder="0"></div>
                                <div class="fila-denominacion"><label>$5 MXN:</label><input type="number" min="0" name="m5" class="input-cantidad" placeholder="0"></div>
                                <div class="fila-denominacion"><label>$2 MXN:</label><input type="number" min="0" name="m2" class="input-cantidad" placeholder="0"></div>
                                <div class="fila-denominacion"><label>$1 MXN:</label><input type="number" min="0" name="m1" class="input-cantidad" placeholder="0"></div>
                                <div class="fila-denominacion"><label>50¢ MXN:</label><input type="number" min="0" name="m05" class="input-cantidad" placeholder="0"></div>
                            </div>
                        </div>
                        <!-- MONTO PARA EL DÍA SIGUIENTE -->
                        <div style="background: #fffbeb; padding: 18px; border-radius: 8px; border: 1px solid #fef3c7; margin-bottom: 20px;">
                            <label for="monto_siguiente_dia" style="display: block; font-weight: bold; margin-bottom: 8px; color: #78350f; font-size: 14px;">¿Cuánto efectivo se queda en caja para cambio mañana?
                            </label>
                            <input type="number" step="0.01" id="monto_siguiente_dia" name="monto_siguiente_dia" class="input-siguiente" required placeholder="Ej: 1000.00">
                        </div>

                        <button type="submit" name="btn_hacer_corte" class="btn-enviar-corte">🔒 Realizar Cierre de Caja</button>
                    </form>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
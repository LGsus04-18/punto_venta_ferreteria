<?php
    require_once "config/conexion.php";

    if (!isset($_SESSION['usuario'])) {
        header("Location: login.php");
        exit();
    }

    if (!isset($_GET['id_corte']) || empty($_GET['id_corte'])) {
        die("Error: ID de corte no especificado.");
    }

    $id_corte = (int)$_GET['id_corte'];

    // ========================================================
    // Obtenemos datos del ultimo corte de caja efectuado
    // ========================================================
    $sql_corte = "SELECT * FROM cortes_caja WHERE id_corte = ?";

    $stmt = $conexion->prepare($sql_corte);
    $stmt->bind_param("i", $id_corte);
    $stmt->execute();
    $resultado_corte = $stmt->get_result();

    if ($resultado_corte->num_rows === 0) {
        die("Error: El corte de caja no existe.");
    }
    $corte = $resultado_corte->fetch_assoc();

    // ========================================================
    // Obtener las ventas del ultimo corte
    // ========================================================
    $sql_historial = "SELECT id_venta, total, fecha FROM historial_ventas WHERE id_corte = ?";
    $stmt_hist = $conexion->prepare($sql_historial);
    $stmt_hist->bind_param("i", $id_corte);
    $stmt_hist->execute();
    $ventas_historial = $stmt_hist->get_result();

    // ========================================================
    // ACCIÓN: GENERAR EXCEL (.CSV NATIVO)
    // ========================================================
    if (isset($_GET['descargar']) && $_GET['descargar'] === 'excel') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="Corte_Caja_' . $id_corte . '_' . date('Ymd') .
        '.csv"');

        // Salida directa al buffer de descarga
        $output = fopen('php://output', 'w');

        // UTF-8 para que Excel reconozca los acentos correctamente
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

        // Encabezados del Reporte de Caja
        fputcsv($output, ['REPORTE DE CORTE DE CAJA - FERRETERIA "LOS CHICARCAS"']);
        fputcsv($output, ['Folio Corte:', $corte['id_corte']]);
        fputcsv($output, ['Fecha Cierre:', $corte['fecha_hora']]);
        fputcsv($output, ['Atendió:', $_SESSION['usuario']]);
        fputcsv($output, []);

        // Tabla de balances generales
        fputcsv($output, ['CONCEPTO', 'MONTO']);
        fputcsv($output, ['(+) Fondo Inicial de Caja', '$' . number_format($corte['monto_inicial'], 2)]);
        fputcsv($output, ['(+) Ventas Totales del Turno', '$' .
        number_format($corte['total_ventas_efectivo'], 2)]);
        fputcsv($output, ['(=) Efectivo Esperado (Sistema)', '$' . number_format($corte['monto_inicial'] +
        $corte['total_ventas_efectivo'], 2)]);
        fputcsv($output, ['(=) Efectivo Físico Contado', '$' .
        number_format($corte['efectivo_real_contado'], 2)]);
        fputcsv($output, ['Diferencia (Faltante/Sobrante)', '$' . number_format($corte['diferencia'], 2)]);
        fputcsv($output, ['Fondo Dejado para el Día Siguiente', '$' .
        number_format($corte['monto_inicial_siguiente_dia'], 2)]);
        fputcsv($output, []); 

        // Tabla del desglose detallado de ventas
        fputcsv($output, ['DETALLE DE VENTAS ARCHIVADAS EN ESTE CORTE']);
        fputcsv($output, ['Folio Venta', 'Fecha y Hora', 'Total Cobrado']);
        while ($v = $ventas_active = $ventas_historial->fetch_assoc()) {
            fputcsv($output, [
            $v['id_venta'],
            $v['fecha'],
            '$' . number_format($v['total'], 2)
            ]);
        }

        fclose($output);
        exit();
    }

    // ========================================================
    //  GENERAR PDF (HTML COMPATIBLE CON IMPRESIÓN)
    // ========================================================
    if (isset($_GET['descargar']) && $_GET['descargar'] === 'pdf') {
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Ticket de Corte #<?php echo $id_corte; ?></title>
    <style>
        body { font-family: 'Courier New', Courier, monospace; width: 300px; margin: 0; padding: 10px;
        font-size: 12px; color: #000; }
        .ticket-header { text-align: center; margin-bottom: 15px; border-bottom: 1px dashed #000;
        padding-bottom: 5px; }
        .ticket-header h2 { margin: 0; font-size: 16px; }
        .fila-ticket { display: flex; justify-content: space-between; margin-bottom: 5px; }
        .total-destacado { font-weight: bold; border-top: 1px dashed #000; padding-top: 4px; margin-top:
        5px; }
        .tabla-ventas { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .tabla-ventas th { text-align: left; border-bottom: 1px solid #000; }
        .no-print-btn { background: #e0532b; color: white; border: none; padding: 10px; width: 100%;
        font-weight: bold; border-radius: 4px; cursor: pointer; margin-top: 20px; }
        @media print { .no-print-btn { display: none; } }
    </style>
</head>
<body onload="window.print();">
    <div class="ticket-header">
        <h2>LOS CHICARCAS</h2>
        <p>*** REPORTE DE CIERRE DE CAJA ***</p>
    </div>

    <div class="fila-ticket">
        <span>Folio Corte:</span> 
        <span>#<?php echo $corte['id_corte'];?></span>
    </div>

    <div class="fila-ticket">
        <span>Fecha:</span> 
        <span><?php echo $corte['fecha_hora']; ?></span>
    </div>

    <div class="fila-ticket">
        <span>Usuario:</span> 
        <span><?php echo $_SESSION['usuario']; ?></span>
    </div>

    <hr style="border: none; border-top: 1px dashed #000;">

    <div class="fila-ticket">
        <span>(+) Fondo Inicial:</span> 
        <span>$<?php echo number_format($corte['monto_inicial'], 2); ?></span>
    </div>

    <div class="fila-ticket">
        <span>(+) Ventas Turno:</span> 
        <span>$<?php echo number_format($corte['total_ventas_efectivo'], 2); ?></span>
    </div>

    <div class="fila-ticket total-destacado">
        <span>(=) Esperado:</span> 
        <span>$<?php echo number_format($corte['monto_inicial'] + $corte['total_ventas_efectivo'], 2); ?></span>
    </div>

    <div class="fila-ticket">
        <span>(=) Real Contado:</span> 
        <span>$<?php echo number_format($corte['efectivo_real_contado'], 2); ?></span>
    </div>

    <div class="fila-ticket" style="font-weight: bold;">
        <span>Diferencia:</span>
        <span>$<?php echo number_format($corte['diferencia'], 2); ?></span>
    </div>

    <div class="fila-ticket">
        <span>Queda en Caja:</span> 
        <span>$<?php echo number_format($corte['monto_inicial_siguiente_dia'], 2); ?></span>
    </div>

    <button class="no-print-btn" onclick="window.print();">Imprimir Ticket</button>

    <br>

    <a href="index.php" class="no-print-btn" style="display:block; text-align:center;
    text-decoration:none; background:#64748b;">Volver al Sistema</a>
</body>
</html>
<?php
    exit();
    }
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Descarga de Reportes - Los Chicarcas</title>
    <link rel="stylesheet" href="css/estilos.css">
    <style>
        .contenedor-principal { display: flex; min-height: calc(100vh - 60px); width: 100%; }
        .contenido-pagina { flex-grow: 1; padding: 40px; background-color: #f1f5f9; display: flex;
        justify-content: center; align-items: center; }
        .card-descarga { background: #ffffff; padding: 40px; border-radius: 12px; text-align: center;
        max-width: 450px; width: 100%; border: 1px solid #e2e8f0; box-shadow: 0 10px 15px -3px
        rgba(0,0,0,0.05); }
        .btn-descargar { display: flex; align-items: center; justify-content: center; gap: 10px; padding:
        14px; color: white; font-weight: bold; border-radius: 8px; text-decoration: none; margin-bottom:
        15px; font-size: 15px; }
        .btn-excel { background-color: #16a34a; }
        .btn-excel:hover { background-color: #15803d; }
        .btn-pdf { background-color: #dc2626; }
        .btn-pdf:hover { background-color: #b91c1c; }
    </style>
</head>
<body>
    <?php include "componentes/header.php"; ?>

    <div class="contenedor-principal">
        <?php include "componentes/sidebar.php"; ?>

        <main class="contenido-pagina">
            <div class="card-descarga">
                <h2 style="color:#1e293b; margin-top:0; margin-bottom:10px;">¡Cierre Exitoso!</h2>
                <p style="color:#64748b; font-size:14px; margin-bottom:30px;">El corte de caja número <strong>#<?php echo $id_corte; ?></strong> se guardó de forma segura. Selecciona el formato de exportación:</p>
                <a href="procesar_reportes.php?id_corte=<?php echo $id_corte; ?>&descargar=excel"
                class="btn-descargar btn-excel">Descargar Reporte en Excel (.CSV)</a>
                <a href="procesar_reportes.php?id_corte=<?php echo $id_corte; ?>&descargar=pdf" target="_blank"
                class="btn-descargar btn-pdf"> Ver e Imprimir Ticket de Caja (PDF)</a>
                <hr style="border:none; border-top:1px solid #e2e8f0; margin:25px 0;">
                <a href="index.php" style="color:#64748b; font-weight:600; text-decoration:none; font-size:14px;">‹Volver a Caja Activa</a>
            </div>
        </main>
    </div>
</body>
</html>
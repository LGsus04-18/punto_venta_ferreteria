<?php
require_once "config/conexion.php";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit();
}
require_once "config/permisos.php";
bloquear_restringidos();   // solo administradores / usuarios con acceso completo

date_default_timezone_set('America/Mexico_City');

// ------------------------------------------------------------------
// Procesar: recibir (suma al inventario) o cancelar un pedido
// ------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_pedido = intval($_POST['id_pedido'] ?? 0);
    $accion    = $_POST['accion'] ?? '';

    try {
        $conexion->begin_transaction();

        // Se bloquea la fila para que dos clics seguidos no sumen dos veces
        $st = $conexion->prepare("SELECT cantidad, estatus, producto_solicitado FROM pedidos_proveedor WHERE id_pedido = ? FOR UPDATE");
        $st->bind_param("i", $id_pedido);
        $st->execute();
        $ped = $st->get_result()->fetch_assoc();
        $st->close();

        if (!$ped || strcasecmp($ped['estatus'], 'Pendiente') !== 0) {
            throw new Exception("Ese pedido ya fue procesado o no existe.");
        }

        if ($accion === 'recibir') {
            $cantidad = intval($ped['cantidad']);
            $nombre   = trim($ped['producto_solicitado']);

            // ¿Ya existe en inventario un producto con ese nombre?
            $st = $conexion->prepare("SELECT id_producto, nombre_producto FROM productos WHERE LOWER(TRIM(nombre_producto)) = LOWER(?) LIMIT 1");
            $st->bind_param("s", $nombre);
            $st->execute();
            $prod = $st->get_result()->fetch_assoc();
            $st->close();

            if ($prod) {
                // Ya existe: solo se suman las piezas
                $id_producto = intval($prod['id_producto']);
                $st = $conexion->prepare("UPDATE productos SET stock = stock + ? WHERE id_producto = ?");
                $st->bind_param("ii", $cantidad, $id_producto);
                $st->execute();
                $st->close();
                $texto = "se sumaron $cantidad pzs a «" . $prod['nombre_producto'] . "».";
            } else {
                // Es nuevo: se da de alta en inventario con el precio de venta capturado
                $precio = round(floatval($_POST['precio_venta'] ?? 0), 2);
                if ($precio <= 0) {
                    throw new Exception("Escribe el precio de venta para dar de alta el producto nuevo.");
                }
                $st = $conexion->prepare("INSERT INTO productos (nombre_producto, precio_venta, stock) VALUES (?, ?, ?)");
                $st->bind_param("sdi", $nombre, $precio, $cantidad);
                $st->execute();
                $id_producto = $conexion->insert_id;
                $st->close();
                $texto = "se agregó «$nombre» al inventario con $cantidad pzs a $" . number_format($precio, 2) . ".";
            }

            $st = $conexion->prepare("UPDATE pedidos_proveedor SET estatus = 'Recibido', id_producto = ?, fecha_recepcion = NOW() WHERE id_pedido = ?");
            $st->bind_param("ii", $id_producto, $id_pedido);
            $st->execute();
            $st->close();

            $_SESSION['flash'] = ['ok', "✔ Pedido #$id_pedido recibido: $texto"];

        } elseif ($accion === 'cancelar') {
            $st = $conexion->prepare("UPDATE pedidos_proveedor SET estatus = 'Cancelado' WHERE id_pedido = ?");
            $st->bind_param("i", $id_pedido);
            $st->execute();
            $st->close();

            $_SESSION['flash'] = ['ok', "Pedido #$id_pedido cancelado."];
        } else {
            throw new Exception("Acción no válida.");
        }

        $conexion->commit();
    } catch (Throwable $e) {
        $conexion->rollback();
        $_SESSION['flash'] = ['error', $e->getMessage()];
    }

    header("Location: recibir_pedidos.php");
    exit();
}

// ------------------------------------------------------------------
// Datos para mostrar
// ------------------------------------------------------------------
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$productos = $conexion->query("SELECT id_producto, nombre_producto, stock FROM productos ORDER BY nombre_producto ASC")
                      ->fetch_all(MYSQLI_ASSOC);

$existentes = [];   // nombre en minúsculas => datos del producto
foreach ($productos as $pr) {
    $existentes[mb_strtolower(trim($pr['nombre_producto']))] = $pr;
}

$pendientes = $conexion->query("SELECT id_pedido, fecha, id_proveedor, producto_solicitado, cantidad
                                FROM pedidos_proveedor WHERE estatus = 'Pendiente' ORDER BY fecha ASC")
                       ->fetch_all(MYSQLI_ASSOC);

$recientes = $conexion->query("SELECT id_pedido, producto_solicitado, cantidad, fecha_recepcion
                               FROM pedidos_proveedor WHERE estatus = 'Recibido' ORDER BY fecha_recepcion DESC LIMIT 10")
                      ->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Los Chicarcas - Recepción de Pedidos</title>
    <link rel="stylesheet" href="css/estilos.css">
    <style>
        .rp-aviso { padding: 12px 16px; border-radius: 10px; font-size: 14px; font-weight: 600; margin-bottom: 16px; }
        .rp-aviso.ok    { background: #d1e7dd; color: #0f5132; border: 1px solid #badbcc; }
        .rp-aviso.error { background: #f8d7da; color: #842029; border: 1px solid #f5c2c7; }

        .rp-lista { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 14px; }
        .rp-card { background: #fff; border: 1px solid var(--color-bordes); border-radius: 14px; padding: 16px; box-shadow: 0 4px 6px -1px rgba(0,0,0,.05); display: flex; flex-direction: column; gap: 10px; }
        .rp-card-cab { display: flex; justify-content: space-between; align-items: center; font-size: 12px; color: var(--gris-400); }
        .rp-folio { font-family: var(--mono); font-weight: 700; color: var(--texto-2); }
        .rp-producto { font-size: 16px; font-weight: 700; color: var(--texto); line-height: 1.25; }
        .rp-datos { display: flex; gap: 8px; flex-wrap: wrap; }
        .rp-chip { font-size: 12px; font-weight: 700; padding: 4px 10px; border-radius: 999px; background: #F1F5F9; color: #475569; }
        .rp-chip.cant { background: rgba(255,90,0,.1); color: var(--color-principal-naranja); }
        .rp-card label { font-size: 11px; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: .04em; }
        .rp-card input[type=number] { width: 100%; height: 40px; padding: 0 10px; border: 1px solid var(--color-bordes); border-radius: 8px; font-size: 14px; background: #F8FAFC; }
        .rp-card input[type=number]:focus { outline: none; border-color: var(--color-principal-naranja); box-shadow: 0 0 0 3px rgba(255,90,0,.12); }
        .rp-nota { font-size: 12px; font-weight: 600; padding: 8px 10px; border-radius: 8px; margin-bottom: 8px; }
        .rp-nota.ok { background: #d1e7dd; color: #0f5132; }
        .rp-nota.nuevo { background: #fff3cd; color: #664d03; }
        .rp-card select { width: 100%; height: 40px; padding: 0 10px; border: 1px solid var(--color-bordes); border-radius: 8px; font-size: 14px; background: #F8FAFC; }
        .rp-card select:focus { outline: none; border-color: var(--color-principal-naranja); box-shadow: 0 0 0 3px rgba(255,90,0,.12); }
        .rp-botones { display: flex; gap: 8px; }
        .rp-botones form { margin: 0; }
        .rp-btn-recibir { flex: 1; }
        .rp-btn-recibir .btn-chicarca { width: 100%; background: #16a34a; }
        .rp-btn-recibir .btn-chicarca:hover { background: #15803d; }
        .rp-btn-cancelar button { height: 100%; padding: 0 14px; border: 1px solid #fecaca; background: #fff; color: #dc2626; border-radius: 8px; font-weight: 700; cursor: pointer; }
        .rp-btn-cancelar button:hover { background: #fee2e2; }

        .rp-vacio { padding: 30px; text-align: center; color: var(--gris-400); background: #fff; border: 1px dashed var(--color-bordes); border-radius: 14px; }
        .rp-subtitulo { margin: 28px 0 10px; font-size: 14px; font-weight: 800; color: var(--texto-2); text-transform: uppercase; letter-spacing: .05em; }
        .rp-reciente { display: flex; justify-content: space-between; gap: 10px; padding: 10px 14px; background: #fff; border: 1px solid var(--color-bordes); border-radius: 10px; margin-bottom: 8px; font-size: 13px; }
        .rp-reciente small { color: var(--gris-400); white-space: nowrap; }
    </style>
</head>
<body class="pagina-pedidos">

    <input type="checkbox" id="toggle-sidebar-check" style="display: none;">
    <label for="toggle-sidebar-check" class="btn-hamburguesa-toggle" style="position: fixed; top: 22px; left: 25px; z-index: 2000; font-size: 26px; color: #FFFFFF !important; cursor: pointer; user-select: none;">☰</label>

    <?php include "componentes/header.php"; ?>

    <div class="contenedor-principal">
        <?php include "componentes/sidebar.php"; ?>

        <main class="contenido-pagina">
            <div class="col-izquierda">

                <div class="inv-cabecera">
                    <h2>✅ Recepción de Pedidos (<?php echo count($pendientes); ?> pendientes)</h2>
                </div>

                <div class="tabla-responsiva">

                    <?php if ($flash): ?>
                        <div class="rp-aviso <?php echo $flash[0] === 'ok' ? 'ok' : 'error'; ?>">
                            <?php echo htmlspecialchars($flash[1]); ?>
                        </div>
                    <?php endif; ?>

                    <?php if (empty($pendientes)): ?>
                        <div class="rp-vacio">No hay pedidos pendientes por recibir. 🎉</div>
                    <?php else: ?>
                        <div class="rp-lista">
                            <?php foreach ($pendientes as $p):
                                $solicitado = trim($p['producto_solicitado']);
                                $existente  = $existentes[mb_strtolower($solicitado)] ?? null;
                            ?>
                                <div class="rp-card">
                                    <div class="rp-card-cab">
                                        <span class="rp-folio">Pedido #<?php echo str_pad($p['id_pedido'], 4, '0', STR_PAD_LEFT); ?></span>
                                        <span><?php echo date('d/m/Y H:i', strtotime($p['fecha'])); ?></span>
                                    </div>

                                    <div class="rp-producto"><?php echo htmlspecialchars($solicitado); ?></div>

                                    <div class="rp-datos">
                                        <span class="rp-chip cant"><?php echo intval($p['cantidad']); ?> pzs</span>
                                        <span class="rp-chip">Proveedor #<?php echo intval($p['id_proveedor']); ?></span>
                                    </div>

                                    <form method="POST" action="recibir_pedidos.php"
                                          onsubmit="return confirm('¿Marcar como recibido e ingresar <?php echo intval($p['cantidad']); ?> pzs al inventario?');">
                                        <input type="hidden" name="id_pedido" value="<?php echo (int)$p['id_pedido']; ?>">
                                        <input type="hidden" name="accion" value="recibir">

                                        <?php if ($existente): ?>
                                            <div class="rp-nota ok">✔ Ya está en inventario (hay <?php echo intval($existente['stock']); ?> pzs). Se sumarán <?php echo intval($p['cantidad']); ?> pzs.</div>
                                        <?php else: ?>
                                            <div class="rp-nota nuevo">🆕 Producto nuevo: se agregará al inventario.</div>
                                            <label for="precio-<?php echo (int)$p['id_pedido']; ?>">Precio de venta ($)</label>
                                            <input type="number" id="precio-<?php echo (int)$p['id_pedido']; ?>" name="precio_venta"
                                                   min="0.01" step="0.01" inputmode="decimal" placeholder="0.00" required>
                                        <?php endif; ?>

                                        <div class="rp-botones" style="margin-top:10px;">
                                            <div class="rp-btn-recibir">
                                                <button type="submit" class="btn-chicarca">✔ Recibir e ingresar a inventario</button>
                                            </div>
                                        </div>
                                    </form>

                                    <form method="POST" action="recibir_pedidos.php" class="rp-btn-cancelar"
                                          onsubmit="return confirm('¿Cancelar este pedido?');">
                                        <input type="hidden" name="id_pedido" value="<?php echo (int)$p['id_pedido']; ?>">
                                        <input type="hidden" name="accion" value="cancelar">
                                        <button type="submit" style="width:100%; padding:8px;">✖ Cancelar pedido</button>
                                    </form>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($recientes)): ?>
                        <div class="rp-subtitulo">Recibidos recientemente</div>
                        <?php foreach ($recientes as $r): ?>
                            <div class="rp-reciente">
                                <span><strong>#<?php echo str_pad($r['id_pedido'], 4, '0', STR_PAD_LEFT); ?></strong>
                                      <?php echo htmlspecialchars($r['producto_solicitado']); ?> · <?php echo intval($r['cantidad']); ?> pzs</span>
                                <small><?php echo $r['fecha_recepcion'] ? date('d/m/Y H:i', strtotime($r['fecha_recepcion'])) : ''; ?></small>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>

                </div>
            </div>
        </main>
    </div>

</body>
</html>
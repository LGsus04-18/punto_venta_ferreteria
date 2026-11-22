<?php
require_once "config/conexion.php";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Solo se procesa si viene del formulario del carrito (POST) y hay algo que cobrar
if (!isset($_SESSION['usuario']) || empty($_SESSION['carrito']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: index.php");
    exit();
}

// ------------------------------------------------------------------
// Datos fijos del negocio para el ticket (cámbialos aquí)
// ------------------------------------------------------------------
$tienda = [
    'direccion' => 'Circuito Poetas 37, Cd. Satélite, 53100 Naucalpan de Juárez, Méx.',
    'rfc'       => 'LCHI261031A1',
    'telefono'  => '55 1102 0290',
    
];

/**
 * Código de barras Code 39 en SVG (solo dígitos). No necesita librerías.
 */
function codigo39_svg($texto, $alto = 46)
{
    $patrones = [
        '0' => 'nnnwwnwnn', '1' => 'wnnwnnnnw', '2' => 'nnwwnnnnw',
        '3' => 'wnwwnnnnn', '4' => 'nnnwwnnnw', '5' => 'wnnwwnnnn',
        '6' => 'nnwwwnnnn', '7' => 'nnnwnnwnw', '8' => 'wnnwnnwnn',
        '9' => 'nnwwnnwnn', '*' => 'nwnnwnwnn',
    ];
    $angosto = 2;
    $ancho_w = 5;
    $x = 0;
    $barras = '';
    $cadena = '*' . preg_replace('/\D/', '', $texto) . '*';

    foreach (str_split($cadena) as $caracter) {
        foreach (str_split($patrones[$caracter]) as $j => $elemento) {
            $ancho = ($elemento === 'w') ? $ancho_w : $angosto;
            if ($j % 2 === 0) {
                $barras .= '<rect x="' . $x . '" y="0" width="' . $ancho . '" height="' . $alto . '"/>';
            }
            $x += $ancho;
        }
        $x += $angosto; // espacio entre caracteres
    }
    $x -= $angosto;

    return '<svg xmlns="http://www.w3.org/2000/svg" class="codigo-barras" viewBox="0 0 ' . $x . ' ' . $alto . '"'
         . ' preserveAspectRatio="none" fill="#000" shape-rendering="crispEdges" role="img" aria-label="Código de barras">'
         . $barras . '</svg>';
}

$total_venta = 0;
$total_articulos = 0;
foreach ($_SESSION['carrito'] as $id => $item) {
    $total_venta += ($item['cantidad'] * $item['precio']);
    $total_articulos += intval($item['cantidad']);
}

// Mismo desglose que se muestra en el carrito
$iva_ticket      = $total_venta * 0.16;
$subtotal_ticket = $total_venta - $iva_ticket;

$id_user = intval($_SESSION['id_usuario'] ?? 1);

// Método de pago (se valida: no se confía en lo que manda el navegador)
$etiquetas = [
    'efectivo'      => 'Efectivo',
    'tarjeta'       => 'Tarjeta',
    'transferencia' => 'Transferencia',
];
$metodo_pago = $_POST['metodo_pago'] ?? 'efectivo';
if (!array_key_exists($metodo_pago, $etiquetas)) {
    $metodo_pago = 'efectivo';
}

// Variables para el diseño del ticket
date_default_timezone_set('America/Mexico_City');
$id_venta_generado = 0;
$fecha_venta = date("d/m/Y H:i:s");
$error_venta = "";
$venta_exitosa = false;

// Datos de tarjeta de ejemplo (aún no hay terminal conectada): ****1234 y AID genérico
$tarjeta_cuenta = '************' . str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
$tarjeta_aid    = 'A0000000031010';

// Todo en una transacción por seguridad
$conexion->begin_transaction();

// Efectivo: con cuánto pagó y cambio (se valida también en el servidor)
$monto_recibido = 0;
$cambio = 0;

try {
    if ($metodo_pago === 'efectivo') {
        $monto_recibido = round(floatval($_POST['monto_recibido'] ?? 0), 2);
        if ($monto_recibido + 0.001 < round($total_venta, 2)) {
            throw new Exception("El monto recibido ($" . number_format($monto_recibido, 2) . ") es menor al total ($" . number_format($total_venta, 2) . ").");
        }
        $cambio = round($monto_recibido - $total_venta, 2);
    }

    // Cabecera de la venta (incluye el método de pago)
    $stmt = $conexion->prepare("INSERT INTO ventas (total, id_usuario, metodo_pago) VALUES (?, ?, ?)");
    if (!$stmt) {
        throw new Exception($conexion->error);
    }
    $stmt->bind_param("dis", $total_venta, $id_user, $metodo_pago);
    if (!$stmt->execute()) {
        throw new Exception($stmt->error);
    }
    $id_venta_generado = $conexion->insert_id;
    $stmt->close();

    // Detalles y descuento de inventario
    foreach ($_SESSION['carrito'] as $id_producto => $item) {
        $id_producto = intval($id_producto);
        $cantidad    = intval($item['cantidad']);
        $precio      = floatval($item['precio']);

        $sql_detalle = "INSERT INTO detalle_ventas (id_venta, id_producto, cantidad, precio_unitario)
                        VALUES ($id_venta_generado, $id_producto, $cantidad, $precio)";
        if (!$conexion->query($sql_detalle)) {
            throw new Exception($conexion->error);
        }

        $sql_restar_stock = "UPDATE productos SET stock = stock - $cantidad WHERE id_producto = $id_producto";
        if (!$conexion->query($sql_restar_stock)) {
            throw new Exception($conexion->error);
        }
    }

    $conexion->commit();
    $venta_exitosa = true;

    // Guardamos el carrito para el ticket y dejamos el carrito activo vacío
    $detalles_ticket = $_SESSION['carrito'];
    $_SESSION['carrito'] = array();

} catch (Exception $e) {
    $conexion->rollback();
    $error_venta = "Error al procesar el cobro: " . $e->getMessage();
}

$folio = str_pad($id_venta_generado, 6, '0', STR_PAD_LEFT);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ticket de Venta #<?php echo $folio; ?></title>
    <style>
        .ticket-wrapper { max-width: 300px; margin: 30px auto; background: white; padding: 15px; border: 1px dashed #000; font-family: 'Courier New', Courier, monospace; font-size: 12px; color: #000; line-height: 1.4; }
        .ticket-logo { display: block; width: 150px; margin: 0 auto 6px; filter: grayscale(1) contrast(1.4); -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .linea-divisoria { border-top: 1px dashed #000; margin: 8px 0; }
        .ticket-dato { font-size: 11px; }
        .fila { display: flex; justify-content: space-between; gap: 8px; }
        .total { font-size: 15px; font-weight: bold; }
        table { width: 100%; font-size: 12px; table-layout: fixed; border-collapse: collapse; }
        th { text-align: left; font-size: 11px; padding-bottom: 3px; }
        td { padding: 1px 0; vertical-align: top; }
        .col-cant { width: 14%; }
        .col-art  { width: 54%; }
        .col-prec { width: 32%; text-align: right; }
        td.col-art { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        td.col-prec { white-space: nowrap; }
        .codigo-barras { display: block; width: 210px; height: 46px; margin: 6px auto 2px; }

        /* Ticket para el PDF: fuera de pantalla, ancho de 80 mm, sin borde ni margen.
           Se usa #ticket (y no .ticket-wrapper) porque la librería copia el ticket a otro
           contenedor al generar el PDF, y el selector por id sí viaja con la copia. */
        #ticket-oculto { position: fixed; left: -9999px; top: 0; }
        #ticket { width: 302px; max-width: none; margin: 0; border: none; box-sizing: border-box; }

        .aviso-cobro { max-width: 320px; margin: 80px auto; padding: 20px; text-align: center; font-family: Arial, sans-serif; font-size: 16px; color: #0f5132; background: #d1e7dd; border-radius: 10px; }
        .aviso-cobro a { display: block; margin-top: 10px; font-size: 13px; color: #0f5132; }
    </style>
</head>
<body>

    <?php if ($venta_exitosa): ?>

        <div class="aviso-cobro">
            ✅ Venta registrada<br>Descargando ticket…
            <a href="index.php">Siguiente venta</a>
        </div>

        <!-- TICKET DE COMPRA (fuera de pantalla; se convierte a PDF) -->
        <div id="ticket-oculto">
        <div class="ticket-wrapper" id="ticket">

            <!-- 1. Logo -->
            <img src="img/logo.png" alt="Ferretería Los Chicarcas" class="ticket-logo">

            <!-- 2 a 4. Dirección, RFC y teléfono -->
            <div class="text-center ticket-dato">
                <?php echo htmlspecialchars($tienda['direccion']); ?><br>
                RFC: <?php echo htmlspecialchars($tienda['rfc']); ?><br>
                Tel: <?php echo htmlspecialchars($tienda['telefono']); ?>
            </div>

            <div class="linea-divisoria"></div>

            <!-- 5 a 7. Folio, fecha y quién atendió -->
            <div class="ticket-dato">
                <strong>Folio:</strong> <?php echo $folio; ?><br>
                <strong>Fecha:</strong> <?php echo $fecha_venta; ?><br>
                <strong>Atendió:</strong> <?php echo htmlspecialchars($_SESSION['usuario']); ?>

            </div>

            <div class="linea-divisoria"></div>

            <!-- 8. Artículos: cantidad, nombre y precio en la misma línea -->
            <table>
                <thead>
                    <tr>
                        <th class="col-cant">Cant</th>
                        <th class="col-art">Artículo</th>
                        <th class="col-prec">Precio</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($detalles_ticket as $item): ?>
                        <tr>
                            <td class="col-cant"><?php echo intval($item['cantidad']); ?></td>
                            <td class="col-art"><?php echo htmlspecialchars($item['nombre']); ?></td>
                            <td class="col-prec">$<?php echo number_format($item['cantidad'] * $item['precio'], 2); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div class="linea-divisoria"></div>

            <!-- 9 y 10. Subtotal, IVA y total -->
            <div class="fila"><span>Subtotal:</span><span>$<?php echo number_format($subtotal_ticket, 2); ?></span></div>
            <div class="fila"><span>IVA (16%):</span><span>$<?php echo number_format($iva_ticket, 2); ?></span></div>
            <div class="fila total"><span>TOTAL:</span><span>$<?php echo number_format($total_venta, 2); ?></span></div>

            <div class="linea-divisoria"></div>

            <!-- 11 a 13. Forma de pago -->
            <div class="ticket-dato">
                <div class="fila"><span>Forma de pago:</span><span><?php echo $etiquetas[$metodo_pago]; ?></span></div>
                <?php if ($metodo_pago === 'efectivo'): ?>
                    <div class="fila"><span>Recibido:</span><span>$<?php echo number_format($monto_recibido, 2); ?></span></div>
                    <div class="fila"><strong>Cambio:</strong><strong>$<?php echo number_format($cambio, 2); ?></strong></div>
                <?php elseif ($metodo_pago === 'tarjeta'): ?>
                    <div class="fila"><span>CTA:</span><span><?php echo $tarjeta_cuenta; ?></span></div>
                    <div class="fila"><span>AID:</span><span><?php echo $tarjeta_aid; ?></span></div>
                <?php endif; ?>
            </div>

            <div class="linea-divisoria"></div>

            <!-- 14. Cantidad de artículos -->
            <div class="fila ticket-dato"><span>Artículos:</span><strong><?php echo $total_articulos; ?></strong></div>

            <!-- 15. Código de barras (folio) -->
            <?php echo codigo39_svg($folio); ?>
            <div class="text-center ticket-dato"><?php echo $folio; ?></div>

            <div class="linea-divisoria"></div>

            <!-- 16 a 18. Página web, agradecimiento y política de cambios -->
            <div class="text-center ticket-dato">
                
                <strong>¡Gracias por su compra!</strong><br><br>
                Conserve su ticket para cambios.<br>
                Consulte nuestra página para políticas de devoluciones.
                https://chicarcas.com.mx<br>
            </div>
        </div>
        </div><!-- /ticket-oculto -->

        <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
        <script>
        window.addEventListener('load', async function () {
            const el = document.getElementById('ticket');
            const altoMm = Math.ceil(el.scrollHeight * 25.4 / 96) + 4;
            try {
                await html2pdf().set({
                    margin: 0,
                    filename: 'ticket-<?php echo $folio; ?>.pdf',
                    image: { type: 'jpeg', quality: 0.98 },
                    html2canvas: { scale: 3, useCORS: true },
                    jsPDF: { unit: 'mm', format: [80, altoMm], orientation: 'portrait' }
                }).from(el).save();
            } catch (e) { return; }   // si falla, queda el aviso con el enlace "Siguiente venta"
            setTimeout(function () { window.location.href = 'index.php'; }, 800);
        });
        </script>

    <?php else: ?>
        <div style="max-width: 500px; margin: 50px auto; padding: 20px; background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; border-radius: 5px; font-family: Arial, sans-serif;">
            <h3>❌ No se pudo procesar la venta</h3>
            <p><?php echo htmlspecialchars($error_venta); ?></p>
            <a href="index.php" style="color: #721c24; font-weight: bold;">Regresar al punto de venta</a>
        </div>
    <?php endif; ?>

</body>
</html>
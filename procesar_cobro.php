<?php
    require_once "config/conexion.php";

    if (!isset($_SESSION['usuario']) || empty($_SESSION['carrito'])) {
        header("Location: index.php");
        exit();
    }

    $total_venta = 0;
    foreach ($_SESSION['carrito'] as $id => $item) {
        $total_venta += ($item['cantidad'] * $item['precio']);
    }

    $id_user = $_SESSION['id_usuario'] ?? 1;

    // Variables para el diseño del ticket
    date_default_timezone_set('America/Mexico_City');
    $id_venta_generado = 0;
    $fecha_venta = date("Y-m-d H:i:s");
    $error_venta = "";
    $venta_exitosa = false;

    // concatenamos todo en una transaccion por seguridad
    $conexion->begin_transaction();

    try {
        // Se inserta la cabecera en la tabla 'ventas'
        $sql_venta = "INSERT INTO ventas (total, id_usuario) VALUES ($total_venta, $id_user)";
        $conexion->query($sql_venta);
        
        // Obtenemos el número de folio (ID) de la venta que se acaba de generar
        $id_venta_generado = $conexion->insert_id;

        // Se recorre el carrito para meter los detalles y restar el stock
        foreach ($_SESSION['carrito'] as $id_producto => $item) {
            $cantidad = $item['cantidad'];
            $precio = $item['precio'];

            // Insertar en 'detalle_ventas'
            $sql_detalle = "INSERT INTO detalle_ventas (id_venta, id_producto, cantidad, precio_unitario) 
                            VALUES ($id_venta_generado, $id_producto, $cantidad, $precio)";
            $conexion->query($sql_detalle);

            // Se resta el inventario en la tabla 'productos'
            $sql_restar_stock = "UPDATE productos SET stock = stock - $cantidad WHERE id_producto = $id_producto";
            $conexion->query($sql_restar_stock);
        }

        // Si todo sale bien entonces se guardan los cambios en MySQL
        $conexion->commit();
        $venta_exitosa = true;

        // Guardamos temporalmente el carrito para el diseño del ticket abajo y limpiamos el carrito activo
        $detalles_ticket = $_SESSION['carrito'];
        $_SESSION['carrito'] = array(); // El carrito queda vacío para la siguiente venta

    } catch (Exception $e) {
        // Si algo falló, deshacemos todo para no corromper el stock
        $conexion->rollback();
        $error_venta = "Error al procesar el cobro: " . $e->getMessage();
    }
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ticket de Venta #<?php echo str_pad($id_venta_generado, 6, '0', STR_PAD_LEFT); ?></title>
    <style>
        .ticket-wrapper { max-width: 300px; margin: 30px auto; background: white; padding: 15px; border: 1px dashed #000; font-family: 'Courier New', Courier, monospace; font-size: 14px; }
        .text-center { text-align: center; }
        .linea-divisoria { border-top: 1px dashed #000; margin: 10px 0; }
        table { width: 100%; font-size: 13px; }
        .text-right { text-align: right; }
        .btn-regresar { display: block; text-align: center; margin: 20px auto; max-width: 200px; padding: 10px; background: #333; color: white; text-decoration: none; font-family: Arial, sans-serif; font-weight: bold; border-radius: 4px; }
        @media print { .btn-regresar { display: none; } .ticket-wrapper { border: none; margin: 0; } }
    </style>
</head>
<body>

    <?php if ($venta_exitosa): ?>
        <!-- VISTA DEL TICKET DE COMPRA -->
        <div class="ticket-wrapper">
            <h3 class="text-center">🔨 FERRETERÍA 🔨<br>"EL CHICARCAS"</h3>
            <p class="text-center">Aquí se construye en serio.</p>
            <p><strong>Folio:</strong> <?php echo str_pad($id_venta_generado, 6, '0', STR_PAD_LEFT); ?><br>
               <strong>Fecha:</strong> <?php echo $fecha_venta; ?><br>
               <strong>Cajero:</strong> <?php echo $_SESSION['usuario']; ?></p>
            
            <div class="linea-divisoria"></div>
            
            <table>
                <thead>
                    <tr>
                        <th>Cant.</th>
                        <th>Art.</th>
                        <th class="text-right">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($detalles_ticket as $item): ?>
                        <tr>
                            <td><?php echo $item['cantidad']; ?>x</td>
                            <td><?php echo substr($item['nombre'], 0, 15); ?></td>
                            <td class="text-right">$<?php echo number_format($item['cantidad'] * $item['precio'], 2); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <div class="linea-divisoria"></div>
            
            <p class="text-right" style="font-size: 16px;"><strong>TOTAL: $<?php echo number_format($total_venta, 2); ?> MXN</strong></p>
            
            <div class="linea-divisoria"></div>
            <p class="text-center" style="font-size: 12px;">Gracias por su compra.<br>Conserve su ticket para cambios.</p>
        </div>

        <a href="index.php" class="btn-regresar">🔄 Siguiente Venta</a>

        <script>
            // Ventana de impresion del ticket
            window.print();
        </script>

    <?php else: ?>
        <div style="max-width: 500px; margin: 50px auto; padding: 20px; background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; border-radius: 5px; font-family: Arial, sans-serif;">
            <h3>❌ No se pudo procesar la venta</h3>
            <p><?php echo $error_venta; ?></p>
            <a href="index.php" style="color: #721c24; font-weight: bold;">Regresar al punto de venta</a>
        </div>
    <?php endif; ?>

</body>
</html>

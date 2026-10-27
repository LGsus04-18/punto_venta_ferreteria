<?php 
require_once "config/conexion.php"; 
 
if (!isset($_SESSION['usuario'])) { 
    header("Location: login.php"); 
    exit(); 
} 
 
$sql_inv = "SELECT id_producto, nombre_producto, precio_venta, stock FROM productos ORDER BY nombre_producto ASC"; 
$resultado_inv = $conexion->query($sql_inv); 
?> 
 
<!DOCTYPE html> 
<html lang="es"> 
<head> 
    <meta charset="UTF-8"> 
    <title>Los Chicarcas - Inventario</title> 
    <link rel="stylesheet" href="css/estilos.css"> 
    <style> 
        .badge-normal { background: #E6F4EA; color: #137333; padding: 4px 8px; border-radius: 4px; font-weight: bold; } 
        .badge-bajo { background: #FEF7E0; color: #B06000; padding: 4px 8px; border-radius: 4px; font-weight: bold; } 
        .badge-agotado { background: #FCE8E6; color: #C5221F; padding: 4px 8px; border-radius: 4px; font-weight: bold; } 
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
            <div class="col-izquierda"> 
                <h2 style="margin-bottom: 20px;">
📦
 Control de Existencias (30 Artículos)</h2> 
                 
                <div class="tabla-responsiva"> 
                    <table> 
                        <thead> 
                            <tr> 
                                <th>Código</th> 
                                <th>Descripción del Artículo</th> 
                                <th>Precio Venta</th> 
                                <th>Existencias</th> 
                                <th>Estatus</th> 
                            </tr> 
                        </thead> 
                        <tbody> 
                            <?php while($row = $resultado_inv->fetch_assoc()):  
                                $stk = $row['stock']; 
                                if($stk == 0) { 
                                    $lbl = "Agotado"; $clse = "badge-agotado"; 
                                } elseif($stk <= 4) { 
                                    $lbl = "Stock Bajo"; $clse = "badge-bajo"; 
                                } else { 
                                    $lbl = "Disponible"; $clse = "badge-normal"; 
                                } 
                            ?> 
                                <tr> 
                                    <td><strong><?php echo str_pad($row['id_producto'], 4, '0', STR_PAD_LEFT); ?></strong></td> 
                                    <td><?php echo $row['nombre_producto']; ?></td> 
                                    <td>$<?php echo number_format($row['precio_venta'], 2); ?></td> 
                                    <td><?php echo $stk; ?> pzs</td> 
                                    <td><span class="<?php echo $clse; ?>"><?php echo $lbl; ?></span></td> 
                                </tr> 
                            <?php endwhile; ?> 
                        </tbody> 
                    </table> 
                </div> 
            </div> 
        </main> 
    </div> 
</body> 
</html> 
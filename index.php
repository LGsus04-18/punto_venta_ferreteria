<?php 
    require_once "config/conexion.php"; 
    
    if (!isset($_SESSION['usuario'])) { 
        header("Location: login.php"); 
        exit(); 
    } 
    
    if (!isset($_SESSION['carrito'])) { 
        $_SESSION['carrito'] = array(); 
    } 
     
    if (isset($_GET['accion'])) { 
        if ($_GET['accion'] == 'agregar' && isset($_GET['id'])) { 
            $id_agregar = intval($_GET['id']); 
            $sql_buscar = "SELECT id_producto, nombre_producto, precio_venta, stock FROM productos WHERE id_producto = $id_agregar"; 
            $res_buscar = $conexion->query($sql_buscar); 
            if ($res_buscar->num_rows === 1) { 
                $prod_encontrado = $res_buscar->fetch_assoc(); 
                if (isset($_SESSION['carrito'][$id_agregar])) { 
                    if ($_SESSION['carrito'][$id_agregar]['cantidad'] < $prod_encontrado['stock']) { 
                        $_SESSION['carrito'][$id_agregar]['cantidad']++; 
                    } 
                } else { 
                    $_SESSION['carrito'][$id_agregar] = [ 
                        'nombre' => $prod_encontrado['nombre_producto'], 
                        'precio' => $prod_encontrado['precio_venta'], 
                        'cantidad' => 1 
                    ]; 
                } 
            } 
            header("Location: index.php"); 
            exit(); 
        } 
        if ($_GET['accion'] == 'vaciar') { 
            $_SESSION['carrito'] = array(); 
            header("Location: index.php"); 
            exit(); 
        } 
    } 
    
    $busqueda = isset($_GET['buscar']) ? trim($_GET['buscar']) : ""; 
    if (!empty($busqueda)) { 
        $sql_cat = "SELECT id_producto, nombre_producto, precio_venta, stock FROM productos WHERE nombre_producto LIKE '%$busqueda%' OR 
    id_producto = '$busqueda' ORDER BY nombre_producto ASC"; 
    } else { 
        $sql_cat = "SELECT id_producto, nombre_producto, precio_venta, stock FROM productos ORDER BY nombre_producto ASC"; 
    } 
    $resultado_productos = $conexion->query($sql_cat); 
?> 
 
<!DOCTYPE html> 
<html lang="es"> 
    <head> 
        <meta charset="UTF-8"> 
        <meta name="viewport" content="width=device-width, initial-scale=1.0"> 
        <title>Los Chicarcas - Punto de Venta</title> 
        <link rel="stylesheet" href="css/estilos.css"> 
    </head> 
    <body> 
        <input type="checkbox" id="toggle-sidebar-check" style="display: none;">

        <label for="toggle-sidebar-check" class="btn-hamburguesa-toggle" style="position: fixed; top: 22px; left: 25px; z-index: 2000; font-size: 26px; color: #FFFFFF !important; cursor: pointer; user-select: none;">☰</label> 

        <!-- 1. Cargamos el encabezado oficial --> 
        <?php include "componentes/header.php"; ?> 
    
        <!-- Contenedor Estructural --> 
        <div class="contenedor-principal"> 
            
            <!-- 2. Cargamos el menú lateral oficial --> 
            <?php include "componentes/sidebar.php"; ?> 
    
            <!-- 3. Contenido de las consultas y tablas --> 
            <main class="contenido-pagina"> 
                
                <!-- SECCIÓN IZQUIERDA: CATÁLOGO --> 
                <div class="col-izquierda"> 
                    <form action="index.php" method="GET" style="display: flex; gap: 10px; margin-bottom: 25px"> 
                        <input type="text" name="buscar" class="buscador-caja" style="margin-bottom: 0; flex: 1;" placeholder="🔍 Buscar por nombre o código de barras..." value="<?php echo htmlspecialchars($busqueda); ?>">
                        <?php if (!empty($busqueda)): ?>
                            <a href="index.php" class="btn-chicarca" style="background-color: #6c757d; display: flex; align-items: center; justify-content: center;">Borrar busqueda</a>
                        <?php endif; ?>
                    </form> 

                    <div class="tabla-responsiva">
                            <table> 
                            <thead> 
                                <tr> 
                                    <th>Código</th> 
                                    <th>Producto</th> 
                                    <th>Precio</th> 
                                    <th>Stock</th> 
                                    <th>Acción</th> 
                                </tr> 
                            </thead> 
                            <tbody> 
                                <?php while($prod = $resultado_productos->fetch_assoc()): ?> 
                                    <tr> 
                                        <td><strong><?php echo str_pad($prod['id_producto'], 4, '0', STR_PAD_LEFT); ?></strong></td> 
                                        <td><?php echo $prod['nombre_producto']; ?></td> 
                                        <td>$<?php echo number_format($prod['precio_venta'], 2); ?></td> 
                                        <td><?php echo $prod['stock']; ?> pzs</td> 
                                        <td> 
                                            <a class="btn-chicarca" style="padding: 5px 10px; font-size: 12px;" href="index.php?accion=agregar&id=<?php echo $prod['id_producto']; ?>">Agregar</a> 
                                        </td> 
                                    </tr> 
                                <?php endwhile; ?> 
                            </tbody> 
                        </table> 
                    </div>
                    
                </div> 
    
                <!-- SECCIÓN DERECHA: CARRITO --> 
                <div class="col-derecha"> 
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;"> 
                        <h3>🛒 CARRITO DE VENTA</h3> 
                        <a href="index.php?accion=vaciar" class="btn-vaciar">🗑 Vaciar</a> 
                    </div> 
    
                    <div class="carrito-cuerpo-scroll"> 
                        <?php if (empty($_SESSION['carrito'])): ?> 
                            <p style="color:var(--color-texto-gris); text-align:center; margin-top:50px;">Carrito vacío.</p> 
                        <?php else: ?> 
                            <table> 
                                <?php  
                                $total_venta = 0; 
                                foreach ($_SESSION['carrito'] as $id => $item):  
                                    $subtotal = $item['cantidad'] * $item['precio']; 
                                    $total_venta += $subtotal; 
                                ?> 
                                    <tr> 
                                        <td><?php echo $item['cantidad']; ?>x <?php echo $item['nombre']; ?></td> 
                                        <td style="text-align:right;">$<?php echo number_format($subtotal, 2); ?></td> 
                                    </tr> 
                                <?php endforeach; ?> 
                            </table> 
                        <?php endif; ?> 
                    </div> 
    
                    <?php if (!empty($_SESSION['carrito'])):  
                        $iva = $total_venta * 0.16; 
                        
                    ?> 
                        <div style="border-top:1px dashed var(--color-bordes); padding-top:15px; margin-top:15px;"> 

                            <p style="display:flex; justify-content:space-between; font-size:14px; margin-bottom: 5px;">
                               <span>Subtotal:</span>
                                <strong>$<?php echo number_format($total_venta - $iva, 2); ?></strong>
                            </p> 

                            <p style="display:flex; justify-content:space-between; font-size:14px; margin-bottom:10px;"><span>IVA (16%):</span><strong>$<?php echo number_format($iva, 2); ?></strong></p>
                             
                            <h2 style="display:flex; justify-content:space-between; color:var(--color-principal-naranja); font-size:22px"><span>TOTAL:</span><span>$<?php echo number_format($total_venta, 2); ?></span></h2> 
                            
                            <a href="procesar_cobro.php" class="btn-chicarca" style="display:block; margin-top:20px;">FINALIZAR VENTA<a>

                        </div> 
                    <?php endif; ?> 
                </div> 
    
            </main> 
        </div> 
    
    </body> 
</html> 
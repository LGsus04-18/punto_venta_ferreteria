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

    $busqueda = "";
    if (isset($_GET['buscar'])) {
        $busqueda = trim($_GET['buscar']);
    }

    if (!empty($busqueda)) {
        $sql_cat = "SELECT id_producto, nombre_producto, precio_venta, stock FROM productos 
                    WHERE nombre_producto LIKE '%$busqueda%' OR id_producto = '$busqueda'
                    ORDER BY nombre_producto ASC";
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
        <title>Ferretería El Chicarcas - Ventas</title>
    </head>
    <body>

        <header>
            <h2>Ferretería "El Chicarcas" - Ventas</h2>
            <p>Empleado: <strong><?php echo $_SESSION['usuario']; ?> (<?php echo $_SESSION['rol']; ?>)</strong> | <a href="logout.php">Cerrar Sesión</a></p>
        </header>

        <!-- esta clase la vas a ocupar en css para dividir del lado derecho el catalogo y del izquiero el carrito si es que asi lo quieren, sino entonces la pueden cambiar -->
        <div class="flex-container">
            
            <!-- COLUMNA IZQUIERDA: CATÁLOGO DE PRODUCTOS -->
            <div class="col-productos">
                <h3>Catálogo de Artículos</h3>
                
                <form action="index.php" method="GET" class="search-container">
                    <input type="text" name="buscar" class="search-input" placeholder="Buscar por nombre o ID exacto (ej. 2 o Candado)..." value="<?php echo htmlspecialchars($busqueda); ?>">
                    <button type="submit" class="btn-search">Buscar</button>
                    <?php if (!empty($busqueda)): ?>
                        <a href="index.php" class="btn-clear">Limpiar Filtro</a>
                    <?php endif; ?>
                </form>

                <table>
                    <thead>
                        <tr>
                            <th>Código (ID)</th>
                            <th>Artículo</th>
                            <th>Precio</th>
                            <th>Disponibles</th>
                            <th>Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($resultado_productos && $resultado_productos->num_rows > 0): ?>
                            <?php while($prod = $resultado_productos->fetch_assoc()): 
                                $id_ceros = str_pad($prod['id_producto'], 4, '0', STR_PAD_LEFT);
                            ?>
                                <tr>
                                    <td><strong><?php echo $id_ceros; ?></strong></td>
                                    <td><?php echo $prod['nombre_producto']; ?></td>
                                    <td>$<?php echo number_format($prod['precio_venta'], 2); ?></td>
                                    <td><?php echo $prod['stock']; ?> pzs</td>
                                    <td>
                                        <a class="btn-add" href="index.php?accion=agregar&id=<?php echo $prod['id_producto']; ?>">Agregar</a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" style="text-align: center; color: red; font-weight: bold;">No se encontraron productos con esa búsqueda.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Agregar el carrito de compras a la derecha -->
            <div class="col-carrito">
                <h3>Carrito de Compras</h3>
                
                <?php if (empty($_SESSION['carrito'])): ?>
                    <p>El carrito está vacío. Agrega productos de la lista.</p>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Cant.</th>
                                <th>Producto</th>
                                <th>Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $total_venta = 0;
                            foreach ($_SESSION['carrito'] as $id => $item): 
                                $subtotal = $item['cantidad'] * $item['precio'];
                                $total_venta += $subtotal;
                            ?>
                                <tr>
                                    <td><?php echo $item['cantidad']; ?>x</td>
                                    <td><?php echo $item['nombre']; ?></td>
                                    <td>$<?php echo number_format($subtotal, 2); ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <tr>
                                <td colspan="2" style="text-align: right; font-weight: bold;">TOTAL:</td>
                                <td style="font-weight: bold; color: green;">$<?php echo number_format($total_venta, 2); ?></td>
                            </tr>
                        </tbody>
                    </table>
                    
                    <a class="btn-vaciar" href="index.php?accion=vaciar">Limpiar Carrito</a>
                    <a class="btn-cobrar" href="procesar_cobro.php">Finalizar Venta (Cobrar)</a>
                <?php endif; ?>
            </div>

        </div>

    </body>
</html>

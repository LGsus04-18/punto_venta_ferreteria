<?php 
require_once "config/conexion.php"; 
 
if (!isset($_SESSION['usuario'])) { 
    header("Location: login.php"); 
    exit(); 
} 
 
$mensaje_prov = ""; 
 
// 1. Registrar un nuevo pedido manual 
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['btn_pedido'])) { 
    $id_prov = intval($_POST['proveedor']); 
    $prod_name = trim($_POST['producto']); 
    $cant_pedir = intval($_POST['cantidad']); 
     
    if(!empty($prod_name) && $cant_pedir > 0) { 
        $sql_ins = "INSERT INTO pedidos_proveedor (id_proveedor, producto_solicitado, cantidad)  
                    VALUES ($id_prov, '$prod_name', $cant_pedir)"; 
        $conexion->query($sql_ins); 
        $mensaje_prov = "
📦
 Pedido registrado correctamente."; 
    } 
} 
 
// 2. Traer proveedores y listado de desabastos automáticos para los enlaces inteligentes 
$lista_proveedores = $conexion->query("SELECT id_proveedor, nombre_proveedor FROM proveedores"); 
$productos_bajos = $conexion->query("SELECT nombre_producto, stock FROM productos WHERE stock <= 4 ORDER BY stock ASC"); 
?> 
 
<!DOCTYPE html> 
<html lang="es"> 
<head> 
    <meta charset="UTF-8"> 
    <title>Los Chicarcas - Pedidos</title> 
    <link rel="stylesheet" href="css/estilos.css"> 
</head> 
<body> 
    <input type="checkbox" id="toggle-sidebar-check" style="display: none;">

        <label for="toggle-sidebar-check" class="btn-hamburguesa-toggle" style="position: fixed; top: 22px; left: 25px; z-index: 2000; font-size: 26px; color: #FFFFFF !important; cursor: pointer; user-select: none;">☰</label> 
    <input type="checkbox" id="toggle-sidebar-check" style="display: none;"> 
    <?php include "componentes/header.php"; ?> 
 
    <div class="contenedor-principal"> 
        <?php include "componentes/sidebar.php"; ?> 
         
        <main class="contenido-pagina"> 
            <!-- Formulario a la izquierda --> 
            <div class="col-izquierda" style="flex: 1;"> 
                <h3>
📝
 Registrar Pedido de Compra</h3><br> 
                <?php if(!empty($mensaje_prov)) echo "<p style='color:green;'>$mensaje_prov</p><br>"; ?> 
                 
                <form action="pedidos.php" method="POST"> 
                    <label>Elegir Distribuidor:</label><br> 
                    <select name="proveedor" class="buscador-caja" style="width:100%;"> 
                        <option value="1">Truper Herramientas</option> 
                        <option value="2">Fandeli Abrasivos</option> 
                        <option value="3">Iusa Electricidad</option> 
                    </select><br><br> 
                     
                    <label>Nombre del Artículo:</label> 
                    <input type="text" name="producto" class="buscador-caja" required placeholder="Ej. Martillo de uña"> 
                     
                    <label>Cantidad a Solicitar:</label> 
                    <input type="number" name="cantidad" class="buscador-caja" required min="1" placeholder="Ej. 50"> 
                     
                    <button type="submit" name="btn_pedido" class="btn-chicarca" style="width:100%;">Generar Orden</button> 
                </form> 
            </div> 
 
            <!-- Sugerencias inteligentes de internet a la derecha --> 
            <div class="col-derecha" style="flex: 1;"> 
                <h3>
🤖
 Sugerencias Rápidas de Resurtido</h3> 
                <p style="font-size:12px; color:var(--color-texto-gris); margin-bottom:15px;">Productos con bajo inventario listos para surtir en internet:</p> 
                 
                <?php if($productos_bajos->num_rows > 0): ?> 
                    <?php while($b = $productos_bajos->fetch_assoc()):  
                        $url_query = urlencode($b['nombre_producto']); 
                    ?> 
                        <div style="background:#FFFDE7; border:1px solid #E6DB55; padding:12px; border-radius:8px; margin-bottom:12px;"> 
                            <p style="font-size:14px; margin-bottom:8px;">
⚠
 <strong><?php echo $b['nombre_producto']; ?></strong> (Quedan solo <?php echo 
$b['stock']; ?> pzs)</p> 
                            <a href="https://mercadolibre.com.mx<?php echo $url_query; ?>" target="_blank" class="btn-chicarca" style="background:#FFF159; 
color:#333; padding:5px 10px; font-size:12px;">
🛒
 Mercado Libre</a> 
                            <a href="https://amazon.com.mx<?php echo $url_query; ?>" target="_blank" class="btn-chicarca" style="background:#FF9900; 
padding:5px 10px; font-size:12px;">
📦
 Amazon</a> 
                        </div> 
                    <?php endwhile; ?> 
                <?php else: ?> 
                    <p style="color:green; font-size:14px; text-align:center; margin-top:30px;">
✔
 Todo el inventario tiene buen stock.</p> 
                <?php endif; ?> 
            </div> 
        </main> 
    </div> 
</body> 
</html>
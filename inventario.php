<?php 
require_once "config/conexion.php"; 
 
if (!isset($_SESSION['usuario'])) { 
    header("Location: login.php"); 
    exit(); 
} 
require_once "config/permisos.php";
bloquear_restringidos();
$mensaje = "";
$editar = null;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        if (isset($_POST['btn_guardar'])) {
            $id     = (int)($_POST['id_producto'] ?? 0);
            $nombre = trim($_POST['nombre_producto']);
            $precio = floatval($_POST['precio_venta']);
            $stock  = (int)$_POST['stock'];

            if ($id > 0) {
                $stmt = $conexion->prepare("UPDATE productos SET nombre_producto=?, precio_venta=?, stock=? WHERE id_producto=?");
                $stmt->bind_param("sdii", $nombre, $precio, $stock, $id);
            } else {
                $stmt = $conexion->prepare("INSERT INTO productos (nombre_producto, precio_venta, stock) VALUES (?, ?, ?)");
                $stmt->bind_param("sdi", $nombre, $precio, $stock);
            }
            $stmt->execute();
            header("Location: inventario.php?ok=guardado");
            exit();
        }

        if (isset($_POST['btn_eliminar'])) {
            $id = (int)$_POST['id_producto'];
            $stmt = $conexion->prepare("DELETE FROM productos WHERE id_producto=?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            header("Location: inventario.php?ok=eliminado");
            exit();
        }
    } catch (mysqli_sql_exception $e) {
        $mensaje = "No se pudo completar la acción: " . $e->getMessage();
    }
}

if (isset($_GET['editar'])) {
    $stmt = $conexion->prepare("SELECT * FROM productos WHERE id_producto=?");
    $id_e = (int)$_GET['editar'];
    $stmt->bind_param("i", $id_e);
    $stmt->execute();
    $editar = $stmt->get_result()->fetch_assoc();
}
$res_total = $conexion->query("SELECT COUNT(*) AS total FROM productos");
$total_productos = (int)$res_total->fetch_assoc()['total'];
$sql_inv = "SELECT id_producto, nombre_producto, precio_venta, stock FROM productos ORDER BY nombre_producto ASC"; 
$resultado_inv = $conexion->query($sql_inv); 
?> 
 
<!DOCTYPE html> 
<html lang="es"> 
<head> 
    <meta charset="UTF-8"> 
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Los Chicarcas - Inventario</title> 
    <link rel="stylesheet" href="css/estilos.css"> 
    <style> 
        .badge-normal { background: #E6F4EA; color: #137333; padding: 4px 8px; border-radius: 4px; font-weight: bold; } 
        .badge-bajo { background: #FEF7E0; color: #B06000; padding: 4px 8px; border-radius: 4px; font-weight: bold; } 
        .badge-agotado { background: #FCE8E6; color: #C5221F; padding: 4px 8px; border-radius: 4px; font-weight: bold; } 
    </style> 
</head> 
<body class="pagina-inventario">
    <input type="checkbox" id="toggle-sidebar-check" style="display: none;">

        <label for="toggle-sidebar-check" class="btn-hamburguesa-toggle" style="position: fixed; top: 22px; left: 25px; z-index: 2000; font-size: 26px; color: #FFFFFF !important; cursor: pointer; user-select: none;">☰</label> 
    
    <?php include "componentes/header.php"; ?> 
 
    <div class="contenedor-principal"> 
        <?php include "componentes/sidebar.php"; ?> 
         
        <main class="contenido-pagina"> 
        <div class="col-izquierda">
    <div class="inv-cabecera">
        <h2>📦 Control de Existencias (<?php echo $total_productos; ?> Artículos)</h2>
    </div>
 
 
                    <?php if (!empty($mensaje)): ?>
                        <div id="mensaje-aviso" style="background:#fee2e2;color:#991b1b;padding:10px;border-radius:8px;margin-bottom:15px;"><?php echo htmlspecialchars($mensaje); ?></div>
                    <?php elseif (isset($_GET['ok'])): ?>
                        <div id="mensaje-aviso" style="background:#dcfce7;color:#166534;padding:10px;border-radius:8px;margin-bottom:15px;">Producto <?php echo htmlspecialchars($_GET['ok']); ?> correctamente.</div>
                    <?php endif; ?>

                    <form method="POST" action="inventario.php" style="display:flex; gap:10px; flex-wrap:wrap; align-items:flex-end; margin-bottom:20px;">
                        <input type="hidden" name="id_producto" value="<?php echo $editar['id_producto'] ?? 0; ?>">
                        
                        <div>
                            <label>Descripción</label><br>
                            <input type="text" name="nombre_producto" required style="padding:8px;width:260px;"
                                value="<?php echo htmlspecialchars($editar['nombre_producto'] ?? ''); ?>">
                        </div>
                        <div>
                            <label>Precio</label><br>
                            <input type="number" step="0.01" min="0" name="precio_venta" required style="padding:8px;width:110px;"
                                value="<?php echo $editar['precio_venta'] ?? ''; ?>">
                        </div>
                        <div>
                            <label>Existencias</label><br>
                            <input type="number" min="0" name="stock" required style="padding:8px;width:110px;"
                                value="<?php echo $editar['stock'] ?? ''; ?>">
                        </div>

                        <button type="submit" name="btn_guardar" style="padding:9px 18px;background:#e0532b;color:#fff;border:none;border-radius:6px;font-weight:bold;cursor:pointer;">
                            💾 <?php echo $editar ? 'Guardar cambios' : 'Guardar'; ?>
                        </button>
                        <?php if ($editar): ?>
                            <a href="inventario.php" style="padding:9px 14px;">Cancelar</a>
                        <?php endif; ?>
                    </form>


                <div class="tabla-responsiva"> 
                    <table> 
                        <thead> 
                            <tr> 
                                <th>Código</th> 
                                <th>Descripción del Artículo</th> 
                                <th>Precio Venta</th> 
                                <th>Existencias</th> 
                                <th>Estatus</th> 
                                <th>Acciones</th>
                                
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
                                    <tr id="prod-<?php echo (int)$row['id_producto']; ?>"
                                    class="<?php echo (isset($_GET['resaltar']) && (int)$_GET['resaltar'] === (int)$row['id_producto']) ? 'fila-resaltada' : ''; ?>">
                                    <td><strong><?php echo str_pad($row['id_producto'], 4, '0', STR_PAD_LEFT); ?></strong></td> 
                                    <td><?php echo $row['nombre_producto']; ?></td> 
                                    <td>$<?php echo number_format($row['precio_venta'], 2); ?></td> 
                                    <td><?php echo $stk; ?> pzs</td> 
                                    <td><span class="<?php echo $clse; ?>"><?php echo $lbl; ?></span></td> 

                                    <td>
    <a href="inventario.php?editar=<?php echo $row['id_producto']; ?>">✏️ Editar</a>
    <form method="POST" action="inventario.php" style="display:inline;"
          onsubmit="return confirm('¿Eliminar este producto? Esta acción no se puede deshacer.');">
        <input type="hidden" name="id_producto" value="<?php echo $row['id_producto']; ?>">
        <button type="submit" name="btn_eliminar" style="background:none;border:none;color:#b91c1c;cursor:pointer;">🗑️ Eliminar</button>
    </form>
</td>
                                </tr> 
                            <?php endwhile; ?> 
                            <script>
                                document.addEventListener('DOMContentLoaded', function () {
                                    var fila = document.querySelector('.fila-resaltada');
                                    if (fila) fila.scrollIntoView({ behavior: 'smooth', block: 'center' });
                                });
                                </script>
                        </tbody> 
                    </table> 
                </div> 
            </div> 
        </main> 
    </div> 
    <script>
        if (window.history.replaceState) {
    history.replaceState(null, '', 'inventario.php');
}
    const aviso = document.getElementById('mensaje-aviso');
    if (aviso) {
        setTimeout(() => {
            aviso.style.transition = 'opacity 0.5s';
            aviso.style.opacity = '0';
            setTimeout(() => aviso.remove(), 500);
        }, 3000);
    }
</script>
</body> 
</html> 
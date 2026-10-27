<?php 
    $pagina_actual = basename($_SERVER['SCRIPT_NAME']);
?> 

<aside class="sidebar"> 
    <nav style="display: flex; flex-direction: column; width: 100%;"> 
        <a href="index.php" class="<?php echo ($pagina_actual == 'index.php') ? 'activo' : ''; ?> ">🛒 Punto de Venta</a> 
        <a href="corte.php" class="<?php echo ($pagina_actual == 'corte.php') ? 'activo' : ''; ?> ">📟 Corte y Arqueo de Caja</a> 
        <a href="inventario.php" class="<?php echo ($pagina_actual == 'inventario.php') ? 'activo' : ''; ?> ">📦 Inventario</a> 
        <a href="reportes.php" class="<?php echo ($pagina_actual == 'reportes.php') ? 'activo' : ''; ?> ">📊 Reportes</a> 
        <a href="pedidos.php" class="<?php echo ($pagina_actual == 'pedidos.php') ? 'activo' : ''; ?> ">🚚 Pedidos a Proveedores</a> 
        <a href="logout.php" style="color: #FF7878;" class="<?php echo ($pagina_actual == 'logout.php') ? 'activo' : ''; ?> ">⚙ Cerrar Sesión</a> 
    </nav> 
</aside> 
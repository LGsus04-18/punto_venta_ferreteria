<?php
    require_once __DIR__ . '/../config/permisos.php';

    $rol_menu = $_SESSION['rol'] ?? '';

    // El invitado no tiene menú
    if ($rol_menu === 'invitado') { return; }

    $es_cajero = ($rol_menu === 'cajero');
    $es_admin  = ($rol_menu === ROL_ADMIN);

    $pagina_actual = basename($_SERVER['SCRIPT_NAME']);

    // Cuentas nuevas que esperan aprobación (solo se muestra a administradores)
    $n_pend = 0;
    if ($es_admin && isset($conexion)) {
        $r_pend = $conexion->query("SELECT COUNT(*) AS n FROM usuarios WHERE rol = 'pendiente'");
        if ($r_pend) { $n_pend = (int) $r_pend->fetch_assoc()['n']; }
    }
?>

<aside class="sidebar">
    <nav style="display: flex; flex-direction: column; width: 100%;">

        <!-- Todos (admin y cajero) -->
        <a href="index.php" class="<?php echo ($pagina_actual == 'index.php') ? 'activo' : ''; ?> ">🛒 Punto de Venta</a>
        <a href="corte_caja.php" class="<?php echo ($pagina_actual == 'corte_caja.php') ? 'activo' : ''; ?> ">📟 Corte y Arqueo de Caja</a>

        <?php if (!$es_cajero): ?>
            <!-- Solo usuarios con acceso completo -->
            <a href="inventario.php" class="<?php echo ($pagina_actual == 'inventario.php') ? 'activo' : ''; ?> ">📦 Inventario</a>
            <a href="reportes.php" class="<?php echo ($pagina_actual == 'reportes.php') ? 'activo' : ''; ?> ">📊 Reportes Vendidos</a>
            <a href="pedidos.php" class="<?php echo ($pagina_actual == 'pedidos.php') ? 'activo' : ''; ?> ">🚚 Pedidos a Proveedores</a>
            <a href="recibir_pedidos.php" class="<?php echo ($pagina_actual == 'recibir_pedidos.php') ? 'activo' : ''; ?> ">✅ Recepción de Pedidos</a>
        <?php endif; ?>

        <?php if ($es_admin): ?>
            <!-- Solo administradores -->
            <a href="usuarios.php" class="<?php echo ($pagina_actual == 'usuarios.php') ? 'activo' : ''; ?> ">👥 Usuarios<?php if ($n_pend > 0): ?><span style="margin-left:auto; background:#FF5A00; color:#fff; font-size:11px; font-weight:700; border-radius:999px; padding:2px 8px;"><?php echo $n_pend; ?></span><?php endif; ?></a>
        <?php endif; ?>
    </nav>
</aside>
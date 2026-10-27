<?php 
    // 1. Buscamos cuáles y cuántos productos tienen 3 piezas o menos 
    $sql_lista_alertas = "SELECT nombre_producto, stock FROM productos WHERE stock <= 3 
    ORDER BY stock ASC"; 
    $res_lista_alertas = $conexion->query($sql_lista_alertas); 
    
    $total_bajos = $res_lista_alertas->num_rows; 

    $pagina_actual = basename($_SERVER['SCRIPT_NAME']);
?>

<!-- Barra Superior Compartida -->
<header class="navbar-top">
    
    <div class="logo-area" style="margin-left: 55px; display: flex; align-items: center; gap:15px;">

        <img src="img/logoMain.png" alt="Los Chicarcas">
        <div class="logo-texto">
            <h2>LOS CHICARCAS</h2>
            <p>Aquí se construye en serio.</p>
        </div>
    </div>

    <?php if ($pagina_actual == 'procesar_reportes.php'): ?>
        <div class="caja-status-cerrada">
            <span style="color: #950606;">●</span> Caja: CERRADA
        </div>
    <?php else: ?>
        <div class="caja-status-abierta">
            <span style="color: #137333;">●</span> Caja: ABIERTA
        </div>
    <?php endif; ?>

    <!-- 📊 SECCIÓN DE NOTIFICACIONES Y AVATAR -->
    <div class="user-badge"  style="display: flex; align-items: center;">
        
        <!-- Contenedor de la campanita -->
        <div class="dropdown-chicarca" tabindex="0" style="margin-right: 20px;"> 
            <div class="notificacion-contenedor" style="position: relative; cursor: pointer;"> 
                <span class="icono-campana" style="font-size: 22px;">🔔</span> 
                    <?php if ($total_bajos > 0): ?> 
                        <span class="punto-alerta-naranja"><?php echo $total_bajos; ?></span> 
                    <?php endif; ?> 
            </div> 
             
            <!-- PANEL DESPLEGABLE CON LOS PRODUCTOS QUE FALTAN --> 
            <div class="dropdown-contenido"> 
                <h4 style="margin-bottom: 10px; border-bottom: 1px solid #ccc; padding-bottom: 5px; color: #333;">⚠ Alertas de Stock</h4> 
                <?php if ($total_bajos == 0): ?> 
                    <p style="font-size: 13px; color: green; text-align: center;">¡Todo el inventario está completo! ✔</p> 
                <?php else: ?> 
                <div style="max-height: 200px; overflow-y: auto;"> 
                    <?php while($alert = $res_lista_alertas->fetch_assoc()): ?> 
                        <div class="alerta-item-stock"> 
                            Faltan existencias de: <strong><?php echo $alert['nombre_producto']; ?></strong>  
                                (Quedan: <span><?php echo $alert['stock']; ?> pzs</span>) 
                        </div> 
                        <?php endwhile; ?> 
                    </div> 
                <?php endif; ?> 
            </div> 
        </div> 

        <div class="dropdown-chicarca" tabindex="0"> 
            <img src="img\fotoPerfil.jpg" alt="Perfil" class="perfil"> 
             
            <!-- MENÚ DE LOGOUT DESPLEGABLE --> 
            <div class="dropdown-contenido user-dropdown-contenido"> 
                <p style="font-size: 13px; font-weight: bold; color: #333; margin-bottom:5px;">@<?php echo $_SESSION['usuario']; ?><p> 
                <p style="font-size: 11px; color: var(--color-texto-gris); margin-bottom:10px;"><?php echo $_SESSION['rol']; ?></p> 
                <a href="logout.php" class="btn-cerrar-sesion-menu">❌ Cerrar Sesión</a> 
            </div> 
        </div>
    </div>
</header>

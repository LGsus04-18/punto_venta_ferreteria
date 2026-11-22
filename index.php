<?php
require_once "config/conexion.php";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once "config/permisos.php";
if (isset($_GET['invitado'])) {
    session_regenerate_id(true);
    $_SESSION['usuario'] = 'Invitado';
    $_SESSION['rol']     = 'invitado';
    unset($_SESSION['id_usuario']);
    header("Location: index.php");
    exit();
}
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
        if ($res_buscar && $res_buscar->num_rows === 1) {
            $prod_encontrado = $res_buscar->fetch_assoc();
            if (isset($_SESSION['carrito'][$id_agregar])) {
                if ($_SESSION['carrito'][$id_agregar]['cantidad'] < $prod_encontrado['stock']) {
                    $_SESSION['carrito'][$id_agregar]['cantidad']++;
                }
            } elseif ($prod_encontrado['stock'] > 0) {
                $_SESSION['carrito'][$id_agregar] = [
                    'nombre'   => $prod_encontrado['nombre_producto'],
                    'precio'   => $prod_encontrado['precio_venta'],
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
    if ($_GET['accion'] == 'eliminar' && isset($_GET['id'])) {
        $id_quitar = intval($_GET['id']);
        unset($_SESSION['carrito'][$id_quitar]);
        header("Location: index.php");
        exit();
    }
}

$busqueda = isset($_GET['buscar']) ? trim($_GET['buscar']) : "";
if (!empty($busqueda)) {
    $busqueda_sql = $conexion->real_escape_string($busqueda);
    $sql_cat = "SELECT id_producto, nombre_producto, precio_venta, stock FROM productos
                WHERE stock > 0
                  AND (nombre_producto LIKE '%$busqueda_sql%' OR id_producto = '$busqueda_sql')
                ORDER BY nombre_producto ASC";
} else {
    $sql_cat = "SELECT id_producto, nombre_producto, precio_venta, stock FROM productos
                WHERE stock > 0
                ORDER BY nombre_producto ASC";
}
$resultado_productos = $conexion->query($sql_cat);

$total_venta = 0;
?>

<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Los Chicarcas - Punto de Venta</title>
        <link rel="stylesheet" href="css/estilos.css">
    </head>
    <body class="pagina-pos">
    <input type="checkbox" id="toggle-sidebar-check" style="display:none;">
<label for="toggle-sidebar-check" class="btn-hamburguesa-toggle" style="position: fixed; top: 22px; left: 25px; z-index: 2000; font-size: 26px; color: #FFFFFF !important; cursor: pointer; user-select: none;">☰</label>

<input type="checkbox" id="toggle-carrito-check" style="display:none;">
<label for="toggle-carrito-check" class="btn-carrito-top">
    🛒
    <?php $n_items = array_sum(array_column($_SESSION['carrito'], 'cantidad')); ?>
    <?php if ($n_items > 0): ?><span class="carrito-badge"><?php echo $n_items; ?></span><?php endif; ?>
</label>

<!-- 1. Encabezado -->
<?php include "componentes/header.php"; ?>

<div class="contenedor-principal">

            <!-- 2. Menú lateral -->
            <?php include "componentes/sidebar.php"; ?>

            <!-- 3. Contenido -->
            <main class="contenido-pagina">

                <!-- SECCIÓN IZQUIERDA: CATÁLOGO -->
                <div class="col-izquierda">
                <div class="pv-cabecera">
                    <div class="pv-titulo">
                        <span class="pv-icono">🛒</span>
                        <div>
                            <h1>Punto de Venta</h1>
                            <p>Busca un producto y agrégalo al carrito</p>
                        </div>
                    </div>

                    <form action="index.php" method="GET" class="barra-busqueda">
                        <input type="text" name="buscar" class="buscador-caja"
                            placeholder="Buscar producto o código..."
                            value="<?php echo htmlspecialchars($busqueda); ?>">

                        <?php if (!empty($busqueda)): ?>
                            <a href="index.php" class="btn-buscar btn-limpiar">Limpiar</a>
                        <?php else: ?>
                            <button type="submit" class="btn-buscar">Buscar</button>
                        <?php endif; ?>
                    </form>
                </div>

                    <div class="tabla-responsiva">
                        <table>
                        <thead>
                            
                            <tr>
                                <th>Código</th>
                                <th>Producto</th>
                                <th>Precio</th>
                                <th>Acción</th>
                            </tr>
                        </thead>
                            <tbody>
                                <?php while ($prod = $resultado_productos->fetch_assoc()): ?>
                                    <tr>
                                        <td><strong><?php echo str_pad($prod['id_producto'], 4, '0', STR_PAD_LEFT); ?></strong></td>
                                        <td><?php echo htmlspecialchars($prod['nombre_producto']); ?></td>
                                        <?php $pp = explode('.', number_format($prod['precio_venta'], 2)); ?>
                                        <td>$<?php echo $pp[0]; ?><small class="centavos">.<?php echo $pp[1]; ?></small></td>
                                        <td>
                                            <?php
                                                $id_p = $prod['id_producto'];
                                                $en_carrito = isset($_SESSION['carrito'][$id_p]);
                                            ?>
                                            <?php if ($prod['stock'] <= 0): ?>
                                                <span class="btn-chicarca btn-sin-stock" style="padding: 5px 10px; font-size: 12px;">Sin stock</span>
                                            <?php elseif ($en_carrito): ?>
                                                <a class="btn-chicarca btn-agregado" style="padding: 5px 10px; font-size: 12px;" href="index.php?accion=agregar&id=<?php echo $id_p; ?>">Agregado</a>
                                            <?php else: ?>
                                                <a class="btn-chicarca" style="padding: 5px 10px; font-size: 12px;" href="index.php?accion=agregar&id=<?php echo $id_p; ?>">Agregar</a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                                
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- SECCIÓN DERECHA: CARRITO -->
                <div class="col-derecha <?php echo empty($_SESSION['carrito']) ? 'carrito-vacio' : 'carrito-lleno'; ?>">
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
                                foreach ($_SESSION['carrito'] as $id => $item):
                                    $subtotal = $item['cantidad'] * $item['precio'];
                                    $total_venta += $subtotal;
                                ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($item['nombre']); ?></td>
                                        <td><?php echo $item['cantidad']; ?></td>
                                        <td>$<?php echo number_format($subtotal, 2); ?></td>
                                        <td style="text-align: right;">
                                            <a href="index.php?accion=eliminar&id=<?php echo $id; ?>" class="btn-quitar" title="Quitar del carrito">&times;</a>
                                        </td>
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

                            <p style="display:flex; justify-content:space-between; font-size:14px; margin-bottom:10px;">
                                <span>IVA (16%):</span>
                                <strong>$<?php echo number_format($iva, 2); ?></strong>
                            </p>

                            <h2 style="display:flex; justify-content:space-between; color:var(--color-principal-naranja); font-size:22px">
                                <span>TOTAL:</span>
                                <span>$<?php echo number_format($total_venta, 2); ?></span>
                            </h2>

                            <!-- Un solo formulario: tipo de cobro + botón de finalizar -->
                            <form action="procesar_cobro.php" method="POST">
                                <div class="metodo-pago">
                                    <span class="metodo-pago-tit">Tipo de cobro</span>
                                    <div class="metodo-pago-botones">
                                        <label class="btn-metodo">
                                            <input type="radio" name="metodo_pago" value="efectivo" checked>
                                            <span>Efectivo</span>
                                        </label>
                                        <label class="btn-metodo">
                                            <input type="radio" name="metodo_pago" value="tarjeta">
                                            <span>Tarjeta</span>
                                        </label>
                                        <label class="btn-metodo">
                                            <input type="radio" name="metodo_pago" value="transferencia">
                                            <span>Transferencia</span>
                                        </label>
                                    </div>
                                </div>

                                <div id="bloque-efectivo" data-total="<?php echo number_format($total_venta, 2, '.', ''); ?>">
                                <div class="efectivo-campo">
                                <label for="monto_recibido">Recibido ($):</label>
                                    <input type="number" id="monto_recibido" name="monto_recibido"
                                        min="<?php echo number_format($total_venta, 2, '.', ''); ?>" step="0.01"
                                        inputmode="decimal" placeholder="0.00">
                                </div>
                                <div class="efectivo-campo">
                                    <span class="efectivo-etiqueta">Cambio:</span>
                                    <div id="texto-cambio" class="cambio-caja">$0.00</div>
                                </div>
                            </div>
                            <button type="submit" id="btn-finalizar" class="btn-chicarca" style="display:block; width:100%; margin-top:20px;">FINALIZAR VENTA</button>

                                
                            </form>

                        </div>
                    <?php endif; ?>
                </div>

            </main>
        </div>

        <script>
(function () {
    const KEY = 'pos_scroll';
    const lista = document.querySelector('.col-izquierda .tabla-responsiva');
    const carrito = document.querySelector('.carrito-cuerpo-scroll');

    function guardar() {
        try {
            sessionStorage.setItem(KEY, JSON.stringify({
                lista: lista ? lista.scrollTop : 0,
                carrito: carrito ? carrito.scrollTop : 0,
                ventana: window.scrollY
            }));
        } catch (e) {}
    }

    // Antes de agregar, quitar o vaciar, guarda la posición
    document.addEventListener('click', function (e) {
        if (e.target.closest('a[href*="accion="]')) guardar();
    });

    // Al cargar, restaura la posición guardada
    try {
        const s = JSON.parse(sessionStorage.getItem(KEY));
        if (s) {
            if (lista) lista.scrollTop = s.lista;
            if (carrito) carrito.scrollTop = s.carrito;
            window.scrollTo(0, s.ventana);
            sessionStorage.removeItem(KEY);
        }
    } catch (e) {}
})();

(function () {
    const bloque = document.getElementById('bloque-efectivo');
    if (!bloque) return;

    const total = parseFloat(bloque.dataset.total) || 0;
    const input = document.getElementById('monto_recibido');
    const cambioTxt = document.getElementById('texto-cambio');
    const btn = document.getElementById('btn-finalizar');
    const radios = document.querySelectorAll('input[name="metodo_pago"]');

    function actualizar() {
        const efectivo = document.querySelector('input[name="metodo_pago"]:checked').value === 'efectivo';
        bloque.style.display = efectivo ? 'grid' : 'none';
        input.required = efectivo;

        if (!efectivo) {
            btn.disabled = false;
            btn.style.opacity = '';
            btn.style.cursor = '';   // <- corrige el círculo con diagonal
            return;
        }

        const recibido = parseFloat(input.value) || 0;
        const cambio = recibido - total;

        if (input.value === '') {
            cambioTxt.textContent = '$0.00';
            cambioTxt.className = 'cambio-caja';
        } else if (cambio < -0.001) {
            cambioTxt.textContent = 'Falta $' + Math.abs(cambio).toFixed(2);
            cambioTxt.className = 'cambio-caja cambio-falta';
        } else {
            cambioTxt.textContent = '$' + Math.max(cambio, 0).toFixed(2);
            cambioTxt.className = 'cambio-caja cambio-ok';
        }

        const valido = recibido + 0.001 >= total;
        btn.disabled = !valido;
        btn.style.opacity = valido ? '' : '0.5';
        btn.style.cursor = valido ? '' : 'not-allowed';
    }

    input.addEventListener('input', actualizar);
    radios.forEach(r => r.addEventListener('change', actualizar));
    actualizar();
})();
</script>
    </body>
</html>
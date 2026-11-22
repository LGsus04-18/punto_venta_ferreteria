<?php
    require_once "config/conexion.php";
    if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit();
    }
    require_once "config/permisos.php";
    bloquear_restringidos();
    // 1. Filtro de periodo
    $periodo = $_GET['periodo'] ?? 'todo';
    $condicion_fecha = "";
    $titulo_periodo = "Todo el historial";
    switch ($periodo) {
    case 'hoy':
    $condicion_fecha = "WHERE DATE(fecha) = CURDATE()";
    $titulo_periodo = "Hoy (" . date('d/m/Y') . ")";
    break;
    case '7dias':
    $condicion_fecha = "WHERE fecha >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
    $titulo_periodo = "Últimos 7 días";
    break;
    case '30dias':
    $condicion_fecha = "WHERE fecha >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
    $titulo_periodo = "Últimos 30 días";
    break;
    default:
    $periodo = 'todo';
    $condicion_fecha = "";
    $titulo_periodo = "Todo el historial";
    break;
    }
    // Subconsulta unificada (Ventas ya cortadas + Ventas del turno activo)
    $subquery_ventas = "
    SELECT id_venta, fecha, total, metodo_pago, 'Corte cerrado' AS estado
    FROM historial_ventas
    UNION ALL
    SELECT id_venta, fecha_hora AS fecha, total, metodo_pago, 'Turno activo' AS estado
    FROM ventas
    ";
    // 2. KPIs Generales
    $sql_kpi = "SELECT
    COUNT(*) AS total_tickets,
    COALESCE(SUM(total), 0) AS ingresos_totales,
    COALESCE(AVG(total), 0) AS ticket_promedio
    FROM ($subquery_ventas) AS todas
    $condicion_fecha";
    $kpi = $conexion->query($sql_kpi)->fetch_assoc();
    // 3. Datos para Gráfica 1: Ingresos por día
    $sql_dias = "SELECT DATE(fecha) AS dia, SUM(total) AS monto
    FROM ($subquery_ventas) AS todas
    $condicion_fecha
    GROUP BY DATE(fecha)
    ORDER BY dia ASC
    LIMIT 15";
    $res_dias = $conexion->query($sql_dias);
    $datos_dias = [];
    $max_dia = 0;
    while ($row = $res_dias->fetch_assoc()) {
    $monto = (float)$row['monto'];
    if ($monto > $max_dia) $max_dia = $monto;
    $datos_dias[] = [
    'etiqueta' => date("d/m", strtotime($row['dia'])),
    'monto' => $monto
    ];
    }
    // 4. Datos para Gráfica 2: Métodos de pago (Dona CSS puro)
    $sql_metodos = "SELECT LOWER(COALESCE(NULLIF(metodo_pago, ''), 'efectivo')) AS metodo,
    SUM(total) AS monto,
    COUNT(*) AS cantidad
    FROM ($subquery_ventas) AS todas
    $condicion_fecha
    GROUP BY metodo
    ORDER BY monto DESC";
    $res_metodos = $conexion->query($sql_metodos);
    $colores_metodos = ['#059669', '#0891B2', '#F59E0B', '#E11D48'];
    $lista_metodos = [];
    $total_metodos_monto = 0;
    $metodo_favorito = "Sin datos";
    $max_cant = 0;
    while ($row = $res_metodos->fetch_assoc()) {
    $monto = (float)$row['monto'];
    $total_metodos_monto += $monto;
    $nombre_m = ucfirst($row['metodo']);
    if ($row['cantidad'] > $max_cant) {
    $max_cant = $row['cantidad'];
    $metodo_favorito = $nombre_m . " (" . $row['cantidad'] . ")";
    }
    $lista_metodos[] = [
    'nombre' => $nombre_m,
    'monto' => $monto,
    'cantidad' => (int)$row['cantidad']
    ];
    }
    // Construir el conic-gradient para CSS
    $gradiente_partes = [];
    $acumulado_pct = 0;
    foreach ($lista_metodos as $i => &$m) {
    $color = $colores_metodos[$i % count($colores_metodos)];
    $m['color'] = $color;
    $pct = $total_metodos_monto > 0 ? ($m['monto'] / $total_metodos_monto) * 100 : 0;
    $m['pct'] = round($pct, 1);
    $inicio = $acumulado_pct;
    $fin = $acumulado_pct + $pct;
    $gradiente_partes[] = "$color {$inicio}% {$fin}%";
    $acumulado_pct = $fin;
    }
    unset($m);
    $css_conic = count($gradiente_partes) > 0
    ? "conic-gradient(" . implode(", ", $gradiente_partes) . ")"
    : "conic-gradient(#E2E8F0 0% 100%)";
    // 5. Datos para Gráfica 3: Días de la semana
    $dias_es = [1 => 'Domingo', 2 => 'Lunes', 3 => 'Martes', 4 => 'Miércoles', 5 => 'Jueves', 6 => 'Viernes', 7
    => 'Sábado'];
    $ventas_semana = array_fill(1, 7, 0.0);
    $sql_semana = "SELECT DAYOFWEEK(fecha) AS num_dia, SUM(total) AS monto
    FROM ($subquery_ventas) AS todas
    $condicion_fecha
    GROUP BY DAYOFWEEK(fecha)";
    $res_semana = $conexion->query($sql_semana);
    $max_semana = 0;
    while ($row = $res_semana->fetch_assoc()) {
    $val = (float)$row['monto'];
    $ventas_semana[(int)$row['num_dia']] = $val;
    if ($val > $max_semana) $max_semana = $val;
    }
    // 6. Productos con bajo stock
    $sql_stock = "SELECT id_producto, nombre_producto, stock, precio_venta
    FROM productos
    WHERE stock <= 10
    ORDER BY stock ASC
    LIMIT 6";
    $res_stock = $conexion->query($sql_stock);
    // 7. Historial de tickets
    $sql_rep = "SELECT id_venta, fecha, total, metodo_pago, estado
    FROM ($subquery_ventas) AS todas
    $condicion_fecha
    ORDER BY fecha DESC
    LIMIT 100";
    $resultado_rep = $conexion->query($sql_rep);
?>
<!DOCTYPE html>
<html lang="es">
<head>
 <meta charset="UTF-8">
 <meta name="viewport" content="width=device-width, initial-scale=1.0">
 <title>Los Chicarcas - Reportes y Analítica</title>
 <link rel="stylesheet" href="css/estilos.css">
</head>
<body class="pagina-reportes">
 <input type="checkbox" id="toggle-sidebar-check" style="display: none;">
 <label for="toggle-sidebar-check" class="btn-hamburguesa-toggle" style="position: fixed; top: 22px;
left: 25px; z-index: 2000; font-size: 26px; color: #FFFFFF !important; cursor: pointer; user-select:
none;">☰</label>
 <?php include "componentes/header.php"; ?>
 <div class="contenedor-principal">
 <?php include "componentes/sidebar.php"; ?>
 <main class="principal">
 <!-- CABECERA Y FILTROS -->
 <div class="rep-header-bar">
 <div class="pv-titulo">
 <div class="pv-icono"> </div>
 <div>
 <h1>Reportes y Rendimiento</h1>
 <p>Mostrando información de: <strong><?php echo $titulo_periodo; ?></strong></p>
 </div>
 </div>
 <div class="rep-filtros">
 <a href="?periodo=hoy" class="btn-filtro <?php echo $periodo === 'hoy' ? 'activo' :
''; ?>">Hoy</a>
 <a href="?periodo=7dias" class="btn-filtro <?php echo $periodo === '7dias' ? 'activo' :
''; ?>">Últimos 7 días</a>
 <a href="?periodo=30dias" class="btn-filtro <?php echo $periodo === '30dias' ?
'activo' : ''; ?>">Últimos 30 días</a>
 <a href="?periodo=todo" class="btn-filtro <?php echo $periodo === 'todo' ? 'activo' :
''; ?>">Todo el historial</a>
 </div>
 </div>
 <!-- TARJETAS KPI -->
 <section class="resumen">
 <div class="tarjeta t-total">
 <div class="tarjeta-cab">
 <span class="tarjeta-tit">Ingresos Totales</span>
 <span class="tarjeta-icono"> </span>
 </div>
 <div class="tarjeta-monto">$<?php echo number_format($kpi['ingresos_totales'], 2); ?>
<small>MXN</small></div>
 <div class="tarjeta-nota">Suma de ventas cobradas</div>
 </div>
 <div class="tarjeta t-verde">
 <div class="tarjeta-cab">
 <span class="tarjeta-tit">Tickets Cobrados</span>
 <span class="tarjeta-icono"> </span>
 </div>
 <div class="tarjeta-monto"><?php echo number_format($kpi['total_tickets']); ?></div>
 <div class="tarjeta-nota">Operaciones registradas</div>
 </div>
 <div class="tarjeta t-cian">
 <div class="tarjeta-cab">
 <span class="tarjeta-tit">Ticket Promedio</span>
 <span class="tarjeta-icono"> </span>
 </div>
 <div class="tarjeta-monto">$<?php echo number_format($kpi['ticket_promedio'], 2); ?>
<small>MXN</small></div>
 <div class="tarjeta-nota">Consumo promedio por cliente</div>
 </div>
 <div class="tarjeta t-rosa">
 <div class="tarjeta-cab">
 <span class="tarjeta-tit">Método Más Usado</span>
 <span class="tarjeta-icono"> </span>
 </div>
 <div class="tarjeta-monto" style="font-size: 19px; padding-top: 4px;"><?php echo
$metodo_favorito; ?></div>
 <div class="tarjeta-nota">Preferencia de pago</div>
 </div>
 </section>
 <!-- BLOQUE 1 DE GRÁFICAS (100% HTML + CSS + PHP PURO) -->
 <section class="graficas-grid">
 <!-- Gráfica 1: Barras Verticales -->
 <div class="grafica-card">
 <h3> Evolución de Ingresos por Fecha <span>(MXN cobrados por día)</span></h3>
 <?php if (count($datos_dias) > 0): ?>
 <div class="chart-barras-v">
 <?php foreach ($datos_dias as $d):
 $altura = $max_dia > 0 ? round(($d['monto'] / $max_dia) * 100) : 0;
 ?>
 <div class="barra-col" title="<?php echo $d['etiqueta'] . ': $' .
number_format($d['monto'], 2); ?>">
 <div class="barra-fill-v" style="height: <?php echo $altura; ?>%;">
 <span class="barra-tooltip">$<?php echo number_format($d['monto'],
0); ?></span>
 </div>
 <span class="barra-label-v"><?php echo $d['etiqueta']; ?></span>
 </div>
 <?php endforeach; ?>
 </div>
 <?php else: ?>
 <p style="text-align:center; color:#94A3B8; padding:40px 0;">Sin datos en este
periodo.</p>
 <?php endif; ?>
 </div>
 <!-- Gráfica 2: Dona CSS -->
 <div class="grafica-card">
 <h3> Métodos de Pago <span>(Proporción de ingresos)</span></h3>
 <div class="dona-contenedor">
 <div class="dona-circulo" style="background: <?php echo $css_conic; ?>;">
 <div class="dona-centro">
 <small>Total</small>
 <strong>$<?php echo number_format($total_metodos_monto, 0); ?></strong>
 </div>
 </div>
 <div class="dona-leyenda">
 <?php foreach ($lista_metodos as $m): ?>
 <div class="leyenda-item">
 <div class="leyenda-izq">
 <span class="leyenda-punto" style="background: <?php echo
$m['color']; ?>;"></span>
 <span><?php echo $m['nombre']; ?> (<?php echo $m['pct']; ?>%)</span>
 </div>
 <strong>$<?php echo number_format($m['monto'], 2); ?></strong>
 </div>
 <?php endforeach; ?>
 </div>
 </div>
 </div>
 </section>
 <!-- BLOQUE 2: DÍAS FUERTES + STOCK BAJO -->
 <section class="graficas-grid graficas-grid-mitad">
 <div class="grafica-card">
 <h3> Días de la Semana Más Fuertes <span>(Naranja = Día récord)</span></h3>
 <div class="chart-barras-h">
 <?php foreach ($dias_es as $num => $nombre_dia):
 $val = $ventas_semana[$num];
 $ancho = $max_semana > 0 ? round(($val / $max_semana) * 100) : 0;
 $es_record = ($val > 0 && $val == $max_semana) ? 'es-max' : '';
 ?>
 <div class="fila-h <?php echo $es_record; ?>">
 <span class="fila-h-dia"><?php echo $nombre_dia; ?></span>
 <div class="fila-h-track">
 <div class="fila-h-fill" style="width: <?php echo $ancho; ?>%;"></div>
 </div>
 <span class="fila-h-monto">$<?php echo number_format($val, 2); ?></span>
 </div>
 <?php endforeach; ?>
 </div>
 </div>
 <div class="grafica-card">
 <h3> Productos con Stock Bajo <span>(10 unidades o menos)</span></h3>
 <div class="tabla-responsiva" style="max-height: 260px;">
 <table>
 <thead>
 <tr>
 <th>Producto</th>
 <th>Precio</th>
 <th>Stock</th>
 </tr>
 </thead>
 <tbody>
 <?php if ($res_stock && $res_stock->num_rows > 0): ?>
 <?php while ($prod = $res_stock->fetch_assoc()): ?>
 <tr>
 <td><?php echo htmlspecialchars($prod['nombre_producto']); ?></
td>
 <td>$<?php echo number_format($prod['precio_venta'], 2); ?></td>
 <td>
 <span class="stock-bajo">
 <?php echo $prod['stock']; ?> pzas
 </span>
 </td>
 </tr>
 <?php endwhile; ?>
 <?php else: ?>
 <tr><td colspan="3" style="text-align:center;">Todo el inventario tiene
buen stock.</td></tr>
 <?php endif; ?>
 </tbody>
 </table>
 </div>
 </div>
 </section>
 <!-- HISTORIAL DE TICKETS -->
 <section class="grafica-card">
 <h3> Últimos Tickets Registrados <span>(Hasta 100 registros recientes)</span></h3>
 <div class="tabla-responsiva" style="max-height: 380px;">
 <table>
 <thead>
 <tr>
 <th>Folio Ticket</th>
 <th>Fecha y Hora</th>
 <th>Método de Pago</th>
 <th>Estado</th>
 <th>Monto Cobrado</th>
 </tr>
 </thead>
 <tbody>
 <?php if ($resultado_rep && $resultado_rep->num_rows > 0): ?>
 <?php while ($vnt = $resultado_rep->fetch_assoc()):
 $metodo_cls = strtolower($vnt['metodo_pago'] ?: 'efectivo');
 ?>
 <tr>
 <td><strong>#<?php echo str_pad($vnt['id_venta'], 6, '0',
STR_PAD_LEFT); ?></strong></td>
 <td><?php echo $vnt['fecha']; ?></td>
 <td>
 <span class="badge-metodo badge-<?php echo $metodo_cls; ?>">
 <?php echo htmlspecialchars($vnt['metodo_pago'] ?:
'Efectivo'); ?>
 </span>
 </td>
 <td style="font-size: 12px; color: #64748B;"><?php echo
$vnt['estado']; ?></td>
 <td style="color: #059669; font-weight: bold;">$<?php echo
number_format($vnt['total'], 2); ?> MXN</td>
 </tr>
 <?php endwhile; ?>
 <?php else: ?>
 <tr><td colspan="5" style="text-align:center;">No hay ventas registradas en
este periodo.</td></tr>
 <?php endif; ?>
 </tbody>
 </table>
 </div>
 </section>
 </main>
 </div>
</body>
</html>
<?php
require_once "config/conexion.php";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit();
}

require_once "config/permisos.php";
bloquear_invitado();

date_default_timezone_set('America/Mexico_City');

// Convierte el resultado (metodo_pago, n, t) en el arreglo de resumen
function armar_resumen($res) {
    $r = [
        'efectivo'      => ['n' => 0, 't' => 0.0],
        'tarjeta'       => ['n' => 0, 't' => 0.0],
        'transferencia' => ['n' => 0, 't' => 0.0],
        'total' => 0.0, 'tickets' => 0
    ];
    while ($f = $res->fetch_assoc()) {
        if (isset($r[$f['metodo_pago']])) {
            $r[$f['metodo_pago']] = ['n' => (int)$f['n'], 't' => (float)$f['t']];
        }
        $r['total']   += (float)$f['t'];
        $r['tickets'] += (int)$f['n'];
    }
    return $r;
}

// Ventas del turno actual (tabla ventas, que se vacía en cada corte)
function ventas_actuales($conexion) {
    $res = $conexion->query("SELECT metodo_pago, COUNT(*) AS n, SUM(total) AS t FROM ventas GROUP BY metodo_pago");
    return armar_resumen($res);
}

// Ventas de un corte ya hecho (tabla historial_ventas)
function ventas_de_corte($conexion, $id_corte) {
    $st = $conexion->prepare("SELECT metodo_pago, COUNT(*) AS n, SUM(total) AS t
                              FROM historial_ventas WHERE id_corte = ? GROUP BY metodo_pago");
    $st->bind_param("i", $id_corte);
    $st->execute();
    $r = armar_resumen($st->get_result());
    $st->close();
    return $r;
}

// Fondo de apertura = lo que se dejó en el último corte
$res_ult = $conexion->query("SELECT monto_inicial_siguiente_dia FROM cortes_caja ORDER BY id_corte DESC LIMIT 1");
$ult = $res_ult ? $res_ult->fetch_assoc() : null;
$monto_inicial_caja = $ult ? floatval($ult['monto_inicial_siguiente_dia']) : 1000.00;

$vt                = ventas_actuales($conexion);
$ventas_del_dia    = $vt['total'];
$efectivo_esperado = $monto_inicial_caja + $vt['efectivo']['t'];

// --- Guardar el corte (lo llama el botón con fetch) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'guardar_corte') {
    header('Content-Type: application/json');

    if (rol_actual() === 'cajero') {
        if (clave_admin_bloqueada()) {
            echo json_encode(['ok' => false, 'error' => 'Demasiados intentos. Espera un minuto.']); exit;
        }
        if (!clave_admin_valida($conexion, $_POST['clave_admin'] ?? '')) {
            echo json_encode(['ok' => false, 'error' => 'Contraseña de administrador incorrecta.']); exit;
        }
    }

    if ($vt['tickets'] <= 0) {
        echo json_encode(['ok' => false, 'error' => 'No hay ventas registradas en el turno actual.']);
        exit;
    }

    $contado   = round(floatval($_POST['efectivo_real_contado'] ?? 0), 2);
    $siguiente = round(floatval($_POST['monto_inicial_siguiente_dia'] ?? 0), 2);

    if ($contado <= 0 || $siguiente > $contado) {
        echo json_encode(['ok' => false, 'error' => 'El efectivo contado debe ser mayor a 0 y no menor al fondo que se queda.']);
        exit;
    }

    $dif     = round($contado - $efectivo_esperado, 2);
    $ef      = $vt['efectivo']['t'];
    $id_user = intval($_SESSION['id_usuario'] ?? 1);

    $conexion->begin_transaction();
    try {
        // 1. Guardar el corte
        $st = $conexion->prepare("INSERT INTO cortes_caja
            (fecha_hora, monto_inicial, total_ventas_efectivo, efectivo_real_contado, id_usuario, diferencia, monto_inicial_siguiente_dia)
            VALUES (NOW(), ?, ?, ?, ?, ?, ?)");
        $st->bind_param("dddidd", $monto_inicial_caja, $ef, $contado, $id_user, $dif, $siguiente);
        $st->execute();
        $id_corte = $conexion->insert_id;
        $st->close();

        // 2. Respaldar las ventas en el historial (con método de pago)
        $st = $conexion->prepare("INSERT INTO historial_ventas (id_usuario, id_venta, total, fecha, id_corte, metodo_pago)
                                  SELECT id_usuario, id_venta, total, fecha_hora, ?, metodo_pago FROM ventas");
        $st->bind_param("i", $id_corte);
        $st->execute();
        $st->close();

        // 3. Limpiar ventas para el siguiente turno
        $conexion->query("DELETE FROM ventas");

        $conexion->commit();
        echo json_encode(['ok' => true, 'id' => $id_corte]);
    } catch (Throwable $e) {
        $conexion->rollback();
        echo json_encode(['ok' => false, 'error' => 'Error al procesar el cierre: ' . $e->getMessage()]);
    }
    exit;
}

// --- Datos del ticket (cuando la URL trae ?ticket=ID) ---
$corte = null;
if (isset($_GET['ticket'])) {
    $idc = intval($_GET['ticket']);
    $st = $conexion->prepare("SELECT * FROM cortes_caja WHERE id_corte = ?");
    $st->bind_param("i", $idc);
    $st->execute();
    $corte = $st->get_result()->fetch_assoc();
    $st->close();

    if ($corte) {
        $st = $conexion->prepare("SELECT fecha_hora FROM cortes_caja WHERE id_corte < ? ORDER BY id_corte DESC LIMIT 1");
        $st->bind_param("i", $idc);
        $st->execute();
        $prev = $st->get_result()->fetch_assoc();
        $st->close();

        $t_desde    = $prev['fecha_hora'] ?? '1970-01-01 00:00:00';
        $tv         = ventas_de_corte($conexion, $idc);   // ahora sale del historial
        $t_esperado = $corte['monto_inicial'] + $corte['total_ventas_efectivo'];
        $t_retiro   = $corte['efectivo_real_contado'] - $corte['monto_inicial_siguiente_dia'];
        $t_iva      = $tv['total'] * 0.16;
        $tienda = [
            'direccion' => 'Circuito Poetas 37, ...',   // la misma de procesar_cobro.php
            'rfc'       => 'XXXX000000XXX',
            'telefono'  => '55 XXXX XXXX',
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Los Chicarcas - Arqueo y Corte de Caja</title>
    <link rel="stylesheet" href="css/estilos.css">
</head>
<body>

    <!-- Un solo checkbox (antes estaba duplicado con el mismo id) -->
    <input type="checkbox" id="toggle-sidebar-check" style="display: none;">
    <label for="toggle-sidebar-check" class="btn-hamburguesa-toggle"
           style="position: fixed; top: 22px; left: 25px; z-index: 2000; font-size: 26px; color: #FFFFFF !important; cursor: pointer; user-select: none;">☰</label>

    <?php include "componentes/header.php"; ?>

    <div class="contenedor-principal">
        <?php include "componentes/sidebar.php"; ?>

        <!-- Faltaba abrir <main>: solo existía el </main> de cierre -->
        <main class="principal">

            <!-- 1. RESUMEN DE VENTAS -->
            <section>
                <div class="seccion-titulo">
                    <h2>🧮 1. Ventas Totales del Día</h2>
                </div>

                     <div class="resumen">
                     <div class="tarjeta t-verde">
                        <div class="tarjeta-cab"><span class="tarjeta-tit">Ventas en Efectivo</span><div class="tarjeta-icono">💵</div></div>
                        <div class="tarjeta-monto">$<?= number_format($vt['efectivo']['t'], 2) ?> <small>MXN</small></div>
                        <p class="tarjeta-nota"><?= $vt['efectivo']['n'] ?> cobros en efectivo</p>
                        </div>

                    <div class="tarjeta t-cian">
                        <div class="tarjeta-cab"><span class="tarjeta-tit">Ventas con Tarjeta</span><div class="tarjeta-icono">💳</div></div>
                        <div class="tarjeta-monto">$<?= number_format($vt['tarjeta']['t'], 2) ?> <small>MXN</small></div>
                        <p class="tarjeta-nota"><?= $vt['tarjeta']['n'] ?> cobros por terminal</p>
                     </div>

                    <div class="tarjeta t-rosa">
                        <div class="tarjeta-cab"><span class="tarjeta-tit">Ventas por Transferencia</span><div class="tarjeta-icono">🔁</div></div>
                        <div class="tarjeta-monto">$<?= number_format($vt['transferencia']['t'], 2) ?> <small>MXN</small></div>
                        <p class="tarjeta-nota"><?= $vt['transferencia']['n'] ?> transferencias</p>
                    </div>

                    <div class="tarjeta t-total">
                        <div class="tarjeta-cab"><span class="tarjeta-tit">Total General Vendido</span><div class="tarjeta-icono">🏦</div></div>
                        <div class="tarjeta-monto">$<?= number_format($ventas_del_dia, 2) ?> <small>MXN</small></div>
                        <p class="tarjeta-nota"><?= $vt['tickets'] ?> ventas totales en turno</p>
                    </div> 
                </div>
            </section>

  


                
            <!-- 3. ARQUEO: EFECTIVO CONTADO -->
            <section class="arqueo" data-esperado="<?= $efectivo_esperado ?>">
                <div class="seccion-titulo"><h2>💰 3. Arqueo de Efectivo</h2></div>
                <div class="arqueo-grid">
                    <div><small>Fondo inicial</small><strong>$<?= number_format($monto_inicial_caja, 2) ?></strong></div>
                    <div><small>+ Ventas en efectivo</small><strong>$<?= number_format($vt['efectivo']['t'], 2) ?></strong></div>
                    <div><small>= Debe haber en caja</small><strong>$<?= number_format($efectivo_esperado, 2) ?></strong></div>
                    <div>
                        <small>Efectivo real contado ($)</small>
                        <input type="number" id="efectivo-contado" min="0" step="0.01" oninput="calcularDiferencia()" placeholder="0.00">
                    </div>
                    <div><small>Diferencia</small><strong id="txt-diferencia">—</strong></div>
                    <div><small>A retirar de caja</small><strong id="txt-retiro">—</strong></div>
                </div>
            </section>

            <!-- 4. FONDO DE CAJA -->
            <section class="fondo">

            <div class="fondo-cab">
                <div>
                    <h2>🪙 4. Fondo Fijo que Permanece en Caja</h2>
                    <p>Verifica billetes y monedas que se quedan en caja para cambio del día siguiente.</p>
                </div>

                <div class="alerta alerta-ok" id="alerta-fondo">
                    <span class="alerta-icono" id="alerta-icono">✔</span>
                    <div>
                        <strong class="alerta-tit" id="alerta-tit" style="display:none;"></strong>
                        <span id="alerta-msg">Caja OK: el fondo de <strong class="monto-fijo">$500.00 MXN</strong> está completo.</span>
                    </div>
                </div>
            </div>

                <div class="fondo-grid">

                    <!-- Billetes -->
                    <div class="panel-denom">
                        <div class="panel-cab">
                            <span class="nombre">💵 Billetes para Cambio</span>
                            <span class="total total-verde" id="total-billetes-fondo">$370.00</span>
                        </div>
                        <div class="filas">
                            <div class="fila-denom">
                                <span>$200.00 MXN</span>
                                <div class="fila-der">
                                    <input type="number" min="0" value="1" data-val="200" oninput="calcularFondo()" class="input-fondo">
                                    <span class="subtotal-denom">$200.00</span>
                                </div>
                            </div>
                            <div class="fila-denom">
                                <span>$100.00 MXN</span>
                                <div class="fila-der">
                                    <input type="number" min="0" value="1" data-val="100" oninput="calcularFondo()" class="input-fondo">
                                    <span class="subtotal-denom">$100.00</span>
                                </div>
                            </div>
                            <div class="fila-denom">
                                <span>$50.00 MXN</span>
                                <div class="fila-der">
                                    <input type="number" min="0" value="1" data-val="50" oninput="calcularFondo()" class="input-fondo">
                                    <span class="subtotal-denom">$50.00</span>
                                </div>
                            </div>
                            <div class="fila-denom">
                                <span>$20.00 MXN</span>
                                <div class="fila-der">
                                    <input type="number" min="0" value="1" data-val="20" oninput="calcularFondo()" class="input-fondo">
                                    <span class="subtotal-denom">$20.00</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Monedas -->
                    <div class="panel-denom">
                        <div class="panel-cab">
                            <span class="nombre">🪙 Monedas para Cambio</span>
                            <span class="total total-ambar" id="total-monedas-fondo">$130.00</span>
                        </div>
                        <div class="filas">
                            <div class="fila-denom">
                                <span>$10.00 MXN</span>
                                <div class="fila-der">
                                    <input type="number" min="0" value="8" data-val="10" oninput="calcularFondo()" class="input-fondo">
                                    <span class="subtotal-denom">$80.00</span>
                                </div>
                            </div>
                            <div class="fila-denom">
                                <span>$5.00 MXN</span>
                                <div class="fila-der">
                                    <input type="number" min="0" value="6" data-val="5" oninput="calcularFondo()" class="input-fondo">
                                    <span class="subtotal-denom">$30.00</span>
                                </div>
                            </div>
                            <div class="fila-denom">
                                <span>$2.00 MXN</span>
                                <div class="fila-der">
                                    <input type="number" min="0" value="5" data-val="2" oninput="calcularFondo()" class="input-fondo">
                                    <span class="subtotal-denom">$10.00</span>
                                </div>
                            </div>
                            <div class="fila-denom">
                                <span>$1.00 MXN</span>
                                <div class="fila-der">
                                    <input type="number" min="0" value="10" data-val="1" oninput="calcularFondo()" class="input-fondo">
                                    <span class="subtotal-denom">$10.00</span>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>

                <!-- Resultado -->
                <span id="total-fondo-contado" hidden>$500.00</span>

                <div class="fondo-pie">
                        <button type="button" id="btn-corte" onclick="guardarCorte()" class="btn-guardar">
                        🔒 Realizar Cierre de Caja
                    </button>
                </div>

            </section>

        </main>
    </div>

    <?php if ($corte): ?>
<div class="ticket-overlay">
    <div class="ticket-corte" id="ticket-corte">
        <img src="img/logo.png" class="tc-logo" alt="Ferretería Los Chicarcas">
        <div class="tc-c"><?= $tienda['direccion'] ?><br>RFC: <?= $tienda['rfc'] ?> · Tel: <?= $tienda['telefono'] ?></div>
        <div class="tc-linea"></div>
        <div class="tc-c"><strong>CORTE DE CAJA</strong></div>
        <div class="tc-linea"></div>

        <div>Folio corte: <?= str_pad($corte['id_corte'], 6, '0', STR_PAD_LEFT) ?></div>
        <div>Apertura: <?= $t_desde === '1970-01-01 00:00:00' ? '—' : $t_desde ?></div>
        <div>Cierre: <?= $corte['fecha_hora'] ?></div>
        <div>Entrega: <?= htmlspecialchars($_SESSION['usuario']) ?> (<?= htmlspecialchars(ucfirst($_SESSION['rol'] ?? 'Sin rol')) ?>)</div>
        <div class="tc-linea"></div>

        <div class="tc-f"><span>Fondo inicial</span><span>$<?= number_format($corte['monto_inicial'], 2) ?></span></div>
        <div class="tc-linea"></div>
        <div class="tc-f"><span>Efectivo (<?= $tv['efectivo']['n'] ?>)</span><span>$<?= number_format($tv['efectivo']['t'], 2) ?></span></div>
        <div class="tc-f"><span>Tarjeta (<?= $tv['tarjeta']['n'] ?>)</span><span>$<?= number_format($tv['tarjeta']['t'], 2) ?></span></div>
        <div class="tc-f"><span>Transferencia (<?= $tv['transferencia']['n'] ?>)</span><span>$<?= number_format($tv['transferencia']['t'], 2) ?></span></div>
        <div class="tc-f tc-b"><span>TOTAL VENDIDO</span><span>$<?= number_format($tv['total'], 2) ?></span></div>
        <div class="tc-f"><span>Subtotal</span><span>$<?= number_format($tv['total'] - $t_iva, 2) ?></span></div>
        <div class="tc-f"><span>IVA (16%)</span><span>$<?= number_format($t_iva, 2) ?></span></div>
        <div class="tc-linea"></div>

        <div class="tc-f"><span>Debe haber en caja</span><span>$<?= number_format($t_esperado, 2) ?></span></div>
        <div class="tc-f"><span>Efectivo contado</span><span>$<?= number_format($corte['efectivo_real_contado'], 2) ?></span></div>
        <div class="tc-f tc-b"><span>
            <?php $d = (float)$corte['diferencia'];
                  echo $d > 0.001 ? 'SOBRANTE' : ($d < -0.001 ? 'FALTANTE' : 'CUADRADO'); ?>
        </span><span>$<?= number_format(abs($d), 2) ?></span></div>
        <div class="tc-linea"></div>

        <div class="tc-f"><span>Fondo para siguiente turno</span><span>$<?= number_format($corte['monto_inicial_siguiente_dia'], 2) ?></span></div>
        <div class="tc-f tc-b"><span>EFECTIVO A RETIRAR</span><span>$<?= number_format($t_retiro, 2) ?></span></div>
        <div class="tc-linea"></div>

        <div>Tickets emitidos: <?= $tv['tickets'] ?></div>
        <br><br>
        <div class="tc-firmas"><span>Entrega</span><span>Recibe</span></div>
        <div class="tc-c" style="margin-top:8px;font-size:11px;">Documento de control interno,<br>no es comprobante fiscal.</div>
    </div>

    <div class="ticket-acciones">
        <button onclick="window.print()" class="btn-guardar">🖨️ Imprimir</button>
        <a href="corte_caja.php" class="btn-guardar" style="background:#6c757d;text-decoration:none;">Cerrar</a>
    </div>
</div>
<?php endif; ?>

    <!-- TOAST (ahora dentro del body, fuera del contenedor) -->
    <div id="modal-clave" class="modal-clave" hidden>
    <div class="modal-clave-caja">
        <h3>🔐 Autorización de administrador</h3>
        <p>Para registrar el corte, ingresa la contraseña de un administrador.</p>
        <input type="password" id="clave-admin" autocomplete="off" placeholder="Contraseña">
        <div class="modal-clave-btns">
            <button type="button" id="clave-cancelar">Cancelar</button>
            <button type="button" id="clave-aceptar">Autorizar</button>
            </div>
        </div>
    </div>
    <div id="toast" class="toast">
        <span>✅</span>
        <span>¡Corte de caja registrado con éxito!</span>
    </div>

    <script>
        // Reloj: solo si existe en el header (en esta página ya no está #live-clock)
        const reloj = document.getElementById('live-clock');
        if (reloj) {
            setInterval(() => {
                reloj.innerText = new Date().toLocaleTimeString('es-MX');
            }, 1000);
        }

        // Conteo del fondo de caja
        function calcularFondo() {
            let totalBilletes = 0;
            let totalMonedas = 0;

            document.querySelectorAll('.input-fondo').forEach(input => {
                const val = parseFloat(input.getAttribute('data-val'));
                const qty = parseFloat(input.value) || 0;
                const subtotal = val * qty;

                input.closest('.fila-denom').querySelector('.subtotal-denom').innerText = '$' + subtotal.toFixed(2);

                if (val >= 20) totalBilletes += subtotal;
                else totalMonedas += subtotal;
            });

            const totalFondo = totalBilletes + totalMonedas;

            document.getElementById('total-billetes-fondo').innerText = '$' + totalBilletes.toFixed(2);
            document.getElementById('total-monedas-fondo').innerText = '$' + totalMonedas.toFixed(2);
            document.getElementById('total-fondo-contado').innerText = '$' + totalFondo.toFixed(2);

            const alerta = document.getElementById('alerta-fondo');
            const icono = document.getElementById('alerta-icono');
            const tit = document.getElementById('alerta-tit');
            const msg = document.getElementById('alerta-msg');

            if (Math.abs(totalFondo - 500) < 0.001) {
                alerta.className = 'alerta alerta-ok';
                icono.textContent = '✔';
                tit.style.display = 'none';
                msg.innerHTML = 'Caja OK: el fondo de <strong class="monto-fijo">$500.00 MXN</strong> está completo.';
            } else if (totalFondo < 500) {
                alerta.className = 'alerta alerta-falta';
                icono.textContent = '⚠️';
                tit.style.display = '';
                tit.textContent = 'ATENCIÓN IMPORTANTE:';
                msg.textContent = 'FALTAN $' + (500 - totalFondo).toFixed(2) + ' PARA LLEGAR A $500';
            } else {
                alerta.className = 'alerta alerta-excede';
                icono.textContent = '⚠️';
                tit.style.display = '';
                tit.textContent = 'ATENCIÓN IMPORTANTE:';
                msg.textContent = 'EXCEDE EN $' + (totalFondo - 500).toFixed(2) + ' (RETIRAR A VENTAS)';
            }
        }

        function calcularDiferencia() {
            const esperado = parseFloat(document.querySelector('.arqueo').dataset.esperado) || 0;
            const input = document.getElementById('efectivo-contado');
            const dif = document.getElementById('txt-diferencia');
            const ret = document.getElementById('txt-retiro');

            if (input.value === '') { dif.textContent = '—'; ret.textContent = '—'; return; }

            const contado = parseFloat(input.value) || 0;
            const d = contado - esperado;
            dif.textContent = (d > 0.001 ? 'Sobran $' : d < -0.001 ? 'Faltan $' : 'Cuadrado $') + Math.abs(d).toFixed(2);
            dif.style.color = Math.abs(d) < 0.001 ? '#198754' : (d > 0 ? '#b8860b' : '#dc3545');

            const fondo = parseFloat(document.getElementById('total-fondo-contado').innerText.replace('$', '')) || 0;
            ret.textContent = '$' + (contado - fondo).toFixed(2);
        }

        const REQUIERE_CLAVE = <?= (rol_actual() === 'cajero') ? 'true' : 'false' ?>;

function pedirClave() {
    return new Promise(resolve => {
        const m = document.getElementById('modal-clave');
        const i = document.getElementById('clave-admin');
        const cerrar = v => { m.hidden = true; resolve(v); };
        m.hidden = false; i.value = ''; i.focus();
        document.getElementById('clave-aceptar').onclick  = () => cerrar(i.value);
        document.getElementById('clave-cancelar').onclick = () => cerrar(null);
        i.onkeydown = e => { if (e.key === 'Enter') cerrar(i.value); };
    });
}

async function guardarCorte() {
    const contado = parseFloat(document.getElementById('efectivo-contado').value);
    const fondo = parseFloat(document.getElementById('total-fondo-contado').innerText.replace('$', '')) || 0;

    if (!contado || contado <= 0) { alert('Captura el efectivo real contado en la sección 3.'); return; }
    if (fondo > contado) { alert('El fondo que se queda no puede ser mayor al contado.'); return; }
    if (!confirm('¿Registrar el corte de caja? Esta acción no se puede deshacer.')) return;

    let clave = '';
    if (REQUIERE_CLAVE) {
        clave = await pedirClave();
        if (clave === null) return;
    }

    const btn = document.getElementById('btn-corte');
    btn.disabled = true;

    const datos = new FormData();
    datos.append('accion', 'guardar_corte');
    datos.append('efectivo_real_contado', contado);
    datos.append('monto_inicial_siguiente_dia', fondo);
    datos.append('clave_admin', clave);

    fetch('corte_caja.php', { method: 'POST', body: datos })
        .then(r => r.json())
        .then(r => {
            if (!r.ok) { alert(r.error || 'No se pudo guardar el corte.'); btn.disabled = false; return; }
            document.getElementById('toast').classList.add('visible');
            setTimeout(() => { window.location = 'corte_caja.php?ticket=' + r.id; }, 900);
        })
        .catch(() => { alert('Error de conexión al guardar.'); btn.disabled = false; });
}
    </script>
</body>
</html>
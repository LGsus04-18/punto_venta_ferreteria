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

// Solo los administradores entran a esta página
if (rol_actual() !== ROL_ADMIN) {
    header("Location: index.php");
    exit();
}

$yo = intval($_SESSION['id_usuario'] ?? 0);
$roles_validos = [ROL_ADMIN, 'cajero'];

// Token anti-CSRF (evita que otra página mande acciones a nombre del administrador)
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

function admins_activos(mysqli $c): int
{
    $rol = ROL_ADMIN;
    $st  = $c->prepare("SELECT COUNT(*) AS n FROM usuarios WHERE rol = ? AND activo = 1");
    $st->bind_param("s", $rol);
    $st->execute();
    $n = (int) $st->get_result()->fetch_assoc()['n'];
    $st->close();
    return $n;
}

// ------------------------------------------------------------------
// Acciones
// ------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!hash_equals($_SESSION['csrf'], $_POST['csrf'] ?? '')) {
            throw new Exception("La sesión de la página caducó. Recarga e intenta de nuevo.");
        }
        $accion = $_POST['accion'] ?? '';
        $id     = intval($_POST['id_usuario'] ?? 0);

        if ($accion === 'crear') {
            $nombre = trim($_POST['nombre'] ?? '');
            $clave  = $_POST['clave'] ?? '';
            $rol    = $_POST['rol'] ?? '';

            if (!preg_match('/^[\p{L}\p{N}._-]{3,50}$/u', $nombre)) {
                throw new Exception("El usuario debe tener de 3 a 50 caracteres (letras, números, punto, guion o guion bajo, sin espacios).");
            }
            if (!preg_match('/^[A-Za-z0-9]{6}$/', $clave)) {
                throw new Exception("La contraseña debe tener exactamente 6 caracteres, solo letras o números (sin espacios, puntos ni guiones).");
            }
            if (!in_array($rol, $roles_validos, true)) {
                throw new Exception("Rol no válido.");
            }

            $st = $conexion->prepare("SELECT 1 FROM usuarios WHERE nombre_usuario = ?");
            $st->bind_param("s", $nombre);
            $st->execute();
            $existe = $st->get_result()->num_rows > 0;
            $st->close();
            if ($existe) {
                throw new Exception("Ya existe un usuario llamado «$nombre».");
            }

            $hash = password_hash($clave, PASSWORD_DEFAULT);
            $st = $conexion->prepare("INSERT INTO usuarios (nombre_usuario, password, rol, activo) VALUES (?, ?, ?, 1)");
            $st->bind_param("sss", $nombre, $hash, $rol);
            $st->execute();
            $st->close();
            $_SESSION['flash'] = ['ok', "✔ Usuario «$nombre» creado como " . ucfirst($rol) . "."];

        } else {
            // Acciones sobre un usuario que ya existe
            $st = $conexion->prepare("SELECT nombre_usuario, rol, activo FROM usuarios WHERE id_usuario = ?");
            $st->bind_param("i", $id);
            $st->execute();
            $u = $st->get_result()->fetch_assoc();
            $st->close();
            if (!$u) {
                throw new Exception("El usuario no existe.");
            }

            // Una cuenta pendiente solo se puede aprobar o rechazar
            if ($u['rol'] === 'pendiente' && !in_array($accion, ['aprobar', 'rechazar'], true)) {
                throw new Exception("Esa cuenta está pendiente: apruébala o recházala primero.");
            }

            if ($accion === 'aprobar') {
                $nuevo = $_POST['rol'] ?? '';
                if ($u['rol'] !== 'pendiente') {
                    throw new Exception("Esa cuenta ya no está pendiente.");
                }
                if (!in_array($nuevo, $roles_validos, true)) {
                    throw new Exception("Elige un rol para aprobar la cuenta.");
                }
                $st = $conexion->prepare("UPDATE usuarios SET rol = ?, activo = 1 WHERE id_usuario = ? AND rol = 'pendiente'");
                $st->bind_param("si", $nuevo, $id);
                $st->execute();
                $st->close();
                $_SESSION['flash'] = ['ok', "✔ «{$u['nombre_usuario']}» fue aprobado como " . ucfirst($nuevo) . ". Ya puede iniciar sesión."];

            } elseif ($accion === 'rechazar') {
                if ($u['rol'] !== 'pendiente') {
                    throw new Exception("Solo se pueden rechazar cuentas pendientes.");
                }
                $st = $conexion->prepare("DELETE FROM usuarios WHERE id_usuario = ? AND rol = 'pendiente'");
                $st->bind_param("i", $id);
                $st->execute();
                $st->close();
                $_SESSION['flash'] = ['ok', "Solicitud de «{$u['nombre_usuario']}» rechazada y eliminada."];

            } elseif ($accion === 'rol') {
                $nuevo = $_POST['rol'] ?? '';
                if (!in_array($nuevo, $roles_validos, true)) {
                    throw new Exception("Rol no válido.");
                }
                if ($id === $yo) {
                    throw new Exception("No puedes cambiar tu propio rol.");
                }
                if ($u['rol'] === ROL_ADMIN && $nuevo !== ROL_ADMIN && (int)$u['activo'] === 1 && admins_activos($conexion) <= 1) {
                    throw new Exception("Debe quedar al menos un administrador activo.");
                }
                $st = $conexion->prepare("UPDATE usuarios SET rol = ? WHERE id_usuario = ?");
                $st->bind_param("si", $nuevo, $id);
                $st->execute();
                $st->close();
                $_SESSION['flash'] = ['ok', "✔ «{$u['nombre_usuario']}» ahora es " . ucfirst($nuevo) . "."];

            } elseif ($accion === 'bloquear' || $accion === 'activar') {
                if ($id === $yo) {
                    throw new Exception("No puedes bloquear tu propio usuario.");
                }
                if ($accion === 'bloquear' && $u['rol'] === ROL_ADMIN && admins_activos($conexion) <= 1) {
                    throw new Exception("Debe quedar al menos un administrador activo.");
                }
                $activo = ($accion === 'activar') ? 1 : 0;
                $st = $conexion->prepare("UPDATE usuarios SET activo = ? WHERE id_usuario = ?");
                $st->bind_param("ii", $activo, $id);
                $st->execute();
                $st->close();
                $_SESSION['flash'] = ['ok', $activo ? "✔ «{$u['nombre_usuario']}» fue desbloqueado." : "🔒 «{$u['nombre_usuario']}» fue bloqueado."];

            } elseif ($accion === 'clave') {
                $clave = $_POST['clave'] ?? '';
                if (!preg_match('/^[A-Za-z0-9]{6}$/', $clave)) {
                    throw new Exception("La contraseña debe tener exactamente 6 caracteres, solo letras o números (sin espacios, puntos ni guiones).");
                }
                $hash = password_hash($clave, PASSWORD_DEFAULT);
                $st = $conexion->prepare("UPDATE usuarios SET password = ? WHERE id_usuario = ?");
                $st->bind_param("si", $hash, $id);
                $st->execute();
                $st->close();
                $_SESSION['flash'] = ['ok', "🔑 Se cambió la contraseña de «{$u['nombre_usuario']}»."];

            } else {
                throw new Exception("Acción no válida.");
            }
        }
    } catch (Throwable $e) {
        $_SESSION['flash'] = ['error', $e->getMessage()];
    }

    header("Location: usuarios.php");
    exit();
}

// ------------------------------------------------------------------
// Datos para mostrar
// ------------------------------------------------------------------
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$usuarios = $conexion->query("SELECT id_usuario, nombre_usuario, rol, activo FROM usuarios ORDER BY (rol = 'pendiente') DESC, activo DESC, rol ASC, nombre_usuario ASC")
                     ->fetch_all(MYSQLI_ASSOC);
$n_pendientes = count(array_filter($usuarios, fn($x) => $x['rol'] === 'pendiente'));
$csrf = $_SESSION['csrf'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Los Chicarcas - Usuarios</title>
    <link rel="stylesheet" href="css/estilos.css">
    <style>
        .us-aviso { padding: 12px 16px; border-radius: 10px; font-size: 14px; font-weight: 600; margin-bottom: 16px; }
        .us-aviso.ok    { background: #d1e7dd; color: #0f5132; border: 1px solid #badbcc; }
        .us-aviso.error { background: #f8d7da; color: #842029; border: 1px solid #f5c2c7; }

        .us-nuevo { background: #fff; border: 1px solid var(--color-bordes); border-radius: 14px; margin-bottom: 18px; }
        .us-nuevo > summary { list-style: none; cursor: pointer; padding: 14px 18px; font-weight: 800; color: var(--color-principal-naranja); user-select: none; }
        .us-nuevo > summary::-webkit-details-marker { display: none; }
        .us-nuevo form { padding: 0 18px 18px; display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap: 12px; align-items: end; }

        .us-campo label { display: block; font-size: 11px; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: .04em; margin-bottom: 4px; }
        .us-campo input, .us-campo select, .us-card select, .us-card input[type=password], .us-card input[type=text] {
            width: 100%; height: 40px; padding: 0 10px; border: 1px solid var(--color-bordes); border-radius: 8px; font-size: 14px; background: #F8FAFC;
        }
        .us-campo input:focus, .us-campo select:focus, .us-card select:focus, .us-card input:focus { outline: none; border-color: var(--color-principal-naranja); box-shadow: 0 0 0 3px rgba(255,90,0,.12); }
        .us-fila-clave { display: flex; gap: 6px; }
        .us-btn-gen { flex: 0 0 auto; padding: 0 12px; border: 1px solid var(--color-bordes); background: #fff; border-radius: 8px; font-weight: 700; cursor: pointer; color: #475569; }

        .us-lista { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 14px; }
        .us-card { background: #fff; border: 1px solid var(--color-bordes); border-radius: 14px; padding: 16px; box-shadow: 0 4px 6px -1px rgba(0,0,0,.05); display: flex; flex-direction: column; gap: 12px; }
        .us-card.bloqueado { background: #FAFAFA; border-style: dashed; }
        .us-card.bloqueado .us-nombre { color: var(--gris-400); text-decoration: line-through; }
        .us-cab { display: flex; align-items: center; gap: 12px; }
        .us-avatar { width: 42px; height: 42px; flex: 0 0 42px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 800; color: #fff; background: linear-gradient(135deg, #FF7A2E, var(--color-principal-naranja)); }
        .us-card.bloqueado .us-avatar { background: #94A3B8; }
        .us-nombre { font-size: 16px; font-weight: 800; color: var(--texto); word-break: break-all; }
        .us-chips { display: flex; gap: 6px; flex-wrap: wrap; margin-top: 3px; }
        .us-chip { font-size: 11px; font-weight: 700; padding: 3px 9px; border-radius: 999px; background: #F1F5F9; color: #475569; }
        .us-chip.admin { background: rgba(255,90,0,.12); color: var(--color-principal-naranja); }
        .us-chip.cajero { background: #dbeafe; color: #1d4ed8; }
        .us-chip.activo { background: #d1e7dd; color: #0f5132; }
        .us-chip.bloq { background: #f8d7da; color: #842029; }
        .us-chip.yo { background: #fff3cd; color: #664d03; }

        .us-form-rol { display: flex; gap: 6px; }
        .us-btn { height: 40px; padding: 0 14px; border: 0; border-radius: 8px; font-weight: 700; cursor: pointer; font-size: 13px; }
        .us-btn.prim { background: var(--color-principal-naranja); color: #fff; }
        .us-btn.gris { background: #e2e8f0; color: #334155; }
        .us-btn.rojo { background: #fff; color: #dc2626; border: 1px solid #fecaca; }
        .us-btn.verde { background: #16a34a; color: #fff; }
        .us-btn[disabled] { opacity: .45; cursor: not-allowed; }
        .us-acciones { display: flex; gap: 8px; flex-wrap: wrap; }
        .us-acciones form { margin: 0; flex: 1; }
        .us-acciones .us-btn { width: 100%; }

        .us-clave > summary { cursor: pointer; font-size: 13px; font-weight: 700; color: #475569; user-select: none; }
        .us-clave form { margin-top: 10px; display: flex; flex-direction: column; gap: 8px; }
        .us-aviso.pend { background: #fff3cd; color: #664d03; border: 1px solid #ffecb5; }
        .us-card.pendiente { border: 2px solid #F59E0B; background: #FFFBEB; }
        .us-chip.pend { background: #fff3cd; color: #664d03; }
        .us-ayuda { font-size: 12px; color: var(--gris-400); margin-top: 14px; }
    </style>
</head>
<body class="pagina-usuarios">

    <input type="checkbox" id="toggle-sidebar-check" style="display: none;">
    <label for="toggle-sidebar-check" class="btn-hamburguesa-toggle" style="position: fixed; top: 22px; left: 25px; z-index: 2000; font-size: 26px; color: #FFFFFF !important; cursor: pointer; user-select: none;">☰</label>

    <?php include "componentes/header.php"; ?>

    <div class="contenedor-principal">
        <?php include "componentes/sidebar.php"; ?>

        <main class="contenido-pagina">
            <div class="col-izquierda">

                <div class="inv-cabecera">
                    <h2>👥 Usuarios (<?php echo count($usuarios); ?>)</h2>
                </div>

                <div class="tabla-responsiva">

                    <?php if ($flash): ?>
                        <div class="us-aviso <?php echo $flash[0] === 'ok' ? 'ok' : 'error'; ?>"><?php echo htmlspecialchars($flash[1]); ?></div>
                    <?php endif; ?>

                    <?php if ($n_pendientes > 0): ?>
                        <div class="us-aviso pend">⏳ <?php echo $n_pendientes; ?> cuenta(s) esperan tu aprobación. Asígnales un rol para que puedan entrar.</div>
                    <?php endif; ?>

                    <!-- Nuevo usuario -->
                    <details class="us-nuevo">
                        <summary>➕ Agregar usuario</summary>
                        <form method="POST" action="usuarios.php" autocomplete="off">
                            <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
                            <input type="hidden" name="accion" value="crear">

                            <div class="us-campo">
                                <label for="nuevo-nombre">Usuario</label>
                                <input type="text" id="nuevo-nombre" name="nombre" required minlength="3" maxlength="50" placeholder="Ej: Adler">
                            </div>
                            <div class="us-campo">
                                <label for="nuevo-clave">Contraseña</label>
                                <div class="us-fila-clave">
                                    <input type="password" id="nuevo-clave" name="clave" required minlength="6" maxlength="6" pattern="[A-Za-z0-9]{6}" title="6 caracteres: solo letras o números" placeholder="6 letras o números">
                                    <button type="button" class="us-btn-gen" onclick="generarClave('nuevo-clave')">🎲</button>
                                </div>
                            </div>
                            <div class="us-campo">
                                <label for="nuevo-rol">Rol</label>
                                <select id="nuevo-rol" name="rol">
                                    <option value="cajero">Cajero (Punto de Venta y Corte)</option>
                                    <option value="<?php echo htmlspecialchars(ROL_ADMIN); ?>">Administrador (acceso total)</option>
                                </select>
                            </div>
                            <div class="us-campo">
                                <button type="submit" class="us-btn prim" style="width:100%;">Crear usuario</button>
                            </div>
                        </form>
                    </details>

                    <!-- Lista -->
                    <div class="us-lista">
                        <?php foreach ($usuarios as $u):
                            $es_yo     = ((int)$u['id_usuario'] === $yo);
                            $bloqueado = ((int)$u['activo'] !== 1);
                            $es_pend   = ($u['rol'] === 'pendiente');
                            $opciones  = array_unique(array_merge($roles_validos, [$u['rol']]));
                        ?>
                            <div class="us-card <?php echo $es_pend ? 'pendiente' : ($bloqueado ? 'bloqueado' : ''); ?>">
                                <div class="us-cab">
                                    <div class="us-avatar"><?php echo htmlspecialchars(mb_strtoupper(mb_substr($u['nombre_usuario'], 0, 1))); ?></div>
                                    <div>
                                        <div class="us-nombre"><?php echo htmlspecialchars($u['nombre_usuario']); ?></div>
                                        <div class="us-chips">
                                            <?php if ($es_pend): ?>
                                                <span class="us-chip pend">⏳ Pendiente de aprobación</span>
                                            <?php else: ?>
                                                <span class="us-chip <?php echo $u['rol'] === ROL_ADMIN ? 'admin' : ($u['rol'] === 'cajero' ? 'cajero' : ''); ?>"><?php echo htmlspecialchars(ucfirst($u['rol'])); ?></span>
                                                <span class="us-chip <?php echo $bloqueado ? 'bloq' : 'activo'; ?>"><?php echo $bloqueado ? '🔒 Bloqueado' : '● Activo'; ?></span>
                                            <?php endif; ?>
                                            <?php if ($es_yo): ?><span class="us-chip yo">Tú</span><?php endif; ?>
                                        </div>
                                    </div>
                                </div>

                                <?php if ($es_pend): ?>
                                    <!-- Aprobar: se asigna el rol y se activa -->
                                    <form method="POST" action="usuarios.php" class="us-form-rol">
                                        <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
                                        <input type="hidden" name="accion" value="aprobar">
                                        <input type="hidden" name="id_usuario" value="<?php echo (int)$u['id_usuario']; ?>">
                                        <select name="rol" required>
                                            <option value="">Asignar rol…</option>
                                            <?php foreach ($roles_validos as $r): ?>
                                                <option value="<?php echo htmlspecialchars($r); ?>"><?php echo htmlspecialchars(ucfirst($r)); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button type="submit" class="us-btn verde">✔ Aprobar</button>
                                    </form>
                                    <form method="POST" action="usuarios.php" style="margin:0;"
                                          onsubmit="return confirm('¿Rechazar y eliminar esta solicitud?');">
                                        <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
                                        <input type="hidden" name="accion" value="rechazar">
                                        <input type="hidden" name="id_usuario" value="<?php echo (int)$u['id_usuario']; ?>">
                                        <button type="submit" class="us-btn rojo" style="width:100%;">✖ Rechazar solicitud</button>
                                    </form>
                                <?php else: ?>
                                <!-- Rol -->
                                <form method="POST" action="usuarios.php" class="us-form-rol">
                                    <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
                                    <input type="hidden" name="accion" value="rol">
                                    <input type="hidden" name="id_usuario" value="<?php echo (int)$u['id_usuario']; ?>">
                                    <select name="rol" <?php echo $es_yo ? 'disabled' : ''; ?>>
                                        <?php foreach ($opciones as $r): ?>
                                            <option value="<?php echo htmlspecialchars($r); ?>" <?php echo $r === $u['rol'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars(ucfirst($r)); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="submit" class="us-btn gris" <?php echo $es_yo ? 'disabled' : ''; ?>>Guardar rol</button>
                                </form>

                                <!-- Bloquear / desbloquear -->
                                <div class="us-acciones">
                                    <form method="POST" action="usuarios.php"
                                          onsubmit="return confirm('<?php echo $bloqueado ? '¿Desbloquear' : '¿Bloquear'; ?> a este usuario?');">
                                        <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
                                        <input type="hidden" name="accion" value="<?php echo $bloqueado ? 'activar' : 'bloquear'; ?>">
                                        <input type="hidden" name="id_usuario" value="<?php echo (int)$u['id_usuario']; ?>">
                                        <button type="submit" class="us-btn <?php echo $bloqueado ? 'verde' : 'rojo'; ?>" <?php echo $es_yo ? 'disabled' : ''; ?>>
                                            <?php echo $bloqueado ? '🔓 Desbloquear' : '🔒 Bloquear'; ?>
                                        </button>
                                    </form>
                                </div>

                                <!-- Contraseña -->
                                <details class="us-clave">
                                    <summary>🔑 Cambiar contraseña</summary>
                                    <form method="POST" action="usuarios.php" autocomplete="off"
                                          onsubmit="return confirm('¿Cambiar la contraseña de este usuario?');">
                                        <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
                                        <input type="hidden" name="accion" value="clave">
                                        <input type="hidden" name="id_usuario" value="<?php echo (int)$u['id_usuario']; ?>">
                                        <div class="us-fila-clave">
                                            <input type="password" id="clave-<?php echo (int)$u['id_usuario']; ?>" name="clave" required minlength="6" maxlength="6" pattern="[A-Za-z0-9]{6}" title="6 caracteres: solo letras o números" placeholder="Nueva (6 letras o números)">
                                            <button type="button" class="us-btn-gen" onclick="generarClave('clave-<?php echo (int)$u['id_usuario']; ?>')">🎲</button>
                                        </div>
                                        <button type="submit" class="us-btn prim">Guardar contraseña</button>
                                    </form>
                                </details>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <p class="us-ayuda">Las contraseñas tienen exactamente 6 caracteres (letras o números) y se guardan cifradas: no se pueden ver. Si alguien la olvida, asígnale una nueva con 🔑. El botón 🎲 genera una y la muestra para que se la compartas.</p>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Genera una contraseña aleatoria de 6 caracteres y la muestra para copiarla
        function generarClave(idInput) {
            const letras = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789';
            const azar = new Uint32Array(6);
            crypto.getRandomValues(azar);
            const campo = document.getElementById(idInput);
            campo.value = Array.from(azar, n => letras[n % letras.length]).join('');
            campo.type = 'text';
        }
    </script>
</body>
</html>
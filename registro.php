<?php
require_once "config/conexion.php";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Quien ya tiene sesión (incluido el invitado) no necesita registrarse
if (isset($_SESSION['usuario'])) {
    header("Location: index.php");
    exit();
}

// Token anti-CSRF
if (empty($_SESSION['csrf_registro'])) {
    $_SESSION['csrf_registro'] = bin2hex(random_bytes(32));
}

const MAX_PENDIENTES = 30;   // tope de solicitudes sin aprobar (evita que llenen la tabla)

$error  = "";
$nombre = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Campo trampa para robots: una persona nunca lo ve ni lo llena
        if (!empty($_POST['sitio_web'])) {
            header("Location: login.php?registro=ok");
            exit();
        }
        if (!hash_equals($_SESSION['csrf_registro'], $_POST['csrf'] ?? '')) {
            throw new Exception("La página caducó. Recárgala e intenta de nuevo.");
        }

        // Máximo 5 intentos cada 10 minutos por sesión
        $ahora = time();
        $_SESSION['reg_intentos'] = array_filter($_SESSION['reg_intentos'] ?? [], fn($t) => $t > $ahora - 600);
        if (count($_SESSION['reg_intentos']) >= 5) {
            throw new Exception("Demasiados intentos. Espera unos minutos.");
        }
        $_SESSION['reg_intentos'][] = $ahora;

        $nombre    = trim($_POST['nombre'] ?? '');
        $clave     = $_POST['clave'] ?? '';
        $confirmar = $_POST['confirmar'] ?? '';

        if (!preg_match('/^[\p{L}\p{N}._-]{3,50}$/u', $nombre)) {
            throw new Exception("El usuario debe tener de 3 a 50 caracteres (letras, números, punto, guion o guion bajo, sin espacios).");
        }
        if (mb_strtolower($nombre) === 'invitado') {
            throw new Exception("Ese nombre de usuario no está disponible.");
        }
        if (!preg_match('/^[A-Za-z0-9]{6}$/', $clave)) {
            throw new Exception("La contraseña debe tener exactamente 6 caracteres, solo letras o números (sin espacios, puntos ni guiones).");
        }
        if ($clave !== $confirmar) {
            throw new Exception("Las contraseñas no coinciden.");
        }

        // Tope de solicitudes pendientes
        $r = $conexion->query("SELECT COUNT(*) AS n FROM usuarios WHERE rol = 'pendiente'");
        if ($r && (int) $r->fetch_assoc()['n'] >= MAX_PENDIENTES) {
            throw new Exception("Hay demasiadas solicitudes en espera. Intenta más tarde o habla con el administrador.");
        }

        // Nombre repetido
        $st = $conexion->prepare("SELECT 1 FROM usuarios WHERE nombre_usuario = ?");
        $st->bind_param("s", $nombre);
        $st->execute();
        $existe = $st->get_result()->num_rows > 0;
        $st->close();
        if ($existe) {
            throw new Exception("Ese nombre de usuario ya está en uso.");
        }

        // Se crea PENDIENTE y BLOQUEADA: no entra hasta que un administrador le asigne rol
        $hash = password_hash($clave, PASSWORD_DEFAULT);
        $st = $conexion->prepare("INSERT INTO usuarios (nombre_usuario, password, rol, activo) VALUES (?, ?, 'pendiente', 0)");
        $st->bind_param("ss", $nombre, $hash);
        $st->execute();
        $st->close();

        unset($_SESSION['reg_intentos']);
        header("Location: login.php?registro=ok");
        exit();

    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crear cuenta - Los Chicarcas</title>
    <link rel="stylesheet" href="css/estilos.css">
</head>
<body class="login-contenedor">

    <div class="login-tarjeta">
        <img src="img/logochicarcas.png" alt="Ferreteria Los Chicarcas" style="height:90px; margin-bottom:10px;">
        <h2 style="color:var(--color-fondo-oscuro); font-size: 24px; font-weight:800; margin-bottom:2px;">Crear cuenta</h2>
        <p style="color:var(--color-principal-naranja); font-weight:bold; font-size:13px; letter-spacing:1px; margin-bottom:18px;">FERRETERÍA "LOS CHICARCAS"</p>

        <div style="background:#fff3cd; color:#664d03; padding:10px; border-radius:8px; font-size:13px; margin-bottom:18px; border:1px solid #ffecb5; text-align:left;">
            ⏳ Tu cuenta quedará <strong>pendiente</strong> hasta que un administrador la apruebe y te asigne un rol.
        </div>

        <?php if (!empty($error)): ?>
            <div style="background-color: #f8d7da; color: #721c24; padding: 10px; border-radius: 8px; font-weight:bold; font-size: 14px; margin-bottom:20px; border: 1px solid #f5c6cb;">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <form action="registro.php" method="POST" autocomplete="off">
            <input type="hidden" name="csrf" value="<?php echo $_SESSION['csrf_registro']; ?>">

            <!-- Campo trampa: oculto para las personas -->
            <div style="position:absolute; left:-9999px;" aria-hidden="true">
                <label>No llenar <input type="text" name="sitio_web" tabindex="-1" autocomplete="off"></label>
            </div>

            <label for="nombre">Usuario</label>
            <input type="text" id="nombre" name="nombre" required minlength="3" maxlength="50"
                   value="<?php echo htmlspecialchars($nombre); ?>" placeholder="Elige tu usuario">

            <label for="clave">Contraseña</label>
            <input type="password" id="clave" name="clave" required minlength="6" maxlength="6" pattern="[A-Za-z0-9]{6}" title="6 caracteres: solo letras o números" placeholder="6 letras o números">

            <label for="confirmar">Confirmar contraseña</label>
            <input type="password" id="confirmar" name="confirmar" required minlength="6" maxlength="6" pattern="[A-Za-z0-9]{6}" title="6 caracteres: solo letras o números" placeholder="Repite tu contraseña">

            <button type="submit" class="btn-login-chicarcas">Crear cuenta</button>
        </form>

        <p class="login-registro">¿Ya tienes cuenta? <a href="login.php">Iniciar sesión</a></p>
    </div>

</body>
</html>
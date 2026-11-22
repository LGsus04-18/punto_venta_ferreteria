<?php
/**
 * config/permisos.php
 * Roles:
 *   - invitado : solo ve el Punto de Venta (sin menú, no cobra)
 *   - cajero   : solo ve Punto de Venta y Corte de Caja; para registrar el corte
 *                necesita la contraseña de un administrador
 *   - cualquier otro rol (admin, etc.): acceso completo
 */

// Valor de la columna "rol" que tienen tus administradores
const ROL_ADMIN = 'admin';

function rol_actual(): string
{
    return $_SESSION['rol'] ?? '';
}

/** Para inventario, reportes, pedidos... Manda al Punto de Venta a invitados y cajeros. */
function bloquear_restringidos(): void
{
    if (in_array(rol_actual(), ['invitado', 'cajero'], true)) {
        header("Location: index.php");
        exit();
    }
}

/** Para el corte de caja: solo se bloquea a los invitados. */
function bloquear_invitado(): void
{
    if (rol_actual() === 'invitado') {
        header("Location: index.php");
        exit();
    }
}

/** ¿Está bloqueado el intento de clave por demasiados errores? */
function clave_admin_bloqueada(): bool
{
    return ($_SESSION['clave_bloqueo'] ?? 0) > time();
}

/**
 * Verifica la contraseña contra TODOS los administradores.
 * 5 errores seguidos = 60 segundos de bloqueo.
 * (Supone contraseñas guardadas con password_hash.)
 */
function clave_admin_valida(mysqli $conexion, string $clave): bool
{
    if (clave_admin_bloqueada() || $clave === '') {
        return false;
    }

    $rol = ROL_ADMIN;
    $st  = $conexion->prepare("SELECT password FROM usuarios WHERE rol = ?");
    $st->bind_param("s", $rol);
    $st->execute();
    $res = $st->get_result();

    while ($f = $res->fetch_assoc()) {
        if (password_verify($clave, $f['password'])) {
            $_SESSION['clave_fallos'] = 0;
            return true;
        }
    }

    $_SESSION['clave_fallos'] = ($_SESSION['clave_fallos'] ?? 0) + 1;
    if ($_SESSION['clave_fallos'] >= 5) {
        $_SESSION['clave_bloqueo'] = time() + 60;
        $_SESSION['clave_fallos']  = 0;
    }
    return false;
}
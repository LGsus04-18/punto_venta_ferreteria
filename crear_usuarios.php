<?php
/**
 * crear_usuarios.php  -  SE USA UNA SOLA VEZ Y SE BORRA DEL SERVIDOR.
 * Crea dos usuarios con rol "cajero" (solo Punto de Venta y Corte de Caja).
 * Solo lo puede ejecutar un administrador con sesión iniciada.
 */
require_once "config/conexion.php";
require_once "config/permisos.php";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (rol_actual() !== ROL_ADMIN) {
    http_response_code(403);
    exit("Solo un administrador puede ejecutar este script.");
}

// ===================== EDITA ESTOS DATOS =====================
$nuevos = [
    $nuevos = [
        ['usuario' => 'Adler',    'password' => '0000'],
        ['usuario' => 'Emiliano', 'password' => '5555'],
];
$rol = 'cajero';
// =============================================================

header('Content-Type: text/plain; charset=utf-8');

foreach ($nuevos as $u) {
    if (strpos($u['password'], 'CAMBIAR') !== false) {
        echo "✖ {$u['usuario']}: cambia la contraseña de ejemplo antes de ejecutar.\n";
        continue;
    }

    $st = $conexion->prepare("SELECT 1 FROM usuarios WHERE nombre_usuario = ?");
    $st->bind_param("s", $u['usuario']);
    $st->execute();
    if ($st->get_result()->num_rows > 0) {
        echo "• {$u['usuario']}: ya existe, no se creó.\n";
        continue;
    }
    $st->close();

    $hash = password_hash($u['password'], PASSWORD_DEFAULT);
    $st = $conexion->prepare("INSERT INTO usuarios (nombre_usuario, password, rol) VALUES (?, ?, ?)");
    $st->bind_param("sss", $u['usuario'], $hash, $rol);

    echo $st->execute()
        ? "✔ {$u['usuario']}: creado con rol '$rol'.\n"
        : "✖ {$u['usuario']}: " . $st->error . "\n";
    $st->close();
}

echo "\nListo. BORRA este archivo del servidor.\n";
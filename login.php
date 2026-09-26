<?php 
    require_once "config/conexion.php"; 
 
    if (isset($_SESSION['usuario'])) { 
        header("Location: index.php"); 
        exit(); 
    } 
    
    $error_mensaje = ""; 
 
    if ($_SERVER["REQUEST_METHOD"] == "POST") { 
        $txt_usuario = trim($_POST['usuario']); 
        $txt_password = $_POST['password'];

        if (!empty($txt_usuario) && !empty($txt_password)) { 
            $sql = "SELECT id_usuario, nombre_usuario, password, rol FROM usuarios WHERE nombre_usuario = ?"; 
            $stmt = $conexion->prepare($sql);

            if ($stmt) { 
                $stmt->bind_param("s", $txt_usuario); 
                $stmt->execute(); 
                $resultado = $stmt->get_result();

                if ($resultado->num_rows === 1) { 
                    $usuario_db = $resultado->fetch_assoc();

                    if (password_verify($txt_password, $usuario_db['password'])) {
                    
                        session_regenerate_id(true);

                        $_SESSION['usuario'] = $usuario_db['nombre_usuario'];
                        $_SESSION['rol'] = $usuario_db['rol'];
                        $_SESSION['id_usuario'] = $usuario_db['id_usuario'];

                        header("Location: index.php");
                        exit();
                    } else {
                        $error_mensaje = "La contraseña ingresada es incorrecta.";
                    } 
                } else { 
                    $error_mensaje = "El usuario ingresado no existe"; 
                } 
                $stmt->close(); 
            } 
        } else { 
            $error_mensaje = "Llena todos los campos, por favor."; 
        } 
    } 
?> 
 
<!DOCTYPE html> 
<html lang="es"> 
    <head> 
        <meta charset="UTF-8"> 
        <meta name="viewport" content="width=device-width, initial-scale=1.0"> 
        <title>Login - CHICARCAS</title> 
    </head> 
    <body>  
        <h2>Iniciar Sesión</h2> 
        <h3>Ferretería "El Chicarcas"</h3> 
    
            <?php if (!empty($error_mensaje)): ?> 
                <p class="error"><?php echo $error_mensaje; ?></p> 
            <?php endif; ?> 
    
            <form action="login.php" method="POST"> 
                <div> 
                    <label for="usuario">Usuario:</label> 
                    <input type="text" id="usuario" name="usuario" required> 
                </div> 
                <br> 
                <div> 
                    <label for="password">Contraseña:</label> 
                    <input type="password" id="password" name="password" required> 
                </div> 
                <br> 
                <div> 
                    <button type="submit">Entrar al Sistema</button> 
                </div> 
            </form> 
        </div> 
    
    </body> 
</html> 
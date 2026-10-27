<?php 
    require_once "config/conexion.php"; 
 
    if (isset($_SESSION['usuario'])) { 
        header("Location: index.php"); 
        exit(); 
    } 
    
    $error_mensaje = ""; 
 
    if ($_SERVER["REQUEST_METHOD"] == "POST") { //verifica envio con POST para que no se vea en la URL
        $txt_usuario = trim($_POST['usuario']);     //trim elimina espacios vacios al principio y al final
        $txt_password = $_POST['password'];

        if (!empty($txt_usuario) && !empty($txt_password)) {    //Verifica que los campos no vayan vacios
            $sql = "SELECT id_usuario, nombre_usuario, password, rol FROM usuarios WHERE nombre_usuario = ?";   // el ?, le dice a la BD, que trate este dato como texto plano y no como una instruccion para evitar inyecciones SQL
            $stmt = $conexion->prepare($sql);   //Prepared statement, se envia la estructura de la consulta sin los datos, y los datos por separado haciendo hincapié en que deben de ser interpretados como texto plano

            if ($stmt) { 
                $stmt->bind_param("s", $txt_usuario);   //Se inyecta de forma segura el usuario, la s es para confirmar que sea tratado como un string
                $stmt->execute();   //ejecuta busqueda
                $resultado = $stmt->get_result();   //guarda resultado

                if ($resultado->num_rows === 1) {   //si encuentra al usuario avanza, sino saltamos al error
                    $usuario_db = $resultado->fetch_assoc();    //extrae los datos del usuario, incluyendo el hash del password

                    if (password_verify($txt_password, $usuario_db['password'])) {  //toma la contraseña escrita y comprueba por medio de matemáticas que coincida con el hash de la BD, sin revelar jamás la contraseña
                    
                        session_regenerate_id(true);    //cada que entra se le asigna un nuevo y aleatorio session ID, y destruye el viejo para evitar el ataque de Session Fixation

                        //Guarda en la memoria del servidor los datos del usuario logueado, para saber quien esta en el index
                        $_SESSION['usuario'] = $usuario_db['nombre_usuario'];
                        $_SESSION['rol'] = $usuario_db['rol'];
                        $_SESSION['id_usuario'] = $usuario_db['id_usuario'];

                        //Nos manda al punto de venta y detiene el código del login
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
    <title>Login - Los Chicarcas</title> 
    <link rel="stylesheet" href="css/estilos.css"> 
</head> 
<body class="login-contenedor"> 
 
    <div class="login-tarjeta">  
        <img src="img/logochicarcas.png" alt="Ferreteria Los Chicarcas" style="height:90px; margin-bottom:10px;"> 
        <h2 style="color:var(--color-fondo-oscuro); font-size: 24px; font-weight:800; margin-bottom:2px;">Iniciar Sesión</h2> 
        <p style="color:var(--color-principal-naranja); font-weight:bold; font-size:13px; letter-spacing:1px; margin-bottom:25px;">FERRETERÍA "LOS CHICARCAS"</p> 
 
        <?php if (!empty($error_mensaje)): ?>
            <div style="background-color: #f8d7da; color: #721c24; padding: 10px; border-radius: 8px; font-weight:bold; font-size: 14px; margin-bottom:20px; border: 1px solid #f5c6cb;">
                <?php echo $error_mensaje; ?>
            </div>
        <?php endif; ?>
 
        <form action="login.php" method="POST">
            <label for="usuario">Usuario</label>
            <input type="text" id="usuario" name="usuario" required placeholder="Ingresa tu usuario">

            <label for="password">Contraseña</label>
            <input type="password" id="password" name="password" required placeholder="••••••••">

            <button type="submit" class="btn-login-chicarcas">Iniciar Sesión</button>
        </form>
    </div> 
 
</body> 
</html>
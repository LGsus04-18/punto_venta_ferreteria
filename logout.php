<?php 
require_once "config/conexion.php"; 
 
// Borramos todas las variables de la sesión 
$_SESSION = array(); 
 
// Destruimos la sesión en el servidor completamente 
session_destroy(); 
// Lo mandamos de regreso al login 
header("Location: login.php"); 
exit(); 
?>
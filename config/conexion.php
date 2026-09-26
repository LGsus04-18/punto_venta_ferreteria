<?php
    session_start();

    $host = "localhost";
    $user = "root";
    $password = "";
    $database = "ferreteria_db";

    $conexion = new mysqli($host, $user, $password, $database);

    if($conexion->connect_error){
        die("Error de conexion: " . $conexion->connect_error);
    }

    $conexion->set_charset("utf8");
?>
<?php 
require_once "config/conexion.php"; 
 
if (!isset($_SESSION['usuario'])) { 
    header("Location: login.php"); 
    exit(); 
} 
 
$sql_rep = "SELECT id_venta, fecha_hora, total FROM ventas ORDER BY id_venta DESC"; 
$resultado_rep = $conexion->query($sql_rep); 
?> 
 
<!DOCTYPE html> 
<html lang="es"> 
<head> 
    <meta charset="UTF-8"> 
    <title>Los Chicarcas - Reportes</title> 
    <link rel="stylesheet" href="css/estilos.css"> 
</head> 
<body> 
    <input type="checkbox" id="toggle-sidebar-check" style="display: none;">

        <label for="toggle-sidebar-check" class="btn-hamburguesa-toggle" style="position: fixed; top: 22px; left: 25px; z-index: 2000; font-size: 26px; color: #FFFFFF !important; cursor: pointer; user-select: none;">☰</label> 
    <input type="checkbox" id="toggle-sidebar-check" style="display: none;"> 
    <?php include "componentes/header.php"; ?> 
 
    <div class="contenedor-principal"> 
        <?php include "componentes/sidebar.php"; ?> 
         
        <main class="contenido-pagina"> 
            <div class="col-izquierda"> 
                <h2 style="margin-bottom: 20px;">
📊
 Historial y Reportes de Ventas</h2> 
                 
                <div class="tabla-responsiva"> 
                    <table> 
                        <thead> 
                            <tr> 
                                <th>Folio Ticket</th> 
                                <th>Fecha y Hora del Cobro</th> 
                                <th>Monto Total Cobrado</th> 
                            </tr> 
                        </thead> 
                        <tbody> 
                            <?php  
                            $gran_total = 0; 
                            if($resultado_rep->num_rows > 0): 
                                while($vnt = $resultado_rep->fetch_assoc()):  
                                    $gran_total += $vnt['total']; 
                                ?> 
                                    <tr> 
                                        <td><strong>#<?php echo str_pad($vnt['id_venta'], 6, '0', STR_PAD_LEFT); ?></strong></td> 
                                        <td><?php echo $vnt['fecha_hora']; ?></td> 
                                        <td style="color: green; font-weight: bold;">$<?php echo number_format($vnt['total'], 2); ?> MXN</td> 
                                    </tr> 
                                <?php endwhile; ?> 
                                <tr style="background: #FFF500; font-size: 16px;"> 
                                    <td colspan="2" style="text-align: right; font-weight: bold;">
💸
 GANANCIA TOTAL ACUMULADA:</td> 
                                    <td style="font-weight: bold; color: black;">$<?php echo number_format($gran_total, 2); ?> MXN</td> 
                                </tr> 
                            <?php else: ?> 
                                <tr><td colspan="3" style="text-align:center;">No se han realizado ventas en el turno, gordo.</td></tr> 
                            <?php endif; ?> 
                        </tbody> 
                    </table> 
                </div> 
            </div> 
        </main> 
    </div> 
</body> 
</html> 
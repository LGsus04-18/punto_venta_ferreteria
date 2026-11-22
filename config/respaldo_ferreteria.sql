SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

CREATE DATABASE IF NOT EXISTS `ferreteria_db` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `ferreteria_db`;

DROP TABLE IF EXISTS `cortes_caja`;
CREATE TABLE `cortes_caja` (
  `id_corte` int(11) NOT NULL,
  `fecha_hora` datetime NOT NULL DEFAULT current_timestamp(),
  `monto_inicial` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total_ventas_efectivo` decimal(10,2) NOT NULL DEFAULT 0.00,
  `efectivo_real_contado` decimal(10,2) NOT NULL DEFAULT 0.00,
  `id_usuario` int(11) NOT NULL,
  `diferencia` decimal(10,2) NOT NULL DEFAULT 0.00,
  `monto_inicial_siguiente_dia` decimal(10,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `cortes_caja` (`id_corte`, `fecha_hora`, `monto_inicial`, `total_ventas_efectivo`, `efectivo_real_contado`, `id_usuario`, `diferencia`, `monto_inicial_siguiente_dia`) VALUES
(2, '2026-10-28 17:00:30', 1000.00, 2641.00, 87059.00, 2, 83418.00, 500.00),
(3, '2026-10-29 17:38:15', 500.00, 3931.00, 4100.00, 2, -331.00, 500.00),
(4, '2026-11-05 14:56:37', 500.00, 16304.00, 500000.00, 2, 483196.00, 500.00),
(5, '2026-11-05 15:20:09', 500.00, 2485.00, 2985.00, 2, 0.00, 500.00),
(6, '2026-11-09 19:56:31', 500.00, 3017.00, 3517.00, 2, 0.00, 500.00),
(7, '2026-11-09 20:41:15', 500.00, 0.00, 500.00, 2, 0.00, 500.00),
(8, '2026-11-14 22:21:09', 500.00, 782.00, 1282.00, 3, 0.00, 500.00),
(9, '2026-11-15 17:51:59', 500.00, 3500.00, 4000.00, 2, 0.00, 500.00);

DROP TABLE IF EXISTS `detalle_ventas`;
CREATE TABLE `detalle_ventas` (
  `id_detalle` int(11) NOT NULL,
  `id_venta` int(11) NOT NULL,
  `id_producto` int(11) NOT NULL,
  `cantidad` int(11) NOT NULL,
  `precio_unitario` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `historial_ventas`;
CREATE TABLE `historial_ventas` (
  `id_historial` int(11) NOT NULL,
  `id_usuario` int(11) DEFAULT NULL,
  `id_venta` int(11) DEFAULT NULL,
  `total` decimal(10,2) DEFAULT NULL,
  `fecha` datetime DEFAULT NULL,
  `id_corte` int(11) DEFAULT NULL,
  `metodo_pago` enum('efectivo','tarjeta','transferencia') NOT NULL DEFAULT 'efectivo'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `historial_ventas` (`id_historial`, `id_usuario`, `id_venta`, `total`, `fecha`, `id_corte`, `metodo_pago`) VALUES
(82, 2, 53, 145.00, '2026-11-05 15:00:41', 5, 'efectivo'),
(83, 2, 54, 150.00, '2026-11-05 15:02:25', 5, 'tarjeta'),
(84, 2, 55, 2340.00, '2026-11-05 15:18:56', 5, 'efectivo'),
(85, 2, 56, 500.00, '2026-11-05 18:08:21', 6, 'tarjeta'),
(86, 2, 57, 75.00, '2026-11-05 18:08:40', 6, 'transferencia'),
(87, 2, 58, 35.00, '2026-11-05 18:08:53', 6, 'transferencia'),
(88, 2, 59, 22.00, '2026-11-05 18:09:04', 6, 'tarjeta'),
(89, 2, 60, 40.00, '2026-11-05 18:10:07', 6, 'tarjeta'),
(90, 2, 61, 260.00, '2026-11-05 18:10:21', 6, 'efectivo'),
(91, 2, 62, 38.00, '2026-11-05 18:10:39', 6, 'tarjeta'),
(92, 2, 63, 22.00, '2026-11-05 18:10:50', 6, 'tarjeta'),
(93, 2, 64, 38.00, '2026-11-05 18:11:24', 6, 'efectivo'),
(94, 2, 65, 500.00, '2026-11-05 18:11:44', 6, 'efectivo'),
(95, 2, 66, 260.00, '2026-11-05 18:12:03', 6, 'efectivo'),
(96, 2, 67, 500.00, '2026-11-05 18:12:48', 6, 'efectivo'),
(97, 2, 68, 75.00, '2026-11-05 18:14:48', 6, 'efectivo'),
(98, 2, 69, 280.00, '2026-11-05 18:16:35', 6, 'tarjeta'),
(99, 2, 70, 40.00, '2026-11-05 18:22:09', 6, 'tarjeta'),
(100, 2, 71, 15.00, '2026-11-05 18:22:20', 6, 'transferencia'),
(101, 2, 72, 40.00, '2026-11-05 18:22:30', 6, 'tarjeta'),
(102, 2, 73, 15.00, '2026-11-05 18:22:47', 6, 'transferencia'),
(103, 2, 74, 75.00, '2026-11-05 18:22:57', 6, 'tarjeta'),
(104, 2, 75, 110.00, '2026-11-05 18:23:07', 6, 'tarjeta'),
(105, 2, 76, 85.00, '2026-11-05 18:23:20', 6, 'tarjeta'),
(106, 2, 77, 650.00, '2026-11-05 18:23:33', 6, 'tarjeta'),
(107, 2, 78, 70.00, '2026-11-05 18:23:44', 6, 'tarjeta'),
(108, 2, 79, 615.00, '2026-11-05 23:28:52', 6, 'efectivo'),
(109, 2, 80, 97.00, '2026-11-06 00:01:38', 6, 'efectivo'),
(110, 2, 81, 97.00, '2026-11-06 00:09:02', 6, 'efectivo'),
(111, 2, 82, 500.00, '2026-11-06 00:18:05', 6, 'efectivo'),
(112, 2, 83, 75.00, '2026-11-09 19:54:49', 6, 'efectivo'),
(116, 2, 84, 75.00, '2026-11-09 20:39:32', 7, 'tarjeta'),
(117, 2, 85, 62.00, '2026-11-09 20:42:07', 8, 'transferencia'),
(118, 1, 86, 782.00, '2026-11-14 21:55:09', 8, 'efectivo'),
(119, 3, 87, 835.00, '2026-11-14 22:25:55', 9, 'tarjeta'),
(120, 5, 88, 592.00, '2026-11-14 22:26:32', 9, 'transferencia'),
(121, 2, 89, 137.00, '2026-11-14 22:27:17', 9, 'tarjeta'),
(122, 1, 90, 2127.00, '2026-11-14 22:28:07', 9, 'efectivo'),
(123, 2, 91, 575.00, '2026-11-14 22:58:46', 9, 'efectivo'),
(124, 1, 92, 798.00, '2026-11-15 01:59:00', 9, 'efectivo'),
(125, 1, 93, 695.00, '2026-11-15 01:59:14', 9, 'transferencia');

DROP TABLE IF EXISTS `pedidos_proveedor`;
CREATE TABLE `pedidos_proveedor` (
  `id_pedido` int(11) NOT NULL,
  `fecha` datetime NOT NULL DEFAULT current_timestamp(),
  `id_proveedor` int(11) NOT NULL,
  `id_producto` int(11) DEFAULT NULL,
  `producto_solicitado` varchar(150) NOT NULL,
  `cantidad` int(11) NOT NULL,
  `estatus` varchar(20) NOT NULL DEFAULT 'Pendiente',
  `fecha_recepcion` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `pedidos_proveedor` (`id_pedido`, `fecha`, `id_proveedor`, `id_producto`, `producto_solicitado`, `cantidad`, `estatus`, `fecha_recepcion`) VALUES
(1, '2026-10-28 17:28:12', 2, 31, 'pistola de calor de 500 wts 120v', 20, 'Recibido', '2026-11-14 22:51:05'),
(2, '2026-11-14 22:52:21', 1, NULL, 'tubos de PVC 5\"', 10, 'Pendiente', NULL),
(3, '2026-11-14 22:54:04', 1, NULL, 'Cable del #10 CONDUMEX', 10, 'Pendiente', NULL),
(4, '2026-11-14 22:56:18', 1, NULL, 'YESO Redimix 34.5 KG', 10, 'Pendiente', NULL),
(5, '2026-11-14 22:56:54', 1, NULL, 'PINTURA COMEX DE 1LT', 23, 'Pendiente', NULL),
(6, '2026-11-15 17:53:22', 1, NULL, 'Puerta Tambor 60x 120 mt', 10, 'Pendiente', NULL),
(7, '2026-11-15 17:54:34', 1, NULL, 'mazo de 10 kg', 5, 'Pendiente', NULL);

DROP TABLE IF EXISTS `productos`;
CREATE TABLE `productos` (
  `id_producto` int(11) NOT NULL,
  `nombre_producto` varchar(100) NOT NULL,
  `precio_venta` decimal(10,2) NOT NULL,
  `stock` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `productos` (`id_producto`, `nombre_producto`, `precio_venta`, `stock`) VALUES
(1, 'Pala redonda', 245.00, 15),
(2, 'Candado de seguridad 50mm', 500.00, 1),
(3, 'Focos LED 12W', 35.00, 49),
(4, 'Socket de porcelana', 18.00, 20),
(6, 'Cinta Teflón 1/2 pulgada', 15.00, 90),
(7, 'Cincel plano para concreto', 75.00, 39),
(8, 'Martillo de uña 16 oz', 160.00, 10),
(9, 'Cinta aislar negra', 22.00, 61),
(10, 'Flexómetro 5 metros', 85.00, 24),
(11, 'Cinta Masking tape', 40.00, 22),
(12, 'Rodillo para pintar 9 pulgadas', 65.00, 7),
(13, 'Espátula flexible 3 pulgadas', 45.00, 29),
(14, 'Pistola de calor profesional', 650.00, 70),
(15, 'Pinza de corte medianas', 130.00, 21),
(16, 'Pilas alcalinas AA (4 piezas)', 85.00, 37),
(17, 'Brocha para pintar 3 pulgadas', 38.00, 19),
(18, 'Bulto de Cemento 50kg', 260.00, 6),
(19, 'Cuchara para albañil 8 pulgadas', 110.00, 13),
(21, 'Tubo de Silicon transparente', 65.00, 29),
(22, 'Cubetas de plástico 19L', 70.00, 22),
(23, 'Estopa para limpieza 1kg', 55.00, 15),
(24, 'Lijadora orbital orbital', 799.00, 6),
(26, 'Disco de corte para metal 4.5', 25.00, 89),
(27, 'Pico con mango', 280.00, 9),
(28, 'Matraca de 1/2 pulgada con dados', 420.00, 7),
(29, 'Estacion de Calor de 500w y cautin de 300w', 859.80, 60),
(30, 'Soldadura de estaño el metro', 7.00, 20),
(31, 'pistola de calor de 500 wts 120v', 500.00, 1);

DROP TABLE IF EXISTS `proveedores`;
CREATE TABLE `proveedores` (
  `id_proveedor` int(11) NOT NULL,
  `nombre_proveedor` varchar(100) NOT NULL,
  `telefono` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `proveedores` (`id_proveedor`, `nombre_proveedor`, `telefono`) VALUES
(1, 'Trupper Herramientas', '55-7002-0020'),
(2, 'Fandeli Abrasivos', '55-5366-1515'),
(3, 'Iusa Electricidad', '800-900-4872');

DROP TABLE IF EXISTS `usuarios`;
CREATE TABLE `usuarios` (
  `id_usuario` int(11) NOT NULL,
  `nombre_usuario` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `rol` varchar(20) NOT NULL DEFAULT 'Cajero',
  `activo` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `usuarios` (`id_usuario`, `nombre_usuario`, `password`, `rol`, `activo`) VALUES
(2, 'Mani', '$2y$10$Rkee5bbxHCylEe457feJWe0.igCSnQuS2XR2TYggmi284d.p9E4eC', 'admin', 1),
(3, 'Adler', '$2y$10$n8AeJ.9dYr.ceriZpmofZ.tSpggiK08cR3MIGeIEuNc2vcZF8YbQi', 'cajero', 0),
(4, 'Emiliano', '$2a$10$6qEkNgG7E.YHF769djj1kODhZhluu0CLJWk0618Y0cTc4G2O1YmAu', 'cajero', 1),
(5, 'Yisus', '$2a$10$ccju5vZRH.gxxNQtqgAKYuMeZGx6WyXJnJ6AlxlTbWjc4ZdzGvnAS', 'admin', 1),
(6, 'Mamberroy', '$2y$10$wYVatNa1dNyEAyWIjhBsHuiBJnay8KY2cqggqeFLTkU5u7i4srW2K', 'pendiente', 0);

DROP TABLE IF EXISTS `ventas`;
CREATE TABLE `ventas` (
  `id_venta` int(11) NOT NULL,
  `fecha_hora` datetime NOT NULL DEFAULT current_timestamp(),
  `total` decimal(10,2) NOT NULL DEFAULT 0.00,
  `id_usuario` int(11) DEFAULT NULL,
  `metodo_pago` enum('efectivo','tarjeta','transferencia') NOT NULL DEFAULT 'efectivo'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


ALTER TABLE `cortes_caja`
  ADD PRIMARY KEY (`id_corte`);

ALTER TABLE `detalle_ventas`
  ADD PRIMARY KEY (`id_detalle`),
  ADD KEY `id_venta` (`id_venta`),
  ADD KEY `id_producto` (`id_producto`);

ALTER TABLE `historial_ventas`
  ADD PRIMARY KEY (`id_historial`);

ALTER TABLE `pedidos_proveedor`
  ADD PRIMARY KEY (`id_pedido`),
  ADD KEY `id_proveedor` (`id_proveedor`);

ALTER TABLE `productos`
  ADD PRIMARY KEY (`id_producto`);

ALTER TABLE `proveedores`
  ADD PRIMARY KEY (`id_proveedor`);

ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id_usuario`),
  ADD UNIQUE KEY `nombre_usuario` (`nombre_usuario`);

ALTER TABLE `ventas`
  ADD PRIMARY KEY (`id_venta`);


ALTER TABLE `cortes_caja`
  MODIFY `id_corte` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

ALTER TABLE `detalle_ventas`
  MODIFY `id_detalle` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=153;

ALTER TABLE `historial_ventas`
  MODIFY `id_historial` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=126;

ALTER TABLE `pedidos_proveedor`
  MODIFY `id_pedido` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

ALTER TABLE `productos`
  MODIFY `id_producto` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32;

ALTER TABLE `proveedores`
  MODIFY `id_proveedor` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

ALTER TABLE `usuarios`
  MODIFY `id_usuario` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

ALTER TABLE `ventas`
  MODIFY `id_venta` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=94;


ALTER TABLE `detalle_ventas`
  ADD CONSTRAINT `detalle_ventas_ibfk_1` FOREIGN KEY (`id_venta`) REFERENCES `ventas` (`id_venta`) ON DELETE CASCADE,
  ADD CONSTRAINT `detalle_ventas_ibfk_2` FOREIGN KEY (`id_producto`) REFERENCES `productos` (`id_producto`);

ALTER TABLE `pedidos_proveedor`
  ADD CONSTRAINT `pedidos_proveedor_ibfk_1` FOREIGN KEY (`id_proveedor`) REFERENCES `proveedores` (`id_proveedor`) ON DELETE CASCADE;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

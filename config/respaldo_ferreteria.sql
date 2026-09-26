CREATE DATABASE IF NOT EXISTS ferreteria_db;
USE ferreteria_db;

CREATE TABLE IF NOT EXISTS productos (
    id_producto INT NOT NULL AUTO_INCREMENT,
    nombre_producto VARCHAR(100) NOT NULL,
    precio_venta DECIMAL(10,2) NOT NULL,
    stock INT NOT NULL DEFAULT 0,
    PRIMARY KEY (id_producto)
);

CREATE TABLE IF NOT EXISTS ventas (
    id_venta INT NOT NULL AUTO_INCREMENT,
    fecha_hora DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    total DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    PRIMARY KEY (id_venta)
);

CREATE TABLE IF NOT EXISTS detalle_ventas (
    id_detalle INT NOT NULL AUTO_INCREMENT,
    id_venta INT NOT NULL,
    id_producto INT NOT NULL,
    cantidad INT NOT NULL,
    precio_unitario DECIMAL(10,2) NOT NULL,
    PRIMARY KEY (id_detalle),
    FOREIGN KEY (id_venta) REFERENCES ventas(id_venta) ON DELETE CASCADE,
    FOREIGN KEY (id_producto) REFERENCES productos(id_producto)
);

CREATE TABLE IF NOT EXISTS usuarios (
    id_usuario INT NOT NULL AUTO_INCREMENT,
    nombre_usuario VARCHAR(50) NOT NULL UNIQUE,
    password TEXT NOT NULL,
    rol VARCHAR(20) NOT NULL DEFAULT 'Cajero',
    PRIMARY KEY (id_usuario)
);

INSERT INTO productos (id_producto, nombre_producto, precio_venta, stock) VALUES
(1, 'Pala redonda', 245.00, 15),
(2, 'Candado de seguridad 50mm', 120.00, 20),
(3, 'Focos LED 12W', 35.00, 50),
(4, 'Socket de porcelana', 18.00, 40),
(5, 'Esmeril angular 4.5 pulgadas', 899.00, 6),
(6, 'Cinta Teflón 1/2 pulgada', 15.00, 100),
(7, 'Cincel plano para concreto', 75.00, 12),
(8, 'Martillo de uña 16 oz', 160.00, 18),
(9, 'Cinta aislar negra', 22.00, 80),
(10, 'Flexómetro 5 metros', 85.00, 25),
(11, 'Cinta Masking tape', 40.00, 35),
(12, 'Rodillo para pintar 9 pulgadas', 65.00, 20),
(13, 'Espátula flexible 3 pulgadas', 45.00, 30),
(14, 'Pistola de calor profesional', 650.00, 5),
(15, 'Pinza de corte diagonal', 130.00, 22),
(16, 'Pilas alcalinas AA (4 piezas)', 85.00, 40),
(17, 'Brocha para pintar 3 pulgadas', 38.00, 50),
(18, 'Bulto de Cemento 50kg', 260.00, 15),
(19, 'Cuchara para albañil 8 pulgadas', 110.00, 14),
(20, 'Juego de llaves Allen', 145.00, 10),
(21, 'Tubo de Silicon transparente', 65.00, 30),
(22, 'Cubetas de plástico 19L', 70.00, 25),
(23, 'Estopa para limpieza 1kg', 55.00, 15),
(24, 'Lijadora orbital orbital', 799.00, 4),
(25, 'Taladro rotomartillo 1/2 pulgada', 950.00, 8),
(26, 'Disco de corte para metal 4.5', 25.00, 90),
(27, 'Pico con mango', 280.00, 10),
(28, 'Matraca de 1/2 pulgada con dados', 420.00, 7)
ON DUPLICATE KEY UPDATE nombre_producto=VALUES(nombre_producto), precio_venta=VALUES(precio_venta), stock=VALUES(stock);

INSERT INTO usuarios (nombre_usuario, password, rol) 
VALUES ('admin', '$2a$12$cY5cMHzwAZXdXaT.DnkzReKIxLh3QINF5.R4Lvp0JoSMh9rkRIKzG', 'Administrador')
ON DUPLICATE KEY UPDATE password=VALUES(password);

-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 28-10-2026 a las 01:45:26
-- Versión del servidor: 10.4.32-MariaDB
-- Versión de PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `gimnasio_db`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `asistencias`
--

CREATE TABLE `asistencias` (
  `id_asistencia` int(11) NOT NULL,
  `id_cliente` int(11) NOT NULL,
  `fecha_hora_ingreso` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `bajas_membresia`
--

CREATE TABLE `bajas_membresia` (
  `id_baja` int(11) NOT NULL,
  `id_suscripcion` int(11) NOT NULL,
  `fecha_baja` datetime DEFAULT current_timestamp(),
  `motivo` varchar(150) NOT NULL,
  `estado` enum('Pendiente','Dada de baja') DEFAULT 'Pendiente'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `bajas_membresia`
--

INSERT INTO `bajas_membresia` (`id_baja`, `id_suscripcion`, `fecha_baja`, `motivo`, `estado`) VALUES
(1, 2, '2026-10-27 17:31:08', 'Cambio de domicilio', 'Pendiente');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `bitacora`
--

CREATE TABLE `bitacora` (
  `id_bitacora` int(11) NOT NULL,
  `id_cliente` int(11) NOT NULL,
  `id_entrenador` int(11) NOT NULL,
  `fecha` date NOT NULL DEFAULT curdate(),
  `grupo_muscular` varchar(50) NOT NULL,
  `detalles_rutina` enum('Build','Balance','Burn','Breathe') NOT NULL,
  `observaciones` text DEFAULT NULL,
  `estado` enum('Publicada','Borrador') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `bitacora`
--

INSERT INTO `bitacora` (`id_bitacora`, `id_cliente`, `id_entrenador`, `fecha`, `grupo_muscular`, `detalles_rutina`, `observaciones`, `estado`) VALUES
(1, 1, 3, '2025-06-14', 'Fuerza base · Semana 1', 'Build', '3', 'Publicada'),
(2, 2, 5, '2026-10-27', 'Movilidad Funcional', 'Balance', '5', 'Publicada');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `bitacora_gym`
--

CREATE TABLE `bitacora_gym` (
  `id_bitacora` int(11) NOT NULL,
  `codigo` varchar(50) NOT NULL,
  `tipo` varchar(50) NOT NULL,
  `descripcion` text NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `fecha_hora` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `bitacora_gym`
--

INSERT INTO `bitacora_gym` (`id_bitacora`, `codigo`, `tipo`, `descripcion`, `id_usuario`, `fecha_hora`) VALUES
(1, 'AUD-0001', ' Aprobación de baja', 'Membresía de Samuel Díaz actualizada a Dada de baja', 1, '2025-06-18 10:42:30'),
(2, 'AUD-0002', 'Publicación de rutina', 'Rutina Fuerza base publicada para Lucía Herrera', 3, '2025-06-18 09:18:00');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `clientes`
--

CREATE TABLE `clientes` (
  `id_cliente` int(11) NOT NULL,
  `nombre` varchar(50) NOT NULL,
  `apellido` varchar(50) NOT NULL,
  `documento` varchar(20) NOT NULL,
  `email` varchar(100) NOT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `fecha_nacimiento` date DEFAULT NULL,
  `fecha_registro` datetime DEFAULT current_timestamp(),
  `estado` enum('Activo','Inactivo','Suspendido') DEFAULT 'Activo'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `clientes`
--

INSERT INTO `clientes` (`id_cliente`, `nombre`, `apellido`, `documento`, `email`, `telefono`, `fecha_nacimiento`, `fecha_registro`, `estado`) VALUES
(1, 'Lucía', 'Herrera', 'ID-102938', 'lucia.h@correo.com', '920555120', NULL, '2025-04-01 01:21:31', 'Activo'),
(2, 'Mariana', 'López', 'ID-104522', 'mariana@correo.com', '958028320', NULL, '2026-10-25 02:44:22', 'Activo'),
(3, 'Carlos', 'Méndez', 'ID-108823', 'carlos@correo.com', '955720445', NULL, '2026-10-26 23:49:27', 'Activo');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `entrenador`
--

CREATE TABLE `entrenador` (
  `id_entrenador` int(11) NOT NULL,
  `nombre` varchar(50) NOT NULL,
  `apellido` varchar(50) NOT NULL,
  `estado` enum('Activo','Inactivo') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `entrenador`
--

INSERT INTO `entrenador` (`id_entrenador`, `nombre`, `apellido`, `estado`) VALUES
(1, 'Diego', 'Ramos', 'Activo'),
(2, 'Mateo', 'Castro', 'Activo'),
(3, 'Luis', 'Flores', 'Activo');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `membresias`
--

CREATE TABLE `membresias` (
  `id_membresia` int(11) NOT NULL,
  `nombre_plan` varchar(50) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `duracion_dias` int(11) NOT NULL,
  `precio` decimal(10,2) NOT NULL,
  `estado` enum('Activo','Inactivo') NOT NULL DEFAULT 'Activo'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `membresias`
--

INSERT INTO `membresias` (`id_membresia`, `nombre_plan`, `descripcion`, `duracion_dias`, `precio`, `estado`) VALUES
(1, 'Plan Mensual', 'Varían según la cadena, los servicios incluidos y si requieren o no un compromiso de permanencia (fidelidad).', 30, 180.00, 'Activo'),
(2, 'Plan Trimestral', 'Dura 12 semanas y se divide en tres fases progresivas: adaptación (mes 1), hipertrofia/fuerza (mes 2) e intensificación (mes 3).', 90, 300.00, 'Activo'),
(3, 'Plan Semestral', 'Una división de cuerpo superior e inferior (Upper/Lower) permite optimizar el volumen muscular y el descanso.', 180, 600.00, 'Activo'),
(4, 'Plan Anual', 'Combina una periodización de entrenamiento estructurada en fases (volumen, fuerza y definición) con la organización de membresías o costos anuales.', 360, 1200.00, 'Activo'),
(5, 'Plan Promocional', NULL, 0, 0.00, 'Inactivo');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `pagos`
--

CREATE TABLE `pagos` (
  `id_pago` int(11) NOT NULL,
  `id_suscripcion` int(11) NOT NULL,
  `monto` decimal(10,2) NOT NULL,
  `fecha_pago` datetime DEFAULT current_timestamp(),
  `metodo_pago` enum('Efectivo','Tarjeta','Transferencia') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `suscripciones_cliente`
--

CREATE TABLE `suscripciones_cliente` (
  `id_suscripcion` int(11) NOT NULL,
  `id_cliente` int(11) NOT NULL,
  `id_membresia` int(11) NOT NULL,
  `fecha_inicio` date NOT NULL,
  `fecha_fin` date NOT NULL,
  `estado_suscripcion` enum('Vigente','Vencida','Dada de Baja') DEFAULT 'Vigente'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `suscripciones_cliente`
--

INSERT INTO `suscripciones_cliente` (`id_suscripcion`, `id_cliente`, `id_membresia`, `fecha_inicio`, `fecha_fin`, `estado_suscripcion`) VALUES
(1, 1, 2, '2025-04-03', '2025-07-03', 'Vencida'),
(2, 2, 2, '2025-03-21', '2025-06-21', 'Vencida'),
(3, 3, 1, '2025-05-02', '2025-06-02', 'Vencida');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios`
--

CREATE TABLE `usuarios` (
  `id_usuario` int(11) NOT NULL,
  `nombre` varchar(50) NOT NULL,
  `apellido` varchar(50) NOT NULL,
  `rol` enum('Entrenador','Recepcionista','Administrador','Cliente') NOT NULL DEFAULT 'Cliente',
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `estado` enum('Activo','Inactivo') NOT NULL DEFAULT 'Activo',
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `usuarios`
--

INSERT INTO `usuarios` (`id_usuario`, `nombre`, `apellido`, `rol`, `email`, `password`, `estado`, `fecha_creacion`) VALUES
(1, 'Mateo', 'Salvattore', 'Administrador', 'mateosalvattore@gmail.com', 'Admin123*', 'Activo', '2026-10-21 02:39:00'),
(2, 'Sofia', 'Martínez', 'Recepcionista', 'sofiamartinez@gmail.com', 'Recepcion123*', 'Activo', '2026-10-21 03:58:20'),
(3, 'Diego', 'Ramos', 'Entrenador', 'diegoramos@gmail.com', 'Entrenador123*', 'Activo', '2026-10-21 04:44:42'),
(4, 'Lucia', 'Herrera', 'Cliente', 'luciaherrera@gmail.com', 'Cliente123*', 'Activo', '2026-10-21 04:45:54'),
(5, 'Mateo', 'Castro', 'Entrenador', 'mateocastro@gmail.com', 'G9PMcqLXvC', 'Activo', '2026-10-27 22:05:38');

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `asistencias`
--
ALTER TABLE `asistencias`
  ADD PRIMARY KEY (`id_asistencia`),
  ADD KEY `id_cliente` (`id_cliente`);

--
-- Indices de la tabla `bajas_membresia`
--
ALTER TABLE `bajas_membresia`
  ADD PRIMARY KEY (`id_baja`),
  ADD KEY `id_suscripcion` (`id_suscripcion`);

--
-- Indices de la tabla `bitacora`
--
ALTER TABLE `bitacora`
  ADD PRIMARY KEY (`id_bitacora`),
  ADD KEY `id_cliente` (`id_cliente`),
  ADD KEY `id_entrenador` (`id_entrenador`);

--
-- Indices de la tabla `bitacora_gym`
--
ALTER TABLE `bitacora_gym`
  ADD PRIMARY KEY (`id_bitacora`),
  ADD KEY `id_usuario` (`id_usuario`);

--
-- Indices de la tabla `clientes`
--
ALTER TABLE `clientes`
  ADD PRIMARY KEY (`id_cliente`),
  ADD UNIQUE KEY `documento` (`documento`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indices de la tabla `entrenador`
--
ALTER TABLE `entrenador`
  ADD PRIMARY KEY (`id_entrenador`);

--
-- Indices de la tabla `membresias`
--
ALTER TABLE `membresias`
  ADD PRIMARY KEY (`id_membresia`);

--
-- Indices de la tabla `pagos`
--
ALTER TABLE `pagos`
  ADD PRIMARY KEY (`id_pago`),
  ADD KEY `id_suscripcion` (`id_suscripcion`);

--
-- Indices de la tabla `suscripciones_cliente`
--
ALTER TABLE `suscripciones_cliente`
  ADD PRIMARY KEY (`id_suscripcion`),
  ADD KEY `id_cliente` (`id_cliente`),
  ADD KEY `id_membresia` (`id_membresia`);

--
-- Indices de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id_usuario`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `asistencias`
--
ALTER TABLE `asistencias`
  MODIFY `id_asistencia` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `bajas_membresia`
--
ALTER TABLE `bajas_membresia`
  MODIFY `id_baja` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `bitacora`
--
ALTER TABLE `bitacora`
  MODIFY `id_bitacora` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `bitacora_gym`
--
ALTER TABLE `bitacora_gym`
  MODIFY `id_bitacora` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `clientes`
--
ALTER TABLE `clientes`
  MODIFY `id_cliente` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `entrenador`
--
ALTER TABLE `entrenador`
  MODIFY `id_entrenador` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `membresias`
--
ALTER TABLE `membresias`
  MODIFY `id_membresia` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de la tabla `pagos`
--
ALTER TABLE `pagos`
  MODIFY `id_pago` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `suscripciones_cliente`
--
ALTER TABLE `suscripciones_cliente`
  MODIFY `id_suscripcion` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id_usuario` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `asistencias`
--
ALTER TABLE `asistencias`
  ADD CONSTRAINT `asistencias_ibfk_1` FOREIGN KEY (`id_cliente`) REFERENCES `clientes` (`id_cliente`) ON DELETE CASCADE;

--
-- Filtros para la tabla `bajas_membresia`
--
ALTER TABLE `bajas_membresia`
  ADD CONSTRAINT `bajas_membresia_ibfk_1` FOREIGN KEY (`id_suscripcion`) REFERENCES `suscripciones_cliente` (`id_suscripcion`);

--
-- Filtros para la tabla `bitacora`
--
ALTER TABLE `bitacora`
  ADD CONSTRAINT `bitacora_ibfk_1` FOREIGN KEY (`id_cliente`) REFERENCES `clientes` (`id_cliente`) ON DELETE CASCADE,
  ADD CONSTRAINT `bitacora_ibfk_2` FOREIGN KEY (`id_entrenador`) REFERENCES `usuarios` (`id_usuario`);

--
-- Filtros para la tabla `bitacora_gym`
--
ALTER TABLE `bitacora_gym`
  ADD CONSTRAINT `bitacora_gym_ibfk_1` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`);

--
-- Filtros para la tabla `pagos`
--
ALTER TABLE `pagos`
  ADD CONSTRAINT `pagos_ibfk_1` FOREIGN KEY (`id_suscripcion`) REFERENCES `suscripciones_cliente` (`id_suscripcion`);

--
-- Filtros para la tabla `suscripciones_cliente`
--
ALTER TABLE `suscripciones_cliente`
  ADD CONSTRAINT `suscripciones_cliente_ibfk_1` FOREIGN KEY (`id_cliente`) REFERENCES `clientes` (`id_cliente`) ON DELETE CASCADE,
  ADD CONSTRAINT `suscripciones_cliente_ibfk_2` FOREIGN KEY (`id_membresia`) REFERENCES `membresias` (`id_membresia`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

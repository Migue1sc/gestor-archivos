-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1:3306
-- Tiempo de generación: 30-04-2025 a las 02:52:09
-- Versión del servidor: 9.1.0
-- Versión de PHP: 8.3.14

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `appinap_db`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `areas`
--

DROP TABLE IF EXISTS `areas`;
CREATE TABLE IF NOT EXISTS `areas` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nombre` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `area_padre_id` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `area_padre_id` (`area_padre_id`)
) ENGINE=MyISAM AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `areas`
--

INSERT INTO `areas` (`id`, `nombre`, `area_padre_id`) VALUES
(1, 'Jurídico', NULL),
(2, 'Contratos', 1),
(3, 'Contabilidad', NULL),
(4, 'Informática ', NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `documentos`
--

DROP TABLE IF EXISTS `documentos`;
CREATE TABLE IF NOT EXISTS `documentos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ruta_archivo` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tipo` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `version` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `fecha_subida` datetime DEFAULT CURRENT_TIMESTAMP,
  `fecha_vencimiento` date DEFAULT NULL,
  `area_id` int NOT NULL,
  `usuario_id` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `area_id` (`area_id`),
  KEY `usuario_id` (`usuario_id`)
) ENGINE=MyISAM AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `documentos`
--

INSERT INTO `documentos` (`id`, `nombre`, `ruta_archivo`, `tipo`, `version`, `fecha_subida`, `fecha_vencimiento`, `area_id`, `usuario_id`) VALUES
(1, 'Prueba 1', 'Uploads/67b75e3c0196d_MACHOTEFormatosReportesServicioSocial.xlsx', 'Tipo de documento 1', 'copia', '2025-04-13 02:24:55', '2025-05-13', 3, 2),
(2, 'Acta Constitutiva', 'Uploads/Acta Constitutiva.pdf', 'Contiene acta constitutiva del inap ', 'certificada', '2025-04-22 20:33:39', '2025-05-31', 2, 4);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `documentos_requeridos`
--

DROP TABLE IF EXISTS `documentos_requeridos`;
CREATE TABLE IF NOT EXISTS `documentos_requeridos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `tramite_id` int NOT NULL,
  `nombre_documento` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `obligatorio` tinyint(1) DEFAULT '1',
  PRIMARY KEY (`id`),
  KEY `tramite_id` (`tramite_id`)
) ENGINE=MyISAM AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `documentos_requeridos`
--

INSERT INTO `documentos_requeridos` (`id`, `tramite_id`, `nombre_documento`, `obligatorio`) VALUES
(1, 1, 'Acta constitutiva', 1),
(2, 1, 'Poder notarial', 1),
(3, 1, 'Comprobante adicional', 0),
(4, 2, 'Propuesta técnica', 1),
(5, 2, 'Cotización', 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `documentos_tramites`
--

DROP TABLE IF EXISTS `documentos_tramites`;
CREATE TABLE IF NOT EXISTS `documentos_tramites` (
  `id` int NOT NULL AUTO_INCREMENT,
  `tramite_id` int NOT NULL,
  `documento_id` int NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tramite_id` (`tramite_id`,`documento_id`),
  KEY `documento_id` (`documento_id`)
) ENGINE=MyISAM AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `documentos_tramites`
--

INSERT INTO `documentos_tramites` (`id`, `tramite_id`, `documento_id`) VALUES
(1, 1, 2);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tramites`
--

DROP TABLE IF EXISTS `tramites`;
CREATE TABLE IF NOT EXISTS `tramites` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nombre` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `tramites`
--

INSERT INTO `tramites` (`id`, `nombre`) VALUES
(1, 'Contrato'),
(2, 'Licitación');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios`
--

DROP TABLE IF EXISTS `usuarios`;
CREATE TABLE IF NOT EXISTS `usuarios` (
  `id` int NOT NULL AUTO_INCREMENT,
  `usuario` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `contrasena` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nombre` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `area_id` int NOT NULL,
  `es_admin` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `usuario` (`usuario`),
  UNIQUE KEY `email` (`email`),
  KEY `area_id` (`area_id`)
) ENGINE=MyISAM AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `usuarios`
--

INSERT INTO `usuarios` (`id`, `usuario`, `email`, `contrasena`, `nombre`, `area_id`, `es_admin`) VALUES
(1, 'admin01', '', '$2y$10$GWkMmyLPXxinYHGN3UzcfOjewGL7vOg2M4RwriQ5szuoEEzsb.EgO', 'Admin General', 0, 1),
(2, 'vhinap25', 'vale_h@gmail.com', '$2y$10$rjC05h9qkrn54X1vZMw8heGI2jqIN0EG/Jpj4rcZ/fEcork3/w1uC', 'Valentina Hernández', 3, 0),
(3, 'ericdc25', 'eric_edc@outlook.com', '$2y$10$yztyc8VTyUQxkEStBTMffOgKpqTv93qgpoO20CB17OFnSj/nEIh5S', 'Eric de la Cruz', 1, 0),
(4, 'isas29', 'isabel_s@outlook.com', '$2y$10$8XxwiR8AXoNI5KQa3nzerOVigj7mogK7n6zgP3QRuEXxiwtAsi7F6', 'Isabel Segura', 2, 0);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

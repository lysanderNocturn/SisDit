-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 31-03-2026 a las 03:50:05
-- Versión del servidor: 10.4.32-MariaDB
-- Versión de PHP: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `sistema`
--

DELIMITER $$
--
-- Procedimientos
--
CREATE DEFINER=`root`@`localhost` PROCEDURE `asignar_folio_salida` (IN `p_tramite_id` INT, IN `p_anio` INT)   BEGIN
  DECLARE v_siguiente INT DEFAULT 1;

  -- Calcular el siguiente número disponible para ese año
  SELECT COALESCE(MAX(folio_salida_numero), 0) + 1
    INTO v_siguiente
    FROM tramites
   WHERE folio_salida_anio = p_anio
     AND folio_salida_numero IS NOT NULL;

  -- Asignar solo si aún no tiene folio de salida
  UPDATE tramites
     SET folio_salida_numero = v_siguiente,
         folio_salida_anio   = p_anio
   WHERE id = p_tramite_id
     AND folio_salida_numero IS NULL;

  SELECT v_siguiente AS folio_salida_asignado;
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `catastro`
--

CREATE TABLE `catastro` (
  `id` int(11) NOT NULL,
  `cuenta_catastral` varchar(50) NOT NULL,
  `propietario` varchar(150) DEFAULT NULL,
  `direccion` varchar(200) DEFAULT NULL,
  `localidad` varchar(100) DEFAULT NULL,
  `utm_x` decimal(12,2) DEFAULT NULL,
  `utm_y` decimal(12,2) DEFAULT NULL,
  `superficie` varchar(50) DEFAULT NULL,
  `activo` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `comentarios_tramites`
--

CREATE TABLE `comentarios_tramites` (
  `id` int(11) NOT NULL,
  `tramite_id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `comentario` text NOT NULL,
  `es_interno` tinyint(1) DEFAULT 0,
  `leido` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `configuracion_sistema`
--

CREATE TABLE `configuracion_sistema` (
  `id` int(11) NOT NULL,
  `clave` varchar(100) NOT NULL,
  `valor` text DEFAULT NULL,
  `descripcion` varchar(255) DEFAULT NULL,
  `tipo` enum('texto','numero','boolean','json') DEFAULT 'texto',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `configuracion_sistema`
--

INSERT INTO `configuracion_sistema` (`id`, `clave`, `valor`, `descripcion`, `tipo`, `updated_at`) VALUES
(1, 'max_file_size', '5242880', 'Tamaño máximo archivo en bytes (5MB)', 'numero', '2026-03-10 20:04:43'),
(2, 'dias_alerta_vencimiento', '3', 'Días antes de alertar vencimiento', 'numero', '2026-03-10 20:04:43'),
(3, 'municipio_nombre', 'Rincón de Romos', 'Nombre del municipio', 'texto', '2026-03-10 20:04:43'),
(4, 'director_nombre', 'LIC. URB. JESÚS BERNARDO DÍAZ DE LEÓN GUTIÉRREZ', 'Nombre del director', 'texto', '2026-03-25 01:39:56'),
(5, 'director_cargo', 'DIRECTOR DE PLANEACIÓN Y DESARROLLO URBANO', 'Cargo del director', 'texto', '2026-03-10 20:04:43'),
(6, 'whatsapp_numero', '4498077899', 'WhatsApp de contacto', 'texto', '2026-03-10 20:04:43'),
(7, 'email_contacto', 'dir.planeacionydu@gmail.com', 'Correo de contacto', 'texto', '2026-03-10 20:04:43'),
(8, 'constancia_reglamento_1', 'En inmuebles construidos deberán colocarse en el exterior, al frente de la construcción junto al acceso principal;', 'Reglamento I de la constancia de número oficial', 'texto', '2026-03-25 01:39:56'),
(9, 'constancia_reglamento_2', 'Los números oficiales en ningún caso deberán ser pintados sobre muros, bloques, columnas y/o en elementos de fácil destrucción;', 'Reglamento II de la constancia de número oficial', 'texto', '2026-03-25 01:39:56'),
(10, 'constancia_reglamento_3', 'Deberán además ser de tipo de fuente legible y permitir una fácil lectura a un mínimo de veinte metros;', 'Reglamento III de la constancia de número oficial', 'texto', '2026-03-25 01:39:56'),
(11, 'constancia_reglamento_4', 'Las placas de numeración deberán colocarse en una altura mínima de dos metros con cincuenta centímetros a partir del nivel de la banqueta.', 'Reglamento IV de la constancia de número oficial', 'texto', '2026-03-25 01:39:56');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `historial_tramites`
--

CREATE TABLE `historial_tramites` (
  `id` int(11) NOT NULL,
  `tramite_id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `accion` enum('Creado','Modificado','Enviado a revisión','Aprobado por Verificador','Aprobado','Rechazado','En corrección') NOT NULL,
  `estatus_anterior` varchar(50) DEFAULT NULL,
  `estatus_nuevo` varchar(50) DEFAULT NULL,
  `comentario` text DEFAULT NULL,
  `fecha` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `logs_actividad`
--

CREATE TABLE `logs_actividad` (
  `id` int(11) NOT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `accion` varchar(100) NOT NULL,
  `tabla_afectada` varchar(50) DEFAULT NULL,
  `registro_id` int(11) DEFAULT NULL,
  `detalles` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `fecha` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `solicitudes_registro`
--

CREATE TABLE `solicitudes_registro` (
  `id` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `apellidos` varchar(100) NOT NULL,
  `correo` varchar(150) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `rol` enum('Usuario','Ventanilla','Verificador') NOT NULL,
  `estado` enum('Pendiente','Aprobado','Rechazado') NOT NULL DEFAULT 'Pendiente',
  `motivo_rechazo` text DEFAULT NULL,
  `fecha_solicitud` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_resolucion` timestamp NULL DEFAULT NULL,
  `resuelto_por` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tipos_tramite`
--

CREATE TABLE `tipos_tramite` (
  `id` int(11) NOT NULL,
  `codigo` varchar(20) NOT NULL,
  `nombre` varchar(150) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `plantilla_pdf` varchar(100) DEFAULT NULL,
  `activo` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `tipos_tramite`
--

INSERT INTO `tipos_tramite` (`id`, `codigo`, `nombre`, `descripcion`, `plantilla_pdf`, `activo`, `created_at`) VALUES
(1, 'NUM_OFICIAL', 'Constancia de Número Oficial', 'Asignación de número oficial a inmuebles. 10 días hábiles.', 'numero_oficial.php', 1, '2026-03-10 20:04:43'),
(2, 'CMCU', 'Constancia de Compatibilidad Urbanística', 'Compatibilidad de uso de suelo. 10 días hábiles.', 'cmcu.php', 1, '2026-03-10 20:04:43'),
(3, 'FUSION', 'Fusión de Predios', 'Fusión de dos o más predios. 10 días hábiles.', 'fusion.php', 1, '2026-03-10 20:04:43'),
(4, 'SUBDIVISION', 'Subdivisión de Predio', 'Subdivisión de un predio. 10 días hábiles.', 'subdivision.php', 1, '2026-03-10 20:04:43'),
(5, 'INFORME_CU', 'Informe de Compatibilidad Urbanística', 'Informe de compatibilidad. 10 días hábiles.', NULL, 1, '2026-03-10 20:04:43'),
(6, 'USO_SUELO', 'Uso de Suelo', 'Constancia de uso de suelo. 10 días hábiles.', NULL, 1, '2026-03-10 20:04:43'),
(7, 'LIC_CONST', 'Licencia de Construcción', 'Licencia para construcción/remodelación. 10 días hábiles.', NULL, 1, '2026-03-10 20:04:43'),
(8, 'OTRO', 'Otro Trámite', 'Trámite no clasificado. 10 días hábiles.', NULL, 1, '2026-03-10 20:04:43');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tramites`
--

CREATE TABLE `tramites` (
  `id` int(11) NOT NULL,
  `folio_numero` int(11) NOT NULL,
  `folio_anio` int(11) NOT NULL,
  `tipo_tramite_id` int(11) NOT NULL,
  `propietario` varchar(150) NOT NULL,
  `direccion` varchar(200) NOT NULL,
  `numero_asignado` varchar(50) DEFAULT NULL,
  `tipo_asignacion` varchar(50) DEFAULT NULL,
  `referencia_anterior` varchar(255) DEFAULT NULL,
  `entre_calles` varchar(255) DEFAULT NULL,
  `localidad` varchar(100) NOT NULL,
  `colonia` varchar(100) DEFAULT NULL,
  `manzana` varchar(50) DEFAULT NULL,
  `lote` varchar(50) DEFAULT NULL,
  `cp` varchar(10) DEFAULT NULL,
  `lat` decimal(12,2) DEFAULT NULL COMMENT 'UTM X (Este)',
  `lng` decimal(12,2) DEFAULT NULL COMMENT 'UTM Y (Norte)',
  `fecha_ingreso` date NOT NULL,
  `fecha_entrega` date NOT NULL,
  `fecha_constancia` date DEFAULT NULL,
  `solicitante` varchar(150) NOT NULL,
  `telefono` varchar(30) NOT NULL,
  `correo` varchar(150) DEFAULT NULL,
  `cuenta_catastral` varchar(50) DEFAULT NULL COMMENT 'Solo números — asignada por trigger si viene vacía',
  `superficie` varchar(50) DEFAULT NULL,
  `ine_archivo` varchar(255) DEFAULT NULL,
  `titulo_archivo` varchar(255) DEFAULT NULL,
  `predial_archivo` varchar(255) DEFAULT NULL,
  `escrituras_archivo` varchar(255) DEFAULT NULL,
  `foto_predio_archivo` varchar(255) DEFAULT NULL,
  `formato_constancia` varchar(255) DEFAULT NULL,
  `carta_poder` varchar(255) DEFAULT NULL,
  `foto1_archivo` varchar(255) DEFAULT NULL,
  `foto2_archivo` varchar(255) DEFAULT NULL,
  `croquis_archivo` varchar(255) DEFAULT NULL COMMENT 'Imagen del croquis del predio para la constancia',
  `otros_archivos` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`otros_archivos`)),
  `datos_especificos` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`datos_especificos`)),
  `comentario_sin_doc` text DEFAULT NULL,
  `estatus` enum('En revisión','Aprobado por Verificador','Aprobado','Rechazado','En corrección') NOT NULL DEFAULT 'En revisión',
  `observaciones` text DEFAULT NULL,
  `verificador_nombre` varchar(150) DEFAULT NULL,
  `aprobado_por` int(11) DEFAULT NULL,
  `fecha_aprobacion` timestamp NULL DEFAULT NULL,
  `aprobado_director` tinyint(1) DEFAULT 0,
  `fecha_aprobacion_director` timestamp NULL DEFAULT NULL,
  `usuario_creador_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `folio_salida_numero` int(11) DEFAULT NULL COMMENT 'Número secuencial de salida (independiente del folio de ingreso)',
  `folio_salida_anio` int(11) DEFAULT NULL COMMENT 'Año del folio de salida'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Disparadores `tramites`
--
DELIMITER $$
CREATE TRIGGER `trg_cuenta_catastral_auto` BEFORE INSERT ON `tramites` FOR EACH ROW BEGIN
  DECLARE siguiente INT DEFAULT 1;
  -- Si no se proporcionó cuenta catastral, asignar automáticamente
  IF (NEW.cuenta_catastral IS NULL OR TRIM(NEW.cuenta_catastral) = '') THEN
    SELECT COALESCE(MAX(CAST(cuenta_catastral AS UNSIGNED)), 0) + 1
      INTO siguiente
      FROM tramites
     WHERE cuenta_catastral REGEXP '^[0-9]+$';
    -- Formato: AAAANNNNNN  (año 4 dígitos + secuencial 6 dígitos) = 10 dígitos puros
    SET NEW.cuenta_catastral = CONCAT(YEAR(NOW()), LPAD(siguiente, 6, '0'));
  END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_historial_insert` AFTER INSERT ON `tramites` FOR EACH ROW BEGIN
  INSERT INTO historial_tramites
    (tramite_id, usuario_id, accion, estatus_nuevo, comentario)
  VALUES
    (NEW.id, NEW.usuario_creador_id, 'Creado', NEW.estatus, 'Trámite creado en el sistema');
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios`
--

CREATE TABLE `usuarios` (
  `id` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `apellidos` varchar(100) NOT NULL,
  `correo` varchar(120) NOT NULL,
  `password` varchar(255) NOT NULL,
  `rol` enum('Usuario','Ventanilla','Verificador','Administrador') DEFAULT 'Usuario',
  `activo` tinyint(1) DEFAULT 1,
  `fecha_registro` timestamp NOT NULL DEFAULT current_timestamp(),
  `ultimo_acceso` timestamp NULL DEFAULT NULL,
  `token_recuperacion` varchar(100) DEFAULT NULL,
  `token_expira` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `usuarios`
--

INSERT INTO `usuarios` (`id`, `nombre`, `apellidos`, `correo`, `password`, `rol`, `activo`, `fecha_registro`, `ultimo_acceso`, `token_recuperacion`, `token_expira`) VALUES
(1, 'Admin', 'Sistema', 'admin@sistema.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrador', 1, '2026-03-10 20:04:43', '2026-03-25 19:24:41', NULL, NULL),
(2, 'Juan Carlos', 'Verificador Gómez', 'verificador@sistema.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Verificador', 1, '2026-03-10 20:04:43', '2026-03-31 01:37:59', NULL, NULL),
(3, 'María Elena', 'Secretaria López', 'secretaria@sistema.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Ventanilla', 1, '2026-03-10 20:04:43', '2026-03-31 01:39:55', NULL, NULL),
(4, 'Pedro Antonio', 'Usuario Martínez', 'usuario@sistema.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Usuario', 1, '2026-03-10 20:04:43', '2026-03-25 06:45:10', NULL, NULL);

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `vista_tramites`
-- (Véase abajo para la vista actual)
--
CREATE TABLE `vista_tramites` (
`id` int(11)
,`folio_numero` int(11)
,`folio_anio` int(11)
,`tipo_tramite_id` int(11)
,`propietario` varchar(150)
,`direccion` varchar(200)
,`localidad` varchar(100)
,`colonia` varchar(100)
,`cp` varchar(10)
,`lat` decimal(12,2)
,`lng` decimal(12,2)
,`fecha_ingreso` date
,`fecha_entrega` date
,`solicitante` varchar(150)
,`telefono` varchar(30)
,`correo` varchar(150)
,`cuenta_catastral` varchar(50)
,`superficie` varchar(50)
,`ine_archivo` varchar(255)
,`titulo_archivo` varchar(255)
,`predial_archivo` varchar(255)
,`escrituras_archivo` varchar(255)
,`foto_predio_archivo` varchar(255)
,`formato_constancia` varchar(255)
,`carta_poder` varchar(255)
,`foto1_archivo` varchar(255)
,`foto2_archivo` varchar(255)
,`otros_archivos` longtext
,`datos_especificos` longtext
,`comentario_sin_doc` text
,`estatus` enum('En revisión','Aprobado por Verificador','Aprobado','Rechazado','En corrección')
,`observaciones` text
,`verificador_nombre` varchar(150)
,`aprobado_por` int(11)
,`fecha_aprobacion` timestamp
,`aprobado_director` tinyint(1)
,`fecha_aprobacion_director` timestamp
,`usuario_creador_id` int(11)
,`created_at` timestamp
,`updated_at` timestamp
,`tipo_tramite_nombre` varchar(150)
,`tipo_tramite_codigo` varchar(20)
,`creador_nombre_completo` varchar(201)
);

-- --------------------------------------------------------

--
-- Estructura para la vista `vista_tramites`
--
DROP TABLE IF EXISTS `vista_tramites`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vista_tramites`  AS SELECT `t`.`id` AS `id`, `t`.`folio_numero` AS `folio_numero`, `t`.`folio_anio` AS `folio_anio`, `t`.`tipo_tramite_id` AS `tipo_tramite_id`, `t`.`propietario` AS `propietario`, `t`.`direccion` AS `direccion`, `t`.`localidad` AS `localidad`, `t`.`colonia` AS `colonia`, `t`.`cp` AS `cp`, `t`.`lat` AS `lat`, `t`.`lng` AS `lng`, `t`.`fecha_ingreso` AS `fecha_ingreso`, `t`.`fecha_entrega` AS `fecha_entrega`, `t`.`solicitante` AS `solicitante`, `t`.`telefono` AS `telefono`, `t`.`correo` AS `correo`, `t`.`cuenta_catastral` AS `cuenta_catastral`, `t`.`superficie` AS `superficie`, `t`.`ine_archivo` AS `ine_archivo`, `t`.`titulo_archivo` AS `titulo_archivo`, `t`.`predial_archivo` AS `predial_archivo`, `t`.`escrituras_archivo` AS `escrituras_archivo`, `t`.`foto_predio_archivo` AS `foto_predio_archivo`, `t`.`formato_constancia` AS `formato_constancia`, `t`.`carta_poder` AS `carta_poder`, `t`.`foto1_archivo` AS `foto1_archivo`, `t`.`foto2_archivo` AS `foto2_archivo`, `t`.`otros_archivos` AS `otros_archivos`, `t`.`datos_especificos` AS `datos_especificos`, `t`.`comentario_sin_doc` AS `comentario_sin_doc`, `t`.`estatus` AS `estatus`, `t`.`observaciones` AS `observaciones`, `t`.`verificador_nombre` AS `verificador_nombre`, `t`.`aprobado_por` AS `aprobado_por`, `t`.`fecha_aprobacion` AS `fecha_aprobacion`, `t`.`aprobado_director` AS `aprobado_director`, `t`.`fecha_aprobacion_director` AS `fecha_aprobacion_director`, `t`.`usuario_creador_id` AS `usuario_creador_id`, `t`.`created_at` AS `created_at`, `t`.`updated_at` AS `updated_at`, `tt`.`nombre` AS `tipo_tramite_nombre`, `tt`.`codigo` AS `tipo_tramite_codigo`, concat(`u`.`nombre`,' ',`u`.`apellidos`) AS `creador_nombre_completo` FROM ((`tramites` `t` left join `tipos_tramite` `tt` on(`t`.`tipo_tramite_id` = `tt`.`id`)) left join `usuarios` `u` on(`t`.`usuario_creador_id` = `u`.`id`)) ;

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `catastro`
--
ALTER TABLE `catastro`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_catastral` (`cuenta_catastral`);

--
-- Indices de la tabla `comentarios_tramites`
--
ALTER TABLE `comentarios_tramites`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_tramite` (`tramite_id`),
  ADD KEY `idx_usuario` (`usuario_id`),
  ADD KEY `idx_leido` (`leido`);

--
-- Indices de la tabla `configuracion_sistema`
--
ALTER TABLE `configuracion_sistema`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_clave` (`clave`);

--
-- Indices de la tabla `historial_tramites`
--
ALTER TABLE `historial_tramites`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_tramite` (`tramite_id`),
  ADD KEY `idx_usuario` (`usuario_id`),
  ADD KEY `idx_fecha` (`fecha`);

--
-- Indices de la tabla `logs_actividad`
--
ALTER TABLE `logs_actividad`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_usuario` (`usuario_id`),
  ADD KEY `idx_fecha` (`fecha`),
  ADD KEY `idx_accion` (`accion`);

--
-- Indices de la tabla `solicitudes_registro`
--
ALTER TABLE `solicitudes_registro`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_correo` (`correo`),
  ADD KEY `idx_estado` (`estado`);

--
-- Indices de la tabla `tipos_tramite`
--
ALTER TABLE `tipos_tramite`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_codigo` (`codigo`),
  ADD KEY `idx_activo` (`activo`);

--
-- Indices de la tabla `tramites`
--
ALTER TABLE `tramites`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_folio` (`folio_numero`,`folio_anio`),
  ADD KEY `idx_estatus` (`estatus`),
  ADD KEY `idx_fecha_ingreso` (`fecha_ingreso`),
  ADD KEY `idx_propietario` (`propietario`),
  ADD KEY `idx_tipo_tramite` (`tipo_tramite_id`),
  ADD KEY `idx_cuenta_catastral` (`cuenta_catastral`),
  ADD KEY `fk_usuario_creador` (`usuario_creador_id`),
  ADD KEY `fk_aprobado_por` (`aprobado_por`),
  ADD KEY `idx_folio_salida` (`folio_salida_numero`,`folio_salida_anio`);

--
-- Indices de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_correo` (`correo`),
  ADD KEY `idx_rol` (`rol`),
  ADD KEY `idx_activo` (`activo`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `catastro`
--
ALTER TABLE `catastro`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `comentarios_tramites`
--
ALTER TABLE `comentarios_tramites`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `configuracion_sistema`
--
ALTER TABLE `configuracion_sistema`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT de la tabla `historial_tramites`
--
ALTER TABLE `historial_tramites`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;

--
-- AUTO_INCREMENT de la tabla `logs_actividad`
--
ALTER TABLE `logs_actividad`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=305;

--
-- AUTO_INCREMENT de la tabla `solicitudes_registro`
--
ALTER TABLE `solicitudes_registro`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT de la tabla `tipos_tramite`
--
ALTER TABLE `tipos_tramite`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT de la tabla `tramites`
--
ALTER TABLE `tramites`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `comentarios_tramites`
--
ALTER TABLE `comentarios_tramites`
  ADD CONSTRAINT `fk_co_tramite` FOREIGN KEY (`tramite_id`) REFERENCES `tramites` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_co_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`);

--
-- Filtros para la tabla `historial_tramites`
--
ALTER TABLE `historial_tramites`
  ADD CONSTRAINT `fk_hi_tramite` FOREIGN KEY (`tramite_id`) REFERENCES `tramites` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_hi_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`);

--
-- Filtros para la tabla `logs_actividad`
--
ALTER TABLE `logs_actividad`
  ADD CONSTRAINT `fk_lo_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL;

--
-- Filtros para la tabla `tramites`
--
ALTER TABLE `tramites`
  ADD CONSTRAINT `fk_tr_aprobador` FOREIGN KEY (`aprobado_por`) REFERENCES `usuarios` (`id`),
  ADD CONSTRAINT `fk_tr_creador` FOREIGN KEY (`usuario_creador_id`) REFERENCES `usuarios` (`id`),
  ADD CONSTRAINT `fk_tr_tipo` FOREIGN KEY (`tipo_tramite_id`) REFERENCES `tipos_tramite` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

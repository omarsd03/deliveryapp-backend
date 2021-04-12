-- phpMyAdmin SQL Dump
-- version 5.0.4
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 12-04-2021 a las 03:33:15
-- Versión del servidor: 10.4.17-MariaDB
-- Versión de PHP: 8.0.0

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `delivery_app`
--

DELIMITER $$
--
-- Funciones
--
CREATE DEFINER=`root`@`localhost` FUNCTION `GeneraFolio` (`FOLIO` INT, `LEN` INT) RETURNS VARCHAR(100) CHARSET latin1 BEGIN

	DECLARE SRC_LEN INT;
    DECLARE DIFF_LEN INT;
    DECLARE CONT INT;
    DECLARE ZERO NVARCHAR(100);
    
    SET ZERO='';
    SET SRC_LEN = LENGTH( CONVERT(FOLIO, NCHAR) );
    IF LEN <= SRC_LEN THEN
        -- SI LA LONGITUD SOLICITADA ES MENOR O IGUAL QUE LA REAL
        SET ZERO = '';
    ELSE
        -- SI LA LONGITUD SOLICITADA ES MAYOR QUE LA REAL
        SET CONT= 0;
        SET DIFF_LEN = LEN - SRC_LEN;
        WHILE(CONT < DIFF_LEN) DO
            -- SET ZERO = ZERO + '0';
            SET ZERO = CONCAT(ZERO, '0');
            SET CONT = CONT + 1;
        END WHILE;
    END IF;
    -- RETURN ZERO + CONVERT(FOLIO, NCHAR);
    RETURN CONCAT( ZERO, convert(FOLIO, NCHAR) );
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `d_delivery_general`
--

CREATE TABLE `d_delivery_general` (
  `id_g_delivery` int(11) NOT NULL,
  `g_folio` varchar(50) NOT NULL DEFAULT 'N/A',
  `g_domicilio` varchar(255) NOT NULL,
  `g_colonia` varchar(255) DEFAULT NULL,
  `g_municipio` varchar(255) DEFAULT NULL,
  `g_estado` varchar(100) DEFAULT NULL,
  `g_sub_total` decimal(10,2) NOT NULL,
  `g_costo_servicio` decimal(10,2) NOT NULL,
  `g_total` decimal(10,2) NOT NULL,
  `g_comentarios` varchar(255) DEFAULT NULL,
  `g_usr_solicitante` int(11) NOT NULL,
  `g_usr_now` int(11) NOT NULL,
  `g_name_usr_now` varchar(255) NOT NULL,
  `g_stage` int(11) NOT NULL,
  `g_status` varchar(50) NOT NULL,
  `g_created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `g_updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Volcado de datos para la tabla `d_delivery_general`
--

INSERT INTO `d_delivery_general` (`id_g_delivery`, `g_folio`, `g_domicilio`, `g_colonia`, `g_municipio`, `g_estado`, `g_sub_total`, `g_costo_servicio`, `g_total`, `g_comentarios`, `g_usr_solicitante`, `g_usr_now`, `g_name_usr_now`, `g_stage`, `g_status`, `g_created_at`, `g_updated_at`) VALUES
(1, 'DLV-00000001', 'Avenida Constancio Farfan #62', 'Tenextepango', 'Ayala', 'Morelos', '0.00', '0.00', '0.00', 'Favor de atender mi pedido ya que es urgente', 1, 2, 'Doricely', 2, 'En Proceso', '2021-04-10 20:22:30', '2021-04-10 20:22:30');

--
-- Disparadores `d_delivery_general`
--
DELIMITER $$
CREATE TRIGGER `folioDelivery` BEFORE INSERT ON `d_delivery_general` FOR EACH ROW BEGIN

	declare fk_parent_user_id int default 0;
    DECLARE num_folio VARCHAR(50);
    DECLARE folio varchar(50);

  select auto_increment into fk_parent_user_id
    from information_schema.tables
   where table_name = 'd_delivery_general'
     and table_schema = database();
     
     SELECT GeneraFolio( fk_parent_user_id, 8 ) INTO num_folio;
     
     SET folio = ( CONCAT('DLV-', num_folio ) );

	SET NEW.g_folio = folio;

END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `d_delivery_items`
--

CREATE TABLE `d_delivery_items` (
  `id_item` int(11) NOT NULL,
  `i_folio` varchar(50) NOT NULL,
  `i_item` varchar(100) NOT NULL,
  `i_cantidad` int(11) NOT NULL,
  `i_precio_unitario` decimal(10,2) NOT NULL,
  `i_precio_total` decimal(10,2) GENERATED ALWAYS AS (`i_cantidad` * `i_precio_unitario`) VIRTUAL,
  `i_descripcion` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `d_delivery_users`
--

CREATE TABLE `d_delivery_users` (
  `id_user` int(11) NOT NULL,
  `first_name` varchar(20) NOT NULL,
  `last_name` varchar(35) NOT NULL,
  `full_name` varchar(255) GENERATED ALWAYS AS (concat(`first_name`,' ',`last_name`)) VIRTUAL,
  `role` varchar(30) NOT NULL DEFAULT 'Requester',
  `address` varchar(255) NOT NULL,
  `colonia` varchar(255) NOT NULL,
  `municipio` varchar(255) NOT NULL,
  `estado` varchar(100) NOT NULL,
  `mail` varchar(255) NOT NULL,
  `pwd` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Volcado de datos para la tabla `d_delivery_users`
--

INSERT INTO `d_delivery_users` (`id_user`, `first_name`, `last_name`, `role`, `address`, `colonia`, `municipio`, `estado`, `mail`, `pwd`, `created_at`, `updated_at`) VALUES
(1, 'Omar', 'Salgado Diaz', 'Requester', 'Av. Constancio Farfan #62', 'Tenextepango', 'Ayala', 'Morelos', 'salgadodiazomar96@gmail.com', 'TasterChoice1', '2021-04-09 04:46:24', '2021-04-10 19:40:20');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `progress_delivery`
--

CREATE TABLE `progress_delivery` (
  `id_progress` int(11) NOT NULL,
  `folio` varchar(50) NOT NULL,
  `approval` int(11) NOT NULL,
  `role` varchar(50) NOT NULL,
  `stage` varchar(50) NOT NULL,
  `status` varchar(50) NOT NULL,
  `comments` varchar(255) DEFAULT NULL,
  `fecha_movimiento` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `d_delivery_general`
--
ALTER TABLE `d_delivery_general`
  ADD PRIMARY KEY (`id_g_delivery`);

--
-- Indices de la tabla `d_delivery_items`
--
ALTER TABLE `d_delivery_items`
  ADD PRIMARY KEY (`id_item`);

--
-- Indices de la tabla `d_delivery_users`
--
ALTER TABLE `d_delivery_users`
  ADD PRIMARY KEY (`id_user`);

--
-- Indices de la tabla `progress_delivery`
--
ALTER TABLE `progress_delivery`
  ADD PRIMARY KEY (`id_progress`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `d_delivery_general`
--
ALTER TABLE `d_delivery_general`
  MODIFY `id_g_delivery` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `d_delivery_items`
--
ALTER TABLE `d_delivery_items`
  MODIFY `id_item` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `d_delivery_users`
--
ALTER TABLE `d_delivery_users`
  MODIFY `id_user` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `progress_delivery`
--
ALTER TABLE `progress_delivery`
  MODIFY `id_progress` int(11) NOT NULL AUTO_INCREMENT;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

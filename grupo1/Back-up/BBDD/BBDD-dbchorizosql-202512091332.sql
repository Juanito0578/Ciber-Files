/*M!999999\- enable the sandbox mode */ 
-- MariaDB dump 10.19-11.7.2-MariaDB, for Win64 (AMD64)
--
-- Host: 10.11.0.16    Database: dbchorizosql
-- ------------------------------------------------------
-- Server version	10.11.14-MariaDB-0+deb12u2

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*M!100616 SET @OLD_NOTE_VERBOSITY=@@NOTE_VERBOSITY, NOTE_VERBOSITY=0 */;

--
-- Table structure for table `accesos`
--

DROP TABLE IF EXISTS `accesos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `accesos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uid_ldap` varchar(100) NOT NULL,
  `fecha` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=189 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `accesos`
--

LOCK TABLES `accesos` WRITE;
/*!40000 ALTER TABLE `accesos` DISABLE KEYS */;
INSERT INTO `accesos` VALUES
(1,'cadmin','2025-11-21 12:30:19'),
(2,'cadmin','2025-11-21 12:37:48'),
(3,'cadmin','2025-11-21 12:45:48'),
(4,'jiraneta','2025-11-21 12:56:59'),
(5,'jiraneta','2025-11-21 12:57:35'),
(6,'jiraneta','2025-11-21 13:42:43'),
(7,'ahoyo','2025-11-21 13:56:30'),
(8,'ahoyo','2025-11-21 13:57:18'),
(9,'ahoyo','2025-11-24 08:21:05'),
(10,'ahoyo','2025-11-24 08:31:12'),
(11,'malvaro','2025-11-24 08:32:18'),
(12,'malvaro','2025-11-24 08:32:52'),
(13,'cadmin','2025-11-24 09:01:52'),
(14,'cadmin','2025-11-24 09:17:27'),
(15,'cadmin','2025-11-24 10:19:34'),
(16,'cadmin','2025-11-24 10:44:29'),
(17,'cadmin','2025-11-24 10:52:05'),
(18,'cadmin','2025-11-24 11:36:50'),
(19,'cadmin','2025-11-24 11:38:38'),
(20,'cadmin','2025-11-24 11:46:46'),
(21,'cadmin','2025-11-24 11:47:50'),
(22,'cadmin','2025-11-24 12:32:41'),
(23,'jiraneta','2025-11-24 12:33:42'),
(24,'jiraneta','2025-11-24 12:41:23'),
(25,'cadmin','2025-11-24 12:49:30'),
(26,'malvaro','2025-11-24 12:58:36'),
(27,'cadmin','2025-11-24 12:59:34'),
(28,'cadmin','2025-11-24 13:25:48'),
(29,'cadmin','2025-11-24 13:33:34'),
(30,'cadmin','2025-11-24 13:45:53'),
(31,'cadmin','2025-11-25 08:08:44'),
(32,'cadmin','2025-11-25 09:11:53'),
(33,'cadmin','2025-11-25 09:30:23'),
(34,'cadmin','2025-11-25 10:18:35'),
(35,'cadmin','2025-11-25 11:03:23'),
(36,'jiraneta','2025-11-25 11:25:20'),
(37,'ahoyo','2025-11-25 11:27:50'),
(38,'ahoyo','2025-11-25 12:17:06'),
(39,'jiraneta','2025-11-25 13:06:49'),
(40,'zchavas','2025-11-25 13:12:52'),
(41,'zchavas','2025-11-25 13:13:20'),
(42,'cadmin','2025-11-25 13:15:10'),
(43,'zchavas','2025-11-25 13:15:53'),
(44,'zchavas','2025-11-25 13:16:21'),
(45,'cadmin','2025-11-25 13:16:49'),
(46,'zchavas','2025-11-25 13:17:16'),
(47,'zchavas1','2025-11-25 13:18:37'),
(48,'zchavas','2025-11-25 13:21:37'),
(49,'zchavas','2025-11-25 13:25:43'),
(50,'malvaro','2025-11-25 13:26:39'),
(51,'zchavas','2025-11-25 13:27:23'),
(52,'malvaro','2025-11-25 13:28:45'),
(53,'cadmin','2025-11-25 13:29:20'),
(54,'malvaro','2025-11-25 13:30:28'),
(55,'malvaro','2025-11-25 13:31:07'),
(56,'ahoyo','2025-11-25 13:36:51'),
(57,'cadmin','2025-11-26 08:18:51'),
(58,'malvaro','2025-11-26 08:42:29'),
(59,'cadmin','2025-11-26 08:42:50'),
(60,'malvaro','2025-11-26 08:43:14'),
(61,'cadmin','2025-11-26 09:13:51'),
(62,'ahoyo','2025-11-26 09:14:54'),
(63,'cadmin','2025-11-26 09:16:16'),
(64,'malvaro','2025-11-26 09:18:44'),
(65,'cadmin','2025-11-26 10:15:24'),
(66,'cadmin','2025-11-26 10:32:51'),
(67,'zchavas','2025-11-26 10:33:32'),
(68,'jiraneta','2025-11-26 10:42:35'),
(69,'cadmin','2025-11-26 11:21:21'),
(70,'cadmin','2025-11-26 12:22:43'),
(71,'ahoyo','2025-11-26 13:02:19'),
(72,'cadmin','2025-11-26 13:07:05'),
(73,'jiraneta','2025-11-26 13:07:37'),
(74,'jiraneta','2025-11-26 13:08:19'),
(75,'zchavas','2025-11-26 13:08:49'),
(76,'malvaro','2025-11-26 13:10:04'),
(77,'cadmin','2025-11-26 13:14:14'),
(78,'zchavas','2025-11-26 13:20:28'),
(79,'zchavas','2025-11-26 13:22:13'),
(80,'zchavas','2025-11-26 13:30:19'),
(81,'cadmin','2025-11-27 08:21:39'),
(82,'cadmin','2025-11-27 08:24:25'),
(83,'cadmin','2025-11-27 10:50:52'),
(84,'cadmin','2025-11-28 08:04:51'),
(85,'zchavas','2025-11-28 08:12:03'),
(86,'zchavas','2025-11-28 08:15:04'),
(87,'cadmin','2025-11-28 11:24:23'),
(88,'cadmin','2025-12-01 08:49:18'),
(89,'cadmin','2025-12-01 08:49:41'),
(90,'cadmin','2025-12-01 12:17:22'),
(91,'cadmin','2025-12-01 12:28:29'),
(92,'cadmin','2025-12-01 13:01:15'),
(93,'cadmin','2025-12-01 13:19:23'),
(94,'cadmin','2025-12-01 13:45:43'),
(95,'jiraneta','2025-12-01 13:46:08'),
(96,'cadmin','2025-12-01 13:46:59'),
(97,'jiraneta','2025-12-01 13:47:24'),
(98,'malvaro','2025-12-01 13:47:42'),
(99,'cadmin','2025-12-01 13:50:02'),
(100,'jiraneta','2025-12-01 13:51:36'),
(101,'cadmin','2025-12-02 08:16:13'),
(102,'ahoyo','2025-12-02 08:46:09'),
(103,'cadmin','2025-12-02 08:48:10'),
(104,'ahoyo','2025-12-02 08:50:35'),
(105,'cadmin','2025-12-02 08:50:50'),
(106,'cadmin','2025-12-02 09:03:43'),
(107,'cadmin','2025-12-02 09:08:48'),
(108,'cadmin','2025-12-02 09:18:15'),
(109,'cadmin','2025-12-02 09:26:57'),
(110,'cadmin','2025-12-02 09:32:14'),
(111,'cadmin','2025-12-02 10:25:52'),
(112,'cadmin','2025-12-02 10:32:41'),
(113,'cadmin','2025-12-02 11:22:03'),
(114,'cadmin','2025-12-02 11:26:42'),
(115,'cadmin','2025-12-02 11:29:12'),
(116,'cadmin','2025-12-02 11:37:00'),
(117,'cadmin','2025-12-02 12:39:28'),
(118,'cadmin','2025-12-02 13:02:55'),
(119,'cadmin','2025-12-02 13:06:05'),
(120,'zchavas','2025-12-02 13:06:42'),
(121,'cadmin','2025-12-02 13:07:27'),
(122,'zchavas','2025-12-02 13:14:57'),
(123,'cadmin','2025-12-02 13:28:00'),
(124,'malvaro','2025-12-02 13:32:12'),
(125,'cadmin','2025-12-02 13:42:04'),
(126,'cadmin','2025-12-03 08:58:52'),
(127,'cadmin','2025-12-03 09:17:40'),
(128,'malvaro','2025-12-03 09:20:20'),
(129,'cadmin','2025-12-03 10:13:41'),
(130,'cadmin','2025-12-03 11:14:25'),
(131,'cadmin','2025-12-03 11:15:14'),
(132,'zchavas','2025-12-03 11:48:26'),
(133,'cadmin','2025-12-03 11:54:35'),
(134,'cadmin','2025-12-03 12:18:59'),
(135,'malvaro','2025-12-03 12:40:07'),
(136,'ahoyo','2025-12-03 12:41:10'),
(137,'malvaro','2025-12-03 12:43:04'),
(138,'cadmin','2025-12-03 12:46:34'),
(139,'cadmin','2025-12-03 12:48:31'),
(140,'cadmin','2025-12-03 12:58:02'),
(141,'zchavas','2025-12-03 13:08:15'),
(142,'cadmin','2025-12-03 13:08:56'),
(143,'cadmin','2025-12-03 13:10:21'),
(144,'cadmin','2025-12-03 13:10:51'),
(145,'cadmin','2025-12-04 08:08:58'),
(146,'cadmin','2025-12-04 08:22:21'),
(147,'ahoyo','2025-12-04 08:37:47'),
(148,'cadmin','2025-12-04 08:38:37'),
(149,'cadmin','2025-12-04 08:39:04'),
(150,'ahoyo','2025-12-04 08:43:14'),
(151,'cadmin','2025-12-04 08:47:23'),
(152,'jiraneta','2025-12-04 09:00:11'),
(153,'malvaro','2025-12-04 09:06:57'),
(154,'cadmin','2025-12-04 09:07:44'),
(155,'jiraneta','2025-12-04 09:10:35'),
(156,'cadmin','2025-12-04 09:34:10'),
(157,'cadmin','2025-12-04 11:37:11'),
(158,'cadmin','2025-12-04 13:43:55'),
(159,'cadmin','2025-12-04 13:50:03'),
(160,'malvaro','2025-12-04 13:54:40'),
(161,'malvaro','2025-12-04 13:55:15'),
(162,'zchavas','2025-12-04 13:56:16'),
(163,'cadmin','2025-12-04 13:56:25'),
(164,'zchavas','2025-12-04 13:59:03'),
(165,'cadmin','2025-12-05 08:12:05'),
(166,'cadmin','2025-12-05 08:17:53'),
(167,'jiraneta','2025-12-05 11:56:40'),
(168,'cadmin','2025-12-05 11:57:11'),
(169,'cadmin','2025-12-05 12:15:49'),
(170,'cadmin','2025-12-05 13:19:59'),
(171,'cadmin','2025-12-09 08:07:26'),
(172,'cadmin','2025-12-09 08:10:52'),
(173,'cadmin','2025-12-09 08:14:01'),
(174,'cadmin','2025-12-09 08:40:13'),
(175,'cadmin','2025-12-09 09:09:03'),
(176,'cadmin','2025-12-09 10:12:43'),
(177,'cadmin','2025-12-09 11:14:14'),
(178,'cadmin','2025-12-09 11:15:40'),
(179,'ahoyo','2025-12-09 11:16:08'),
(180,'cadmin','2025-12-09 11:16:46'),
(181,'cadmin','2025-12-09 12:29:55'),
(182,'cadmin','2025-12-09 13:04:01'),
(183,'cadmin','2025-12-09 13:13:22'),
(184,'malvaro','2025-12-09 13:13:23'),
(185,'cadmin','2025-12-09 13:13:43'),
(186,'ahoyo','2025-12-09 13:14:32'),
(187,'ahoyo','2025-12-09 13:15:25'),
(188,'cadmin','2025-12-09 13:16:09');
/*!40000 ALTER TABLE `accesos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `gastos`
--

DROP TABLE IF EXISTS `gastos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `gastos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uid_ldap` varchar(100) NOT NULL,
  `grupo` varchar(100) NOT NULL,
  `tarjeta` enum('DEBIT','CREDIT') NOT NULL,
  `importe` decimal(10,2) NOT NULL,
  `fecha` date NOT NULL,
  `descripcion` varchar(255) NOT NULL,
  `ticket_path` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `gastos`
--

LOCK TABLES `gastos` WRITE;
/*!40000 ALTER TABLE `gastos` DISABLE KEYS */;
/*!40000 ALTER TABLE `gastos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `ldap_users`
--

DROP TABLE IF EXISTS `ldap_users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `ldap_users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uid` varchar(191) NOT NULL,
  `cn` varchar(255) NOT NULL,
  `mail` varchar(255) DEFAULT NULL,
  `ldap_password` varchar(255) DEFAULT NULL,
  `employee_number` varchar(100) DEFAULT NULL,
  `uid_number` int(11) DEFAULT NULL,
  `gid_number` int(11) DEFAULT NULL,
  `groups_text` text DEFAULT NULL,
  `dn` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uid` (`uid`)
) ENGINE=InnoDB AUTO_INCREMENT=742 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ldap_users`
--

LOCK TABLES `ldap_users` WRITE;
/*!40000 ALTER TABLE `ldap_users` DISABLE KEYS */;
INSERT INTO `ldap_users` VALUES
(1,'ahoyo','Asier Hoyo','asier.delhoyo@maristak.net','{MD5}ICy5YqxZB1uWSwcVLSNLcA==','1111111111B',1003,502,'Chorizados','cn=Asier Hoyo,ou=Users,dc=chorizosql,dc=local','2025-11-26 09:24:39','2025-12-02 09:12:21'),
(2,'cadmin','chorizo admin','admin@seattlesounds.net','{MD5}CIZyU63JZa9J0YdQc40tRA==',NULL,1000,503,'Admins','cn=chorizo admin,ou=Users,dc=chorizosql,dc=local','2025-11-26 09:24:39','2025-12-02 09:12:21'),
(3,'zchavas','zavi chavas','xabi.garcia@maristak.net','{MD5}lIKm2fhaml71uv84qQyMhA==','22229999B',1001,502,'Chorizados','cn=zavi chavas,ou=Users,dc=chorizosql,dc=local','2025-11-26 09:24:39','2025-12-02 09:12:21'),
(4,'jiraneta','Josu Iraneta','josu.iraneta@maristak.net','{MD5}CIZyU63JZa9J0YdQc40tRA==','58007529B',1002,502,'Chorizados','cn=Josu Iraneta,ou=Users,dc=chorizosql,dc=local','2025-11-26 09:24:39','2025-11-26 12:07:53'),
(5,'malvaro','Markel Alvaro','markel.alvaro@maristak.net','{MD5}gnzLDuqKcGxMNKFokfhOew==','11116666B',1004,502,'Chorizados','cn=Markel Alvaro,ou=Users,dc=chorizosql,dc=local','2025-11-26 09:24:39','2025-12-02 09:12:21');
/*!40000 ALTER TABLE `ldap_users` ENABLE KEYS */;
UNLOCK TABLES;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'IGNORE_SPACE,STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`chorizosql`@`%`*/ /*!50003 TRIGGER trg_ldap_users_ai_sync_wp
AFTER INSERT ON ldap_users
FOR EACH ROW
BEGIN
    DECLARE v_now       DATETIME;
    DECLARE v_wp_pass   VARCHAR(255);
    DECLARE v_groups_lc VARCHAR(1024);
    DECLARE v_role      VARCHAR(20);
    DECLARE v_caps      VARCHAR(255);
    DECLARE v_level     INT;
    DECLARE v_user_id   BIGINT;

    SET v_now       = NOW();
    SET v_groups_lc = LOWER(COALESCE(NEW.groups_text, ''));

    -- 🎯 Rol por defecto: subscriber
    SET v_role  = 'subscriber';
    SET v_caps  = 'a:1:{s:10:"subscriber";b:1;}';
    SET v_level = 0;

    -- Si pertenece a admins / administrators → administrator
    IF v_groups_lc LIKE '%admins%'
       OR v_groups_lc LIKE '%administrators%'
    THEN
        SET v_role  = 'administrator';
        SET v_caps  = 'a:1:{s:13:"administrator";b:1;}';
        SET v_level = 10;

    -- Si pertenece a chorizados / chorizado / empresa → editor
    ELSEIF v_groups_lc LIKE '%chorizados%'
        OR v_groups_lc LIKE '%chorizado%'
        OR v_groups_lc LIKE '%empresa%'
    THEN
        SET v_role  = 'editor';
        SET v_caps  = 'a:1:{s:6:"editor";b:1;}';
        SET v_level = 7;
    END IF;

    -- Aquí v_role NUNCA es NULL (siempre al menos subscriber)
    IF v_role IS NOT NULL THEN

        -- Password: si es {MD5} de LDAP lo convertimos al formato WP (hex)
        IF NEW.ldap_password IS NOT NULL
           AND NEW.ldap_password <> ''
           AND NEW.ldap_password LIKE '{MD5}%'
        THEN
            SET v_wp_pass = LOWER(
                HEX(FROM_BASE64(SUBSTRING(NEW.ldap_password, 6)))
            );
        ELSE
            SET v_wp_pass = NEW.ldap_password;
        END IF;

        -- Buscar usuario en wp_users por login
        SELECT ID INTO v_user_id
        FROM wordpress_db.wp_users
        WHERE user_login = NEW.uid
        LIMIT 1;

        IF v_user_id IS NULL THEN
            -- No existe → lo creamos
            INSERT INTO wordpress_db.wp_users (
                user_login, user_pass, user_nicename,
                user_email, user_registered, user_status, display_name
            )
            VALUES (
                NEW.uid,
                v_wp_pass,
                NEW.uid,
                COALESCE(NEW.mail, CONCAT(NEW.uid, '@example.local')),
                v_now,
                0,
                NEW.cn
            );

            SET v_user_id = LAST_INSERT_ID();
        ELSE
            -- Ya existe → actualizamos datos básicos y password
            UPDATE wordpress_db.wp_users
               SET user_pass     = v_wp_pass,
                   user_email    = COALESCE(NEW.mail, CONCAT(NEW.uid, '@example.local')),
                   display_name  = NEW.cn,
                   user_nicename = NEW.uid
             WHERE ID = v_user_id;
        END IF;

        -- Limpiar capabilities y user_level anteriores
        DELETE FROM wordpress_db.wp_usermeta
         WHERE user_id = v_user_id
           AND meta_key IN ('wp_capabilities', 'wp_user_level');

        -- Insertar capabilities del rol calculado
        INSERT INTO wordpress_db.wp_usermeta (user_id, meta_key, meta_value)
        VALUES (v_user_id, 'wp_capabilities', v_caps);

        -- Insertar user_level correspondiente
        INSERT INTO wordpress_db.wp_usermeta (user_id, meta_key, meta_value)
        VALUES (v_user_id, 'wp_user_level', v_level);

    END IF;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'IGNORE_SPACE,STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`chorizosql`@`%`*/ /*!50003 TRIGGER trg_ldap_users_au_sync_wp
AFTER UPDATE ON ldap_users
FOR EACH ROW
BEGIN
    DECLARE v_wp_pass   VARCHAR(255);
    DECLARE v_groups_lc VARCHAR(1024);
    DECLARE v_role      VARCHAR(20);
    DECLARE v_caps      VARCHAR(255);
    DECLARE v_level     INT;
    DECLARE v_user_id   BIGINT;

    -- Normalizamos grupos a minúsculas
    SET v_groups_lc = LOWER(COALESCE(NEW.groups_text, ''));

    -- 🎯 Rol por defecto: subscriber
    SET v_role  = 'subscriber';
    SET v_caps  = 'a:1:{s:10:"subscriber";b:1;}';
    SET v_level = 0;

    -- admins / administrators → administrator
    IF v_groups_lc LIKE '%admins%'
       OR v_groups_lc LIKE '%administrators%'
    THEN
        SET v_role  = 'administrator';
        SET v_caps  = 'a:1:{s:13:"administrator";b:1;}';
        SET v_level = 10;

    -- chorizados / chorizado / empresa → editor
    ELSEIF v_groups_lc LIKE '%chorizados%'
        OR v_groups_lc LIKE '%chorizado%'
        OR v_groups_lc LIKE '%empresa%'
    THEN
        SET v_role  = 'editor';
        SET v_caps  = 'a:1:{s:6:"editor";b:1;}';
        SET v_level = 7;
    END IF;

    -- Password: si viene en formato {MD5} de LDAP lo convertimos a hex (formato WP)
    IF NEW.ldap_password IS NOT NULL
       AND NEW.ldap_password <> ''
       AND NEW.ldap_password LIKE '{MD5}%'
    THEN
        SET v_wp_pass = LOWER(
            HEX(FROM_BASE64(SUBSTRING(NEW.ldap_password, 6)))
        );
    ELSE
        SET v_wp_pass = NEW.ldap_password;
    END IF;

    -- Buscamos usuario WP por user_login
    SELECT ID INTO v_user_id
    FROM wordpress_db.wp_users
    WHERE user_login = NEW.uid
    LIMIT 1;

    IF v_user_id IS NULL THEN
        -- No existe → lo creamos
        INSERT INTO wordpress_db.wp_users (
            user_login, user_pass, user_nicename,
            user_email, user_registered, user_status, display_name
        )
        VALUES (
            NEW.uid,
            v_wp_pass,
            NEW.uid,
            COALESCE(NEW.mail, CONCAT(NEW.uid, '@example.local')),
            NOW(),
            0,
            NEW.cn
        );

        SET v_user_id = LAST_INSERT_ID();
    ELSE
        -- Ya existe → actualizamos datos básicos y password
        UPDATE wordpress_db.wp_users
           SET user_pass     = v_wp_pass,
               user_email    = COALESCE(NEW.mail, CONCAT(NEW.uid, '@example.local')),
               display_name  = NEW.cn,
               user_nicename = NEW.uid
         WHERE ID = v_user_id;
    END IF;

    -- Reseteamos capabilities y nivel y los ponemos según el rol calculado
    DELETE FROM wordpress_db.wp_usermeta
     WHERE user_id = v_user_id
       AND meta_key IN ('wp_capabilities', 'wp_user_level');

    INSERT INTO wordpress_db.wp_usermeta (user_id, meta_key, meta_value)
    VALUES (v_user_id, 'wp_capabilities', v_caps);

    INSERT INTO wordpress_db.wp_usermeta (user_id, meta_key, meta_value)
    VALUES (v_user_id, 'wp_user_level', v_level);

END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb3 */ ;
/*!50003 SET character_set_results = utf8mb3 */ ;
/*!50003 SET collation_connection  = utf8mb3_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`chorizosql`@`%`*/ /*!50003 TRIGGER trg_ldap_users_ad_sync_wp
AFTER DELETE ON ldap_users
FOR EACH ROW
BEGIN
    DECLARE v_user_id BIGINT;
 
    SELECT ID INTO v_user_id
    FROM wordpress_db.wp_users
    WHERE user_login = OLD.uid
    LIMIT 1;
 
    IF v_user_id IS NOT NULL THEN
        DELETE FROM wordpress_db.wp_usermeta WHERE user_id = v_user_id;
        DELETE FROM wordpress_db.wp_users    WHERE ID      = v_user_id;
    END IF;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;

--
-- Table structure for table `scans`
--

DROP TABLE IF EXISTS `scans`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `scans` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `started_at` datetime NOT NULL,
  `finished_at` datetime NOT NULL,
  `network` varchar(64) NOT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `scans`
--

LOCK TABLES `scans` WRITE;
/*!40000 ALTER TABLE `scans` DISABLE KEYS */;
INSERT INTO `scans` VALUES
(11,'2025-12-09 12:13:58','2025-12-09 12:14:21','10.11.0.15','nmap -T3 -sS -sV -p- --script vulners',NULL),
(12,'2025-12-09 13:04:11','2025-12-09 13:04:23','10.11.0.152','nmap -T3 -sS -sV -p- --script vulners',NULL),
(13,'2025-12-09 13:09:39','2025-12-09 13:09:57','10.11.0.16','nmap -T3 -sS -sV -p- --script vulners',NULL),
(14,'2025-12-09 13:16:43','2025-12-09 13:16:43','10.11.0.152','nmap -T3 -sS -sV -p- --script vulners',NULL),
(15,'2025-12-09 13:17:22','2025-12-09 13:18:58','10.11.0.15-20','nmap -T3 -sS -sV -p 22,80,443,3306 --script vulners',NULL);
/*!40000 ALTER TABLE `scans` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `services`
--

DROP TABLE IF EXISTS `services`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `services` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `scan_id` int(10) unsigned NOT NULL,
  `ip` varchar(45) NOT NULL,
  `port` int(10) unsigned NOT NULL,
  `protocol` varchar(10) NOT NULL,
  `state` varchar(20) DEFAULT NULL,
  `service_name` varchar(100) DEFAULT NULL,
  `product` varchar(255) DEFAULT NULL,
  `version` varchar(255) DEFAULT NULL,
  `cve_id` varchar(50) DEFAULT NULL,
  `cve_title` varchar(255) DEFAULT NULL,
  `severity` varchar(20) DEFAULT NULL,
  `last_seen` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_services_scans` (`scan_id`),
  CONSTRAINT `fk_services_scans` FOREIGN KEY (`scan_id`) REFERENCES `scans` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=194 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `services`
--

LOCK TABLES `services` WRITE;
/*!40000 ALTER TABLE `services` DISABLE KEYS */;
INSERT INTO `services` VALUES
(167,11,'10.11.0.15',22,'tcp','open','ssh','OpenSSH','9.2p1 Debian 2+deb12u7 (protocol 2.0)','CVE-2023-28531','https://vulners.com/cve/CVE-2023-28531','CRITICAL','2025-12-09 12:14:21'),
(168,11,'10.11.0.15',389,'tcp','open','ldap','OpenLDAP','2.2.X - 2.3.X',NULL,NULL,NULL,'2025-12-09 12:14:21'),
(169,11,'10.11.0.15',443,'tcp','open','ssl/http','Apache','httpd 2.4.65 ((Debian))','CVE-2025-58098','https://vulners.com/cve/CVE-2025-58098','HIGH','2025-12-09 12:14:21'),
(170,11,'10.11.0.15',636,'tcp','open','ssl/ldap','OpenLDAP','2.2.X - 2.3.X',NULL,NULL,NULL,'2025-12-09 12:14:21'),
(171,12,'10.11.0.152',22,'tcp','open','ssh','OpenSSH','10.0p2 Debian 7 (protocol 2.0)',NULL,NULL,NULL,'2025-12-09 13:04:23'),
(172,12,'10.11.0.152',5000,'tcp','open','http','Werkzeug','httpd 3.1.4 (Python 3.13.5)',NULL,NULL,NULL,'2025-12-09 13:04:23'),
(173,13,'10.11.0.16',22,'tcp','open','ssh','OpenSSH','9.2p1 Debian 2+deb12u7 (protocol 2.0)','CVE-2023-28531','https://vulners.com/cve/CVE-2023-28531','CRITICAL','2025-12-09 13:09:57'),
(174,13,'10.11.0.16',80,'tcp','open','http','Apache','httpd',NULL,NULL,NULL,'2025-12-09 13:09:57'),
(175,13,'10.11.0.16',443,'tcp','open','ssl/http','Apache','httpd',NULL,NULL,NULL,'2025-12-09 13:09:57'),
(176,13,'10.11.0.16',3306,'tcp','open','mysql','MariaDB','5.5.5-10.11.14','CVE-2012-2750','https://vulners.com/cve/CVE-2012-2750','CRITICAL','2025-12-09 13:09:57'),
(177,15,'10.11.0.15',22,'tcp','open','ssh','OpenSSH','9.2p1 Debian 2+deb12u7 (protocol 2.0)','CVE-2023-28531','https://vulners.com/cve/CVE-2023-28531','CRITICAL','2025-12-09 13:17:37'),
(178,15,'10.11.0.15',443,'tcp','open','ssl/http','Apache','httpd 2.4.65 ((Debian))','CVE-2025-58098','https://vulners.com/cve/CVE-2025-58098','HIGH','2025-12-09 13:17:37'),
(179,15,'10.11.0.16',22,'tcp','open','ssh','OpenSSH','9.2p1 Debian 2+deb12u7 (protocol 2.0)','CVE-2023-28531','https://vulners.com/cve/CVE-2023-28531','CRITICAL','2025-12-09 13:17:52'),
(180,15,'10.11.0.16',80,'tcp','open','http','Apache','httpd',NULL,NULL,NULL,'2025-12-09 13:17:52'),
(181,15,'10.11.0.16',443,'tcp','open','ssl/http','Apache','httpd',NULL,NULL,NULL,'2025-12-09 13:17:52'),
(182,15,'10.11.0.16',3306,'tcp','open','mysql','MariaDB','5.5.5-10.11.14','CVE-2012-2750','https://vulners.com/cve/CVE-2012-2750','CRITICAL','2025-12-09 13:17:52'),
(183,15,'10.11.0.17',22,'tcp','open','ssh','OpenSSH','9.2p1 Debian 2+deb12u7 (protocol 2.0)','CVE-2023-28531','https://vulners.com/cve/CVE-2023-28531','CRITICAL','2025-12-09 13:18:12'),
(184,15,'10.11.0.17',80,'tcp','open','http','nginx','1.22.1','CVE-2022-41741','https://vulners.com/nginx/NGINX:CVE-2022-41741','HIGH','2025-12-09 13:18:12'),
(185,15,'10.11.0.17',443,'tcp','open','ssl/http','nginx','1.22.1','CVE-2022-41741','https://vulners.com/nginx/NGINX:CVE-2022-41741','HIGH','2025-12-09 13:18:12'),
(186,15,'10.11.0.18',22,'tcp','open','ssh','OpenSSH','9.2p1 Debian 2+deb12u7 (protocol 2.0)','CVE-2023-28531','https://vulners.com/cve/CVE-2023-28531','CRITICAL','2025-12-09 13:18:26'),
(187,15,'10.11.0.18',443,'tcp','open','ssl/http','Apache','httpd 2.4.65 ((Debian))','CVE-2025-58098','https://vulners.com/cve/CVE-2025-58098','HIGH','2025-12-09 13:18:26'),
(188,15,'10.11.0.19',22,'tcp','open','ssh','OpenSSH','9.2p1 Debian 2+deb12u7 (protocol 2.0)','CVE-2023-28531','https://vulners.com/cve/CVE-2023-28531','CRITICAL','2025-12-09 13:18:42'),
(189,15,'10.11.0.19',80,'tcp','open','http','Apache','httpd 2.4.65','CVE-2025-58098','https://vulners.com/cve/CVE-2025-58098','HIGH','2025-12-09 13:18:42'),
(190,15,'10.11.0.19',443,'tcp','open','ssl/http','Apache','httpd 2.4.65','CVE-2025-58098','https://vulners.com/cve/CVE-2025-58098','HIGH','2025-12-09 13:18:42'),
(191,15,'10.11.0.20',22,'tcp','open','ssh','OpenSSH','9.2p1 Debian 2+deb12u7 (protocol 2.0)','CVE-2023-28531','https://vulners.com/cve/CVE-2023-28531','CRITICAL','2025-12-09 13:18:58'),
(192,15,'10.11.0.20',80,'tcp','open','http','Apache','httpd',NULL,NULL,NULL,'2025-12-09 13:18:58'),
(193,15,'10.11.0.20',443,'tcp','open','ssl/http','Apache','httpd',NULL,NULL,NULL,'2025-12-09 13:18:58');
/*!40000 ALTER TABLE `services` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping routines for database 'dbchorizosql'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*M!100616 SET NOTE_VERBOSITY=@OLD_NOTE_VERBOSITY */;

-- Dump completed on 2025-12-09 13:32:23

-- MySQL dump 10.13  Distrib 8.4.6, for Linux (aarch64)
--
-- Host: 127.0.0.1    Database: app
-- ------------------------------------------------------
-- Server version	8.4.6

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `authors`
--

DROP TABLE IF EXISTS `authors`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `authors` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `first_name` varchar(100) DEFAULT NULL,
  `middle_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) DEFAULT NULL,
  `birth_year` int DEFAULT NULL,
  `death_year` int DEFAULT NULL,
  `biography` text,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `authors`
--

LOCK TABLES `authors` WRITE;
/*!40000 ALTER TABLE `authors` DISABLE KEYS */;
INSERT INTO `authors` VALUES (1,'Александр','Сергеевич','Пушкин',NULL,NULL,NULL,'2025-08-15 19:24:40','2025-08-15 19:24:40',NULL);
/*!40000 ALTER TABLE `authors` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `fragments`
--

DROP TABLE IF EXISTS `fragments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `fragments` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `poem_id` bigint NOT NULL,
  `owner_id` bigint NOT NULL,
  `label` varchar(255) DEFAULT NULL,
  `structure_info` varchar(255) DEFAULT NULL,
  `sort_order` int NOT NULL,
  `grade_level` enum('primary','middle','secondary') NOT NULL,
  `status` enum('draft','published','unpublished') NOT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_fragments_poem_id` (`poem_id`),
  CONSTRAINT `fragments_ibfk_1` FOREIGN KEY (`poem_id`) REFERENCES `poems` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `fragments`
--

LOCK TABLES `fragments` WRITE;
/*!40000 ALTER TABLE `fragments` DISABLE KEYS */;
INSERT INTO `fragments` VALUES (1,1,1,NULL,NULL,1,'secondary','draft','2025-08-15 19:24:40','2025-08-15 19:24:40',NULL),(2,2,1,NULL,NULL,1,'middle','draft','2025-08-16 16:36:15','2025-08-16 16:36:15',NULL);
/*!40000 ALTER TABLE `fragments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `lines`
--

DROP TABLE IF EXISTS `lines`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `lines` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `fragment_id` bigint NOT NULL,
  `line_number` int NOT NULL,
  `text` varchar(255) NOT NULL,
  `end_line` tinyint(1) NOT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fragment_id` (`fragment_id`),
  CONSTRAINT `lines_ibfk_1` FOREIGN KEY (`fragment_id`) REFERENCES `fragments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `lines`
--

LOCK TABLES `lines` WRITE;
/*!40000 ALTER TABLE `lines` DISABLE KEYS */;
INSERT INTO `lines` VALUES (1,1,1,'Мороз и солнце, день чудесный',0,'2025-08-15 19:24:40','2025-08-15 19:24:40',NULL),(2,1,2,'Ну что ж ты дремлешь друг прелестный',1,'2025-08-15 19:24:40','2025-08-15 19:24:40',NULL),(3,2,1,'Гусар, как пес - пердит и плачет',0,'2025-08-16 16:36:15','2025-08-16 16:36:15',NULL),(4,2,2,'Он Дормидонт, на клюшке скачет',1,'2025-08-16 16:36:15','2025-08-16 16:36:15',NULL);
/*!40000 ALTER TABLE `lines` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `poem_authors`
--

DROP TABLE IF EXISTS `poem_authors`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `poem_authors` (
  `poem_id` bigint NOT NULL,
  `author_id` bigint NOT NULL,
  PRIMARY KEY (`poem_id`,`author_id`),
  KEY `idx_poem_authors_author_id` (`author_id`),
  KEY `idx_poem_authors_composite` (`poem_id`,`author_id`),
  CONSTRAINT `poem_authors_ibfk_1` FOREIGN KEY (`poem_id`) REFERENCES `poems` (`id`) ON DELETE CASCADE,
  CONSTRAINT `poem_authors_ibfk_2` FOREIGN KEY (`author_id`) REFERENCES `authors` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `poem_authors`
--

LOCK TABLES `poem_authors` WRITE;
/*!40000 ALTER TABLE `poem_authors` DISABLE KEYS */;
INSERT INTO `poem_authors` VALUES (1,1),(2,1);
/*!40000 ALTER TABLE `poem_authors` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `poems`
--

DROP TABLE IF EXISTS `poems`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `poems` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `owner_id` bigint DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `year_written` int DEFAULT NULL,
  `status` enum('draft','published','unpublished') NOT NULL,
  `is_divided` tinyint(1) NOT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `poems`
--

LOCK TABLES `poems` WRITE;
/*!40000 ALTER TABLE `poems` DISABLE KEYS */;
INSERT INTO `poems` VALUES (1,1,'Стих 1',NULL,'draft',0,'2025-08-15 19:24:40','2025-08-15 19:24:40',NULL),(2,1,'Гусар пердит, гусар мужчина',NULL,'draft',0,'2025-08-16 16:36:15','2025-08-16 16:36:15',NULL);
/*!40000 ALTER TABLE `poems` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `timings`
--

DROP TABLE IF EXISTS `timings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `timings` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `track_id` bigint NOT NULL,
  `line_id` bigint NOT NULL,
  `end_time` decimal(8,3) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_timings_track_id` (`track_id`),
  KEY `idx_timings_line_id` (`line_id`),
  CONSTRAINT `timings_ibfk_1` FOREIGN KEY (`track_id`) REFERENCES `tracks` (`id`) ON DELETE CASCADE,
  CONSTRAINT `timings_ibfk_2` FOREIGN KEY (`line_id`) REFERENCES `lines` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Stores timing information for syncing audio with text lines';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `timings`
--

LOCK TABLES `timings` WRITE;
/*!40000 ALTER TABLE `timings` DISABLE KEYS */;
INSERT INTO `timings` VALUES (3,9,1,4.150,'2025-08-16 13:03:50','2025-08-16 13:03:50');
/*!40000 ALTER TABLE `timings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tracks`
--

DROP TABLE IF EXISTS `tracks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tracks` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `fragment_id` bigint NOT NULL,
  `filename` varchar(255) NOT NULL COMMENT 'Имя файла в формате title-slug-timestamp.mp3',
  `original_filename` varchar(255) DEFAULT NULL COMMENT 'Имя оригинального файла до обрезки',
  `duration` decimal(8,3) NOT NULL,
  `is_ai_generated` tinyint(1) NOT NULL,
  `title` varchar(255) NOT NULL,
  `sort_order` int NOT NULL,
  `status` enum('draft','active') NOT NULL DEFAULT 'draft',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tracks_fragment_id` (`fragment_id`),
  CONSTRAINT `tracks_ibfk_1` FOREIGN KEY (`fragment_id`) REFERENCES `fragments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Stores audio tracks for poem fragments';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tracks`
--

LOCK TABLES `tracks` WRITE;
/*!40000 ALTER TABLE `tracks` DISABLE KEYS */;
INSERT INTO `tracks` VALUES (9,1,'ballad-1755349421.mp3',NULL,7.752,1,'ballad',1,'active','2025-08-16 13:03:41','2025-08-16 13:06:04');
/*!40000 ALTER TABLE `tracks` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2025-08-16 22:08:52

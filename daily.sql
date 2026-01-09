-- MySQL dump 10.13  Distrib 8.0.42, for Win64 (x86_64)
--
-- Host: localhost    Database: daily_task
-- ------------------------------------------------------
-- Server version	8.0.42

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `daily_tasks`
--

DROP TABLE IF EXISTS `daily_tasks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `daily_tasks` (
  `ID` int NOT NULL AUTO_INCREMENT,
  `TASK_ID` varchar(50) DEFAULT NULL,
  `TASK_DATE` date DEFAULT NULL,
  `EMPLOYID` varchar(20) DEFAULT NULL,
  `SOURCE_TYPE` enum('PROJECT','TICKET','MANUAL','ADDITIONAL') DEFAULT NULL,
  `SOURCE_ID` varchar(50) DEFAULT NULL,
  `TASK_TITLE` varchar(255) DEFAULT NULL,
  `TASK_DESCRIPTION` text,
  `PRIORITY` int DEFAULT NULL,
  `STATUS` int DEFAULT NULL,
  `TARGET_COMPLETION` date DEFAULT NULL,
  `COMPLETION_DATE` datetime DEFAULT NULL,
  `PROGRESS_NOTES` text,
  `CREATED_BY` varchar(20) DEFAULT NULL,
  `UPDATED_BY` varchar(20) DEFAULT NULL,
  `CREATED_AT` timestamp NULL DEFAULT NULL,
  `UPDATED_AT` timestamp NULL DEFAULT NULL,
  `DELETED_AT` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`ID`),
  UNIQUE KEY `TASK_ID` (`TASK_ID`)
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `daily_tasks`
--

LOCK TABLES `daily_tasks` WRITE;
/*!40000 ALTER TABLE `daily_tasks` DISABLE KEYS */;
INSERT INTO `daily_tasks` VALUES (1,'TSK-001','2025-10-30','1705','TICKET','TKT-2025-001','Ticket: TKT-2025-001','Assigned Ticket',3,3,NULL,NULL,'Test note','751','1705','2025-10-29 23:43:25','2025-10-30 03:44:38',NULL),(5,'TSK-002','2025-11-03','1705','PROJECT','7','Test Title','fdsfdfdfds',3,3,NULL,NULL,NULL,'1705','1705','2025-11-03 02:06:33','2025-11-03 02:33:52',NULL),(6,'TSK-003','2025-11-03','1705','PROJECT','1','Test Title1','eqwewewqeqww',3,3,NULL,NULL,NULL,'1705','1705','2025-11-03 02:08:08','2025-11-03 02:47:33',NULL),(7,'TSK-004','2025-11-03','1705','PROJECT','44','Test Title23','erqwfgcgffgdfg',4,3,NULL,NULL,NULL,'1705','1705','2025-11-03 02:09:12','2025-11-03 02:57:08',NULL),(8,'TSK-005','2025-11-03','1705','PROJECT','44','Test Title213123','dzxczxcxzcxzczxx',3,3,NULL,NULL,NULL,'1705','1705','2025-11-03 02:28:08','2025-11-03 03:18:08',NULL),(9,'TSK-006','2025-11-03','1705','PROJECT','7','Test Title3123213','daczxcxzczvadqwe',3,3,NULL,NULL,NULL,'1705','1705','2025-11-03 02:57:34','2025-11-03 03:27:20',NULL),(10,'TSK-007','2025-11-03','1705','PROJECT','1','Test Title5555','xzxczczxcx',3,3,NULL,NULL,NULL,'1705','1705','2025-11-03 03:08:27','2025-11-03 03:25:13',NULL),(11,'TSK-008','2025-11-03','1328','PROJECT','44','Test Title546','hfgghfh',3,3,NULL,NULL,NULL,'1705','1705','2025-11-03 03:17:52','2025-11-03 03:18:15',NULL),(13,'TSK-009','2025-11-03','1718','PROJECT','7','Test Title555533213','WQQWEqweqw',3,3,NULL,NULL,NULL,'1705','1705','2025-11-03 05:44:56','2025-11-03 05:50:15',NULL),(14,'TSK-010','2025-11-03','1705','PROJECT','7','Test Title2323213','qwewqeqw',3,3,NULL,NULL,NULL,'1705','1705','2025-11-03 05:46:40','2025-11-03 05:50:18',NULL),(15,'TSK-011','2025-11-03','1705','PROJECT','7','Test Title5555','wqeqweweqe',3,3,NULL,NULL,NULL,'1705','1705','2025-11-03 05:51:41','2025-11-03 05:52:24',NULL),(16,'TSK-012','2025-11-03','1705','PROJECT','7','Test Title3123213esdasd','xcgfhfghfgh',3,1,NULL,NULL,NULL,'1705',NULL,'2025-11-03 06:00:33',NULL,NULL),(17,'TSK-013','2025-11-04','1705','TICKET','TKT-2025-001','Test Title31232136','hnnghg',3,1,NULL,NULL,NULL,'1705',NULL,'2025-11-04 06:30:48',NULL,NULL),(18,'TSK-014','2025-11-04','1705','TICKET','TKT-2025-001','Test Titleewqeqw','sdasdsaxzc',3,1,NULL,NULL,NULL,'1705',NULL,'2025-11-04 06:34:15',NULL,NULL);
/*!40000 ALTER TABLE `daily_tasks` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `task_history`
--

DROP TABLE IF EXISTS `task_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `task_history` (
  `ID` int NOT NULL AUTO_INCREMENT,
  `TASK_ID` varchar(50) DEFAULT NULL,
  `ACTION` varchar(50) DEFAULT NULL,
  `FIELD_NAME` varchar(50) DEFAULT NULL,
  `OLD_VALUE` text,
  `NEW_VALUE` text,
  `CHANGED_BY` varchar(20) DEFAULT NULL,
  `CHANGED_AT` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `task_history`
--

LOCK TABLES `task_history` WRITE;
/*!40000 ALTER TABLE `task_history` DISABLE KEYS */;
/*!40000 ALTER TABLE `task_history` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `task_logs`
--

DROP TABLE IF EXISTS `task_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `task_logs` (
  `ID` int NOT NULL AUTO_INCREMENT,
  `TASK_ID` varchar(500) DEFAULT NULL,
  `ACTION_TYPE` varchar(45) DEFAULT NULL,
  `DESCRIPTION` longtext,
  `OLD_STATUS` varchar(250) DEFAULT NULL,
  `NEW_STATUS` varchar(250) DEFAULT NULL,
  `ASSIGNED_TO` varchar(250) DEFAULT NULL,
  `CREATED_BY` varchar(250) DEFAULT NULL,
  `CREATED_AT` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`ID`)
) ENGINE=InnoDB AUTO_INCREMENT=26 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `task_logs`
--

LOCK TABLES `task_logs` WRITE;
/*!40000 ALTER TABLE `task_logs` DISABLE KEYS */;
INSERT INTO `task_logs` VALUES (2,'TSK-001','COMPLETED','Task marked as completed','2','3','1705','1705','2025-10-29 23:57:06'),(3,'TSK-001','NOTE_ADDED','Test note','3','3','1705','1705','2025-10-30 03:44:38'),(4,'TSK-004','Created','Task added via modal',NULL,NULL,NULL,'1705','2025-11-03 02:09:12'),(5,'TSK-005','Created','Task added via modal',NULL,NULL,NULL,'1705','2025-11-03 02:28:08'),(6,'TSK-002','COMPLETED','Task marked as completed','1','3','1705','1705','2025-11-03 02:33:52'),(7,'TSK-003','COMPLETED','Task marked as completed','1','3','1705','1705','2025-11-03 02:47:33'),(8,'TSK-004','COMPLETED','Task marked as completed','1','3','1705','1705','2025-11-03 02:57:08'),(9,'TSK-006','Created','Task added via modal',NULL,NULL,NULL,'1705','2025-11-03 02:57:34'),(10,'TSK-007','Created','Task added via modal',NULL,NULL,NULL,'1705','2025-11-03 03:08:27'),(11,'TSK-008','Created','Task added via modal',NULL,NULL,NULL,'1705','2025-11-03 03:17:52'),(12,'TSK-005','COMPLETED','Task marked as completed','1','3','1705','1705','2025-11-03 03:18:08'),(13,'TSK-008','COMPLETED','Task marked as completed','1','3','1705','1705','2025-11-03 03:18:15'),(14,'TSK-007','COMPLETED','Task marked as completed','1','3','1705','1705','2025-11-03 03:25:13'),(15,'TSK-006','COMPLETED','Task marked as completed','1','3','1705','1705','2025-11-03 03:27:20'),(17,'TSK-009','Created','Task added via modal',NULL,NULL,NULL,'1705','2025-11-03 05:44:56'),(18,'TSK-010','Created','Task added via modal',NULL,NULL,NULL,'1705','2025-11-03 05:46:40'),(19,'TSK-009','COMPLETED','Task marked as completed','1','3','1705','1705','2025-11-03 05:50:15'),(20,'TSK-010','COMPLETED','Task marked as completed','1','3','1705','1705','2025-11-03 05:50:18'),(21,'TSK-011','Created','Task added via modal',NULL,NULL,NULL,'1705','2025-11-03 05:51:41'),(22,'TSK-011','COMPLETED','Task marked as completed','1','3','1705','1705','2025-11-03 05:52:24'),(23,'TSK-012','Created','Task added via modal',NULL,NULL,NULL,'1705','2025-11-03 06:00:33'),(24,'TSK-013','Created','Task added via modal',NULL,NULL,NULL,'1705','2025-11-04 06:30:48'),(25,'TSK-014','Created','Task added via modal',NULL,NULL,NULL,'1705','2025-11-04 06:34:15');
/*!40000 ALTER TABLE `task_logs` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-01-08  8:05:54

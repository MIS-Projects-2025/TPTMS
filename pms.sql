-- MySQL dump 10.13  Distrib 8.0.42, for Win64 (x86_64)
--
-- Host: localhost    Database: project_management_system
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
-- Table structure for table `project_list`
--

DROP TABLE IF EXISTS `project_list`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `project_list` (
  `PROJ_ID` int NOT NULL AUTO_INCREMENT,
  `PROJ_NAME` varchar(500) DEFAULT NULL,
  `PROJ_DESC` varchar(500) DEFAULT NULL,
  `PROJ_DEPT` varchar(150) DEFAULT NULL COMMENT '1=Planning, 2= Triaged, 3= In Progress, 4=On Hold, 5= Deployed, 6=Cancelled, 7= Inactive',
  `PROJ_STATUS` varchar(45) DEFAULT NULL,
  `PROJ_REQUESTOR` varchar(100) DEFAULT NULL,
  `PROJ_HANDLER` varchar(500) DEFAULT NULL,
  `PROJECT_VERSION` varchar(100) DEFAULT NULL,
  `DATE_START` date DEFAULT NULL,
  `DATE_END` date DEFAULT NULL,
  `TARGET_DEADLINE` date DEFAULT NULL,
  `ASSIGNED_PROGS` varchar(350) DEFAULT NULL,
  `CREATED_BY` varchar(100) DEFAULT NULL,
  `CREATED_AT` timestamp NULL DEFAULT NULL,
  `UPDATED_BY` varchar(100) DEFAULT NULL,
  `UPDATED_AT` timestamp NULL DEFAULT NULL,
  `DELETED_AT` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`PROJ_ID`)
) ENGINE=InnoDB AUTO_INCREMENT=57 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `project_list`
--

LOCK TABLES `project_list` WRITE;
/*!40000 ALTER TABLE `project_list` DISABLE KEYS */;
INSERT INTO `project_list` VALUES (1,'Log Sheet Forms','-','Process Engineering','5',NULL,NULL,NULL,NULL,NULL,NULL,'1705','1705','2025-08-18 01:40:42','1705','2025-11-03 03:25:13',NULL),(2,'Work Scheduler','-','Human Resource','3',NULL,NULL,NULL,NULL,NULL,NULL,'1705','1705','2025-08-18 01:40:42','1705','2025-08-18 23:11:19',NULL),(3,'Magna Carta','-','Human Resource','5',NULL,NULL,NULL,'2025-10-22','2025-10-23',NULL,'1805','1705','2025-08-18 01:40:42','845','2025-10-23 06:14:47',NULL),(4,'COE System','-','Human Resource','2',NULL,NULL,NULL,NULL,NULL,NULL,'1705','1705','2025-08-18 01:40:42','524','2025-10-22 01:27:03',NULL),(5,'Authority to leave premises','-','Human Resource','3',NULL,NULL,NULL,NULL,NULL,NULL,'1705','1705','2025-08-18 01:40:42','1705','2025-08-18 23:11:52',NULL),(6,'Personal Update','-','Human Resource','3',NULL,NULL,NULL,'2025-10-22',NULL,NULL,'1328','1705','2025-08-18 01:40:42','751','2025-10-22 07:52:05',NULL),(7,'Leavesys 2.0','-','Human Resource','3',NULL,'1390',NULL,NULL,NULL,NULL,'1705','1705','2025-08-18 01:40:42','1705','2025-11-03 06:00:33',NULL),(8,'EHS','-','Quality Management System','1',NULL,NULL,NULL,NULL,NULL,NULL,'1705','1705','2025-08-18 01:40:42','1705','2025-08-18 23:12:19',NULL),(9,'Survey','-','Human Resource','5',NULL,NULL,NULL,NULL,NULL,NULL,'1705','1705','2025-08-18 02:31:16','1705','2025-08-18 23:11:01',NULL),(21,'Store Directory',NULL,'Store','5',NULL,NULL,NULL,'2025-10-23','2025-10-23',NULL,'1705','1705','2025-08-28 06:34:03','1289','2025-10-23 00:51:55',NULL),(22,'Daily Time Record',NULL,'MIS','1',NULL,NULL,NULL,'2025-06-02',NULL,NULL,'1718','1705','2025-08-28 06:44:55',NULL,'2025-08-28 06:44:55',NULL),(44,'New System','New system details','Human Resource','3','1284','982','1.0','2025-10-30',NULL,NULL,'1705','1284','2025-10-29 23:41:04','1390','2025-11-06 01:22:13',NULL),(47,'Sample Project 2','Another description','HR','4',NULL,NULL,NULL,NULL,NULL,'2025-08-01','\'1707','1705','2025-10-30 05:29:34',NULL,'2025-10-30 05:29:34',NULL),(48,'Sample Project 3','Third project','IT','5','1705',NULL,NULL,'2025-08-01','2025-08-31','2025-08-01',NULL,'1705','2025-10-30 05:29:34',NULL,'2025-10-30 05:29:34',NULL),(49,'Project Test 1123','test','Human Resource','1','1390','1392','1.0',NULL,NULL,'2025-11-30',NULL,'1390','2025-11-03 06:03:43','1390','2025-11-06 01:37:32',NULL),(50,'Test new system','Descriptionsdasd','Human Resource','1','1390','1392','1.0',NULL,NULL,'2025-11-28',NULL,'1390','2025-11-03 07:50:21','1705','2025-11-07 02:36:58',NULL),(53,'TEST_TICKET_DEADLINE_ONLY','Test project - only ticket deadline matters','MIS','4',NULL,NULL,NULL,NULL,NULL,'2025-11-07',NULL,'SYSTEM','2025-11-06 05:54:35','System','2025-11-06 05:55:05',NULL),(54,'Test new system drawer','dasdsdsasadsa','MIS','1',NULL,'1328',NULL,NULL,NULL,'2026-11-01',NULL,'1705','2025-11-07 02:44:35','1705','2025-11-07 02:44:35',NULL),(55,'Production System','Test Production details','MIS','1','751',NULL,'1.0',NULL,NULL,NULL,NULL,'751','2025-11-11 02:24:59','751','2025-11-11 02:24:59',NULL),(56,'gfgdfggfddfg','vbvcbcvbcvbcvbcvbvc','Human Resource','1','1390',NULL,'1.0',NULL,NULL,NULL,NULL,'1390','2025-11-13 23:17:45','1390','2025-11-13 23:17:45',NULL);
/*!40000 ALTER TABLE `project_list` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `project_logs`
--

DROP TABLE IF EXISTS `project_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `project_logs` (
  `ID` int NOT NULL AUTO_INCREMENT,
  `PROJ_ID` varchar(100) DEFAULT NULL,
  `ACTION_TYPE` varchar(100) DEFAULT NULL,
  `DESCRIPTION` longtext,
  `PROJECT_VERSION` varchar(100) DEFAULT NULL,
  `PROJ_STATUS` varchar(45) DEFAULT NULL,
  `ASSIGNED_PROGS` varchar(500) DEFAULT NULL,
  `REQUEST_TYPE` int DEFAULT NULL COMMENT 'Type of request from ticket',
  `TICKET_ID` varchar(100) DEFAULT NULL COMMENT 'Ticket ID that triggered this action',
  `ACTION_BY` varchar(100) DEFAULT NULL,
  `UPDATE_AT` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`ID`)
) ENGINE=InnoDB AUTO_INCREMENT=28 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `project_logs`
--

LOCK TABLES `project_logs` WRITE;
/*!40000 ALTER TABLE `project_logs` DISABLE KEYS */;
INSERT INTO `project_logs` VALUES (1,'44','CREATED','Project created from ticket','1.0','1',NULL,1,'TKT-2025-001','1284','2025-10-29 23:41:04'),(2,'44','DH_APPROVED','Project status updated to Ready','1.0','2',NULL,1,'TKT-2025-001','524','2025-10-29 23:42:38'),(3,'44','OD_APPROVED','Project status updated to Ready','1.0','2',NULL,1,'TKT-2025-001','1432','2025-10-29 23:42:59'),(4,'44','ASSIGNED','Project status updated to In Progress','1.0','3','1705',1,'TKT-2025-001','751','2025-10-29 23:43:26'),(5,'44','DEPLOYED','Project status updated to Deployed - All tickets closed','1.0','5',NULL,1,'TKT-2025-001','1284','2025-10-29 23:57:58'),(6,'1','ON_HOLD','Project status updated to On Hold','1.0','3',NULL,NULL,'TSK-007','1705','2025-11-03 03:08:27'),(7,'44','ASSIGNED','Project status updated to In Progress','1.0','3',NULL,NULL,'TSK-008','1705','2025-11-03 03:17:52'),(8,'7','DEPLOYED','Project status updated to Deployed','1.0','5',NULL,NULL,'TSK-006','1705','2025-11-03 03:27:20'),(9,'7','ASSIGNED','Project status updated to In Progress','1.0','3',NULL,NULL,'TSK-009','1390','2025-11-03 05:43:20'),(10,'7','ASSIGNED','Project status updated to In Progress','1.0','3',NULL,NULL,'TSK-009','1705','2025-11-03 05:44:56'),(11,'7','ASSIGNED','Project status updated to In Progress','1.0','3',NULL,NULL,'TSK-010','1705','2025-11-03 05:46:40'),(12,'7','DEPLOYED','Project status updated to Deployed','1.0','5',NULL,NULL,'TSK-010','1705','2025-11-03 05:50:18'),(13,'7','ASSIGNED','Project status updated to In Progress','1.0','3',NULL,NULL,'TSK-011','1705','2025-11-03 05:51:41'),(14,'7','DEPLOYED','Project status updated to Deployed','1.0','5',NULL,NULL,'TSK-011','1705','2025-11-03 05:52:24'),(15,'7','ASSIGNED','Project status updated to In Progress','1.0','3',NULL,NULL,'TSK-012','1705','2025-11-03 06:00:33'),(16,'49','CREATED','Project created from ticket','1.0','1',NULL,1,'TKT-2025-002','1390','2025-11-03 06:03:44'),(17,'50','CREATED','Project created from ticket','1.0','1',NULL,1,'TKT-2025-003','1390','2025-11-03 07:50:21'),(18,'44','UPDATED','Project details updated','1.0','3',NULL,NULL,NULL,'1390','2025-11-06 01:22:13'),(19,'50','UPDATED','Project details updated','1.0','1',NULL,NULL,NULL,'1390','2025-11-06 01:27:08'),(20,'49','UPDATED','Project details updated','1.0','1',NULL,NULL,NULL,'1390','2025-11-06 01:28:52'),(21,'49','UPDATED','Project details updated','1.0','1',NULL,NULL,NULL,'1390','2025-11-06 01:36:42'),(22,'49','UPDATED','Project details updated','1.0','1',NULL,NULL,NULL,'1390','2025-11-06 01:37:32'),(23,'53','ON_HOLD','Project status updated to On Hold','1.0','4',NULL,5,'TEST_OVERDUE_TICKET_','System','2025-11-06 05:55:05'),(24,'50','UPDATED','Project details updated','1.0','1',NULL,NULL,NULL,'1705','2025-11-07 02:36:58'),(25,'54','CREATED','Project created','1.0','1',NULL,NULL,NULL,'1705','2025-11-07 02:44:35'),(26,'55','CREATED','Project created from ticket','1.0','1',NULL,1,'TKT-2025-009','751','2025-11-11 02:24:59'),(27,'56','CREATED','Project created from ticket','1.0','1',NULL,1,'TKT-2025-011','1390','2025-11-13 23:17:45');
/*!40000 ALTER TABLE `project_logs` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-01-08  8:05:40

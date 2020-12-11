/***************************
4107056019 廖柏丞 第7次作業 12/9
4107056019 Bocheng Liao The 7th Homework 12/9
***************************/

-- phpMyAdmin SQL Dump
-- version 5.0.2
-- https://www.phpmyadmin.net/
--
-- 主機： 127.0.0.1
-- 產生時間： 2020-12-05 06:05:08
-- 伺服器版本： 10.4.13-MariaDB
-- PHP 版本： 7.4.8

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- 資料庫： `major`
--
CREATE DATABASE IF NOT EXISTS `major` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `major`;

-- --------------------------------------------------------

--
-- 資料表結構 `health`
--

CREATE TABLE `health` (
  `studentID` text NOT NULL,
  `gender` enum('male','female') NOT NULL,
  `birthdate` date NOT NULL,
  `height` float NOT NULL,
  `weight` float NOT NULL,
  `vision_of_left_eye` float NOT NULL,
  `vision_of_right_eye` float NOT NULL,
  `waistline` float NOT NULL,
  `scoliosis` tinyint(1) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- 傾印資料表的資料 `health`
--

INSERT INTO `health` (`studentID`, `gender`, `birthdate`, `height`, `weight`, `vision_of_left_eye`, `vision_of_right_eye`, `waistline`, `scoliosis`) VALUES
('0', 'female', '1999-07-09', 1.66, 70, 0.5, 0, 60.4, 0),
('1', 'male', '1999-02-18', 1.75, 82, 1, 0.6, 71.3, 1),
('2', 'male', '2000-10-24', 1.73, 51, 0.9, 0.5, 65.3, 0),
('3', 'female', '2000-05-16', 1.7, 71, 1.1, 0.8, 56.3, 0),
('4', 'male', '2000-06-21', 1.79, 83, 0.5, 0.4, 67.2, 1),
('5', 'female', '2000-10-06', 1.76, 42, 0.1, 0, 55.6, 0),
('6', 'male', '2000-06-27', 1.59, 52, 0.5, 0.1, 73.1, 0),
('7', 'male', '2000-02-22', 1.64, 64, 1, 0.6, 76.4, 1),
('8', 'male', '2000-10-28', 1.69, 41, 1, 0.6, 75.6, 0),
('9', 'female', '1999-05-17', 1.51, 53, 0.3, 0, 71.5, 1);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

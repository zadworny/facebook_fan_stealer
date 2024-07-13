-- phpMyAdmin SQL Dump
-- version 4.0.10deb1
-- http://www.phpmyadmin.net
--
-- Host: localhost
-- Generation Time: Apr 10, 2015 at 05:07 PM
-- Server version: 5.5.41-0ubuntu0.14.04.1
-- PHP Version: 5.5.9-1ubuntu4.7

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

--
-- Database: `fanstealer`
--

-- --------------------------------------------------------

--
-- Table structure for table `posts`
--

CREATE TABLE IF NOT EXISTS `posts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `post_id` varchar(200) NOT NULL,
  `task_id` int(11) NOT NULL,
  `done` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `task_id` (`task_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `tasks`
--

CREATE TABLE IF NOT EXISTS `tasks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `page_id` varchar(100) NOT NULL,
  `fb_user_id` varchar(100) NOT NULL,
  `interval_from` datetime NOT NULL,
  `interval_to` datetime NOT NULL,
  `access_token` text NOT NULL,
  `app_id` varchar(255) DEFAULT NULL,
  `secret` varchar(255) DEFAULT NULL,
  `posts_count` int(11) NOT NULL DEFAULT '0',
  `posts_processed_count` int(11) NOT NULL DEFAULT '0',
  `emails_count` int(11) NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL,
  `wait_until` datetime NOT NULL,
  `status` int(11) NOT NULL DEFAULT '0',
  `pid` int(11) NOT NULL DEFAULT '-1',
  PRIMARY KEY (`id`),
  KEY `fb_user_id` (`fb_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `tasks_done`
--

CREATE TABLE IF NOT EXISTS `tasks_done` (
  `id` int(11) NOT NULL,
  `page_id` varchar(100) NOT NULL,
  `fb_user_id` varchar(100) NOT NULL,
  `interval_from` datetime NOT NULL,
  `interval_to` datetime NOT NULL,
  `emails_count` int(11) NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL,
  `finished_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `fb_user_id` (`fb_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

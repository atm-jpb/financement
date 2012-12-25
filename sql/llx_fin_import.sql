-- phpMyAdmin SQL Dump
-- version 3.4.10.1deb1
-- http://www.phpmyadmin.net
--
-- Client: localhost
-- Généré le : Mar 25 Décembre 2012 à 13:11
-- Version du serveur: 5.5.28
-- Version de PHP: 5.3.10-1ubuntu3.4

SET SQL_MODE="NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;

--
-- Base de données: `cpro_dolibarr`
--

-- --------------------------------------------------------

--
-- Structure de la table `llx_fin_import`
--

CREATE TABLE IF NOT EXISTS `llx_fin_import` (
  `rowid` int(11) NOT NULL,
  `entity` int(11) NOT NULL,
  `fk_user_author` int(11) NOT NULL,
  `type_import` varchar(32) NOT NULL,
  `date` datetime NOT NULL,
  `filename` varchar(255) NOT NULL,
  `nb_lines` int(11) NOT NULL,
  `nb_errors` int(11) NOT NULL,
  PRIMARY KEY (`rowid`),
  KEY `entity` (`entity`),
  KEY `fk_user_author` (`fk_user_author`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

-- phpMyAdmin SQL Dump
-- version 3.4.10.1deb1
-- http://www.phpmyadmin.net
--
-- Client: localhost
-- Généré le : Mar 25 Décembre 2012 à 12:52
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
-- Structure de la table `llx_fin_score`
--

CREATE TABLE IF NOT EXISTS `llx_fin_score` (
  `rowid` int(11) NOT NULL AUTO_INCREMENT,
  `fk_soc` int(11) NOT NULL,
  `score` int(11) NOT NULL,
  `encours_max` float NOT NULL,
  `date` datetime NOT NULL,
  `fk_import` int(11) DEFAULT '0',
  `fk_user_author` int(11) NOT NULL,
  PRIMARY KEY (`rowid`),
  KEY `fk_soc` (`fk_soc`),
  KEY `fk_import` (`fk_import`),
  KEY `fk_user_author` (`fk_user_author`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=2 ;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

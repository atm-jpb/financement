-- phpMyAdmin SQL Dump
-- version 3.4.10.1deb1
-- http://www.phpmyadmin.net
--
-- Client: localhost
-- Généré le : Mar 25 Décembre 2012 à 13:10
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
-- Structure de la table `llx_fin_grille_leaser`
--

CREATE TABLE IF NOT EXISTS `llx_fin_grille_leaser` (
  `rowid` int(11) NOT NULL AUTO_INCREMENT,
  `fk_soc` int(11) NOT NULL DEFAULT '0',
  `fk_type_contrat` int(11) NOT NULL DEFAULT '0',
  `montant` float NOT NULL DEFAULT '0',
  `periode` int(11) NOT NULL DEFAULT '0',
  `coeff` float NOT NULL DEFAULT '0',
  `fk_user` int(11) NOT NULL,
  `tms` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`rowid`),
  KEY `fk_soc` (`fk_soc`),
  KEY `fk_type_contrat` (`fk_type_contrat`),
  KEY `coeff` (`coeff`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COMMENT='utf8_general_ci' AUTO_INCREMENT=6 ;

--
-- Contenu de la table `llx_fin_grille_leaser`
--

INSERT INTO `llx_fin_grille_leaser` (`rowid`, `fk_soc`, `fk_type_contrat`, `montant`, `periode`, `coeff`, `fk_user`, `tms`) VALUES
(2, 1, 1, 10000, 8, 3.3, 1, '2012-12-25 12:09:21'),
(3, 1, 1, 20000, 8, 3.42, 1, '2012-12-25 12:04:44'),
(4, 1, 1, 10000, 12, 3.4, 1, '2012-12-25 12:04:44'),
(5, 1, 1, 20000, 12, 3.48, 1, '2012-12-25 12:04:44');

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
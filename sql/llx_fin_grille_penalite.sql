-- phpMyAdmin SQL Dump
-- version 3.4.10.1deb1
-- http://www.phpmyadmin.net
--
-- Client: localhost
-- Généré le : Mar 25 Décembre 2012 à 13:20
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
-- Structure de la table `llx_fin_grille_penalite`
--

CREATE TABLE IF NOT EXISTS `llx_fin_grille_penalite` (
  `rowid` int(11) NOT NULL AUTO_INCREMENT,
  `opt_group` varchar(32) NOT NULL,
  `opt_name` varchar(32) NOT NULL,
  `opt_value` float NOT NULL,
  PRIMARY KEY (`rowid`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=10 ;

--
-- Contenu de la table `llx_fin_grille_penalite`
--

INSERT INTO `llx_fin_grille_penalite` (`rowid`, `opt_group`, `opt_name`, `opt_value`) VALUES
(1, 'opt_periodicite', 'opt_trimestriel', 0),
(2, 'opt_periodicite', 'opt_mensuel', 5),
(3, 'opt_mode_reglement', 'opt_prelevement', 0),
(4, 'opt_mode_reglement', 'opt_virement', 3),
(5, 'opt_mode_reglement', 'opt_cheque', 8),
(6, 'opt_terme', 'opt_a_echoir', 0),
(7, 'opt_terme', 'opt_echu', 4),
(8, 'opt_administration', 'opt_administration', 4.5),
(9, 'opt_creditbail', 'opt_creditbail', 6);

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

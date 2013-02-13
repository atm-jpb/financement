-- phpMyAdmin SQL Dump
-- version 3.4.11.1deb1
-- http://www.phpmyadmin.net
--
-- Client: localhost
-- Généré le: Mer 13 Février 2013 à 16:23
-- Version du serveur: 5.5.29
-- Version de PHP: 5.4.6-1ubuntu1.1

SET SQL_MODE="NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

--
-- Base de données: `cpro_dolibarr`
--

-- --------------------------------------------------------

--
-- Structure de la table `llx_fin_grille_penalite`
--

CREATE TABLE IF NOT EXISTS `llx_fin_grille_penalite` (
  `rowid` int(11) NOT NULL AUTO_INCREMENT,
  `opt_name` varchar(32) NOT NULL,
  `opt_value` varchar(32) NOT NULL,
  `penalite` float NOT NULL,
  PRIMARY KEY (`rowid`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=10 ;

--
-- Contenu de la table `llx_fin_grille_penalite`
--

INSERT INTO `llx_fin_grille_penalite` (`rowid`, `opt_name`, `opt_value`, `penalite`) VALUES
(1, 'opt_periodicite', 'TRIMESTRE', 0),
(2, 'opt_periodicite', 'MOIS', 5),
(3, 'opt_mode_reglement', 'PRE', 0),
(4, 'opt_mode_reglement', 'VIR', 3),
(5, 'opt_mode_reglement', 'oCHQ', 8),
(6, 'opt_terme', '1', 0),
(7, 'opt_terme', '0', 4),
(8, 'opt_administration', '1', 4.5),
(9, 'opt_creditbail', '1', 6);


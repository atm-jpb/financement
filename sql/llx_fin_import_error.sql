-- phpMyAdmin SQL Dump
-- version 3.4.11.1deb1
-- http://www.phpmyadmin.net
--
-- Client: localhost
-- Généré le: Jeu 31 Janvier 2013 à 11:19
-- Version du serveur: 5.5.29
-- Version de PHP: 5.4.6-1ubuntu1.1

SET SQL_MODE="NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

--
-- Base de données: `cpro_dolibarr`
--

-- --------------------------------------------------------

--
-- Structure de la table `llx_fin_import_error`
--

CREATE TABLE IF NOT EXISTS `llx_fin_import_error` (
  `rowid` int(11) NOT NULL AUTO_INCREMENT,
  `fk_import` int(11) NOT NULL,
  `num_line` int(11) NOT NULL,
  `content_line` longtext NOT NULL,
  `error_msg` varchar(255) NOT NULL,
  `error_data` varchar(255) NOT NULL,
  `sql_executed` longtext NOT NULL,
  `type_erreur` varchar(32) NOT NULL,
  `sql_errno` varchar(255) DEFAULT NULL,
  `sql_error` longtext,
  PRIMARY KEY (`rowid`),
  KEY `fk_import` (`fk_import`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8;


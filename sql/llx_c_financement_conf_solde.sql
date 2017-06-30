CREATE TABLE `llx_c_financement_conf_solde` (
  `rowid` int(11) NOT NULL,
  `fk_nature` varchar(255) DEFAULT NULL,
  `fk_type_contrat` varchar(255) NOT NULL,
  `periode` int(11) NOT NULL,
  `base_solde` varchar(20) NOT NULL,
  `percent` double(24,8) NOT NULL DEFAULT '0.00000000',
  `percent_nr` double(24,8) NOT NULL DEFAULT '0.00000000',
  `entity` int(11) NOT NULL DEFAULT '1',
  `active` tinyint(4) NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
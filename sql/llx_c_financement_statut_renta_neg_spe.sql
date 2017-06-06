CREATE TABLE llx_c_financement_statut_renta_neg_spe
(
  rowid             integer NOT NULL AUTO_INCREMENT PRIMARY KEY,
  code         		varchar(20)  NOT NULL,
  label				varchar(80)	 NOT NULL,
  entity            integer NOT NULL DEFAULT 1,
  active			tinyint	DEFAULT 1  NOT NULL		
)ENGINE=innodb;

ALTER TABLE llx_c_financement_statut_renta_neg_spe ADD INDEX uk_c_financement_statut_renta_neg_spe_id (code);
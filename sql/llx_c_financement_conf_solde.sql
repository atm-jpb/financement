CREATE TABLE llx_c_financement_conf_solde
(
  rowid             integer NOT NULL AUTO_INCREMENT PRIMARY KEY,
  fk_type_contrat   varchar(255) NOT NULL,
  periode           integer NOT NULL,
  base_solde        varchar(20) NOT NULL,
  percent           double(24,8) NOT NULL DEFAULT 0,
  percent_nr        double(24,8) NOT NULL DEFAULT 0,
  entity            integer NOT NULL DEFAULT 1,
  active            tinyint NOT NULL DEFAULT 1		
)ENGINE=innodb;

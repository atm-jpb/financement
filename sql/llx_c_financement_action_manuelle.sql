CREATE TABLE if not exists llx_c_financement_action_manuelle
(
  rowid   integer NOT NULL AUTO_INCREMENT PRIMARY KEY,
  code    varchar(20) NOT NULL UNIQUE,
  label   varchar(80)	NOT NULL,
  entity  integer NOT NULL DEFAULT 1,
  active  integer	NOT NULL DEFAULT 1
)ENGINE=innodb;

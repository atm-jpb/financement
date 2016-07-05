CREATE TABLE IF NOT EXISTS llx_c_financement_categorie_bien (
  rowid integer NOT NULL auto_increment PRIMARY KEY,
  entity integer NOT NULL DEFAULT 1,
  cat_id integer NOT NULL UNIQUE,
  label varchar(100) DEFAULT NULL,
  active integer
) ENGINE=InnoDB;
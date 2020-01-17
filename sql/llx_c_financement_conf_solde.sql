create table `llx_c_financement_conf_solde` (
    `rowid`            int(11)       not null,
    `fk_nature`        varchar(255)           default null,
    `fk_type_contrat`  varchar(255)  not null,
    `periode`          int(11)       not null,
    `date_application` date                   default null,
    `base_solde`       varchar(20)   not null,
    `percent`          double(24, 8) not null default '0.00000000',
    amount             double(24, 8) not null default 0,
    `percent_nr`       double(24, 8) not null default '0.00000000',
    `entity`           int(11)       not null default '1',
    `active`           tinyint(4)    not null default '1'
) engine = InnoDB
  default charset = utf8;
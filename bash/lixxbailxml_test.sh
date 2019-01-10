echo 'cd IN \n put '$1'\n' > /var/www/dolibarr-fin/documents/financement/XML/Lixxbail/lixcmd.txt

sftp -b /var/www/dolibarr-fin/documents/financement/XML/Lixxbail/lixcmd.txt cpro_rec@host_rec@test.b2b.eurofactor.com

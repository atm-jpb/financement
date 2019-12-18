echo 'cd in \n put '$1'\n' > /var/www/dolibarr-fin/documents/financement/XML/BNP/bnpcmd.txt

sftp -b /var/www/dolibarr-fin/documents/financement/XML/BNP/bnpcmd.txt -P 6710 G04920QY@159.50.103.15

echo 'cd IN \n put '$1'\n' > /var/www/dolibarr-fin/htdocs/custom/financement/bash/lixcmd.txt

sftp -b /var/www/dolibarr-fin/htdocs/custom/financement/bash/lixcmd.txt cpro@b2b.eurofactor.com

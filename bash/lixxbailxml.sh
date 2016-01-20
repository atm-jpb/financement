echo 'cd OUT \n put '$1'\n' > /var/www/dolibarr-fin/htdocs/custom/financement/bash/fuckingcmd.txt

sftp -b /var/www/dolibarr-fin/htdocs/custom/financement/bash/fuckingcmd.txt cpro@b2b.eurofactor.com

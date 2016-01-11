echo 'cd IN \n put '$1'\n' > /var/www/dolibarr-fin/htdocs/custom/financement/bash/fuckingcmd.txt

sftp -b fuckingcmd.txt cpro@b2b.eurofactor.com

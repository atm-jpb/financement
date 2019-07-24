echo 'put '$1'\n' > /var/www/dolibarr-fin/documents/financement/XML/CMCIC/cmcic.txt

sftp -b /var/www/dolibarr-fin/documents/financement/XML/CMCIC/cmcic.txt -P 6332 CPROVAL@GTW-EI.hd.e-i.com

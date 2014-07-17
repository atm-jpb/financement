#!/bin/bash
if test -f $1; then
	sftp cpro@b2b.eurofector.com
	cd IN
	put $1
	bye
else
echo "****** ERROR : nom de fichier icorrect *****"
fi

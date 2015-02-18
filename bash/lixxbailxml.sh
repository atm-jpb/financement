echo 'cd IN \n put '$1'\n' > fuckingcmd.txt

sftp -b fuckingcmd.txt cpro@b2b.eurofactor.com

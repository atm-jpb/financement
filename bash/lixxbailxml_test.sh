echo $'cd IN \n put '$1$'\n' > fuckingcmd.txt

sftp -b fuckingcmd.txt cpro_rec@host_rec@test.b2b.eurofactor.com

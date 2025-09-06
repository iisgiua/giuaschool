#!/bin/bash

# terminazione immediata sugli errori
set -e

# avvia MySql in background
service mariadb start

# imposta configurazione per sistemi ARM
echo 'Mutex posixsem' >> /etc/apache2/apache2.conf

# avvia Apache in foreground
exec /usr/sbin/apache2ctl -D FOREGROUND

#!/bin/bash

# Start MySql service in background
service mariadb start

# Adjust mutex configuration and start Apache service in foreground
echo 'Mutex posixsem' >> /etc/apache2/apache2.conf
/usr/sbin/apache2ctl -D FOREGROUND

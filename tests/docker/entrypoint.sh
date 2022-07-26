#!/bin/sh

# Start MySql service in background
service mariadb start

# Start Apache service in foreground
/usr/sbin/apache2ctl -D FOREGROUND

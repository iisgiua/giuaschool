#!/bin/sh

# Start Mysql service in background
service mysql start

# Start Apache service in foreground
/usr/sbin/apache2ctl -D FOREGROUND


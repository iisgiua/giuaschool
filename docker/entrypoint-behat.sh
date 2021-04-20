#!/bin/sh

# Start Mysql service in background
service mysql start

# Start Apache service in foreground
/usr/sbin/apache2ctl -D FOREGROUND

# Run Unit tests
php -d memory_limit=-1 vendor/bin/behat || exit 1

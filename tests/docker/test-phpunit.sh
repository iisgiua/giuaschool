#!/bin/bash

# Start services
service mariadb start
service apache2 start
dbus-daemon --config-file=/usr/share/dbus-1/system.conf --print-address

# Make sure test sessions directory exists
mkdir -p var/sessions/test

# Run only phpunit tests
php -d memory_limit=-1 bin/phpunit --coverage-clover clover.xml

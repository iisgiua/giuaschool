#!/bin/sh

# Start services
service mariadb start
service apache2 start
dbus-daemon --config-file=/usr/share/dbus-1/system.conf --print-address

# Run only phpunit tests
php -d memory_limit=-1 bin/phpunit --coverage-clover clover.xml

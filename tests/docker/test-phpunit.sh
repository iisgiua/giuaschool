#!/bin/bash

# terminazione immediata sugli errori
set -e

# avvia servizi
service mariadb start
service apache2 start
dbus-daemon --config-file=/usr/share/dbus-1/system.conf --print-address
php bin/consonle cache:warmup

# Resegue test PHPUnit
php -d memory_limit=-1 vendor/bin/phpunit --coverage-clover clover.xml

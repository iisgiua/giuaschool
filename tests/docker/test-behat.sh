#!/bin/bash

# terminazione immediata sugli errori
set -e

# avvia servizi
service mariadb start
service apache2 start
dbus-daemon --config-file=/usr/share/dbus-1/system.conf --print-address
google-chrome --headless --disable-gpu --disable-software-rasterizer --disable-dev-shm-usage --no-sandbox --remote-debugging-address=0.0.0.0 --remote-debugging-port=9222 --window-size=1920,1080 --ignore-certificate-errors 2> /dev/null &
php bin/consonle cache:warmup

# esegue test Behat
php -d memory_limit=-1 vendor/bin/behat $1 --stop-on-failure -f progress

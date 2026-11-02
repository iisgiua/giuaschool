#!/bin/bash

# terminazione immediata sugli errori
set -e

# disabilita Xdebug e la copertura codice: non servono per i test Behat
export XDEBUG_MODE=off
for ini in /etc/php/*/apache2/conf.d/99-giuaschool-test.ini; do
  [ -f "$ini" ] && sed -i 's/^pcov\.enabled = 1/pcov.enabled = 0/' "$ini"
done

# avvia servizi
service mariadb start
service apache2 start
dbus-daemon --config-file=/usr/share/dbus-1/system.conf --print-address
google-chrome --headless=new --disable-gpu --disable-software-rasterizer --disable-dev-shm-usage --no-sandbox --remote-debugging-address=0.0.0.0 --remote-debugging-port=9222 --window-size=1920,1080 --ignore-certificate-errors 2> /dev/null &
php -d pcov.enabled=0 -d xdebug.mode=off bin/console cache:clear -q

# esegue test Behat
php -d memory_limit=-1 -d pcov.enabled=0 -d xdebug.mode=off vendor/bin/behat "$@" --stop-on-failure -f progress

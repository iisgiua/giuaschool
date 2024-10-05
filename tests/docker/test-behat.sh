#!/bin/bash

# Start services
service mariadb start
service apache2 start
dbus-daemon --config-file=/usr/share/dbus-1/system.conf --print-address
google-chrome --headless --disable-gpu --disable-software-rasterizer --disable-dev-shm-usage --no-sandbox --remote-debugging-address=0.0.0.0 --remote-debugging-port=9222 --window-size=1920,1080 --ignore-certificate-errors 2> /dev/null &

# Make sure test sessions directory exists
mkdir -p var/sessions/test

# Run only Behat tests
php -d memory_limit=-1 vendor/bin/behat $1 --stop-on-failure --rerun -f progress

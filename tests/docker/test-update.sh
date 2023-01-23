#!/bin/bash

# start services
service mariadb start
service apache2 start
dbus-daemon --config-file=/usr/share/dbus-1/system.conf --print-address
google-chrome --headless --disable-gpu --disable-software-rasterizer --disable-dev-shm-usage --no-sandbox --remote-debugging-address=0.0.0.0 --remote-debugging-port=9222 --window-size=1920,1080 --ignore-certificate-errors 2> /dev/null &

# test step 1: unzip files
su -s /bin/bash -p -c "php -d memory_limit=-1 vendor/bin/behat tests/features/test-update-1.feature --stop-on-failure -f progress" www-data
retval=$?
if [ $retval -ne 0 ]; then
  exit 1
fi
composer -q install --no-progress --no-scripts
rm -r var/cache/*
rm -r tests/temp/*

# test other steps
su -s /bin/bash -p -c "php -d memory_limit=-1 vendor/bin/behat tests/features/test-update-2.feature --stop-on-failure -f progress" www-data
retval=$?
if [ $retval -ne 0 ]; then
  exit 1
fi
rm -r tests/temp/*

# smoke test
su -s /bin/bash -p -c "php -d memory_limit=-1 vendor/bin/behat tests/features/test-update-3.feature --stop-on-failure -f progress" www-data

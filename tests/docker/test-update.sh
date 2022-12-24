#!/bin/bash

# Start services
service mariadb start
service apache2 start
dbus-daemon --config-file=/usr/share/dbus-1/system.conf --print-address
google-chrome --headless --disable-gpu --disable-software-rasterizer --disable-dev-shm-usage --no-sandbox --remote-debugging-address=0.0.0.0 --remote-debugging-port=9222 --window-size=1920,1080 --ignore-certificate-errors 2> /dev/null &

# Set environment for testing
cd src/Install
wget https://github.com/iisgiua/giuaschool/releases/download/update-v$1/giuaschool-update-v$1.zip
cd ../..
unzip -qo src/Install/giuaschool-update-v$1.zip src/Install/* 2> /dev/null
unzip -qo src/Install/giuaschool-update-v$1.zip public/install/* 2> /dev/null
wget -O tests/Behat/BaseContext.php https://github.com/iisgiua/giuaschool/raw/master/tests/Behat/BaseContext.php
wget -O tests/Behat/BrowserContext.php https://github.com/iisgiua/giuaschool/raw/master/tests/Behat/BrowserContext.php
wget -O tests/features/test-update-1.feature https://github.com/iisgiua/giuaschool/raw/master/tests/docker/test-update-1.feature
wget -O tests/features/test-update-2.feature https://github.com/iisgiua/giuaschool/raw/master/tests/docker/test-update-2.feature
wget -O tests/features/Url.feature https://github.com/iisgiua/giuaschool/raw/master/tests/features/Url.feature
chown -R www-data:www-data src/* tests/*
echo "token='test'" > .gs-updating
echo "version='$1-build'" >> .gs-updating

# Test step 1: unzip files
php -d memory_limit=-1 vendor/bin/behat tests/features/test-update-1.feature --stop-on-failure -f progress
retval=$?
if [ $retval -ne 0 ]; then
  exit 1
fi
composer -q install --no-progress --no-scripts

# Test other steps
php -d memory_limit=-1 vendor/bin/behat tests/features/test-update-2.feature --stop-on-failure -f progress
retval=$?
if [ $retval -ne 0 ]; then
  exit 1
fi

# Smoke test
php bin/console app:alice:load tests/features/TestFixtures.yml --dump tests/temp/TestFixtures
php -d memory_limit=-1 vendor/bin/behat tests/features/Url.feature --stop-on-failure -f progress

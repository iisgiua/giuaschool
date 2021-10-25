#!/bin/sh

# Start services
service mysql start
service apache2 start

# Run Behat tests
while [ true ] ; do echo ; df -h ; free -h ; ps aux ; echo "---------" ; loop &
exec php -d memory_limit=-1 vendor/bin/behat --stop-on-failure -f progress

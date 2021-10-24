#!/bin/sh

# Start services
service mysql start
service apache2 start

# Run Unit tests
free -h
swapon --show
free -h -s 30 &
exec php -d memory_limit=-1 bin/phpunit --coverage-clover clover.xml

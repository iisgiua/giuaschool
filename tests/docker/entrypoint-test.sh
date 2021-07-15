#!/bin/sh

# Start services
service mysql start
service apache2 start

# Run Unit tests
php -d memory_limit=-1 bin/phpunit --coverage-clover clover.xml || exit 1

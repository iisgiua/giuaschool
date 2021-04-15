#!/bin/sh

# Start services
service mysql start
service apache2 start

# Run Unit tests
php -d memory_limit=-1 vendor/bin/simple-phpunit --coverage-clover clover.xml tests/DatabaseTestCase.php || exit 1

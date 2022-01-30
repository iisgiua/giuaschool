#!/bin/sh

# Start services
service mariadb start
service apache2 start

# Run Unit tests
exec php -d memory_limit=-1 bin/phpunit --coverage-clover clover.xml

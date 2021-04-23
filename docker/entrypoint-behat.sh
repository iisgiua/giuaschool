#!/bin/sh

# Start services
service mysql start
service apache2 start

# Run Behat tests
php -d memory_limit=-1 vendor/bin/behat -f progress -o behat.txt || exit 1

ls -lar /var/www/giuaschool/tests/data

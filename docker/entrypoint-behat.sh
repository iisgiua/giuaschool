#!/bin/sh

# Start services
service mysql start
service apache2 start

# Run Behat tests
php -d memory_limit=-1 vendor/bin/behat || exit 1

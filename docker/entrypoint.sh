#!/bin/sh

### Start services
#service apache2 start
service mysql start

### Configure Apache

### Configure PHP

### Configure Mysql
mysqladmin -u root password root
php bin/console doctrine:database:create -e dev
php bin/console doctrine:schema:update -f -e dev

exit 0

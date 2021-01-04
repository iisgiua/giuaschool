#!/bin/sh

### Start services
#service apache2 start
service mysql start

### Configure Apache

### Configure PHP

### Configure Mysql
mysql -u root -e "ALTER USER 'root'@'localhost' IDENTIFIED WITH mysql_native_password BY 'root';"

php bin/console doctrine:database:create -e dev
php bin/console doctrine:schema:update -f -e dev

exit 0

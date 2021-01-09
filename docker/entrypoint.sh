#!/bin/sh

### Configure Apache
a2enmod rewrite
sed -r -i -e "s/^;date\.timezone\s*=.*$/date.timezone = Europe\/Rome/" /etc/php/7.4/apache2/php.ini
sed -r -i -e "s/;opcache.enable=1.*$/opcache.enable=1/" /etc/php/7.4/apache2/php.ini
sed -r -i -e "s/^;?date\.timezone\s*=.*$/date.timezone = Europe\/Rome/" /etc/php/7.4/cli/php.ini



### Start services
service apache2 start
#service mysql start


### Configure PHP

### Configure Mysql
#mysql -u root -e "ALTER USER 'root'@'localhost' IDENTIFIED BY 'root';"

#php bin/console doctrine:database:create -e dev
#php bin/console doctrine:schema:update -f -e dev

exit 0

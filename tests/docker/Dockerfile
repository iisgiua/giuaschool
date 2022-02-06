########## First Step - Build system
FROM debian:11.2 as systembuilder

### System and build environments
ARG DEBIAN_FRONTEND=noninteractive
ENV LANGUAGE="it_IT.UTF-8"
ENV LANG="it_IT.UTF-8"
ENV LC_ALL="it_IT.UTF-8"
ENV COMPOSER_ALLOW_SUPERUSER="1"

### Install system
RUN \
# Source repositories
  echo "deb http://deb.debian.org/debian bullseye main contrib non-free" > /etc/apt/sources.list && \
  echo "deb http://deb.debian.org/debian bullseye-updates main contrib non-free" >> /etc/apt/sources.list && \
  echo "deb http://security.debian.org/debian-security bullseye-security main contrib non-free" >> /etc/apt/sources.list && \
# Set locale
  apt-get -qq update && \
  apt-get -yqq --no-install-recommends --no-install-suggests install locales && \
  sed -i -e "s/# $LANG/$LANG/" /etc/locale.gen && \
  echo "LANGUAGE=$LANG" > /etc/default/locale && \
  echo "LANG=$LANG" >> /etc/default/locale && \
  echo "LC_ALL=$LANG" >> /etc/default/locale && \
  dpkg-reconfigure --frontend=noninteractive locales && \
  update-locale LANG=$LANG && \
  ln -sf /usr/share/zoneinfo/Europe/Rome /etc/localtime && \
# Install dev tools
  apt-get -yqq --no-install-recommends --no-install-suggests install \
  curl wget ca-certificates apt-utils debconf-utils lsb-release zip unzip git \
# Install Apache and MariaDB
  apache2=2.4.* \
  mariadb-common=1:10.5.* mariadb-server=1:10.5.* mariadb-client=1:10.5.* \
# Install PHP 7.4
  php php-curl php-gd php-intl php-mbstring php-mysql php-xml php-zip \
# Install external dependencies: libreoffice, unoconv and PDF utils
  libreoffice-nogui unoconv poppler-utils && \
  apt-get clean && \
  rm -rf /var/lib/apt/lists/* && \
# Install Composer
  php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" && \
  HASH="$(wget -q -O - https://composer.github.io/installer.sig)" && \
  php -r "if (hash_file('SHA384', 'composer-setup.php') === '$HASH') { echo 'Composer installer verified'; } else { echo 'Composer installer corrupt'; unlink('composer-setup.php'); } echo PHP_EOL;" && \
  php composer-setup.php --install-dir=/usr/local/bin --filename=composer && \
  rm composer-setup.php && \
# Configure Apache/PHP
  a2enmod rewrite && \
  a2enmod ssl && \
  sed -r -i -e 's/^;date\.timezone *=.*$/date.timezone = Europe\/Rome/' /etc/php/7.4/apache2/php.ini && \
  sed -r -i -e 's/;opcache.enable=1.*$/opcache.enable=1/' /etc/php/7.4/apache2/php.ini && \
  sed -r -i -e 's/^;date\.timezone *=.*$/date.timezone = Europe\/Rome/' /etc/php/7.4/cli/php.ini && \
  rm -f /etc/apache2/sites-available/default-ssl.conf
COPY ./tests/docker/apache2-certificate.crt /etc/ssl/cert/apache2-certificate.crt
COPY ./tests/docker/apache2-certificate.key /etc/ssl/private/apache2-certificate.key
COPY ./tests/docker/apache2-site.conf /etc/apache2/sites-available/000-default.conf
COPY ./tests/docker/apache2-mpm.conf /etc/apache2/mods-available/mpm_prefork.conf


########## Second Step - Build Symfony framework
FROM systembuilder as symfonybuilder

### Install Symfony
WORKDIR /var/www/giuaschool
COPY --chown=www-data:www-data composer.* symfony.lock ./
RUN \
  composer -q install --no-dev --no-progress --prefer-dist --no-suggest --optimize-autoloader --no-plugins --no-scripts && \
  composer clear-cache


########## Third Step - Build Application
FROM symfonybuilder as applicationbuilder

### Configure application
WORKDIR /var/www/giuaschool
COPY --chown=www-data:www-data . .
RUN \
# adjust scripts permissions
  chmod 755 tests/docker/*.sh && \
# set PROD environment
  sed -r -i -e "s/^APP_ENV\s*=.*$/APP_ENV=prod/" .env && \
# create database
  service mariadb start && mysqladmin -u root password 'root' && \
  php bin/console doctrine:database:create -n -q && \
  php bin/console doctrine:schema:update -f -q && \
# init database
  mysql -uroot -proot giuaschool < src/Install/init-db.sql && \
  php bin/console security:encode-password -n admin App\\Entity\\Amministratore 2> /dev/null | grep "Encoded password" | sed -r -e "s/^\s*Encoded password\s+(\S+)\s*$/UPDATE gs_utente SET password='\1' where username='admin';/" > admin-pswd && \
  mysql -uroot -proot giuaschool < admin-pswd && \
  rm -f admin-pswd && \
# configure SPID access
  cp tests/docker/spid-php-openssl.cnf.conf vendor/italia/spid-php/spid-php-openssl.cnf && \
  cp tests/docker/spid-php-setup.json.conf vendor/italia/spid-php/spid-php-setup.json && \
  cp tests/docker/spid-config.tpl.conf vendor/italia/spid-php/setup/config/config.tpl && \
  cd vendor/italia/spid-php/setup && \
  cd .. && \
  composer install && \
  composer clear-cache && \
  cd vendor/simplesamlphp/simplesamlphp/modules && \
  tar -zxf ../../../../../../../tests/docker/spid-theme.tgz && \
  cd /var/www/giuaschool/public && \
  rm -f spid && \
  ln -s -r ../vendor/italia/spid-php/vendor/simplesamlphp/simplesamlphp/www/ spid && \
  cd ../var/log && \
  ln -s -r ../../vendor/italia/spid-php/vendor/simplesamlphp/simplesamlphp/log/ spid && \
  cd ../.. && \
# adjust file permissions
  chown -R www-data:www-data .


### Configure services
EXPOSE 443
CMD tests/docker/entrypoint.sh

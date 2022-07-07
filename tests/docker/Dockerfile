########## First Step - Build system
FROM debian:11.2 as system_builder

### System and build environments
ARG DEBIAN_FRONTEND=noninteractive
ENV COMPOSER_ALLOW_SUPERUSER="1"

### Install system
RUN \
# Source repositories
  echo "deb http://deb.debian.org/debian bullseye main contrib non-free" > /etc/apt/sources.list && \
  echo "deb http://deb.debian.org/debian bullseye-updates main contrib non-free" >> /etc/apt/sources.list && \
  echo "deb http://security.debian.org/debian-security bullseye-security main contrib non-free" >> /etc/apt/sources.list && \
# Init APT
  apt-get -qq update && \
  apt-get -yqq --no-install-recommends --no-install-suggests install apt-utils && \
# Set locale
  apt-get -yqq --no-install-recommends --no-install-suggests install locales && \
  sed -i -e "s/# it_IT.UTF-8/it_IT.UTF-8/" /etc/locale.gen && \
  echo "LANGUAGE=it_IT.UTF-8" > /etc/default/locale && \
  echo "LANG=it_IT.UTF-8" >> /etc/default/locale && \
  echo "LC_ALL=it_IT.UTF-8" >> /etc/default/locale && \
  dpkg-reconfigure --frontend=noninteractive locales && \
  update-locale LANG=it_IT.UTF-8 && \
  ln -sf /usr/share/zoneinfo/Europe/Rome /etc/localtime && \
# Install dev tools
  apt-get -yqq --no-install-recommends --no-install-suggests install \
  curl wget ca-certificates debconf-utils lsb-release zip unzip git \
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
FROM system_builder as symfony_builder

### Install Symfony
WORKDIR /var/www/giuaschool
COPY --chown=www-data:www-data composer.* symfony.lock ./
RUN \
  composer -q install --no-dev --no-progress --prefer-dist --optimize-autoloader --no-plugins --no-scripts && \
  composer -q clear-cache --no-plugins


########## Third Step - Build Application for PROD environment
FROM symfony_builder as application_prod

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
  mysql -uroot -proot --default-character-set=utf8 giuaschool < src/Install/init-db.sql && \
  php bin/console security:encode-password -n admin App\\Entity\\Amministratore 2> /dev/null | grep "Encoded password" | sed -r -e "s/^\s*Encoded password\s+(\S+)\s*$/UPDATE gs_utente SET password='\1' where username='admin';/" > admin-pswd && \
  mysql -uroot -proot giuaschool < admin-pswd && \
  rm -f admin-pswd && \
# install SPID library
  mkdir vendor/italia && \
  cd vendor/italia && \
  tar -zxf ../../tests/docker/spid-php.tgz && \
  cd spid-php && \
  composer -q install --no-dev --no-progress --prefer-dist --no-plugins --no-scripts && \
  composer -q clear-cache --no-plugins && \
  cd vendor/simplesamlphp/simplesamlphp/modules && \
  tar -zxf ../../../../../../../tests/docker/spid-theme.tgz && \
  cd .. && \
  mkdir log cert && \
  cp ../../.gitkeep log/ && \
  cp ../../.gitkeep cert/ && \
  cd /var/www/giuaschool && \
# adjust file permissions
  chown -R www-data:www-data .

### Configure services
EXPOSE 443
CMD tests/docker/entrypoint.sh


########## Fourth Step - Build Application for DEV environment
FROM application_prod as application_dev

### Build environment
ARG DEBIAN_FRONTEND=noninteractive

### Configure application
WORKDIR /var/www/giuaschool
RUN \
# Set DEV environment
  sed -r -i -e "s/^APP_ENV\s*=.*$/APP_ENV=dev/" .env && \
  composer -q install --no-progress --prefer-dist --no-suggest --optimize-autoloader --no-plugins --no-scripts && \
  composer clear-cache && \
  chown -R www-data:www-data .


########## Fifth Step - Build Application for TEST environment
FROM application_dev as application_test

### Build environment
ARG DEBIAN_FRONTEND=noninteractive

### Configure application
WORKDIR /var/www/giuaschool
RUN \
# Install chrome headless
  wget -qO - https://dl.google.com/linux/linux_signing_key.pub | gpg --dearmor -o /usr/share/keyrings/googlechrome-linux-keyring.gpg && \
  echo "deb [arch=amd64 signed-by=/usr/share/keyrings/googlechrome-linux-keyring.gpg] http://dl.google.com/linux/chrome/deb/ stable main" | tee /etc/apt/sources.list.d/google-chrome.list && \
  apt-get -qq update && \
  apt-get -yqq --no-install-recommends --no-install-suggests install google-chrome-stable && \
  dbus-uuidgen > /var/lib/dbus/machine-id && \
  mkdir -p /var/run/dbus && \
# Install xdebug
  apt-get -yqq --no-install-recommends --no-install-suggests install php-xdebug && \
  echo "xdebug.mode=coverage" >> /etc/php/7.4/mods-available/xdebug.ini && \
  apt-get clean && \
  rm -rf /var/lib/apt/lists/* && \
# Set TEST environment
  sed -r -i -e "s/^APP_ENV\s*=.*$/APP_ENV=test/" .env && \
  service mariadb start && \
  php bin/console doctrine:database:drop -f -q && \
  php bin/console doctrine:database:create -n -q && \
  php bin/console doctrine:schema:update -f -q && \
  php bin/console doctrine:fixtures:load -n -q --group=Test && \
  mysqldump -uroot -proot giuaschool -t -n --compact --result-file='tests/temp/Test.fixtures' && \
  chown -R www-data:www-data .

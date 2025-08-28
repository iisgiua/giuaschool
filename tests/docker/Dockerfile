########## First Step - Build system
FROM debian:12.6 AS system_builder

### System and build environments
ARG DEBIAN_FRONTEND=noninteractive
ENV COMPOSER_ALLOW_SUPERUSER="1"

### Install system
RUN \
# Source repositories
  rm /etc/apt/sources.list.d/debian.sources && \
  echo "deb http://deb.debian.org/debian bookworm main contrib non-free-firmware" > /etc/apt/sources.list && \
  echo "deb http://deb.debian.org/debian bookworm-updates main contrib non-free-firmware" >> /etc/apt/sources.list && \
  echo "deb http://security.debian.org/debian-security bookworm-security main contrib non-free-firmware" >> /etc/apt/sources.list && \
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
  mariadb-common=1:10.11.* mariadb-server=1:10.11.* mariadb-client=1:10.11.* \
# Install PHP 8.2
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
# Configure Apache/PHP/MySQL
  a2enmod rewrite && \
  a2enmod ssl && \
  sed -r -i -e 's/^;date\.timezone *=.*$/date.timezone = Europe\/Rome/' /etc/php/8.2/apache2/php.ini && \
  sed -r -i -e 's/^;date\.timezone *=.*$/date.timezone = Europe\/Rome/' /etc/php/8.2/cli/php.ini && \
  sed -r -i -e 's/;opcache.enable=1.*$/opcache.enable=1/' /etc/php/8.2/apache2/php.ini && \
  rm -f /etc/apache2/sites-available/default-ssl.conf
COPY ./tests/docker/apache2-certificate.crt /etc/ssl/cert/apache2-certificate.crt
COPY ./tests/docker/apache2-certificate.key /etc/ssl/private/apache2-certificate.key
COPY ./tests/docker/apache2-site.conf /etc/apache2/sites-available/000-default.conf
COPY ./tests/docker/apache2-mpm.conf /etc/apache2/mods-available/mpm_prefork.conf
COPY ./tests/docker/50-client.cnf /etc/mysql/mariadb.conf.d/50-client.cnf


########## Second Step - Build Symfony framework
FROM system_builder AS symfony_builder

### Install Symfony
WORKDIR /var/www/giuaschool
COPY --chown=www-data:www-data composer.* symfony.lock ./
RUN \
  composer -q install --no-progress --no-scripts && \
  composer -q clear-cache


########## Third Step - Build Application for DEV environment
FROM symfony_builder AS application_dev

### Build environment
ARG DEBIAN_FRONTEND=noninteractive

### Configure application
WORKDIR /var/www/giuaschool
COPY --chown=www-data:www-data . .
RUN \
# adjust scripts permissions
  chmod 755 tests/docker/*.sh && \
# create database
  service mariadb start && mysqladmin -u root password 'root' && \
  php bin/console doctrine:database:create -n -q && \
  php bin/console doctrine:schema:update -f -q && \
  php bin/console doctrine:schema:create --dump-sql > src/Install/create-db.sql && \
  php bin/console doctrine:schema:drop --dump-sql > src/Install/drop-db.sql && \
# init database
  php bin/console app:alice:load _giuaschool --dump src/Install/init-db && \
  # # install SPID library
  # mkdir vendor/italia && \
  # cd vendor/italia && \
  # tar -zxf ../../tests/docker/spid-php.tgz && \
  # cd spid-php && \
  # composer -q install --no-dev --no-progress --no-scripts && \
  # composer -q clear-cache && \
  # rm -r vendor/italia/spid-smart-button/.git && \
  # rm -r vendor/italia/spid-sp-access-button/.git && \
  # cd vendor/simplesamlphp/simplesamlphp/modules && \
  # tar -zxf /var/www/giuaschool/tests/docker/spid-theme.tgz && \
  # cd .. && \
  # mkdir log cert && \
  # cp ../../.gitkeep log/ && \
  # cp ../../.gitkeep cert/ && \
  # cd /var/www/giuaschool && \
# adjust file permissions
  chown -R www-data:www-data .

### Configure services
EXPOSE 443
CMD tests/docker/entrypoint.sh


########## Fourth Step - Build Application for PROD environment
FROM application_dev AS application_prod

### Configure application
WORKDIR /var/www/giuaschool
RUN \
# set PROD environment
  sed -r -i -e "s/^APP_ENV\s*=.*$/APP_ENV=prod/" .env && \
# remove DEV dependencies
  composer install -q --no-dev --no-scripts && \
  composer -q clear-cache && \
# remove unused files
  rm -f -r src/Command/AliceLoadCommand.php src/DataFixtures/ src/Install/init-db.map && \
  rm -f -r tests/*.php tests/Behat/ tests/data/ tests/features/ tests/temp/ tests/UnitTest/ && \
  rm -f -r var/cache/* var/log/* var/sessions/* && \
  rm -f .dockerignore .env.test .gitignore behat.yml phpunit.xml publiccode.yml rector.php && \
# adjust file permissions
  chown -R www-data:www-data .

### Configure services
EXPOSE 443
CMD tests/docker/entrypoint.sh


########## Fifth Step - Build Application for TEST environment
FROM application_dev AS application_test

### Build environment
ARG DEBIAN_FRONTEND=noninteractive

### Configure application
WORKDIR /var/www/giuaschool
RUN \
# Install chrome headless
  wget -q -O - https://dl.google.com/linux/linux_signing_key.pub | gpg --dearmor -o /usr/share/keyrings/google-chrome.gpg && \
  echo "deb [arch=amd64 signed-by=/usr/share/keyrings/google-chrome.gpg] http://dl.google.com/linux/chrome/deb/ stable main" | tee /etc/apt/sources.list.d/google-chrome.list && \
  apt-get -qq update && \
  apt-get -yqq --no-install-recommends --no-install-suggests install google-chrome-stable && \
  mkdir -p /var/lib/dbus && \
  mkdir -p /var/run/dbus && \
  dbus-uuidgen > /var/lib/dbus/machine-id && \
# Install xdebug
  apt-get -yqq --no-install-recommends --no-install-suggests install php-xdebug && \
  echo "xdebug.mode=coverage" >> /etc/php/8.2/mods-available/xdebug.ini && \
  apt-get clean && \
  rm -rf /var/lib/apt/lists/* && \
# Set TEST environment
  sed -r -i -e "s/^APP_ENV\s*=.*$/APP_ENV=test/" .env && \
  mkdir -p var/sessions/test && \
  service mariadb start && \
  php bin/console app:alice:load tests/features/_testFixtures.yml --dump tests/temp/_testFixtures && \
  chown -R www-data:www-data .

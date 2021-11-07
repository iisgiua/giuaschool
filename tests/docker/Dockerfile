########## First Step - Build system
FROM debian:10.10 as systembuilder

### System and build environments
ARG DEBIAN_FRONTEND=noninteractive
ENV LANG="it_IT.UTF-8"
ENV LANGUAGE="it_IT.UTF-8"
ENV LC_ALL="it_IT.UTF-8"
ENV COMPOSER_ALLOW_SUPERUSER="1"

### Install system
RUN \
# Source repositories
  echo "deb http://deb.debian.org/debian/ buster main contrib non-free" > /etc/apt/sources.list && \
  echo "deb http://deb.debian.org/debian-security buster/updates main contrib non-free" >> /etc/apt/sources.list && \
# Set locale
  apt-get -qq update && \
  apt-get -yqq --no-install-recommends --no-install-suggests install locales && \
  sed -i -e "s/# $LANG/$LANG/" /etc/locale.gen && \
  echo "LANG=$LANG">/etc/default/locale && \
  echo "LC_ALL=$LANG">>/etc/default/locale && \
  dpkg-reconfigure --frontend=noninteractive locales && \
  update-locale LANG=$LANG && \
  ln -sf /usr/share/zoneinfo/Europe/Rome /etc/localtime && \
# Install dev tools
  apt-get -yqq --no-install-recommends --no-install-suggests install \
  curl wget ca-certificates apt-utils debconf-utils lsb-release zip unzip git \
# Install Apache and MariaDB
  apache2=2.4.* \
  mariadb-common=1:10.3.* mariadb-server=1:10.3.* mariadb-client=1:10.3.* && \
# Install PHP 7.4
  wget -O /etc/apt/trusted.gpg.d/php.gpg https://packages.sury.org/php/apt.gpg && \
  echo "deb https://packages.sury.org/php/ $(lsb_release -sc) main" > /etc/apt/sources.list.d/php.list && \
  apt-get -qq update && \
  apt-get -yqq --no-install-recommends --no-install-suggests install \
  php7.4 php7.4-curl php7.4-gd php7.4-intl php7.4-mbstring php7.4-mysql php7.4-readline php7.4-xml php7.4-zip && \
# Install external dependencies: libreoffice, unoconv and PDF utils
  apt-get -yqq --no-install-recommends --no-install-suggests install \
  libreoffice unoconv poppler-utils && \
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
  sed -r -i -e 's/^;date\.timezone *=.*$/date.timezone = Europe\/Rome/' /etc/php/7.4/apache2/php.ini && \
  sed -r -i -e 's/;opcache.enable=1.*$/opcache.enable=1/' /etc/php/7.4/apache2/php.ini && \
  sed -r -i -e 's/^;date\.timezone *=.*$/date.timezone = Europe\/Rome/' /etc/php/7.4/cli/php.ini
COPY ./tests/docker/apache2-site.conf /etc/apache2/sites-available/000-default.conf
COPY ./tests/docker/apache2-mpm.conf /etc/apache2/mods-available/mpm_prefork.conf


########## Second Step - Build Symfony framework
FROM systembuilder as symfonybuilder

### Install Symfony
WORKDIR /var/www/giuaschool
COPY --chown=www-data:www-data composer.* symfony.lock ./
RUN \
  composer -q install --no-progress --prefer-dist --no-suggest --optimize-autoloader --no-plugins --no-scripts && \
  composer clear-cache


########## Third Step - Build Application
FROM symfonybuilder as applicationbuilder

### Configure application
WORKDIR /var/www/giuaschool
COPY --chown=www-data:www-data . .
RUN \
  sed -r -i -e "s/^APP_ENV\s*=.*$/APP_ENV=prod/" .env && \
  service mysql start && mysqladmin -u root password 'root' && \
  php bin/console doctrine:database:create -n -q && \
  php bin/console doctrine:schema:update -f -q && \
  php bin/console doctrine:fixtures:load --group=App -n -q -e dev && \
  chown -R www-data:www-data . && \
  chmod 755 tests/docker/*.sh


### Configure services
EXPOSE 80
CMD tests/docker/entrypoint.sh

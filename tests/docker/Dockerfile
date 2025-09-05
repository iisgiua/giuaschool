########## Base Step - Imposta sistema operativo, tool e servizi
FROM debian:12.11 AS base

# variabili di ambiente
ARG DEBIAN_FRONTEND=noninteractive
ENV COMPOSER_ALLOW_SUPERUSER=1
ENV LANG=it_IT.UTF-8
ENV LANGUAGE=it_IT:it
ENV LC_ALL=it_IT.UTF-8
ENV TZ=Europe/Rome

# installa pacchetti base e configurazione sistema
RUN apt-get -qq update && \
    apt-get -yqq --no-install-recommends install \
      # installa data/ora locale
      locales tzdata \
      # installa tool
      curl wget ca-certificates lsb-release git unzip zip \
      # installa Apache 2.4.*
      apache2 \
      # installa MariaDB 10.11.*
      mariadb-server mariadb-client \
      # installa PHP 8.2
      php php-curl php-gd php-intl php-mbstring php-mysql php-xml php-zip \
      # installa LibreOffice e tool PDF
      libreoffice-nogui unoconv poppler-utils && \
    # pulizia APT e file inutili
    apt-get clean && \
    rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/* /usr/share/doc/* /usr/share/man/* && \
    # configura data/ora locale
    echo "it_IT.UTF-8 UTF-8" > /etc/locale.gen && \
    locale-gen && \
    ln -fs /usr/share/zoneinfo/$TZ /etc/localtime && \
    dpkg-reconfigure -f noninteractive tzdata && \
    # configura PHP
    sed -ri 's/^;date\.timezone *=.*$/date.timezone = Europe\/Rome/' /etc/php/8.2/apache2/php.ini && \
    sed -ri 's/^;date\.timezone *=.*$/date.timezone = Europe\/Rome/' /etc/php/8.2/cli/php.ini && \
    sed -ri 's/;opcache.enable=1.*$/opcache.enable=1/' /etc/php/8.2/apache2/php.ini && \
    # configura Apache
    a2enmod rewrite ssl && \
    rm -f /etc/apache2/sites-available/default-ssl.conf

# copia file di configurazione Apache e MariaDB
COPY ./tests/docker/apache2-certificate.crt /etc/ssl/cert/apache2-certificate.crt
COPY ./tests/docker/apache2-certificate.key /etc/ssl/private/apache2-certificate.key
COPY ./tests/docker/apache2-site.conf /etc/apache2/sites-available/000-default.conf
COPY ./tests/docker/apache2-mpm.conf /etc/apache2/mods-available/mpm_prefork.conf
COPY ./tests/docker/50-client.cnf /etc/mysql/mariadb.conf.d/50-client.cnf


########## Composer Step - Installa e configura Composer
FROM base AS composer_builder

# installa Composer (verificato)
RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" && \
    HASH="$(wget -q -O - https://composer.github.io/installer.sig)" && \
    php -r "if (hash_file('SHA384', 'composer-setup.php') !== '$HASH') exit(1);" && \
    php composer-setup.php --install-dir=/usr/local/bin --filename=composer && \
    rm composer-setup.php


########## Database Step - Configura Symfony e il database iniziale
FROM composer_builder AS database_builder

WORKDIR /var/www/giuaschool
COPY --chown=www-data:www-data ./ ./

# installa ambiente di sviluppo e inizializza il database
RUN composer install -q --no-scripts --no-progress && \
    composer -q clear-cache && \
    # avvia servizio MariaDB
    service mariadb start && \
    # crea database
    mysqladmin -uroot password 'root' && \
    php bin/console doctrine:database:create -n -q && \
    php bin/console doctrine:schema:update -f -q && \
    php bin/console doctrine:schema:create --dump-sql > src/Install/create-db.sql && \
    php bin/console doctrine:schema:drop --dump-sql > src/Install/drop-db.sql && \
    # dati iniziali
    php bin/console app:alice:load _giuaschool --dump src/Install/init-db && \
    cp -pr /var/lib/mysql tests/temp/ && \
    php bin/console app:alice:load tests/features/_testFixtures.yml --dump tests/temp/_testFixtures && \
    # ferma servizio MariaDB
    mysqladmin -uroot -proot shutdown && \
    # pulizia file inutili
    rm -rf /tmp/* /var/tmp/* /usr/share/doc/* /usr/share/man/* var/log/* src/Install/init-db.map


########## Development Step - Installa ambiente di sviluppo
FROM database_builder AS dev

WORKDIR /var/www/giuaschool

# installa ambiente di sviluppo
RUN mkdir -p var/cache/dev var/sessions/dev && \
    # pulizia file inutili
    rm -rf var/log/* tests/temp/mysql && \
    # imposta permessi sui file
    chown -R www-data:www-data . && \
    chmod 755 tests/docker/*.sh

EXPOSE 443
ENTRYPOINT ["tests/docker/entrypoint.sh"]
CMD []
LABEL org.opencontainers.image.title="giua@school - Ambiente di sviluppo"
LABEL org.opencontainers.image.documentation="https://iisgiua.github.io/giuaschool-docs/"
LABEL org.opencontainers.image.source="https://github.com/iisgiua/giuaschool"


########## Test Step - Installa ambiente di test
FROM dev AS test

WORKDIR /var/www/giuaschool

# Installazione Chrome Headless + Xdebug
RUN wget -q -O - https://dl.google.com/linux/linux_signing_key.pub | gpg --dearmor -o /usr/share/keyrings/google-chrome.gpg && \
    echo "deb [arch=amd64 signed-by=/usr/share/keyrings/google-chrome.gpg] http://dl.google.com/linux/chrome/deb/ stable main" > /etc/apt/sources.list.d/google-chrome.list && \
    apt-get -qq update && \
    apt-get -yqq --no-install-recommends install google-chrome-stable php-xdebug && \
    # configurazione Chrome Headless + Xdebug
    echo "xdebug.mode=coverage" >> /etc/php/8.2/mods-available/xdebug.ini && \
    mkdir -p /var/lib/dbus /var/run/dbus && \
    dbus-uuidgen > /var/lib/dbus/machine-id && \
    # imposta ambiente di test
    sed -ri "s/^APP_ENV\s*=.*$/APP_ENV=test/" .env && \
    # crea directory di ambiente
    mkdir -p var/cache/test var/sessions/test && \
    # pulizia APT e file inutili
    apt-get clean && \
    rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/* /usr/share/doc/* /usr/share/man/* var/log/* var/cache/dev var/sessions/dev && \
    # imposta permessi sui file
    chown -R www-data:www-data .

EXPOSE 443
ENTRYPOINT ["tests/docker/entrypoint.sh"]
CMD []
LABEL org.opencontainers.image.title="giua@school - Ambiente di test"
LABEL org.opencontainers.image.documentation="https://iisgiua.github.io/giuaschool-docs/"
LABEL org.opencontainers.image.source="https://github.com/iisgiua/giuaschool"


########## Production Step - Installa ambiente di produzione
FROM composer_builder AS prod

WORKDIR /var/www/giuaschool
COPY --chown=www-data:www-data ./ ./
COPY --from=database_builder /var/www/giuaschool/tests/temp/mysql/ /var/lib/mysql/

# installa ambiente di produzione
RUN composer install -q --no-dev --no-scripts && \
    composer -q clear-cache && \
    # imposta ambiente di produzione
    sed -ri "s/^APP_ENV\s*=.*$/APP_ENV=prod/" .env && \
    # crea directory di ambiente
    mkdir -p var/cache/prod var/sessions/prod && \
    # pulizia file inutili
    rm -rf /tmp/* /var/tmp/* /usr/share/doc/* /usr/share/man/* var/log/* && \
    rm -r src/Command/AliceLoadCommand.php  && \
    rm -rf src/DataFixtures tests/*.php tests/Behat tests/data tests/features tests/temp tests/UnitTest && \
    rm -f .dockerignore .env.test .gitignore behat.yml phpunit.xml publiccode.yml rector.php && \
    # imposta permessi sui file
    chown -R www-data:www-data .

EXPOSE 443
ENTRYPOINT ["tests/docker/entrypoint.sh"]
CMD []
LABEL org.opencontainers.image.title="giua@school - Ambiente di produzione"
LABEL org.opencontainers.image.documentation="https://iisgiua.github.io/giuaschool-docs/"
LABEL org.opencontainers.image.source="https://github.com/iisgiua/giuaschool"

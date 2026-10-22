# syntax=docker/dockerfile:1
########## Base Step - Imposta sistema operativo, tool e servizi
FROM debian:12 AS base

# variabili di ambiente
ARG DEBIAN_FRONTEND=noninteractive
ENV COMPOSER_ALLOW_SUPERUSER=1
ENV LANG=it_IT.UTF-8
ENV LANGUAGE=it_IT:it
ENV LC_ALL=it_IT.UTF-8
ENV TZ=Europe/Rome

# installa pacchetti base e configurazione sistema
# (le cache di APT restano nel cache mount BuildKit, non nell'immagine)
RUN --mount=type=cache,target=/var/cache/apt,sharing=locked \
    --mount=type=cache,target=/var/lib/apt,sharing=locked \
    apt-get -qq update && \
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
    # pulizia dei file inutili
    rm -rf /tmp/* /var/tmp/* /usr/share/doc/* /usr/share/man/* && \
    # configura data/ora locale
    echo "it_IT.UTF-8 UTF-8" > /etc/locale.gen && \
    locale-gen && \
    ln -fs /usr/share/zoneinfo/$TZ /etc/localtime && \
    dpkg-reconfigure -f noninteractive tzdata && \
    # configura PHP (la versione viene rilevata, non codificata)
    PHPVER="$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')" && \
    for sapi in apache2 cli; do \
      printf '%s\n' \
        'date.timezone = Europe/Rome' \
        'expose_php = Off' \
        'opcache.enable = 1' \
        'opcache.enable_cli = 1' \
        'opcache.validate_timestamps = 1' \
        'realpath_cache_size = 4096k' \
        'realpath_cache_ttl = 600' \
        > "/etc/php/$PHPVER/$sapi/conf.d/99-giuaschool.ini"; \
    done && \
    # configura Apache
    a2enmod rewrite ssl && \
    rm -f /etc/apache2/sites-available/default-ssl.conf

# copia file di configurazione Apache e MariaDB
COPY ./tests/docker/apache2-certificate.crt /etc/ssl/cert/apache2-certificate.crt
COPY ./tests/docker/apache2-certificate.key /etc/ssl/private/apache2-certificate.key
COPY ./tests/docker/apache2-site.conf /etc/apache2/sites-available/000-default.conf
COPY ./tests/docker/apache2-mpm.conf /etc/apache2/mods-available/mpm_prefork.conf
COPY ./tests/docker/50-client.cnf /etc/mysql/mariadb.conf.d/50-client.cnf

# limita i permessi della chiave privata del certificato
RUN chmod 600 /etc/ssl/private/apache2-certificate.key


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

# tuning MariaDB per gli ambienti di sviluppo e test (non incluso in produzione)
COPY ./tests/docker/99-giuaschool-test.cnf /etc/mysql/mariadb.conf.d/99-giuaschool-test.cnf

COPY --chown=www-data:www-data ./ ./

# installa ambiente di sviluppo e inizializza il database
RUN --mount=type=cache,target=/composer-cache \
    COMPOSER_CACHE_DIR=/composer-cache composer install -q --no-scripts --no-progress && \
    # configurazione locale dell'immagine: stessi valori dell'ambiente di test
    # (il dump delle fixture contiene note_bes cifrate con APP_SECRET)
    grep -hE '^(DATABASE_URL|APP_SECRET)=' .env.test > .env.local && \
    test -s .env.local && \
    chmod 600 .env.local && \
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
    # imposta permessi sui file creati come root (senza riscrivere l'intero albero)
    chmod 755 tests/docker/*.sh && \
    find . -user root -exec chown www-data:www-data {} +

EXPOSE 443
ENTRYPOINT ["tests/docker/entrypoint.sh"]
CMD []
LABEL org.opencontainers.image.title="giua@school - Ambiente di sviluppo"
LABEL org.opencontainers.image.documentation="https://iisgiua.github.io/giuaschool-docs/"
LABEL org.opencontainers.image.source="https://github.com/iisgiua/giuaschool"


########## Test Step - Installa ambiente di test
FROM dev AS test

# il frontend noninteractive non si eredita tra gli stage
ARG DEBIAN_FRONTEND=noninteractive

# l'ambiente di test carica .env.example e .env.test (non .env.local)
ENV APP_ENV=test
ENV APP_DEBUG=1

WORKDIR /var/www/giuaschool

# Installazione Chrome Headless + Xdebug + PCOV
RUN --mount=type=cache,target=/var/cache/apt,sharing=locked \
    --mount=type=cache,target=/var/lib/apt,sharing=locked \
    wget -q -O - https://dl.google.com/linux/linux_signing_key.pub | gpg --dearmor -o /usr/share/keyrings/google-chrome.gpg && \
    echo "deb [arch=amd64 signed-by=/usr/share/keyrings/google-chrome.gpg] http://dl.google.com/linux/chrome/deb/ stable main" > /etc/apt/sources.list.d/google-chrome.list && \
    apt-get -qq update && \
    apt-get -yqq --no-install-recommends install google-chrome-stable php-xdebug php-pcov && \
    # configurazione Chrome Headless + coverage: PCOV è il driver predefinito,
    # Xdebug resta installato e si attiva con XDEBUG_MODE=debug
    PHPVER="$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')" && \
    for sapi in apache2 cli; do \
      printf '%s\n' \
        'pcov.enabled = 1' \
        'xdebug.mode = off' \
        > "/etc/php/$PHPVER/$sapi/conf.d/99-giuaschool-test.ini"; \
    done && \
    mkdir -p /var/lib/dbus /var/run/dbus && \
    dbus-uuidgen > /var/lib/dbus/machine-id && \
    # crea directory di ambiente
    mkdir -p var/cache/test var/sessions/test && \
    # crea directory di download
    mkdir -p tests/temp/download && \
    chmod 777 tests/temp/download && \
    # pulizia dei file inutili
    rm -rf /tmp/* /var/tmp/* /usr/share/doc/* /usr/share/man/* var/log/* var/cache/dev var/sessions/dev && \
    # imposta permessi sui file creati come root (senza riscrivere l'intero albero)
    find . -user root -exec chown www-data:www-data {} +

EXPOSE 443
ENTRYPOINT ["tests/docker/entrypoint.sh"]
CMD []
LABEL org.opencontainers.image.title="giua@school - Ambiente di test"
LABEL org.opencontainers.image.documentation="https://iisgiua.github.io/giuaschool-docs/"
LABEL org.opencontainers.image.source="https://github.com/iisgiua/giuaschool"


########## Production Step - Installa ambiente di produzione
FROM composer_builder AS prod

# l'ambiente di produzione usa .env.example come modello e .env.local per i valori della macchina
ENV APP_ENV=prod
ENV APP_DEBUG=0

WORKDIR /var/www/giuaschool
COPY --chown=www-data:www-data ./ ./
COPY --from=database_builder /var/www/giuaschool/tests/temp/mysql/ /var/lib/mysql/

# installa ambiente di produzione
RUN --mount=type=cache,target=/composer-cache \
    COMPOSER_CACHE_DIR=/composer-cache composer install -q --no-dev --no-scripts && \
    rm -f src/Command/AliceLoadCommand.php  && \
    # crea directory di ambiente
    mkdir -p var/cache/prod var/sessions/prod && \
    # pulizia file inutili
    rm -rf /tmp/* /var/tmp/* /usr/share/doc/* /usr/share/man/* var/log/* && \
    rm -rf src/DataFixtures tests/*.php tests/Behat tests/data tests/features tests/temp tests/UnitTest && \
    rm -f .dockerignore .env .env.local .env.test .env-dist .gitignore behat.yml phpunit.xml publiccode.yml rector.php && \
    # imposta permessi sui file creati come root (senza riscrivere l'intero albero)
    find . -user root -exec chown www-data:www-data {} +

EXPOSE 443
ENTRYPOINT ["tests/docker/entrypoint.sh"]
CMD []
LABEL org.opencontainers.image.title="giua@school - Ambiente di produzione"
LABEL org.opencontainers.image.documentation="https://iisgiua.github.io/giuaschool-docs/"
LABEL org.opencontainers.image.source="https://github.com/iisgiua/giuaschool"

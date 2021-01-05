FROM php:7.4-apache-buster

RUN docker-php-ext-install pdo pdo_mysql mysqli

RUN curl --insecure https://getcomposer.org/composer.phar -o /usr/bin/composer && chmod +x /usr/bin/composer

RUN composer -V

ENV COMPOSER_ALLOW_SUPERUSER 1

RUN composer install --no-dev --prefer-dist --optimize-autoloader

# Set timezone
#RUN rm /etc/localtime
#RUN ln -s /usr/share/zoneinfo/Europe/Berlin /etc/localtime
#RUN "date"

# Install Debian
FROM debian:10.7

# Update APT
RUN apt-get update && apt-get upgrade -y

# Install Apache, MariaDB, PHP, Composer
RUN apt-get install -y \
  apache2 libapache2-mod-php7.4 \
  mariadb-common mariadb-server mariadb-client \
  composer \
  php7.4

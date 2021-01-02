# Install Debian
FROM debian:10.7

# Update APT
RUN apt-get update && apt-get upgrade -y

# Install Apache, MariaDB, Composer
RUN apt-get install -y \
  apache2 \
  mariadb-common mariadb-server mariadb-client \
  composer=1.*

# Test Apache version
RUN apachectl -v; \
  service apache2 status

# Test MariaDB version
RUN mysql -V; \
  service mysql status

# Test Composer version
RUN composer -V

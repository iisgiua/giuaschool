# Install Debian
FROM debian:10.7

# System environments
ENV LOCALE="it_IT.UTF-8"

# Install software
RUN \
# Source repositories
  echo "deb http://deb.debian.org/debian/ buster main contrib non-free" > /etc/apt/sources.list && \
  echo "deb-src http://deb.debian.org/debian/ buster main contrib non-free" >> /etc/apt/sources.list && \
  echo "deb http://deb.debian.org/debian-security buster/updates main contrib non-free" >> /etc/apt/sources.list && \
  echo "deb-src http://deb.debian.org/debian-security buster/updates main contrib non-free" >> /etc/apt/sources.list && \
# Update APT source list
  apt-get update && apt-get upgrade -y && apt-get install -y \
# Install dev tools
  apt-utils build-essential debconf-utils debconf default-mysql-client \
  curl wget unzip rsync git \
  vim nano \
  openssh-client \
  locales && \
# Set locale
  sed -i -e "s/# $LOCALE/$LOCALE/" /etc/locale.gen && \
  echo "LANG=$LOCALE">/etc/default/locale && \
  dpkg-reconfigure --frontend=noninteractive locales && \
  update-locale LANG=$LOCALE


# Install Apache, MariaDB, Composer
RUN  \
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

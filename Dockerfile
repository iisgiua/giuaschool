# Install Debian
FROM debian:10.7

# System environments
ENV LOCALE="it_IT.UTF-8"

# Install software
RUN \
# Source repositories
  echo "deb http://deb.debian.org/debian/ buster main contrib non-free" > /etc/apt/sources.list && \
  echo "deb http://deb.debian.org/debian-security buster/updates main contrib non-free" >> /etc/apt/sources.list && \
# Update APT source list
  apt-get -qq update && apt-get -qqy upgrade && \
# Install dev tools
  apt-get -y install \
  apt-utils build-essential debconf-utils lsb-release \
  curl wget unzip rsync git \
  apt-transport-https openssh-client ca-certificates \
  locales && \
# Set locale
  sed -i -e "s/# $LOCALE/$LOCALE/" /etc/locale.gen && \
  echo "LANG=$LOCALE">/etc/default/locale && \
  dpkg-reconfigure --frontend=noninteractive locales && \
  update-locale LANG=$LOCALE


# Install Apache, MariaDB, Composer
RUN apt-get install -y \
  apache2='2\.4\..*' \
  mariadb-common='10\.3\..*' mariadb-server='10\.3\..*' mariadb-client='10\.3\..*' \
  composer='1\..*'

# Install PHP 7.4
RUN \
  wget -O /etc/apt/trusted.gpg.d/php.gpg https://packages.sury.org/php/apt.gpg && \
  echo "deb https://packages.sury.org/php/ $(lsb_release -sc) main" > /etc/apt/sources.list.d/php.list && \
  apt-get  update && \
  apt-get -y install \
  php7.4


# Check software version
RUN \
  cat /etc/debian_version && \
  cat /etc/os-release && \
  apachectl -v && \
  mysql -V && \
  composer -V && \
  php -v

# Start services
RUN \
  service apache2 start && \
  service mysql start && \
  service apache2 status && \
  service mysql status

#!/bin/bash

# terminazione immediata sugli errori
set -e

# se vengono passati argomenti al container, esegui solo quel comando
if [ $# -gt 0 ]; then
  exec "$@"
fi

# avvia MySql in background
service mariadb start

# avvia Apache in foreground
exec /usr/sbin/apache2ctl -D FOREGROUND

#!/bin/bash

# terminazione immediata sugli errori
set -e

# se vengono passati argomenti al container, esegui solo quel comando
if [ $# -gt 0 ]; then
  exec "$@"
fi

# crea la configurazione locale (credenziali del database e codice segreto)
# se manca: in produzione il segreto è casuale e resta nel container, non nell'immagine
if [ ! -f /var/www/giuaschool/.env.local ]; then
  AMBIENTE="${APP_ENV:-prod}"
  if [ "$AMBIENTE" = "prod" ]; then
    LIVELLO_LOG="warning"
  else
    LIVELLO_LOG="debug"
  fi
  (umask 077; printf "APP_ENV='%s'\nDATABASE_URL='mysql://root:root@localhost:3306/giuaschool'\nAPP_SECRET='%s'\nLOG_LEVEL='%s'\n" \
    "$AMBIENTE" "$(php -r 'echo bin2hex(random_bytes(20));')" "$LIVELLO_LOG" > /var/www/giuaschool/.env.local)
  chmod 600 /var/www/giuaschool/.env.local
  chown www-data:www-data /var/www/giuaschool/.env.local
fi

# avvia MySql in background
service mariadb start

# avvia Apache in foreground
exec /usr/sbin/apache2ctl -D FOREGROUND

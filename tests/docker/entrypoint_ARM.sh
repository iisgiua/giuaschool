#!/bin/bash

# terminazione immediata sugli errori
set -e

# imposta configurazione per sistemi ARM (operazione idempotente)
echo 'Mutex posixsem' > /etc/apache2/conf-available/giuaschool-arm.conf
ln -sf ../conf-available/giuaschool-arm.conf /etc/apache2/conf-enabled/giuaschool-arm.conf

# delega all'entrypoint standard (gestione degli argomenti inclusa)
exec /var/www/giuaschool/tests/docker/entrypoint.sh "$@"

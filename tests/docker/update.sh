#!/bin/bash

# terminazione immediata sugli errori
set -e

# pulizia dei file inutili
rm -f -r bin/
rm -f -r tests/
rm -f -r var/cache/* var/log/* var/sessions/*

# rinomina .env per evitare sia sovrascritto in aggiornamento
mv .env .env-dist

# crea l'archivio con i file modificati e aggiunti
wget -q -P ../ https://github.com/iisgiua/giuaschool/releases/download/$1/giuaschool-release-$1.md5.zip
unzip -q ../giuaschool-release-$1.md5.zip -d ../
find ./ -type f -exec md5sum {} + | sort -k 2 > ../giuaschool-update.md5
diff -n ../giuaschool-release.md5 ../giuaschool-update.md5 | sed -E '/^.{,32}$/d' | sed -E 's/^.*\s+//' > ../lista.txt
cat ../lista.txt | zip -q -9 -@ ../giuaschool-update.zip

#!/bin/bash

# Remove unused files
rm -f -r bin/
rm -f -r tests/
rm -f -r var/cache/* var/log/* var/sessions/*

# Adjust cache and session dirs
mkdir -p var/cache/prod
mkdir -p var/sessions/prod
chown -R www-data:www-data var

# La configurazione della macchina e quella di test non devono finire nell'archivio
rm -f .env .env.local .env.local.php .env.test .env-dist

# Create archive
zip -q -r -9 ../giuaschool-release.zip ./
find ./ -type f -exec md5sum {} + | sort -k 2 > giuaschool-release.md5
zip -q -9 ../giuaschool-release.md5.zip giuaschool-release.md5

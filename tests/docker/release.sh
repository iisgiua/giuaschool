#!/bin/bash

# Remove unused files
rm -f -r bin/
rm -f -r tests/
rm -f -r var/cache/* var/log/* var/sessions/*

# Rename .env to avoid overwriting on update
mv .env .env-dist

# Create archive
zip -q -y -r -9 ../giuaschool-release.zip ./
find ./ -type f -exec md5sum {} + | sort -k 2 > giuaschool-release.md5
zip -q -9 ../giuaschool-release.md5.zip giuaschool-release.md5

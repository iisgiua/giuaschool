#!/bin/bash

# Remove unused files
rm -f -r bin/
rm -f -r tests/
rm -f -r var/cache/* var/log/* var/sessions/*

# Rename .env to avoid overwriting on update
mv .env .env-dist

# Create archive for added/changed files
wget -q -P ../ https://github.com/trinko/giuaschool/releases/download/$1/giuaschool-release-$1.md5.zip
unzip -q ../giuaschool-release-$1.md5.zip -d ../
find ./ -type f -exec md5sum {} + | sort -k 2 > ../giuaschool-update.md5
diff -n ../giuaschool-release.md5 ../giuaschool-update.md5 | sed -E '/^.{,32}$/d' | sed -E 's/^.*\s+//' > ../lista.txt
cat ../lista.txt | zip -q -y -9 -@ ../giuaschool-update.zip

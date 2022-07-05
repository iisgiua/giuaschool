#!/bin/bash

# Remove unused files
rm -f -r bin/
rm -f -r src/DataFixtures/
rm -f -r tests/
rm -f -r var/cache/*
rm -f -r var/log/*
rm -f -r var/sessions/*
rm -f .dockerignore .env.test .gitignore behat.yml composer.* phpunit.xml symfony.lock publiccode.yml
rm -f -r ./*/*/.gitkeep ./*/*/*/.gitkeep

# Rename .env to avoid overwriting on update
mv .env .env-dist

# Create archive for added/changed files
wget -q -P ../ https://github.com/trinko/giuaschool/releases/download/$1/giuaschool-release-$1.md5.zip
unzip -q ../giuaschool-release-$1.md5.zip -d ../
find ./ -type f -exec md5sum {} + | sort -k 2 > ../giuaschool-update.md5
diff -n ../giuaschool-release.md5 ../giuaschool-update.md5 | sed -E '/^.{,32}$/d' | sed -E 's/^.*\s+//' > ../lista.txt
cat ../lista.txt | zip -q -y -9 -@ ../giuaschool-update.zip

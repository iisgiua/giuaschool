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

# Create archive for changed files
DATE="${1:0:10} ${1:11:8}"
find ./ -type f -newermt $DATE | zip -q -y -9 giuaschool-update.zip -@

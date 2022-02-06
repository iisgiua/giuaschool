#!/bin/sh

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

# Archive release
zip -q -y -r -9 giuaschool-release.zip ./

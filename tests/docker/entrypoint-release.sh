#!/bin/sh

# Clear installation files
rm -f -r tests/
rm -f -r var/cache/*
rm -f -r var/sessions/*
rm -f -r var/log/*
rm -f bin/phpunit
rm -f .dockerignore .env.test .gitignore behat.yml composer.* phpunit.xml symfony.lock
rm -f -r ./*/*/.gitkeep ./*/*/*/.gitkeep

# Archive release
zip -v -y -r giuaschool-release.zip ./
#-- tar -zcf giuaschool-release.tgz ./

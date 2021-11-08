#!/bin/sh

# Export database
service mysql start
mysqldump -u root -proot giuaschool > database.sql

# Remove unused files
rm -f -r tests/
rm -f -r var/cache/*
rm -f -r var/sessions/*
rm -f -r var/log/*
rm -f bin/phpunit
rm -f .dockerignore .env.test .gitignore behat.yml composer.* phpunit.xml symfony.lock
rm -f -r ./*/*/.gitkeep ./*/*/*/.gitkeep

# Archive release
zip -q -y -r giuaschool-release.zip ./

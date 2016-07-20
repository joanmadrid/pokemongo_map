#!/bin/sh
sudo chmod 777 -R app/cache
sudo chmod 777 -R app/logs
composer install
php app/console cache:clear --no-warmup
php app/console cache:clear --env=prod --no-warmup
php app/console doc:sc:up --force
php app/console assets:install web
sudo chmod 777 -R app/cache
sudo chmod 777 -R app/logs
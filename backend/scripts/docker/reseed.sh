#!/bin/sh
/usr/bin/composer install
echo $DB_PASSWORD
/wait && ./artisan migrate:fresh --seed -f

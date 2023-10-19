#!/bin/sh
/usr/bin/composer install
/wait && ./artisan migrate:fresh --seed -f

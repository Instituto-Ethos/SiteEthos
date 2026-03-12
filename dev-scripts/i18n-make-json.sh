#!/bin/bash

docker compose exec wordpress bash -c "cd /var/www/html/wp-content/themes/hacklab-theme && wp i18n make-json --no-purge languages/"

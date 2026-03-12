#!/bin/bash

docker compose exec wordpress bash -c "cd /var/www/html/wp-content/themes/hacklab-theme && wp i18n make-pot --exclude=assets,library/blocks/**.js . languages/hacklabr.pot"

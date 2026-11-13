#!/bin/bash

docker compose exec wordpress bash -c "cd /var/www/html/wp-content/themes/SiteEthos && wp i18n make-pot --exclude=assets,library/blocks/**.js . languages/hacklabr.pot"

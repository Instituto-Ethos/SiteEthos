#!/bin/bash
DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
CDIR=$( pwd )
cd $DIR/../themes
ln -s hacklab-theme SiteEthos
zip -r ../zips/SiteEthos.zip SiteEthos -x "SiteEthos/node_modules/*"
rm SiteEthos

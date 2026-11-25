#!/bin/bash
DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
CDIR=$( pwd )
cd $DIR/../themes
ln -s hacklab-theme SiteEthos
# "-x *docs-locais*" garante que documentos sensíveis locais nunca entrem
# no pacote, mesmo se forem criados acidentalmente dentro do tema.
zip -r ../zips/SiteEthos.zip SiteEthos -x "SiteEthos/node_modules/*" "*docs-locais*"
rm SiteEthos

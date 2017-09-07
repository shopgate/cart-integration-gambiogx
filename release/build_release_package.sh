#!/bin/sh

ZIP_FILE_NAME=shopgate-gambiogx-integration.zip

rm -rf src/shopgate/vendor release/package $ZIP_FILE_NAME
mkdir release/package
composer install -vvv --no-dev
rsync -av --exclude-from './release/exclude-filelist.txt' ./src/ release/package
rsync -av ./modman release/package
rsync -av ./README.md release/package/shopgate
rsync -av ./LICENSE.md release/package/shopgate
rsync -av ./CONTRIBUTING.md release/package/shopgate
rsync -av ./CHANGELOG.md release/package/shopgate
cd release/package
zip -r ../../$ZIP_FILE_NAME .

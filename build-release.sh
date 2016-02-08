#!/bin/bash

if [ -z "$1" ]; then
    echo "Usage: $( basename $0 ) <release version>"
    exit 1
fi
RELEASE_VERSION=$1

CURRENT_MASTER_SHA=$( git log -1 | grep commit | awk '{print $2}' )

git tag -d $RELEASE_VERSION
git push origin :refs/tags/$RELEASE_VERSION

TMP_DIR=$( mktemp -d )
git clone git@github.com:taeram/xenforo-gpx-to-google-map.git $TMP_DIR

cd $TMP_DIR
git rm .gitignore
git rm *.sh

composer install --optimize-autoloader
git add vendor
git rm composer.*

mkdir -p ABDS/
git mv GpxViewer ABDS/
git mv vendor ABDS/
git mv *.md ABDS/

git commit -am "Tag $RELEASE_VERSION"
git tag $RELEASE_VERSION
git push --tags

cd /tmp
rm -rf $TMP_DIR

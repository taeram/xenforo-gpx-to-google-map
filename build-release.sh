#!/bin/bash

if [ -z "$1" ]; then
    echo "Usage: $( basename $0 ) <release version>"
    exit 1
fi
RELEASE_VERSION=$1

CURRENT_MASTER_SHA=$( git log -1 | grep commit | awk '{print $2}' )

git tag -d $RELEASE_VERSION
git push origin :refs/tags/$RELEASE_VERSION

git rm .gitignore
git rm --cached *.sh

composer install --optimize-autoloader
git rm composer.*

mkdir -p library/ABDS/
git mv GpxViewer library/ABDS/

git add vendor
git commit -am "Tag $RELEASE_VERSION"
git tag $RELEASE_VERSION
git push --tags

rm -f "$0" && git checkout $CURRENT_MASTER_SHA && git branch -D master && git checkout -b master

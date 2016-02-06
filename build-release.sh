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
git rm composer.*

composer install
git add vendor
git commit -am "Tag for version $RELEASE_VERSION"
git tag $RELEASE_VERSION
git push --tags

git checkout $CURRENT_MASTER_SHA
git branch -D master
git checkout -b master

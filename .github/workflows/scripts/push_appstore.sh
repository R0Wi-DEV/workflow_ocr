#!/bin/bash

# Used env-variables:
#   APP_NAME
#   APPSTORE_TOKEN
#   NIGHTLY
#   DOWNLOAD_URL
#   APP_PRIVATE_KEY_FILE

echo "Downloading app tarball for signing"
wget "$DOWNLOAD_URL" -O "${APP_NAME}.tar.gz"

echo "Creating signature for app release"
sign="`openssl dgst -sha512 -sign ${APP_PRIVATE_KEY_FILE} ${APP_NAME}.tar.gz | openssl base64 -A`"

echo "Creating new app release in Nextcloud appstore (nightly=${NIGHTLY})"
curl -X POST https://apps.nextcloud.com/api/v1/apps/releases -H "Authorization: Token ${APPSTORE_TOKEN}" -H "Content-Type: application/json" -d "{\"download\":\"${DOWNLOAD_URL}\", \"signature\": \"${sign}\", \"nightly\": ${NIGHTLY} }"

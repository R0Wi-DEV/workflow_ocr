#!/bin/bash

# Used env-variables:
#   APP_NAME
#   APPSTORE_TOKEN
#   NIGHTLY
#   DOWNLOAD_URL
#   ENCRYPTED_PK_PATH
#   PK_DECRYPT_PW

echo "Downloading app tarball for signing"
wget "$DOWNLOAD_URL" -O "${APP_NAME}.tar.gz"

echo "Decrypt signing key"
openssl aes-256-cbc -in $ENCRYPTED_PK_PATH -out ${APP_NAME}.key -pass pass:${PK_DECRYPT_PW} -d -iter 1000 -salt

echo "Creating signature for app release"
sign="`openssl dgst -sha512 -sign ${APP_NAME}.key ${APP_NAME}.tar.gz | openssl base64 -A`"

echo "Creating new app release in Nextcloud appstore (nightly=${NIGHTLY}"
curl -X POST https://apps.nextcloud.com/api/v1/apps/releases -H "Authorization: Token ${APPSTORE_TOKEN}" -H "Content-Type: application/json" -d "{\"download\":\"$download_url\", \"signature\": \"$sign\", \"nightly\": ${NIGHTLY} }"

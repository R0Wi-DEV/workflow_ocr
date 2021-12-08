# Mainly taken from https://github.com/microsoft/vscode-remote-try-php/blob/main/.devcontainer/Dockerfile

ARG VARIANT=8.0-bullseye
ARG NODE_VERSION="none"

FROM mcr.microsoft.com/vscode/devcontainers/php:0-${VARIANT}

RUN if [ "${NODE_VERSION}" != "none" ]; then su vscode -c "umask 0002 && . /usr/local/share/nvm/nvm.sh && nvm install ${NODE_VERSION} 2>&1"; fi

RUN apt-get update && export DEBIAN_FRONTEND=noninteractive \
     && apt-get -y install --no-install-recommends make ocrmypdf tesseract-ocr-eng tesseract-ocr-deu smbclient \
     && apt-get clean \
     && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/* /usr/share/doc/*
FROM ubuntu:noble

ARG DEBIAN_FRONTEND=noninteractive

# Base tooling + PHP 8.4 (matches the range supported by this app, see
# composer.json and appinfo/info.xml)
RUN apt-get update -y && \
    apt-get install -y apache2 vim nano sudo gnupg2 wget curl git rsync \
        software-properties-common lsb-release ca-certificates apt-transport-https && \
    add-apt-repository ppa:ondrej/php -y && \
    apt-get update -y

RUN apt-get install --no-install-recommends -y \
    php8.4 \
    php8.4-common \
    php8.4-gd \
    php8.4-zip \
    php8.4-curl \
    php8.4-xml \
    php8.4-mbstring \
    php8.4-pgsql \
    php8.4-intl \
    php8.4-imagick \
    php8.4-gmp \
    php8.4-apcu \
    php8.4-bcmath \
    php8.4-opcache \
    php8.4-cli \
    php8.4-dev \
    php8.4-xdebug \
    libmagickcore-6.q16-7-extra \
    make \
    unzip

# Composer
RUN curl -sS https://getcomposer.org/installer -o /tmp/composer-setup.php && \
    curl -sS https://composer.github.io/installer.sig -o /tmp/composer-setup.sig && \
    php -r "if (hash_file('sha384', '/tmp/composer-setup.php') !== trim(file_get_contents('/tmp/composer-setup.sig'))) { echo 'Composer installation failed, invalid hash'; exit(1); }" && \
    php /tmp/composer-setup.php --install-dir=/usr/local/bin --filename=composer && \
    rm /tmp/composer-setup.php /tmp/composer-setup.sig

RUN echo "xdebug.remote_enable = 1" >> /etc/php/8.4/cli/conf.d/20-xdebug.ini && \
    echo "xdebug.remote_autostart = 1" >> /etc/php/8.4/cli/conf.d/20-xdebug.ini && \
    echo "apc.enable_cli=1" >> /etc/php/8.4/cli/conf.d/20-apcu.ini

# Autostart XDebug for apache
RUN { \
    echo "xdebug.mode=debug"; \
    echo "xdebug.start_with_request=yes"; \
    } >> /etc/php/8.4/apache2/conf.d/20-xdebug.ini

# Increase PHP memory limit to 512mb
RUN sed -i 's/memory_limit = .*/memory_limit = 512M/' /etc/php/8.4/apache2/php.ini

# Apache modules required by Nextcloud + vhost pointing at the checkout
# living in /var/www/nextcloud instead of the default docroot
RUN a2enmod rewrite headers env dir mime setenvif && \
    a2dissite 000-default
COPY apache-nextcloud.conf /etc/apache2/sites-available/nextcloud.conf
RUN a2ensite nextcloud

# Dedicated DevContainer user runs Apache
ENV APACHE_RUN_USER=devcontainer
ENV APACHE_RUN_GROUP=devcontainer
# Delete any existing user/group with UID/GID 1000 first
RUN (getent passwd 1000 && userdel -r $(getent passwd 1000 | cut -d: -f1)) || true && \
    (getent group 1000 && groupdel $(getent group 1000 | cut -d: -f1)) || true && \
    groupadd -g 1000 ${APACHE_RUN_GROUP} && \
    useradd -u 1000 -g 1000 -ms /bin/bash ${APACHE_RUN_USER} && \
    adduser ${APACHE_RUN_USER} sudo && \
    echo '%sudo ALL=(ALL) NOPASSWD:ALL' >> /etc/sudoers && \
    sed -ri "s/^export APACHE_RUN_USER=.*$/export APACHE_RUN_USER=${APACHE_RUN_USER}/" "/etc/apache2/envvars" && \
    sed -ri "s/^export APACHE_RUN_GROUP=.*$/export APACHE_RUN_GROUP=${APACHE_RUN_GROUP}/" "/etc/apache2/envvars"

# Pre-create the (later volume-mounted) Nextcloud directory with the right
# ownership, so setup.sh can clone/install without needing root.
RUN mkdir -p /var/www/nextcloud && chown -R ${APACHE_RUN_USER}:${APACHE_RUN_GROUP} /var/www/nextcloud

USER devcontainer

# NVM / NodeJS (matches this app's package.json engines)
RUN curl -o- https://raw.githubusercontent.com/nvm-sh/nvm/v0.39.0/install.sh | bash
RUN bash --login -i -c 'source /home/devcontainer/.bashrc && nvm install 24'

WORKDIR /var/www/nextcloud

#!/bin/bash
#
# Runs on every container start (devcontainer "postStartCommand").
#
# Checks whether Nextcloud is already cloned and installed inside the
# persistent "nextcloud" volume at /var/www/nextcloud. If not:
#   1. Determines which Nextcloud server branch is compatible with this app
#      by reading the <nextcloud max-version="..."/> entry from
#      appinfo/info.xml, and checking whether a matching "stable<VERSION>"
#      branch exists in the nextcloud/server repository (falling back to
#      "master" otherwise).
#   2. Clones that branch as a shallow, single-branch, single-commit clone
#      (see unshallow.sh if you ever need the full history).
#   3. Installs Nextcloud against the "db" Postgres service and enables
#      this app.
#
# Idempotent: if Nextcloud is already installed, this is a (fast) no-op.
set -eo pipefail

NC_DIR="/var/www/nextcloud"
APP_NAME="workflow_ocr"
APP_DIR="$NC_DIR/apps/$APP_NAME"
NC_REPO_URL="https://github.com/nextcloud/server.git"

DB_HOST="${NEXTCLOUD_DB_HOST:-db}"
DB_PORT="${NEXTCLOUD_DB_PORT:-5432}"
DB_NAME="${NEXTCLOUD_DB_NAME:-nextcloud}"
DB_USER="${NEXTCLOUD_DB_USER:-nextcloud}"
DB_PASSWORD="${NEXTCLOUD_DB_PASSWORD:-nextcloud}"
ADMIN_USER="${NEXTCLOUD_ADMIN_USER:-admin}"
ADMIN_PASSWORD="${NEXTCLOUD_ADMIN_PASSWORD:-admin}"

# Both directories are bind mounts / volumes owned by the container user,
# but git still insists on an explicit safe.directory entry.
git config --global --add safe.directory "$NC_DIR"
git config --global --add safe.directory "$APP_DIR"

# Docker auto-creates the parent directory of a nested bind mount (here:
# apps/, the parent of the apps/workflow_ocr bind mount) before the
# container starts, and does so as root - regardless of who owns the rest
# of the volume. That silently blocks the (non-root) devcontainer user from
# creating sibling app directories during the clone below, so reclaim it
# every start.
sudo mkdir -p "$NC_DIR/apps"
sudo chown "${APACHE_RUN_USER:-devcontainer}:${APACHE_RUN_GROUP:-devcontainer}" "$NC_DIR" "$NC_DIR/apps"

if [ -n "$CLAUDE_CONFIG_DIR" ]; then
    sudo chown -R "$(id -u):$(id -g)" "$CLAUDE_CONFIG_DIR"
fi

is_cloned() {
    [ -f "$NC_DIR/version.php" ] && [ -f "$NC_DIR/apps/files/appinfo/info.xml" ]
}

is_installed() {
    is_cloned && php "$NC_DIR/occ" status --output=json 2>/dev/null | grep -Eq '"installed":[[:space:]]*true'
}

wait_for_db() {
    echo "==> Waiting for database at ${DB_HOST}:${DB_PORT}"
    local i=0
    until (echo >"/dev/tcp/${DB_HOST}/${DB_PORT}") >/dev/null 2>&1; do
        i=$((i + 1))
        if [ "$i" -ge 60 ]; then
            echo "Database did not become available in time" >&2
            exit 1
        fi
        sleep 1
    done
}

# Reads the highest Nextcloud version this app declares compatibility with
# (appinfo/info.xml -> <dependencies><nextcloud max-version="...">) and
# checks whether a "stable<VERSION>" branch exists for it upstream. Falls
# back to "master" whenever no version could be determined or no matching
# branch exists.
determine_nextcloud_branch() {
    local max_version
    max_version=$(sed -n 's/.*<nextcloud[^>]*max-version="\([0-9]*\)".*/\1/p' "$APP_DIR/appinfo/info.xml" | head -n1)

    if [ -z "$max_version" ]; then
        echo "master"
        return
    fi

    local candidate="stable${max_version}"
    # Query the fully-qualified ref: an unqualified "stableXX" pattern also
    # matches "backport/NNNNN/stableXX" branches via git's suffix matching.
    if [ -n "$(git ls-remote --heads "$NC_REPO_URL" "refs/heads/${candidate}")" ]; then
        echo "$candidate"
    else
        echo "master"
    fi
}

clone_nextcloud() {
    local branch
    branch="$(determine_nextcloud_branch)"
    echo "==> Cloning nextcloud/server (branch: ${branch}, shallow single-branch/single-commit)"

    local staging_dir
    staging_dir="$(mktemp -d)"

    git clone --branch "$branch" --single-branch --depth 1 "$NC_REPO_URL" "$staging_dir"
    git -C "$staging_dir" submodule update --init --recursive --depth 1

    # $NC_DIR already contains the bind-mounted apps/workflow_ocr directory
    # (this app), so merge the freshly cloned core sources into it instead
    # of cloning straight into it.
    rsync -a "$staging_dir"/ "$NC_DIR"/
    rm -rf "$staging_dir"
}

install_nextcloud() {
    echo "==> Installing Nextcloud"
    php "$NC_DIR/occ" maintenance:install \
        --verbose \
        --database=pgsql \
        --database-name="$DB_NAME" \
        --database-host="$DB_HOST" \
        --database-port="$DB_PORT" \
        --database-user="$DB_USER" \
        --database-pass="$DB_PASSWORD" \
        --admin-user="$ADMIN_USER" \
        --admin-pass="$ADMIN_PASSWORD"

    echo "==> Installing app dependencies for $APP_NAME"
    pushd "$APP_DIR" > /dev/null
    composer install --no-dev
    popd > /dev/null

    echo "==> Enabling $APP_NAME"
    php "$NC_DIR/occ" app:enable "$APP_NAME"
}

wait_for_db

if is_installed; then
    echo "Nextcloud is already cloned and installed, nothing to do."
else
    is_cloned || clone_nextcloud
    install_nextcloud
fi

sudo service apache2 restart

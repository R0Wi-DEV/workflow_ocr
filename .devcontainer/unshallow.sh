#!/bin/bash
#
# The Nextcloud core checkout at /var/www/nextcloud is created by setup.sh
# as a shallow, single-branch, single-commit clone to keep the initial
# container setup fast (see setup.sh -> clone_nextcloud).
#
# Run this script manually whenever you need the full git history for that
# checkout, for example to `git blame`, `git bisect`, or switch to a
# different branch/tag/commit.
set -eo pipefail

NC_DIR="/var/www/nextcloud"

if [ ! -d "$NC_DIR/.git" ]; then
    echo "No git checkout found at $NC_DIR" >&2
    exit 1
fi

echo "==> Unshallowing Nextcloud checkout at $NC_DIR (this may take a while)"
git -C "$NC_DIR" fetch --unshallow
git -C "$NC_DIR" remote set-branches origin '*'
git -C "$NC_DIR" fetch --all --tags

echo "Done. $NC_DIR is now a full clone."

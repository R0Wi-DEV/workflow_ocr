#!/bin/bash
#
# Main container process. Starts Apache and keeps the container running.
#
# The logic which checks whether Nextcloud is already cloned/installed
# (and clones + installs it otherwise) lives in setup.sh, which is executed
# separately via the devcontainer "postStartCommand" once the container is
# up and the volumes/bind mounts are available.
sudo service apache2 start

while sleep 1000; do :; done

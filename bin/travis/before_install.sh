#!/usr/bin/env bash

# NAME
#     before_install.sh - Prepare the environment.
#
# SYNOPSIS
#     before_install.sh
#
# DESCRIPTION
#     Configures the Travis CI environment, installs ORCA, and prepares the SUT.

cd "$(dirname "$0")" || exit; source _includes.sh

# Display configuration values.
printenv | grep ORCA_

# The remaining before_install commands should only be run on Travis CI.
[[ "$TRAVIS" ]] || exit 0

# Display the Google Chrome version.
google-chrome-stable --version

# Disable Xdebug.
phpenv config-rm xdebug.ini

{
  # Remove PHP memory limit.
  echo 'memory_limit = -1'
  # Prevent email errors.
  echo 'sendmail_path = /bin/true'
  # Prevent PHPStan warnings about APCu constants.
  echo 'extension = apcu.so'
} >> "$HOME/.phpenv/versions/$(phpenv version-name)/etc/conf.d/travis.ini"

# Install the PECL YAML parser for strict YAML parsing.
yes | pecl install yaml

# Install Composer optimizations for faster builds.
composer global require \
  hirak/prestissimo \
  zaporylie/composer-drupal-optimizations

# Install ORCA.
composer -d"$ORCA_ROOT" install

orca --version

# Ensure the checked out branch is named after the nearest Git version branch.
git -C "$ORCA_SUT_DIR" rev-parse --abbrev-ref HEAD
if [[ $(git -C "$ORCA_SUT_DIR" rev-parse --abbrev-ref HEAD) != "$ORCA_SUT_BRANCH" ]]; then
  git -C "$ORCA_SUT_DIR" branch -f "$ORCA_SUT_BRANCH"
  git -C "$ORCA_SUT_DIR" checkout "$ORCA_SUT_BRANCH"
fi

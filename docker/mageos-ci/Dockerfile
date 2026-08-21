# syntax=docker/dockerfile:1

# Mage-OS CI/sandbox image for magento-bricklayer.
#
# Built by .github/workflows/mageos-image.yml, which prepares the build
# context first: an installed Mage-OS tree in ./mageos and a database seed
# in docker/mageos-ci/seed.sql.gz. This Dockerfile is not buildable from a
# bare checkout on its own.
#
# The image ships PHP + Magento extensions, Composer, a MariaDB client,
# the installed Mage-OS filesystem, the seed dump, and the mageos-init
# script that restores the seed against sidecar db/opensearch containers.
# Bricklayer is NOT baked in; CI injects the working tree via a Composer
# path repository, and sandbox users `composer require` it like anyone else.

ARG PHP_VERSION=8.4
FROM php:${PHP_VERSION}-cli

# Resolves distro-specific build deps automatically; survives base-image
# distro bumps (bookworm -> trixie) without touching this file.
ADD --chmod=0755 \
    https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions \
    /usr/local/bin/
RUN install-php-extensions bcmath gd intl opcache pcntl pdo_mysql soap sockets xsl zip

# Unpinned by design: the image is rebaked daily against a floating base,
# so pinned Debian package versions would break on every base bump.
# hadolint ignore=DL3008
RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip mariadb-client jq \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

# opcache.enable_cli is deliberate: with revalidate_freq at its default (2)
# it reproduces the long-lived-daemon opcache staleness environment that the
# reinitialize opcache invalidation targets, so the integration suite runs
# in the same conditions real MCP server processes do. pcntl likewise keeps
# the SIGALRM code-runner timeout path (the primary enforcement) exercised.
RUN { \
        echo 'memory_limit=2G'; \
        echo 'opcache.enable_cli=1'; \
    } > /usr/local/etc/php/conf.d/mageos-ci.ini

ENV COMPOSER_ALLOW_SUPERUSER=1 \
    MAGEOS_ROOT=/var/www/mageos

COPY mageos /var/www/mageos
COPY docker/mageos-ci/seed.sql.gz /opt/mageos/seed.sql.gz
COPY docker/mageos-ci/mageos-init /usr/local/bin/mageos-init
RUN chmod 0755 /usr/local/bin/mageos-init

WORKDIR /var/www/mageos
CMD ["bash"]

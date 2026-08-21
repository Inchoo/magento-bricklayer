# Mage-OS CI image

Pre-baked Mage-OS installation used by the `integration` GitHub Actions workflow, published to
`ghcr.io/inchoo/magento-bricklayer/mageos-ci`. It exists so integration runs skip the expensive part
(`composer create-project` + `setup:install`, roughly 10 minutes) and go straight to testing Bricklayer
against a live Magento runtime. The same image doubles as a disposable local sandbox.

## What is inside

PHP CLI with all Magento extensions (plus `pcntl` and CLI opcache, deliberately, to match long-lived MCP
server conditions), Composer, a MariaDB client, an installed Mage-OS tree (`mage-os/project-minimal-edition`,
the slim distribution without bundled extensions) at `/var/www/mageos` in developer mode, a gzipped database seed at `/opt/mageos/seed.sql.gz`, and `mageos-init` on `PATH`. Mage-OS is
installed with `--no-dev`: its own PHPUnit and PHPStan would otherwise share one autoloader with the
package's dev tools during the integration run and collide on major versions.

Bricklayer is not baked in. CI injects the working tree via a Composer path repository on every run, so the
image never goes stale relative to the code under test.

## How a run works

The job starts three containers on one network: this image (job container), `mariadb:11.4` as `db`, and
`opensearchproject/opensearch:3.2.0` as `opensearch`. `mageos-init` waits for both services, imports the
seed if the database is empty, rewrites `env.php` and the search configuration to point at the service
hostnames, and flushes caches. Hostnames and credentials are overridable via `MAGEOS_DB_HOST`,
`MAGEOS_DB_NAME`, `MAGEOS_DB_USER`, `MAGEOS_DB_PASSWORD`, `MAGEOS_SEARCH_HOST`, `MAGEOS_SEARCH_PORT`,
`MAGEOS_SEED`, and `MAGEOS_ROOT`.

## Local sandbox usage

```bash
docker network create mageos
docker run -d --name db --network mageos \
  -e MARIADB_ROOT_PASSWORD=mageos -e MARIADB_DATABASE=mageos mariadb:11.4
docker run -d --name opensearch --network mageos \
  -e discovery.type=single-node -e DISABLE_SECURITY_PLUGIN=true \
  -e DISABLE_INSTALL_DEMO_CONFIG=true -e OPENSEARCH_JAVA_OPTS='-Xms512m -Xmx512m' \
  opensearchproject/opensearch:3.2.0
docker run -it --network mageos ghcr.io/inchoo/magento-bricklayer/mageos-ci:latest \
  bash -c 'mageos-init && bash'
```

Inside the container, `composer require inchoo/magento-bricklayer` (or a path repository pointing at a
mounted checkout) gives you a working install to poke at. `bin/magento` and `vendor/bin/bricklayer` behave
as on any dev install.

## Rebaking

The `mageos-image` workflow rebakes daily, on manual dispatch (with `mageos-version` and `php-version`
inputs), and whenever files in this directory change. Tags: `latest` plus `<mageos-version>-php<php>`.
Every successful bake re-runs the integration lane against the exact tag it pushed, so a broken bake is
caught immediately rather than on the next code PR.

First-time setup: after the first bake, set the GHCR package visibility to public (repository Packages
settings). Fork PRs can only pull the image without credentials once it is public.

## Stack notes

The database is MariaDB 11.4 rather than the MySQL 8.4 that Mage-OS certifies. MariaDB 11.4 is in the
supported upstream matrix, and it keeps the client tooling inside the image trivial: MySQL 8.4's default
`caching_sha2_password` authentication is unreliable with the Debian-packaged MariaDB client that Debian
gives us, while a MariaDB server needs no workarounds for dump or import. Switching to MySQL means changing
the two service blocks in both workflows and solving the client question in the Dockerfile.

The seed is minimal (no sample data). Tests that need entities create and remove them through Bricklayer's
own tools, which is itself coverage.


# Fusio-Integration

This repo contains some integration tests for Fusio. More information about
Fusio at https://www.fusio-project.org/

## Upgrade

This test sequentially downloads the latest release from GitHub and installs
them step by step, which then triggers all migrations. At the end we compare the
database of a complete new installation and an upgraded installation, there
should be no difference.

## Marketplace

We check whether all apps at the marketplace are installable and have a correct
hash.

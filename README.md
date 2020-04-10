
# Fusio-Integration

This repo contains some integration tests for Fusio. If you are interested at
the results you can take a look at [Travis-CI](https://travis-ci.org/github/apioo/fusio-integration).
More information about Fusio at https://www.fusio-project.org/

## Upgrade

This test sequentially downloads the latest releases from GitHub and installs
them step by step, which then triggers all migrations. At the end we compare the
database of a complete new installation and an upgraded installation, there
should be no difference.

## Marketplace

The marketplace provides a list of apps which are installable through the Fusio
backend, it is based on a simple [YAML file](https://github.com/apioo/fusio/blob/master/marketplace.yaml).
This test checks whether all apps at the marketplace are installable and have a
correct hash.

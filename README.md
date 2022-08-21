
# Fusio-Integration

This repo contains some integration tests for Fusio. If you are interested at
the results you can take a look at the [Actions](https://github.com/apioo/fusio-integration/actions).
More information about Fusio at https://www.fusio-project.org/

## Upgrade

This test sequentially downloads the latest releases from GitHub and installs
them step by step, which then triggers all migrations. At the end we compare the
database of a complete new installation and an upgraded installation, there
should be no difference.

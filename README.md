# Pilcrow

## Development

### Getting Started

Pilcrow uses [Lando](https://lando.dev) to manage its development environment.

1. [Install Lando](https://docs.lando.dev/basics/installation.html)
2. Clone this repository to your local machine: `git clone https://github.com/MESH-Research/Pilcrow`
3. Head into the Pilcrow directory and run: `lando start`
4. Coffee. Downloading. Building. Patience. Step 3 may take approximately three to fifteen minutes to complete.
5. Migrate database: `lando artisan migrate`
6. Once the bootstrapping process is finished, open a browser to <https://pilcrow.lndo.site/>

The stack is running `yarn dev` in the node container, so editing source files should result in HMR / recompiling as needed.  PHP files are served with PHP-FPM and should not require restarting the container to load changes.

### Tips / Troubleshooting

* Lando generates its own CA cert, which [you can add to your OS certificate store](https://docs.lando.dev/config/security.html#trusting-the-ca) (optional, but super nice).
* Node and composer packages are updated on *rebuild* only.  To update dependencies without rebuilding, use `lando composer install` and/or `lando yarn install`. You may have to run `lando restart` if node dependencies have changed.
* Database migrations are *not* automatically applied, so you'll need to run `lando artisan migrate` to apply them as needed.
* See [https://pilcrow.meshresearch.dev/contributing/wsl.html](https://pilcrow.meshresearch.dev/contributing/wsl.html) for additional notes on
  developer setup under Windows Subsystem for Linux (WSL)

### Pilcrow Wiki - <https://github.com/MESH-Research/Pilcrow/wiki>

* [An opinionated application](https://github.com/MESH-Research/Pilcrow/wiki/An-Opinionated-Application)
* [Pilcrow Links](https://github.com/MESH-Research/Pilcrow/wiki/Pilcrow-Links)
* [Style Guide - in the Making](https://github.com/MESH-Research/Pilcrow/wiki/Style-Guide---in-the-making)


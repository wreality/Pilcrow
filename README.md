<h1 align="center">
    <a href="https://pilcrow.mesh-research.dev">
    <img src="docs/.vuepress/public/logo-100x100.png"/>
    <br />
    Pilcrow
    </a>
</h1>
<div align="center">

[![GitHub Workflow Status (with event)](https://img.shields.io/github/actions/workflow/status/mesh-research/pilcrow/CI.yml?logo=github&style=for-the-badge)](https://github.com/MESH-Research/Pilcrow/actions/workflows/CI.yml)
![GitHub release (with filter)](https://img.shields.io/github/v/release/mesh-research/pilcrow?style=for-the-badge)
![Rennovate Status](https://img.shields.io/badge/renovate-enabled-green?style=for-the-badge)

</div>

<div align="center">

[Documentation](https://pilcrow.meshresearch.dev) &bullet;
[Demo](https://release.pilcrow.dev) &bullet;
[Discussions](https://github.com/MESH-Research/Pilcrow/discussions) &bullet;
[Bugs/Feature Requests](https://github.com/MESH-Research/Pilcrow/issues)

</div>

# About Pilcrow

Pilcrow is a web application designed to support the Collaborative Community Review (CCR) peer-review process.
## What is Collaborative Community Review (CCR)
Collaborative Community Review is a healthier, more effective way to review and develop work for publication.

Traditional peer review at academic journals serves a gatekeeping role, determining whether a piece is publishable or not; this decision comes after the piece is nearly complete. This type of peer review practice often proves hostile to new ideas, unproven authors, and unfamiliar audiences. The process can even be traumatic for those involved. The PPJ is changing that with transparency, community engagement, and ongoing developmental conversations.

CCR nurtures new ideas by supporting pieces through their development, creating supportive experiences for authors and audiences. The goal of this review process is to both prepare pieces for publication and improve them in those preparations. CCR is structured to encourage peer engagement rooted in trust and a shared commitment to improving the work through candid and collegial feedback. The review process is formative in a twofold sense:

1. The Piece: CCR helps shape the work so that it might effectively enrich public life, and

2. The People: CCR cultivates habits of responsiveness and collegiality among participants.

Because the review process is formative for the piece and the people involved, each participant must enter into it willing to learn and be transformed by the process itself.

To read more about the CCR process, [visit the Public Philosophy Journal's page describing the process](https://publicphilosophyjournal.org/about-community-collaborative-review/)




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


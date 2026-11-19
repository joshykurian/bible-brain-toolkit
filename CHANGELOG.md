# Changelog

All notable changes are listed here. The format follows [Keep a Changelog](https://keepachangelog.com/) and the project uses
[Semantic Versioning](https://semver.org/).

## [1.1.0] - 2026-11-19

Hosted and published by Shalom Design S2dio (SDS): website <https://www.shalomdesigns2dio.com/bible-brain-toolkit/>, source <https://github.com/shalomdesigns2dio/bible-brain-toolkit>.

First public repository release.

### Changed
- Credits: developer and AI-assistance statement, and links to Bible Brain and Faith Comes By Hearing in the README, About page, demo website, notices, plugin headers and in-app About text. Shalom Design S2dio (SDS) is named as developer and host, and the notice that this is an unofficial community project, not published or supported by Faith Comes By Hearing, is back in every place it appears.
- Demo audio is now an audible computer-read voice with real verse timing (it was a very quiet tone).
- GitHub Actions and test tools updated: setup-python 7, setup-node 7, upload-artifact 7, configure-pages 6, upload-pages-artifact 5, deploy-pages 5, Playwright 1.65.2 or newer. Dependabot now groups updates into one monthly pull request.
- The repository ignore rules no longer let a generic template rule hide `lib/` and `site/`.

### Added
- Website package for SDS: `python scripts/build_website.py` writes an upload-ready folder and ZIP for `/bible-brain-toolkit/` (with `.htaccess`, `robots.txt`, `sitemap.xml`). Guide: `docs/website-hosting.md`.
- The embed code generator now shares the landing page look (header, fonts, colours, footer); shared styles live in `site/assets/site.css`.
- Demo website (`site/`) with live players on sample data, a "use my own proxy" option, and a GitHub Pages workflow.
- Submission kit (`docs/submission/`) and `scripts/make_wporg_package.py` for the WordPress.org, Drupal.org and Joomla directories.
- Listing-quality WordPress `readme.txt` with External services and Privacy sections.
- Repository structure with a single source of truth in `shared/` and tools to sync, build and check (`scripts/`).
- Interface translations (draft) for Hindi, Bengali, Nepali, Sinhala, Urdu (right-to-left) and Malayalam, chosen automatically from the page language.
- Downloader command line mode (`--list-countries`, `--list-filesets`, `--fileset`).
- Configurable API base address for the proxy and the downloader (for testing against a mock server).
- Origin allow-list through the `BIBLE_BRAIN_ALLOWED_ORIGINS` environment variable and a cache folder override.
- Automated tests: downloader, PHP proxy running under PHP, and browser tests in Chromium.
- GitHub Actions for CI and for building release ZIP files, issue and pull request templates, Dependabot.
- Documentation: README, getting started, architecture, option reference, analytics, localization, about and links, troubleshooting, roadmap.
- About and links (Bible Brain, developer documentation, API reference, request a key, license, terms, copyright policy) in the WordPress settings page, Drupal settings form, Joomla plugin description, embed generator, downloader window and `--about`, and in `docs/about.md`.

### Changed
- Version selector now adapts to the available width so long version names are readable.
- Skip buttons keep their `+15` and `-15` labels in right-to-left languages.
- WordPress plugin internals renamed to a unique prefix (`BBPlay_`, `bbplay_`) and the REST namespace to `bible-brain-players/v1`, so the plugin does not clash with bbPress. Settings from early test builds are carried over on activation.
- The player's in-memory cache is kept per endpoint, so one page can switch between servers.

## [1.0.0]

Initial toolkit: WordPress plugin, Drupal module, Joomla plugin, HTML5 embed with proxy, and the desktop downloader.

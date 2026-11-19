# Contributing

Thank you for helping make Scripture easier to reach. Translators, developers, designers, testers and ministry practitioners
are all welcome, and you do not need to be a programmer to help.

## Ways to help
- **Review or add a translation** of the player interface, see [docs/localization.md](docs/localization.md). This is one of the most valuable contributions.
- **Test on a real site** (WordPress, Drupal, Joomla, a static site) and report what you find.
- **Share field experience**: which platforms, languages and approaches help people engage with Scripture in your context.
- **Fix bugs and add features**, see the [roadmap](docs/roadmap.md).
- **Improve the documentation**, especially for non-technical users.

## Ground rules
- Be kind and respectful, see the [Code of Conduct](CODE_OF_CONDUCT.md).
- **Never commit an API key**, personal data, or Scripture text or audio. Use the mock server and public-domain samples in tests.
- Do not remove the Bible Brain credit or copyright display from the players.
- Keep it light: the players have no dependencies and must stay fast on cheap phones.
- Write text (docs, comments, interface) in clear, respectful language.

## Repository layout

| Path | Purpose |
|---|---|
| `shared/` | **Single source of truth** for the player JS, CSS, generator, PHP core and translations |
| `packages/*` | The installable products. Files copied from `shared/` must not be edited here |
| `scripts/` | Build, sync and check tools |
| `tests/` | Downloader tests, PHP proxy tests, browser tests, shared mock server (`helpers.py`) |
| `docs/` | Documentation |

## Workflow

```bash
git checkout -b my-change
# edit files in shared/ (not the copies in packages/)
python scripts/build_locales.py      # if you changed shared/i18n
python scripts/sync_shared.py        # copy shared files into every package
python scripts/build_site.py         # refresh the demo website after changing shared/
python scripts/test_all.py           # all checks and tests
```

Then open a pull request. CI runs the same checks on PHP 7.4, 8.1 and 8.3.

### Setting up tests
- Python 3.8+, PHP 7.4+ and Node 18+ on your PATH
- Browser tests: `pip install -r tests/requirements.txt` and `python -m playwright install chromium`
- Quick run without the browser: `python scripts/test_all.py --fast`
- Re-take the README screenshots: `python tests/js/screenshots.py`

### Style
- JavaScript: ES5-style (`var`, no build step), so older Android browsers keep working.
- PHP: compatible with PHP 7.4 in `shared/` and the WordPress and standalone packages. The Drupal and Joomla wrappers may use PHP 8.1 features.
- Python: standard library only for the downloader, 3.8 compatible.
- Follow `.editorconfig`.

### Versions and releases
- The version lives in `VERSION`. Run `python scripts/check_versions.py` after changing it: it must match the WordPress header,
  `readme.txt`, the Joomla manifest, the Drupal library file and the downloader.
- Add notes to `CHANGELOG.md`.
- Tag the release (`git tag v1.2.0 && git push --tags`). The release workflow builds the ZIP files and publishes them.

## Reporting bugs
Please include the platform and versions (CMS, PHP, browser), what you expected, what happened, and any message from the
browser console. **Remove your API key first.**

## Security
Please do not open public issues for vulnerabilities, see [SECURITY.md](SECURITY.md).

## Licence
By contributing you agree that your contribution is released under the project's licence, GPL-2.0-or-later.

# Getting started

This guide takes you from nothing to a working player. Choose the path that matches your website.

## 1. Get a free Bible Brain API key

1. Open <https://4.dbt.io/api_key/request> and request a key. It arrives by email.
2. Keep it private. Anyone with the key can use your quota.
3. Read the terms and the licence of each Bible version you plan to publish. Licences differ between versions and
   some versions allow listening but not downloading.

## 2. Pick your platform

| You have | Use | Time |
|---|---|---|
| A WordPress site | [WordPress plugin](#wordpress) | 5 minutes |
| A Drupal 10/11 site | [Drupal module](#drupal) | 10 minutes |
| A Joomla 4/5 site | [Joomla plugin](#joomla) | 5 minutes |
| Any other site with PHP hosting | [HTML5 embed](#html5-embed) | 10 minutes |
| Wix, Blogger, Squarespace, Google Sites | [iframe page](#site-builders) | 10 minutes |
| Only a computer, wanting files | [Downloader](#downloader) | 5 minutes |

You can also run one proxy (for example the WordPress site) and let other websites use it, see
[architecture](architecture.md#one-proxy-many-sites).

## WordPress
1. Plugins > Add New > Upload Plugin, choose `bible-brain-wordpress-<version>.zip`, activate.
2. Settings > Bible Brain Players. Paste your key and save.
3. Choose default versions for the reader, audio and video players (optional, but handy).
4. Add shortcodes to any page or post:
   ```text
   [bb_audio country="IN" language="hin" bible="HINDOV"]
   [bb_reader country="IN" bible="HINDOV" selector="no"]
   [bb_video country="IN" language="hin"]
   ```
5. To find the codes for your language, open `generator.html` from the HTML5 package in a browser with your own address as the
   proxy: `generator.html?proxy=https://yoursite/wp-json/bible-brain-players/v1/`. Choose a country, language and version, then copy the values.

## Drupal
1. Copy `packages/drupal/bible_brain` to `modules/custom/bible_brain`.
2. `drush en bible_brain` (or Extend > Bible Brain Players).
3. Configuration > Media > Bible Brain Players: paste your key and save.
4. Structure > Block layout > Place block > **Bible Brain player**. Choose the type and enter country, language and version.

## Joomla
1. System > Install > Extensions > Upload Package File: `plg_system_biblebrain-joomla-<version>.zip`.
2. System > Plugins: enable **System - Bible Brain Players** and paste your key.
3. Add tags to an article: `{bbp audio country="IN" language="hin" bible="HINDOV"}`.

## HTML5 embed
1. Upload `packages/html5-embed` to your host, for example to `https://example.org/bible/`.
2. Copy `config.sample.php` to `config.php`, paste your key, make `cache/` writable.
3. Check `https://example.org/bible/bbp-proxy.php?ep=status`. You should see `"key_set":true`.
4. Open `generator.html`, choose a country, language and version, and copy the code it shows.
5. Paste the code where you want the player.

## Site builders
Wix, Blogger, Squarespace and Google Sites often block scripts. Use the generator's iframe option: it gives you an `<iframe>`
that loads `iframe.html` from your server, which you can embed with the builder's "Embed" element.

## Downloader
```bash
python packages/downloader/main.py          # window
python packages/downloader/main.py --help   # command line
```
Details: [packages/downloader/README.md](../packages/downloader/README.md).

## 3. Share it

Turn on the share bar (it is on by default) and post the player link in the groups and channels your community already uses.

## Next steps
- [Option reference](reference.md)
- [Analytics](analytics.md)
- [Translating the interface](localization.md)
- [Troubleshooting](troubleshooting.md)

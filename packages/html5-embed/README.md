# Bible Brain HTML5 embed

Put a Bible reader, audio player or video player on any website with a few lines of HTML.
Visitors pick country, language and version. Your free API key stays on your server.

## Files
| File | Purpose |
|---|---|
| `bible-brain-players.js` / `.css` | The players (no dependencies) |
| `bbp-proxy.php` + `BibleBrainCore.php` | Small proxy that holds your API key and caches data |
| `config.sample.php` | Copy to `config.php` and add your key |
| `generator.html` | Choose a version and copy ready-made code (HTML5, iframe, WordPress, Joomla, Drupal) |
| `example.html` | A working page with all three players |
| `iframe.html` | For site builders that block scripts (Wix, Blogger, Squarespace, Google Sites) |

## Set up (about 5 minutes)
1. Upload this whole folder to a PHP 7.4+ web host (for example `https://example.org/bible/`).
2. Request a free key at https://4.dbt.io/api_key/request. Copy `config.sample.php` to `config.php` and paste the key.
   Make the `cache/` folder writable.
3. Open `https://example.org/bible/bbp-proxy.php?ep=status`. You should see `"key_set":true`.
4. Open `https://example.org/bible/generator.html`, choose a country, language and version, and copy the code.
5. Paste the code into any page. Done.

## The code you paste
    <link rel="stylesheet" href="https://example.org/bible/bible-brain-players.css">
    <div class="bbp-audio" data-rest="https://example.org/bible/bbp-proxy.php?ep="
         data-country="IN" data-language="hin" data-bible="HINDOV"></div>
    <script src="https://example.org/bible/bible-brain-players.js" defer></script>

Use `bbp-reader`, `bbp-audio` or `bbp-video`. Options (all `data-` attributes): `country`, `language`, `bible`, `fileset`,
`book`, `chapter`, `selector="no"` (lock to one version), `share`, `reflect`, `listen`, `theme`, `accent`, `title`, `prompts` (questions separated by `|`).

## Already running the WordPress plugin, Drupal module or Joomla plugin?
They also work as the proxy. Use `data-rest="https://yoursite/wp-json/bible-brain-players/v1/"` (WordPress),
`data-rest="https://yoursite/bible-brain/api/"` (Drupal) or `data-rest="https://yoursite/index.php?bbapi="` (Joomla).

## Interface language
The player follows the page language (`<html lang="hi">`) or `data-lang="hi"`. Draft translations are included for Hindi, Bengali, Nepali, Sinhala, Urdu and Malayalam.

## Security
The API key is only in `config.php` (blocked by the included `.htaccess` on Apache; keep it outside the web root on other servers).
Set `allowed_origins` in `config.php` (or the `BIBLE_BRAIN_ALLOWED_ORIGINS` environment variable) to your own sites to stop others from using your proxy.

## Events for analytics
Each player pushes events (`bbp_play`, `bbp_complete`, `bbp_share`, `bbp_version_select`, ...) to `window.dataLayer` when it exists
and also fires a `bbp:event` DOM event, so Google Tag Manager, GA4 or Matomo can count listening and sharing.

## About and links
[Bible Brain](https://www.faithcomesbyhearing.com/audio-bible-resources/bible-brain) |
[Developer documentation](https://www.faithcomesbyhearing.com/bible-brain/developer-documentation) |
[API reference](https://www.faithcomesbyhearing.com/bible-brain/api-reference) |
[Request a free API key](https://4.dbt.io/api_key/request) |
[License](https://www.faithcomesbyhearing.com/bible-brain/license) |
[Terms](https://www.faithcomesbyhearing.com/bible-brain/terms-conditions) |
[Copyright policy](https://www.faithcomesbyhearing.com/copyright-policy)

Developed by Shalom Design S2dio (SDS), with the help of AI tools. This is an unofficial community project and is not published or supported by Faith Comes By Hearing. Scripture content is provided by [Bible Brain](https://www.faithcomesbyhearing.com/audio-bible-resources/bible-brain), a project of [Faith Comes By Hearing](https://www.faithcomesbyhearing.com/). Bible Brain support: support@digitalbibleplatform.com

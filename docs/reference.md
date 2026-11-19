# Option reference

Every package supports the same options. Only the syntax differs.

## Options

| Option | Values | Default | Notes |
|---|---|---|---|
| `country` | Two-letter code, e.g. `IN` | from settings | Which country's Bible list is shown first |
| `language` | Language code, e.g. `hin` | from settings | |
| `bible` | Version abbreviation, e.g. `HINDOV` | from settings | |
| `fileset` | Fileset ID, e.g. `HINDOVN2DA` | auto | Choose a specific recording |
| `book` | Book code, e.g. `JHN` | first book | |
| `chapter` | Number | 1 | |
| `selector` | `yes` / `no` | `yes` | `no` hides the selector and locks to the given version |
| `share` | `yes` / `no` | on | Share bar |
| `reflect` | `yes` / `no` | on | "Reflect and respond" panel |
| `listen` | `yes` / `no` | on | Reader only: play the chapter audio while reading |
| `theme` | `auto` / `light` / `dark` | `auto` | |
| `accent` | CSS colour | `#7E0E2A` | |
| `title` | Text | none | Heading above the player |
| `prompts` | Questions separated by `\|` | built-in | Replace the reflection questions |
| `lang` | Language code | page language | Interface language, see [localization](localization.md) |

## Syntax by platform

### WordPress
```text
[bb_reader country="IN" bible="HINDOV" book="JHN" chapter="3" selector="no"]
[bb_audio  country="IN" language="hin" bible="HINDOV" theme="dark"]
[bb_video  country="IN" language="hin" title="Watch"]
```
Global defaults (accent, theme, prompts, share, reflect, listen, cache hours, allowed countries) are on
Settings > Bible Brain Players.

### Joomla
```text
{bbp reader country="IN" bible="HINDOV" selector="no"}
{bbp audio country="IN" language="hin" bible="HINDOV"}
{bbp video country="IN" language="hin" title="Watch the Gospel"}
```

### Drupal
Place the **Bible Brain player** block and fill in the form, or paste the HTML5 snippet into a Full HTML field with
`data-rest="/bible-brain/api/"`.

### HTML5
```html
<div class="bbp-reader" data-country="IN" data-bible="HINDOV" data-selector="no"
     data-prompts="What did you hear?|What will you do?" data-lang="hi"></div>
```
Use the classes `bbp-reader`, `bbp-audio` or `bbp-video`. Every option above is a `data-` attribute. Set `data-rest` on the
`<script>` tag (or on the element) to point at your proxy.

### JSON mode (what the CMS wrappers output)
```html
<div class="bbp" data-bbp='{"type":"audio","uid":1,"rest":"/wp-json/bible-brain-players/v1/","country":"IN","language":"hin","bible":"HINDOV"}'></div>
```

## JavaScript API

```js
BBPlayers.mount(element);   // initialise one element (for content added after page load)
BBPlayers.boot();           // scan the page again
BBPlayers.locales;          // ["bn","hi","ml","ne","si","ur"]
```

## Deep links

A player writes its state into the page address as `?bbp-<type>=COUNTRY.BIBLE.FILESET.BOOK.CHAPTER`
(for example `?bbp-audio=IN.HINDOV.HINDOVN2DA.JHN.3`). Opening that address restores the same chapter, so shared links
land where the sender was. With several players of one type on a page, the second is `bbp-audio-2`, and so on.

## Standalone proxy settings (`config.php`)

| Key | Default | Meaning |
|---|---|---|
| `api_key` | `` | Your Bible Brain key (or set `BIBLE_BRAIN_KEY`) |
| `cache_hours` | `12` | How long to cache responses |
| `allowed_countries` | `` | Comma list, empty means all |
| `allowed_origins` | `*` | Comma list of websites allowed to use the proxy (or `BIBLE_BRAIN_ALLOWED_ORIGINS`) |
| `rate_limit` | `300` | Requests per visitor per five minutes |
| `cache_dir` | `./cache` | Writable folder (or `BIBLE_BRAIN_CACHE_DIR`) |
| `api_base` | Bible Brain | Override for testing (or `BIBLE_BRAIN_API_BASE`) |

## Downloader command line

```text
--key KEY            (or BIBLE_BRAIN_KEY)
--list-countries
--country IN  [--language hin] --list-filesets [--all-types]
--fileset ID [--type audio|audio_drama|text_usx|text_plain|...] [--out FOLDER]
--version
```
Exit codes: `0` success, `1` an error from the service or a cancel, `2` missing or wrong arguments, `130` interrupted.

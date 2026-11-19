# WordPress.org Plugin Directory

This guide takes the WordPress plugin from this repository to a listing in the official directory.
Rules summarized here come from the [plugin review checklist](https://make.wordpress.org/plugins/handbook/performing-reviews/review-checklist/) and the [Plugin Check and two-factor announcement](https://make.wordpress.org/plugins/2024/10/01/plugin-check-and-2fa-now-mandatory-for-new-plugin-submissions/). Rules change, so read the current versions before you submit.

## 1. What reviewers look for, and where the plugin stands

| Rule | Status in this plugin |
|---|---|
| 100% GPLv2-or-later, declared in the header and readme | Done (`License` and `License URI` in both). |
| Unique prefix for everything the plugin defines | Done. Classes `BBPlay_*`, constants `BBPLAY_*`, option `bbplay_settings`, REST namespace `<slug>/v1`. The old `bbp_` prefix was removed because the bbPress plugin uses it. |
| Trademark: the brand must not be the first word of the name or slug unless you own it | Handled by the two variants below. |
| Remote service disclosed in the readme, with terms and privacy links | Done, in "External services". |
| All scripts and styles local, loaded with `wp_enqueue_*` | Done for the package built by the script (hls.js bundled, CDN removed). |
| No tracking without opt-in, no cookies set | Nothing is sent by the plugin. Analytics events go to `window.dataLayer` only if the site already has it. |
| Escape output, sanitize input, nonces and capability checks on admin actions | Implemented in the code. A human and Plugin Check must still confirm it on a real site. |
| No errors with `WP_DEBUG` on | **Not yet verified.** Test this yourself (section 4). |

## 2. Choose the name and build the package

**Independent publisher** (you are not Faith Comes By Hearing):

```bash
python scripts/make_wporg_package.py --variant independent \
    --wp-user YOUR_WPORG_USERNAME --tested-up-to X.Y --hls path/to/hls.min.js --author "Shalom Design S2dio (SDS)"
```

Result: plugin name "Audio Bible Players for Bible Brain", slug `audio-bible-players-for-bible-brain`.

**Official publisher** (a Faith Comes By Hearing account submits it):

```bash
python scripts/make_wporg_package.py --variant official \
    --wp-user FCBH_WPORG_USERNAME --tested-up-to X.Y --hls path/to/hls.min.js --author "Faith Comes By Hearing"
```

Result: plugin name "Bible Brain Players", slug `bible-brain-players`. Submit this only from the owner's account, and mention in the review notes that the submitter represents the owner.

Notes on the options:

* `--tested-up-to` must be the newest WordPress version **you actually tested**. Do not guess.
* `--hls` is the official `hls.min.js` file of version 1.5.17 (or newer, then change the version number in `includes/class-bbp-shortcodes.php` and in the readme). Download it from the [hls.js releases](https://github.com/video-dev/hls.js/releases) and keep the file unchanged. hls.js is Apache 2.0 licensed, which is compatible with GPL version 2 or later.
* Output goes to `dist/wporg/`: the plugin folder, a ZIP ready to upload, and `<slug>-assets/` with the icon, banners and screenshots.

## 3. Create your account

1. Make a WordPress.org account for the publisher. Use an address on the organization's own domain for the official path.
2. Turn on two-factor authentication (required for new submissions).
3. Choose the Contributors name carefully. It must be the username of a real account.

## 4. Test before you submit

On a clean WordPress site with `WP_DEBUG` and `WP_DEBUG_LOG` turned on:

1. Install the ZIP from `dist/wporg/`. Activate. Open Settings > Bible Brain Players, paste a real key, press "Test connection".
2. Add the three shortcodes to a page and use each player on a phone-size window. Confirm audio, video and text load and the debug log stays empty.
3. Deactivate and delete the plugin and confirm the settings are removed.
4. Install the [Plugin Check](https://wordpress.org/plugins/plugin-check/) plugin, run it on the plugin, and fix every error. Warnings should be understood and, if kept, explained in the review notes.
5. Check the readme with the [readme validator](https://wordpress.org/plugins/developers/readme-validator/).

## 5. Submit

1. Go to <https://wordpress.org/plugins/developers/add/> while logged in.
2. Upload the ZIP. Paste the review notes below in the field for extra information.
3. Wait for the email. Reply to reviewer questions on the same thread and fix every point they raise before you reply.
4. When approved you receive an SVN address. Upload the plugin to `trunk/`, copy it to `tags/<version>/`, and put the contents of `dist/wporg/<slug>-assets/` into the **top-level** `assets/` folder (not inside `trunk/`).

### Notes for the reviewer (copy and adapt)

> This plugin shows a Bible reader, audio player and video player. It connects to the Bible Brain API (https://4.dbt.io/api), run by Faith Comes By Hearing, to look up the available languages and Bible versions and to get the text, audio and video links. The service, its terms, license and privacy links are listed in the "External services" section of the readme. An API key is required. It is free and the settings page explains how to request it. The key is stored in the options table and used only on the server by the plugin's REST proxy; it is never printed on public pages.
>
> The plugin sets no cookies and does not track visitors. Player choices are kept in the visitor's browser local storage. Analytics events are pushed to `window.dataLayer` only when the site already provides it.
>
> hls.js 1.5.17 (Apache 2.0) is bundled unchanged in `assets/vendor/hls.min.js` so video works in Chrome, Firefox and Edge. Its readable source is at https://github.com/video-dev/hls.js.
>
> Public read-only REST routes are intentional: they let the front-end players work for logged-out visitors. They accept only a fixed list of endpoints, validate every parameter, never accept an arbitrary URL, and are rate limited per visitor.
>
> [Official variant only] I submit this plugin on behalf of Faith Comes By Hearing, the owner of the Bible Brain name.

### Short text for forms

* **Name:** see section 2.
* **One-line description (150 characters at most):** Audio-first Bible Reader, Audio Player and Video Player. Visitors choose country, language and version. Uses the free Bible Brain API.
* **Tags (five at most):** bible, audio bible, scripture, video, gospel.
* **Long description, FAQ, installation, changelog:** already in `readme.txt`.

## 6. Questions reviewers often ask

* *"Why are the REST routes public?"* Answer as in the notes above.
* *"Does the plugin send data to a third party?"* Yes, only to the Bible Brain API from the server, as described in "External services". No visitor personal data is sent.
* *"Why is there a minified file?"* It is the unmodified official hls.js release, with the source link in the readme.
* *"Who owns the Bible Brain name?"* Faith Comes By Hearing. The independent variant says it is not endorsed. The official variant is submitted by the owner.

## 7. After approval

* Keep `Stable tag` in `readme.txt` equal to the newest tag in SVN, and keep the plugin header version equal to it (`python scripts/check_versions.py` checks the repository copy).
* For each release, update the plugin in `trunk/`, create `tags/<new version>/`, change `Stable tag`, and add the changelog lines.
* Optional: automate the SVN steps with the community [10up deploy action](https://github.com/10up/action-wordpress-plugin-deploy). This repository does not include it because it has not been tested here.
* Respond to the support forum. Unanswered topics can lead the team to close a plugin.

## 8. Known limits to tell reviewers and users honestly

* The interface translations for Hindi, Bengali, Nepali, Sinhala, Urdu and Malayalam are drafts that need review by native speakers. The plugin has no `.po` files yet; strings that use `__()` are in English unless the page language has a built-in player translation.
* The plugin has not been tested on every WordPress version or with every theme.

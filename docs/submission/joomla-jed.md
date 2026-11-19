# Joomla Extensions Directory (JED)

This guide lists the Joomla plugin in the [Joomla Extensions Directory](https://extensions.joomla.org/). The rules below come from the JED [submission requirements](https://extensions.joomla.org/support/knowledgebase/submission-requirements/submission-requirements-made-easy). JED rules change, so read the current page and the "JED entries checklists" before you submit.

## Requirements and where the plugin stands

| Requirement | Status |
|---|---|
| GPL licence only, and the `<license>` tag in the manifest is correct | Yes: "GNU General Public License version 2 or later". |
| GPL notice in PHP files (PH1) | Added as `@license GPL-2.0-or-later` in the PHP file headers. |
| `defined('_JEXEC') or die;` at the top of PHP files (PH2) | Present in `services/provider.php` and `src/Extension/Biblebrain.php`. The shared library `lib/BibleBrainCore.php` has no guard, because the same file also runs outside Joomla. It only declares classes and prints nothing. If a JED reviewer insists, add the guard to the Joomla copy after each sync. |
| Installs through the Joomla installer without manual steps | Yes: one ZIP with the manifest at its root, built by `python scripts/build.py`. |
| Update server listed in the manifest (US1) | **Not yet.** Create it (section below). |
| Name does not contain "Joomla" or similar without permission | The name "Bible Brain Players" is fine. Do not add "Joomla" to the name. |
| Not listed on the Vulnerable Extensions List, or marked resolved | Search the Vulnerable Extensions List (linked from the JED knowledgebase) before you submit. |
| Works on a supported Joomla version | The manifest uses the Joomla 4 and 5 plugin structure. **Not yet tested inside Joomla**; test on a clean site first. |

## Steps

1. **Account.** Register on extensions.joomla.org and read the terms of service (the TOS).
2. **Fill in the manifest author fields.** In `packages/joomla/plg_system_biblebrain/biblebrain.xml` replace the placeholder author with a real name, and add `<authorEmail>` and `<authorUrl>` lines. These show on the installed extension and in JED.
3. **Add an update server.**
   * Copy `docs/submission/templates/joomla-update.xml` to a stable https address you control, for example a `joomla-update.xml` in your repository's GitHub Pages site, and fill in the values (the download address, the SHA-256 of the ZIP, your name).
   * Add this block to the manifest, before `</extension>`, with your real address:

     ```xml
     <updateservers>
         <server type="extension" priority="1" name="Bible Brain Players">https://YOUR-HOST/joomla-update.xml</server>
     </updateservers>
     ```
   * Rebuild with `python scripts/build.py`. Compute the SHA-256 with `sha256sum dist/plg_system_biblebrain-joomla-1.1.0.zip`.
4. **Test.** On a clean Joomla site: install the ZIP, enable "System - Bible Brain Players", paste the key, put a `{bbp audio ...}` tag into an article, check audio, video and text, then uninstall and confirm nothing is left. Try the update by installing an older version first.
5. **Prepare the listing material** below: a clear logo and two or three screenshots help (the form states the exact sizes). Use the screenshots in `docs/images/` only as a starting point; take new ones from a real Joomla site.
6. **Submit** at <https://extensions.joomla.org/> with "Add extension". Choose the closest category the form offers, paste the text below, and add the screenshots and the download or documentation links.

## Listing text (copy and adapt)

**Name:** Bible Brain Players

**Short description (one or two sentences):** Add an audio-first Bible Reader, Audio Player and Video Player to any article with a simple tag. Visitors choose country, language and Bible version, using the free Bible Brain API.

**Long description:**

> Bible Brain Players brings the Bible, in the languages your visitors speak, to your Joomla site. Write a tag such as `{bbp audio country="IN" language="hin" bible="HINDOV"}` in any article and a mobile-first player appears. Visitors choose a country, then a language, then a version.
>
> * Audio player with chapter navigation, speed control and verse highlighting when timing is available.
> * Video player for Gospel films and Bible clips.
> * Bible text reader with adjustable text size and share links.
> * A server-side proxy keeps your API key private and caches answers.
> * Right-to-left support and draft interface translations for Hindi, Bengali, Nepali, Sinhala, Urdu and Malayalam.
> * No cookies and no tracking. Optional analytics events work with the tag tool you already use.
>
> **Requires** a free Bible Brain API key (https://4.dbt.io/api_key/request). The plugin contacts the Bible Brain API (run by Faith Comes By Hearing) from your server. See the terms at https://www.faithcomesbyhearing.com/bible-brain/terms-conditions . Each Bible version has its own license.
>
> Developed by Shalom Design S2dio (SDS), with the help of AI tools. This is an unofficial community project and is not published or supported by Faith Comes By Hearing.

**Documentation link:** the repository's `docs/getting-started.md` (or its published address).

**Support link:** the repository's issue tracker.

## Notes

* Keep the version in the manifest, the update server file, and `VERSION` equal (`python scripts/check_versions.py` covers the first and the last).
* Every release needs a new `<update>` block in the update server file.
* Joomla 6 compatibility is **not tested**. Change the `targetplatform` value in the update template only after you have tested it.

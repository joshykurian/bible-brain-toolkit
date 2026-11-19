=== Bible Brain Players ===
Contributors: bible-brain-toolkit-contributors
Tags: bible, audio bible, scripture, video, gospel
Requires at least: 5.8
Requires PHP: 7.4
Stable tag: 1.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Audio-first Bible Reader, Audio Player and Video Player. Visitors choose country, language and version. Uses the free Bible Brain API.

== Description ==

Put the Bible in the languages your visitors speak, in the form they prefer: listening first, then watching, then reading.

**Three players, three shortcodes**

* `[bb_audio]` - Audio Bible player with chapter navigation, speed control, skip back and forward, optional next-chapter autoplay and verse highlighting when the version provides timing.
* `[bb_video]` - Gospel film and Bible video clips from the catalog, with a clip list and next-clip autoplay.
* `[bb_reader]` - Bible text reader with a chapter picker, adjustable text size, verse numbers and share links.

**Built for oral and mobile-first communities**

* Visitors pick a country, then a language, then a version, so people find their own language quickly.
* Large touch targets, low data use, right-to-left support and interface translations (draft) for Hindi, Bengali, Nepali, Sinhala, Urdu and Malayalam.
* Optional "Share this" buttons for WhatsApp, Telegram, Facebook and a copy-link button.
* Optional reflection prompts that invite listeners to tell the passage back and respond.
* Optional analytics events for Google Tag Manager and other tag tools, sent only if your site already has such a tool.

**Your API key stays on your server**

The plugin has its own small REST proxy. Visitors never see your key, and answers are cached so your key is not used up quickly. A built-in rate limit protects your quota.

**Free key**

You need your own free Bible Brain API key. The settings page explains how to request one in a few minutes.

== External services ==

This plugin connects to the Bible Brain API (DBP v4), a service run by Faith Comes By Hearing. Without it the players cannot show any Scripture content.

**What it is used for:** looking up the countries, languages, Bible versions, books and chapters that are available, and getting the Bible text, audio and video links that the visitor asks to read, hear or watch.

**What is sent and when:** requests are made from your web server, not from the visitor's browser, when a visitor opens a player or changes a selection, and when you use the settings page. Each request carries your API key and the search values (for example country code, language code, Bible version and chapter). No visitor names, e-mail addresses or IP addresses are sent to the Bible Brain API.

**Where audio and video play from:** the Bible Brain API returns temporary, signed links to media files. The visitor's browser then loads those audio and video files directly from the servers named in the links (these are controlled by Faith Comes By Hearing and its content hosting provider), so the visitor's IP address is visible to those servers in the normal way.

* Service: https://www.faithcomesbyhearing.com/audio-bible-resources/bible-brain
* API endpoint used: https://4.dbt.io/api
* Terms and conditions: https://www.faithcomesbyhearing.com/bible-brain/terms-conditions
* License terms: https://www.faithcomesbyhearing.com/bible-brain/license
* Copyright policy: https://www.faithcomesbyhearing.com/copyright-policy
* Privacy policy: https://www.faithcomesbyhearing.com/privacy-policy

Each Bible version has its own license. Please check the license of a version before you use it, and keep the copyright notice that the players show.

**Optional: hls.js from a CDN.** Video streams play natively in Safari and on iOS. Other browsers need the hls.js library. If the file `assets/vendor/hls.min.js` is present in the plugin (it is in releases from WordPress.org), the plugin serves it from your own site and contacts no other service. Only in a build without that file and with the setting "Load hls.js from jsDelivr" turned on does the visitor's browser load it from https://cdn.jsdelivr.net (privacy: https://www.jsdelivr.com/terms/privacy-policy-jsdelivr-net).

== Privacy ==

The plugin does not set cookies and does not track visitors. It stores the visitor's player choices (such as playback speed and last selected version) in the browser's local storage only, so they are remembered on that device. Analytics events are pushed to `window.dataLayer` only if your site already provides it; nothing is sent anywhere by the plugin itself. Your API key is stored in the WordPress options table and is never printed on public pages.

== Third-party libraries ==

* hls.js 1.5.17 - Apache License 2.0 - https://github.com/video-dev/hls.js (file `assets/vendor/hls.min.js` when present; the readable source code is available at that address).

== Using it as a proxy ==

The plugin's REST address (`https://yoursite/wp-json/bible-brain-players/v1/`) also serves the HTML5 embed code from the Bible Brain Toolkit, so one WordPress site can power players on other websites.

== Installation ==

1. Upload the plugin and activate it.
2. Go to Settings > Bible Brain Players. Request a free key at https://4.dbt.io/api_key/request, paste it and save. Use "Test connection" to check it.
3. Choose default versions for the audio, video and reader players.
4. Add a shortcode to any page or post, for example `[bb_audio country="IN" language="hin" bible="HINDOV"]`.

== Frequently Asked Questions ==

= Do I need an API key? =

Yes. It is free. Request one at https://4.dbt.io/api_key/request and paste it on the settings page.

= Can visitors choose their own language? =

Yes. Leave `selector` on (the default) and visitors choose country, language and version. Use `selector="no"` to show one fixed version.

= Which shortcode options are there? =

`country`, `language`, `bible`, `fileset`, `book`, `chapter`, `selector`, `share`, `reflect`, `listen`, `theme` and `title`. The settings page lists them with examples.

= Does it work with the block editor? =

Yes. Add a Shortcode block and paste the shortcode.

= Why does video not play in my browser? =

Safari and iOS play Bible Brain video natively. Chrome, Firefox and Edge need the hls.js library, which is included in the release from WordPress.org. If you installed from another source, see the "External services" section.

= Where can I get help? =

Open a support topic in the plugin's forum. Please include the page address and the text of any message the player shows.

== Screenshots ==

1. The Audio Bible player with country, language and version selectors.
2. The Bible Reader with verse highlighting while audio plays.
3. The Video Player with the clip list.

== Changelog ==

= 1.1.0 =
* Interface translations (draft) for Hindi, Bengali, Nepali, Sinhala, Urdu and Malayalam, chosen from the page language.
* Selector layout adapts to long version names; right-to-left fixes.
* Internal names now use a unique prefix so the plugin works next to other plugins.

= 1.0.0 =
* First release.

== Upgrade Notice ==

= 1.1.0 =
Adds interface translations and layout fixes. Your settings are kept.

== Notes ==

Developed by Shalom Design S2dio (SDS), with the help of AI tools (see AI assistance in the project README). Scripture content is provided by Bible Brain (https://www.faithcomesbyhearing.com/audio-bible-resources/bible-brain), a project of Faith Comes By Hearing (https://www.faithcomesbyhearing.com/).

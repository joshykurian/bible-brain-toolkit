# Bible Brain Players for Drupal 10 and 11

Bible reader, audio player and video player blocks powered by the Bible Brain API.

## Install
1. Copy the `bible_brain` folder to `modules/custom/` (or install with Composer from your own repository).
2. Enable it: Extend > "Bible Brain Players", or `drush en bible_brain`.
3. Open Configuration > Media > Bible Brain Players (`/admin/config/media/bible-brain`).
   Request a free key at https://4.dbt.io/api_key/request, paste it, set default versions, save.
4. Place the block: Structure > Block layout (or Layout Builder) > Place block > **Bible Brain player**.
   Pick Audio player, Bible reader or Video player and enter the country, language and version codes.

## Find the codes
Open `/modules/custom/bible_brain/generator/generator.html?proxy=/bible-brain/api/`, choose a country, language and version, and copy the values.

## Notes
- The players call `/bible-brain/api/<endpoint>` on your own site. The key never reaches the browser.
- You can also paste the HTML5 snippet from the generator into a Full HTML body field. Use `data-rest="/bible-brain/api/"`.
- Needs the "administer bible brain" permission to change settings.
- Check the licence and copyright of each Bible version before you publish it.

## About and links
[Bible Brain](https://www.faithcomesbyhearing.com/audio-bible-resources/bible-brain) |
[Developer documentation](https://www.faithcomesbyhearing.com/bible-brain/developer-documentation) |
[API reference](https://www.faithcomesbyhearing.com/bible-brain/api-reference) |
[Request a free API key](https://4.dbt.io/api_key/request) |
[License](https://www.faithcomesbyhearing.com/bible-brain/license) |
[Terms](https://www.faithcomesbyhearing.com/bible-brain/terms-conditions) |
[Copyright policy](https://www.faithcomesbyhearing.com/copyright-policy)

Developed by Shalom Design S2dio (SDS), with the help of AI tools. This is an unofficial community project and is not published or supported by Faith Comes By Hearing. Scripture content is provided by [Bible Brain](https://www.faithcomesbyhearing.com/audio-bible-resources/bible-brain), a project of [Faith Comes By Hearing](https://www.faithcomesbyhearing.com/). Bible Brain support: support@digitalbibleplatform.com

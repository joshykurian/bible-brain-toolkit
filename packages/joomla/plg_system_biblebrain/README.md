# Bible Brain Players for Joomla 4.4, 5 and 6

A system plugin that adds a Bible reader, audio player and video player anywhere in your pages.

## Install
1. System > Install > Extensions > Upload Package File, choose `plg_system_biblebrain.zip`.
2. System > Plugins, search "Bible Brain", open **System - Bible Brain Players**, set Status to Enabled.
3. Request a free API key at https://4.dbt.io/api_key/request, paste it in the plugin, set default versions, save.

## Use it
Type a tag in any article, custom module or other content:

    {bbp audio country="IN" language="hin" bible="HINDOV"}
    {bbp reader country="IN" bible="HINDOV" selector="no"}
    {bbp video country="IN" language="hin" title="Watch the Gospel"}

Attributes: `country`, `language`, `bible`, `fileset`, `book`, `chapter`, `selector="no"`, `share`, `reflect`, `listen`, `theme`, `title`.

## Find the codes
Open `https://yoursite/media/plg_system_biblebrain/generator/generator.html?proxy=/index.php?bbapi=` and copy the values it shows.

## Notes
- The players call `index.php?bbapi=<endpoint>` on your own site. The key never reaches the browser.
- To translate the player text, paste JSON in "Interface labels", for example `{"country":"देश","language":"भाषा"}`.
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

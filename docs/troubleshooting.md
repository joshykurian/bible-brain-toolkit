# Troubleshooting and FAQ

## The player shows "Something went wrong" or stays empty

1. **Check the proxy.** Open the proxy address in a browser:
   - Standalone: `https://host/bible/bbp-proxy.php?ep=status` should show `"key_set":true`.
   - WordPress: `https://site/wp-json/bible-brain-players/v1/countries`
   - Drupal: `https://site/bible-brain/api/countries`
   - Joomla: `https://site/index.php?bbapi=countries`
   You should see JSON with a list of countries.
2. **"No Bible Brain API key has been set yet."** Add the key in the plugin or module settings, or in `config.php`.
3. **"Invalid API key"** The key was copied with a space, has not been activated yet, or belongs to another service.
4. **403 or a blank page from the proxy.** Some hosts block `wp-json` or unusual query strings. Ask the host, or try the standalone proxy.
5. **Browser console.** Press F12, open Console and Network, reload and look for red lines. Remove your key before sharing screenshots.

## No versions are listed for my country or language
Bible Brain lists content per country. Try a neighbouring country, check that the **media type** exists (audio, video or text exist
independently), and make sure `allowed_countries` is not restricting the list. Video filesets are far fewer than audio or text.

## Audio plays but there is no sound or it stops
Signed links expire. Reload the page. If the problem repeats, your server clock may be wrong, or a content filter is blocking the CDN.
Check that the visitor's network allows media from the Bible Brain CDN hosts.

## Video does not play
The video player uses HLS. Safari plays it natively. Other browsers use **hls.js**, loaded from a public CDN by default. If your
site has a strict Content Security Policy, allow that CDN or host the file yourself (WordPress: `assets/vendor/README.txt`
explains how, and "Load hls.js from jsDelivr" in Settings controls the CDN).

## Too many requests
The proxy limits each visitor to 300 requests per 5 minutes. Normal use stays far below that. If you run a classroom behind one
network address, raise `rate_limit`.

## The HTML5 embed does not work on Wix, Blogger or Squarespace
Those builders often strip scripts. Use the generator's **iframe** option instead.

## Cache problems
Delete the files in `cache/` (standalone) or use "Clear cached catalog data" on the WordPress settings page. Changed settings usually need a
cache clear before they show.

## Text shows in the wrong direction
Right-to-left languages need `lang="ur"` (or another RTL code) on the page or `data-lang` on the player.

## FAQ

**Is this an official Bible Brain product?** No. It is a community toolkit that uses the public Bible Brain API.

**Is it free?** The toolkit is free software. Bible Brain keys are free to request, but you must follow its terms.

**Can I use it offline?** The players need a connection. The downloader can prepare files for offline distribution where the
licence allows it.

**Can I change the colours?** Yes: `accent`, `theme`, or override the CSS variables beginning with `--bbp-`.

**Can I show only one Bible version?** Yes. Use `selector="no"` with `bible="..."`.

**Can I add several players to one page?** Yes. Each gets its own deep link parameter.

**Does it track my visitors?** The players do not set cookies or send data anywhere except to your own proxy. They only emit
events locally for your analytics tool, see [analytics](analytics.md). They remember a visitor's last selection in the browser's
local storage.

**Can I download whole Bibles from the web players?** No. Use the downloader, and only where the licence permits.

**Where do I report a bug?** Open an issue on GitHub. Please remove your API key from anything you paste.

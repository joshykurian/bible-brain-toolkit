# Security policy

## Supported versions
The latest release receives security fixes.

## Reporting a vulnerability
Please **do not** open a public issue. Use GitHub's private reporting: **Security > Report a vulnerability** on this repository
(maintainers: enable "Private vulnerability reporting" in the repository settings). Include what you found, how to reproduce it
and the versions affected. We aim to acknowledge reports within a few days and to fix confirmed issues promptly.

## What the toolkit does to protect sites
- The Bible Brain **API key stays on the server**. It is never sent to a browser, included in a response or placed in a URL the
  browser sees. Automated tests check this.
- **Strict validation** of every proxy parameter (identifiers, book codes, chapter numbers, media types) before any upstream call.
- **Output encoding**: text from the API is inserted as text, not HTML.
- **Rate limiting** per visitor and **response caching** to protect your quota.
- **Allow-lists**: optional country list, and an origin list (`allowed_origins`) so other websites cannot use your proxy.
- Cache and configuration folders are protected with `.htaccess` files on Apache. On other servers keep `config.php` outside the
  web root and block the `cache/` folder.
- The downloader stores a saved key in a file readable only by its owner (permissions 0600) and only if the user ticks "Remember key".

## Your responsibilities
- Keep the key secret. Rotate it if it leaks.
- Set `allowed_origins` to your own sites for the standalone proxy.
- Keep WordPress, Drupal, Joomla and PHP up to date.
- Serve everything over HTTPS.
- Review the licence of every Bible version you publish.

## Known limits
- The default `allowed_origins` is `*`, which is convenient for testing. Restrict it in production.
- The rate limiter is per visitor address and is not a replacement for a firewall.

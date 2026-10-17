# Ploetner Site TXT

**llms.txt and security.txt for every site in a WordPress multisite network.**

Each site answers with its own files: [`/llms.txt`](https://llmstxt.org/) and
[`/.well-known/security.txt`](https://www.rfc-editor.org/rfc/rfc9116), set
under Settings, Reading. Works for subdirectory networks, mapped domains and
multi-network installs that share one document root.

Built for the Ploetner network (ploetner.cloud, ploetner.dev, msls.co, ...).
Formerly `ploetner-llms-txt`: the llms.txt option and filter keep their names,
so existing content stays.

## Requirements

- WordPress **7.0+**
- PHP **8.1+**

## How it works

- **Per-site text:** Settings, Reading, field "llms.txt". Markdown, served as is.
- **Generated default** when the field is empty: the site title as heading
  (host name if there is none), the tagline as summary, and up to 25 published
  top-level pages as links (front page first, manual excerpts as descriptions,
  password-protected pages left out).
- **Served by WordPress:** the request path is matched on `parse_request`, so no
  rewrite rules need flushing on any site. Works for sites in subdirectories
  (`example.com/site/llms.txt`) and with mapped domains.
- **Response:** `200`, `text/plain; charset=utf-8`, `Cache-Control: public,
  max-age=3600`, `X-Robots-Tag: noindex`, `X-Content-Type-Options: nosniff`. `HEAD` gets headers only. An empty
  result answers `404`.

### security.txt

- **Where:** RFC 9116 looks at the root of a host only. The site at the root of
  each domain gets the fields (the main site of a subdirectory network, every
  site with its own domain); sites in a subdirectory show a note instead.
- **Fields:** Contact (required; e-mail addresses become `mailto:`, `https` and
  `tel:` links pass), Expires (required; empty or past becomes one year from
  today, minus one day, on save), Policy, Encryption and Acknowledgments (optional, `https`
  only), Preferred-Languages (defaults to the site language). `Canonical` is
  added automatically.
- **No contact, no file:** without a contact the path answers `404`.
- **Reminder:** administrators see a dashboard notice from 30 days before
  Expires, and after it has passed.

**Do not put a physical `llms.txt` or `.well-known/security.txt` in the
document root.** The web server would serve that one file for every site of
the network, before WordPress runs. If your CDN generates a security.txt
(e.g. Cloudflare), use one or the other, not both.

### Filters

```php
// Add a section on one brand's sites, or return '' to switch llms.txt off (404).
add_filter( 'ploetner_llms_txt', function ( string $content, int $blog_id, bool $stored ): string {
	return $content . "\n\n## Optional\n\n- [Site check API](https://ploetner.cloud/wp-json/ploetner-cloud/v1/check?url=example.com)";
}, 10, 3 );

// Change or switch off the security.txt of a site (empty string: 404).
add_filter( 'ploetner_security_txt', function ( string $content, int $blog_id ): string {
	return $content;
}, 10, 2 );
```

## Installation

**Multisite (one network):** network-activate the plugin.

**Multi-network:** network activation is per network. To load it on every site
of every network, use a must-use loader instead of activating it:

```php
<?php
// wp-content/mu-plugins/ploetner-site-txt.php
require WP_PLUGIN_DIR . '/ploetner-site-txt/ploetner-site-txt.php';
```

Single sites work too: activate it like any plugin.

## Branching and releases

Development happens on `dev` (the default branch). Features land via pull
request into `dev`; CI (PHPCS, PHPStan, PHPUnit on PHP 8.1/8.2/8.3) runs on
every pull request and on pushes to `dev`.

On each push to `dev`, the **Build** job assembles the deployable plugin
(`composer install --no-dev`, filtered through `.distignore`) and force-pushes
it to `main`. A version tag (`v*`) builds `ploetner-site-txt.zip` and attaches it
to a GitHub Release.

## Quality checks

```bash
composer phpcs     # WordPress-Extra coding standard
composer phpstan   # static analysis, level 8
composer phpunit   # Brain Monkey unit tests
composer qa        # all three
```

## Translations

German ships in `languages/`.

```bash
vendor/bin/wp i18n make-pot . languages/ploetner-site-txt.pot
vendor/bin/wp i18n update-po languages/ploetner-site-txt.pot languages
vendor/bin/wp i18n make-mo languages && vendor/bin/wp i18n make-php languages
```

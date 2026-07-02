# MantisBT EmailReporting Plugin

**Version 1.0.0** (this fork)

The EmailReporting plugin allows you to report an issue in MantisBT by sending an
email to a particular mail account. It can also add notes/attachments to an existing
issue by replying to it.

This copy is a locally maintained fork of the official plugin (based on upstream
version 0.10.1, see [Original project](#original-project) below) with additional
fixes and features applied on top — see [Current state of this fork](#current-state-of-this-fork)
and the [Changelog](doc/CHANGELOG.txt) for details.

Requirements (this fork)
=========================
* MantisBT 1.3.0 or higher (same as upstream 0.10.x). **Compatible and tested with
  MantisBT 2.28.4.**
* **PHP 8.0 or higher.** Older PHP versions are no longer supported by this fork —
  see the PHP 8 compatibility work below.
* `/api/soap/mc_file_api.php` must be present (standard MantisBT SOAP API file)
* Optional: `mbstring` extension (charset conversion), `OpenSSL` (mail encryption)

Setup
=====
1. Extract/clone this repository into `mantis/plugins/EmailReporting/` (the folder
   name must be `EmailReporting`).
2. Open "Manage Plugins" in MantisBT and install/enable EmailReporting; it should show
   as version 1.0.0.
3. Go to "Manage EmailReporting" to add a mailbox (POP3/IMAP host, credentials,
   target project) and adjust the plugin's configuration options.
4. Schedule `scripts/bug_report_mail.php` to run periodically, e.g. via cron:
   ```
   */5 * * * * /usr/local/bin/php /path/to/mantis/plugins/EmailReporting/scripts/bug_report_mail.php
   ```
   See `doc/INSTALL.txt` for the full set of scheduling notes (webserver-triggered
   alternative, Windows scheduling, etc.) — those are unchanged from upstream.
5. **Optional but recommended: cron failure alerting.** Since PHP fatal errors halt
   the script silently as far as cron is concerned, this fork adds
   `scripts/bug_report_mail_cron.sh`, a wrapper that emails you if the job fails (or,
   with `-s`, also when it succeeds — handy for testing the alert itself):
   ```
   cd mantis/plugins/EmailReporting/scripts
   cp bug_report_mail_cron.env.example bug_report_mail_cron.env
   # edit bug_report_mail_cron.env: set PHP_BIN and ALERT_EMAIL
   chmod +x bug_report_mail_cron.sh
   ```
   Then point cron at the wrapper instead of `bug_report_mail.php` directly:
   ```
   */5 * * * * /path/to/mantis/plugins/EmailReporting/scripts/bug_report_mail_cron.sh
   ```
   `bug_report_mail_cron.env` is gitignored (it's server-specific and not meant to be
   committed) — copy it fresh from the `.example` file on each server you deploy to.
   The wrapper sends mail via `sendmail`, so a working local MTA (or `sendmail`-compatible
   relay such as Postfix, Exim, or msmtp) is required for alerts to actually be delivered.

Utility scripts
================
In addition to the standard `scripts/bug_report_mail.php` (the actual mail-fetching
job, unchanged from upstream) and `scripts/bug_report_mail_cron.sh` (cron failure
alerting, see [Setup](#setup) above), this fork includes:

### `scripts/patch_mantis_html_email.sh`
Patches **MantisBT core** (not this plugin) so that outgoing notification emails are
sent as HTML instead of plain text. It edits `core/email_api.php` and
`core/classes/EmailSenderPhpMailer.class.php` to wrap the email body in
`<html><body>...</body></html>` (converting newlines to `<br />`) and switches
PHPMailer to `isHTML( true )`.

**Usage:**
```
scripts/patch_mantis_html_email.sh /path/to/mantis
```
The argument is your MantisBT root directory (defaults to `public_html` if omitted).
The script:
* takes timestamped backups of both files before touching them
  (`<file>.bak.YYYYMMDD_HHMMSS`)
* is idempotent — safe to re-run, it detects and skips already-applied patches
* prints the backup paths and a `diff -u` command to review exactly what changed

**Important caveats:**
* Written against and only tested with **MantisBT 2.28.4**, where email sending was
  restructured (the `EmailSenderPhpMailer` class and the `email_api.php` body-building
  code this script targets). It is **not tested against any other MantisBT version**
  — older or newer releases may have a different structure that the `sed`/`grep`
  patterns in this script don't match, in which case it will simply report that it
  can't find what it's looking for rather than silently patching the wrong thing.
* This patches MantisBT **core** files directly, not the plugin. Since it's a core
  hack rather than an official option, it will need to be **re-applied after any
  MantisBT core upgrade** (upgrades will overwrite the patched files with the
  original, plain-text versions).
* It assumes the two target files match the exact structure this script's `sed`/
  `grep` patterns expect (based on the stock MantisBT PHPMailer email sender); if
  your MantisBT version has customized those files already, verify the diff before
  trusting the patched output.
* To roll back, restore from the timestamped `.bak` files it creates.

Current state of this fork
===========================
On top of the official 0.10.1 release, this copy includes:

* **PHP 8.0–8.4 compatibility.** The upstream code (including the vendored PEAR
  libraries under `core_pear/`) predates PHP 8 and had several fatal errors and
  deprecation warnings under it, including a parse-time fatal error in the
  HTML-to-Markdown converter that broke the plugin outright on PHP 8.3+. All known
  fatal errors and dynamic-property deprecations have been fixed; the plugin no
  longer supports PHP versions older than 8.0.
* **Fixed a charset-detection bug** that could turn accented characters (e.g.
  Hungarian ő/ű/ö/ü, or other Central/Western European letters) into `?`, or in
  worse cases silently drop the entire email body/subject. The mail parser
  (`core/Mail/Parser.php`) now validates the declared charset against the actual
  content instead of trusting a broken auto-detect mode, and no longer discards text
  when charset conversion fails.
* **New: cut off long quoted reply/history text.** A new option,
  "Cut off quoted reply/history text" (`mail_strip_quoted_lines`), truncates the
  description/note once a run of consecutive `>`-quoted lines is found, regardless of
  mail client or language. This complements the existing (English-only, or
  exact-text-match) reply-removal options. Off by default; configurable via the
  "Minimum number of consecutive quoted lines" setting.
* **New issues from private projects are created as private.** Previously all
  emailed-in issues used the site/project default visibility regardless of the
  target project; new issues now inherit `VS_PRIVATE` when the target project itself
  is private.
* **New: cron failure alerting.** `scripts/bug_report_mail_cron.sh` wraps the
  scheduled job and emails you (via `sendmail`) if it fails, so a broken mailbox or a
  PHP error doesn't fail silently. See [Setup](#setup) above.

Feature set
===========
* Create a new issue from an incoming email
* Add a note (and/or attachments) to an existing issue by replying to it, matched via
  Message-ID/References or via the issue ID in the subject line
* Fetch mail via POP3 or IMAP, with per-mailbox authentication (USER, LOGIN, PLAIN,
  APOP, CRAM-MD5, DIGEST-MD5, SCRAM-SHA-*), optional STARTTLS/SSL, and per-mailbox
  project/category assignment
* Multiple mailboxes, each independently filtered/configured; IMAP folder-per-project
  support
* Parse plain text, HTML (optionally converted to Markdown) and MIME-encoded emails,
  including signed emails
* Attachments: add email attachments to the issue, block specific attachments by MD5
  hash, log rejected attachments, limit description/note size with optional overflow
  attachment
* Reply/signature cleanup: strip signatures by delimiter, remove exact-match or
  Gmail-style quoted replies, remove MantisBT's own notification footer from replies,
  and cut off long generic quoted-reply blocks
* Reporter handling: report as a single fixed "mail" user, resolve reporters by
  sender email address, optionally auto-create new user accounts, optional LDAP
  lookup, disposable-email checking, fallback reporter on failure
* Use the email's priority header to set the issue priority, with a configurable
  priority mapping
* New issues inherit their target project's visibility (private projects create
  private issues)
* Debug mode: save raw/parsed emails to disk, track memory usage, optionally attach
  the complete original email to the issue
* Cron failure alerting via an optional wrapper script (see above)

License
=======
GPL-2.0, same as the original plugin and MantisBT itself — see [LICENSE](LICENSE).
The vendored third-party code keeps its own license: `core_pear/` (PEAR, Auth_SASL,
Net_POP3/IMAP, Mail_mimeDecode) is BSD-licensed, and `core/Mail/Markdownify/` is
LGPL-licensed; see the header comments in those files.

Original project
=================
This plugin is originally developed and maintained by the MantisBT team and
contributors, and hosted at
[github.com/mantisbt-plugins/EmailReporting](https://github.com/mantisbt-plugins/EmailReporting).
For the unmodified, officially released version, requirements, downloads and support
channels, see below.

Requirements
------------
EmailReporting v0.10.0 and later versions:

* MantisBT 1.3.0 or higher

EmailReporting v0.9.x:

* MantisBT 1.2.6 until 1.3.99

Optional:

* PHP 7.0 is supported from EmailReporting 0.9.2 and higher
* PHP 7.1 is supported from EmailReporting 0.10.0 and higher

EmailReporting v0.8.4 and earlier versions:

* MantisBT 1.2.0 until 1.2.5

All versions:

* Ability to set scheduled / cron jobs on the webserver
* /api/soap/mc_file_api.php is required for EmailReporting to function properly

Download
--------
The stable releases can be downloaded from the GitHub downloads page: https://github.com/mantisbt-plugins/EmailReporting/releases
The development versions are not meant for production environments. Use at your own risk

Source code
-----------
EmailReporting plugin is hosted in GitHub along with other MantisBT plugins. GitHub URL: https://github.com/mantisbt-plugins/EmailReporting

Support
-------
### Documentation
https://www.mantisbt.org/wiki/doku.php/mantisbt:plugins:emailreporting

### Forum
Please use forum to get help in installing and using EmailReporting plugin. Visit [EmailReporting Forum](https://www.mantisbt.org/forums/viewforum.php?f=13)

### Bug Tracker
To report an issue or feature request for EmailReporting plugin, visit [Mantis BugTracker](http://www.mantisbt.org/bugs/set_project.php?project_id=10). (Make sure that you select the correct project from the drop-down)

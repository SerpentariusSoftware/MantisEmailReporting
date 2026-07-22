# MantisBT EmailReporting Plugin

**Version 1.0.8** (this fork)

The EmailReporting plugin allows you to report an issue in MantisBT by sending an
email to a particular mail account. It can also add notes/attachments to an existing
issue by replying to it.

This copy is a locally maintained fork of the official plugin (based on upstream
version 0.10.1, see [Original project](#original-project) below) with additional
fixes and features applied on top — see [Current state of this fork](#current-state-of-this-fork)
and the [Changelog](doc/CHANGELOG.md) for details. For a full reference of every
configuration option (also available in-app under "Manage EmailReporting" →
"Documentation"), see [doc/DOCUMENTATION.md](doc/DOCUMENTATION.md).

Requirements (this fork)
=========================
* **MantisBT 2.0.0 or higher.** Older MantisBT versions (pre-2.0, using the old
  `html_page_top`/`html_page_bottom`/`print_bracket_link` layout API) are no longer
  supported by this fork — see below. **Compatible and tested with MantisBT 2.28.4.**
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
5. **Optional but recommended: cron failure alerting.** Since PHP fatal errors — and,
   as of this fork, a mailbox that fails to process (bad login, connection refused,
   disabled mailbox, wrong project, etc.) — would otherwise go unnoticed as far as
   cron is concerned, this fork adds `scripts/bug_report_mail_cron.sh`, a wrapper that
   emails you if the job fails (or, with `-s`, also when it succeeds — handy for
   testing the alert itself):
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
   Since this typically runs every few minutes, a persistent failure won't send one
   email per run — once an alert is sent, further alerts are held off for
   `ALERT_COOLDOWN_SECONDS` (default 6 hours) until the job either succeeds again or
   the cooldown elapses.

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

* **New: local, up-to-date documentation with per-option descriptions.** The
  "[?]" help link next to every option in "Manage Configuration Options" and
  "Manage Mailboxes" used to point at the upstream (pre-fork) plugin's wiki
  page, which doesn't describe this fork's own options (several have been
  renamed, added, or removed since 0.10.1). It now points at
  [doc/DOCUMENTATION.md](doc/DOCUMENTATION.md), rendered by this fork's
  "Documentation" nav tab and anchored straight to the matching property -
  including full descriptions for options this fork added that were
  previously undocumented anywhere (`mail_monitor_make_public`,
  `mail_monitor_exclude_addresses`, `mail_strip_quoted_lines`,
  `mail_strip_quoted_lines_min_lines`). The Change Log and Documentation
  pages both now render real Markdown instead of a wall of preformatted
  plain text.
* **New: redesigned "Manage Mailboxes".** The page starts on a minimal screen
  showing only the mailbox picker and an Add button; the settings form only
  appears once you pick a mailbox or click Add, repopulating instantly via
  JavaScript (no page reload) and toggling the IMAP-only fields based on
  mailbox type. Add/Save/Copy/Test/Complete test/Delete are individual
  buttons that submit immediately, replacing the old
  radio-button-plus-generic-submit-button flow. Clicking Add while the form
  already shows something asks for confirmation before discarding it; Delete,
  Add-with-blank-fields, and Complete test each ask for confirmation before
  proceeding. Test and Complete test no longer submit the form or reload the
  page at all - they run in the background against whatever's currently in
  the fields (saved or not) and show the result inline, so testing
  connection settings for a mailbox you haven't saved yet no longer risks
  losing what you've typed. As part of all this, the mailbox password field
  is no longer pre-filled with the stored (decoded) password: leaving it
  blank now means "keep the current password" rather than clearing it, which
  also means the stored password is never sent back to the browser at all
  anymore. The switcher's JavaScript ships as a real external file
  (`files/manage_mailbox.js`) rather than an inline `<script>` tag, since
  MantisBT's default CSP (`script-src 'self'`) silently blocks inline
  scripts entirely.
* **Fixed a stray unclosed `<form>` tag** that used to be on "Manage EmailReporting"
  (in the old mailbox-selector form at the bottom of the page, since removed
  by the redesign above).
* **New: Cc/To auto-monitor exclusion list, and an option to auto-publicize on
  monitor add.** The existing "Add users to issue monitoring list from Cc and To
  fields in mail header" option (`mail_add_users_from_cc_to`) now has two
  companion options: `mail_monitor_exclude_addresses` (prefix match, any domain -
  e.g. `helpdesk@` matches any domain), so addresses like your own monitored
  mailbox's address never get auto-subscribed to their own notifications and
  create a feedback loop; and `mail_monitor_make_public` (default ON), since
  MantisBT's notification filter only exempts the *reporter* from the
  private-issue access check (not monitors) - without it, adding a Cc/To monitor
  to a private issue wouldn't actually get them the notifications they were just
  subscribed to unless the project already gave them sufficient access,
  previously requiring doing this by hand.
* **Fixed accented characters in the email subject turning into `?`.** The
  charset-detection fix below (originally applied to the body) had never been
  mirrored for the Subject header: an RFC 2047 encoded-word's declared charset
  was trusted unconditionally, so a mail client/webmail gateway mislabeling it
  (e.g. tagging ISO-8859-2 text as UTF-8) made accented characters silently
  turn into `?`. Header decoding now validates the declared charset against the
  actual bytes and falls back to detection, the same as the body; raw,
  non-RFC2047 8-bit subjects are now also charset-detected instead of being
  passed through unconverted.
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
* **New: cron failure alerting, including per-mailbox failures.** `scripts/bug_report_mail_cron.sh`
  wraps the scheduled job and emails you (via `sendmail`) if it fails. `bug_report_mail.php`
  itself now also exits non-zero if any individual mailbox failed to process (bad
  login, connection refused, disabled mailbox, wrong project, etc.) — previously such
  failures were only echoed into the job's own output and never surfaced as a failure,
  so a broken mailbox could go unnoticed indefinitely. Repeat alerts for an ongoing
  failure are held off for `ALERT_COOLDOWN_SECONDS` (default 6 hours) so a persistent
  failure doesn't send one email per cron run. See [Setup](#setup) above.
* **Dropped MantisBT 1.x compatibility.** Removed the legacy pre-2.0 layout code paths
  (`html_page_top`/`html_page_bottom`/`print_bracket_link`, none of which exist in
  MantisBT 2.x), which were dead weight given this fork already requires MantisBT
  2.0.0+. Also fixed a live bug this cleanup surfaced: `manage_config_edit.php` called
  `print_bracket_link()` unconditionally (not behind the legacy-version check), which
  no longer exists in MantisBT 2.x and threw a fatal error whenever the "Bug priority
  mapping" config field failed validation on save.
* **Fixed duplicate relationship history/email on reply-linked issues.** When a reply
  links a new issue to an existing "master" bug, the plugin called MantisBT's
  `relationship_add()` and then manually repeated the history logging and notification
  email that `relationship_add()` already performs internally — resulting in duplicate
  history entries on both issues and a duplicate notification email to the new issue's
  watchers. The manual duplicate calls have been removed.
* **Fixed a false-positive "Manage EmailReporting" database collation report.** The
  built-in collation check compared collation names against the literal string
  `"utf8_"`, which stopped matching once MySQL/MariaDB started naming these collations
  `utf8mb3_*`/`utf8mb4_*` — so it flagged every single table and column as `BAD`
  regardless of actual collation, including ones already on the better `utf8mb4`. It
  now correctly recognizes any UTF-8-family collation.
* **Updated the "scheduled job" hint on "Manage EmailReporting"** to recommend
  `scripts/bug_report_mail_cron.sh` (this fork's failure-alerting wrapper, see above)
  instead of only listing the two raw ways to invoke `bug_report_mail.php` directly,
  and clarified that the second option is for a one-off manual test, not for
  scheduling.

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
* Optionally auto-add Cc/To recipients as issue monitors (with a configurable
  address exclusion list, and auto-publicize private issues so they're notified)
* Debug mode: save raw/parsed emails to disk, track memory usage, optionally attach
  the complete original email to the issue
* Cron failure alerting via an optional wrapper script (see above)

License
=======
GPL-2.0, same as the original plugin and MantisBT itself — see [LICENSE](LICENSE).
The vendored third-party code keeps its own license: `core_pear/` (PEAR, Auth_SASL,
Net_POP3/IMAP, Mail_mimeDecode) is BSD-licensed, `core/Mail/Markdownify/` is
LGPL-licensed, and `core/Markdown/` (Parsedown, used to render the Change Log
page) is MIT-licensed; see the header comments and `LICENSE`/`LICENSE.txt`
files in those directories.

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

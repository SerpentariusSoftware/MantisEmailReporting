# MantisBT EmailReporting Plugin

The EmailReporting plugin allows you to report an issue in MantisBT by sending an
email to a particular mail account. It can also add notes/attachments to an existing
issue by replying to it.

This copy is a locally maintained fork of the official plugin (based on upstream
version 0.10.1, see [Original project](#original-project) below) with additional
fixes and features applied on top — see [Current state of this fork](#current-state-of-this-fork).

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
  and (new, see above) cut off long generic quoted-reply blocks
* Reporter handling: report as a single fixed "mail" user, resolve reporters by
  sender email address, optionally auto-create new user accounts, optional LDAP
  lookup, disposable-email checking, fallback reporter on failure
* Use the email's priority header to set the issue priority, with a configurable
  priority mapping
* Debug mode: save raw/parsed emails to disk, track memory usage, optionally attach
  the complete original email to the issue

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

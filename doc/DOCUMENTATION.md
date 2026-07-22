# EmailReporting Documentation

This is the documentation for **this fork** of the EmailReporting plugin (see
`README.md` in the plugin's folder for the full list of fork-specific changes,
and the **Change Log** page above for a version-by-version history). It covers
day-to-day setup and every configuration option shown in "Manage
Configuration Options" and "Manage Mailboxes".

## Setup

1. Extract/clone this repository into `mantis/plugins/EmailReporting/` (the
   folder name must be `EmailReporting`).
2. Open "Manage Plugins" in MantisBT and install/enable EmailReporting.
3. Go to "Manage EmailReporting" &rarr; "Manage Mailboxes" to add a mailbox
   (POP3/IMAP host, credentials, target project) and adjust the
   configuration options described below.
4. Schedule the mail-fetching job to run periodically.

<a name="setting_up_a_scheduled_cron_job_for_emailreporting"></a>
### Scheduling the mail-fetching job

Emails are not collected automatically - something needs to run the job on a
schedule (e.g. every few minutes via cron). There are two ways to invoke it,
but only the first can be scheduled via cron; the second runs the exact same
job through a browser instead, which is only useful for a one-off manual
test, not for scheduling:

1. `scripts/bug_report_mail.php` (direct PHP CLI invocation) - this is the
   one to point cron at:
   ```
   */5 * * * * /usr/local/bin/php /path/to/mantis/plugins/EmailReporting/scripts/bug_report_mail.php
   ```
2. `plugin.php?page=EmailReporting/bug_report_mail` (same job, run through
   the webserver) - only for a manual test in a browser; disabled by default
   via `mail_secured_script` (see below).

**Recommended:** point cron at `scripts/bug_report_mail_cron.sh` in this
plugin's folder instead of calling option 1 directly. It runs the same
script, but also emails you if a mailbox fails or the job crashes, holding
off repeat alerts for a few hours so a persistent failure doesn't flood your
inbox:

```
cd mantis/plugins/EmailReporting/scripts
cp bug_report_mail_cron.env.example bug_report_mail_cron.env
# edit bug_report_mail_cron.env: set PHP_BIN and ALERT_EMAIL
chmod +x bug_report_mail_cron.sh
```
```
*/5 * * * * /path/to/mantis/plugins/EmailReporting/scripts/bug_report_mail_cron.sh
```

## Feature set

- Create a new issue from an incoming email, or add a note/attachment to an
  existing one by replying (matched via Message-ID/References, or the issue
  ID in the subject line)
- POP3 or IMAP, with per-mailbox authentication, optional STARTTLS/SSL, and
  per-mailbox project/category assignment
- Multiple mailboxes, each independently filtered/configured; IMAP
  folder-per-project support
- Plain text, HTML (optionally converted to Markdown), and MIME-encoded
  emails, including signed emails
- Attachments: add email attachments to the issue, block specific
  attachments by MD5 hash, log rejected attachments, limit description/note
  size with optional overflow attachment
- Reply/signature cleanup: strip signatures by delimiter, remove exact-match
  or Gmail-style quoted replies, remove MantisBT's own notification footer
  from replies, cut off long generic quoted-reply blocks
- Reporter handling: report as a single fixed "mail" user, resolve reporters
  by sender email address, optionally auto-create new user accounts,
  optional LDAP lookup, disposable-email checking, fallback reporter on
  failure
- Auto-add Cc/To recipients as issue monitors, with an address exclusion
  list and automatic public-visibility promotion so they actually get
  notified
- Use the email's priority header to set the issue priority, with a
  configurable priority mapping
- New issues inherit their target project's visibility (private projects
  create private issues)
- Debug mode: save raw/parsed emails to disk, track memory usage, optionally
  attach the complete original email to the issue
- Cron failure alerting via `scripts/bug_report_mail_cron.sh`

## Configuration options

Each section below matches a section on "Manage Configuration Options".

### Security configuration options

<a name="mail_secured_script"></a>
#### Secure the EmailReporting script

Protects `bug_report_mail.php` from being run by a random visitor through the
webserver. Leave this **On** unless you specifically need to trigger the job
via a URL instead of cron - in that case, turn it off and consider setting
the IP restriction below too.

<a name="mail_secured_ipaddr"></a>
#### Restrict webserver access to this IP address

Only meaningful when the option above is off: if you must invoke
`bug_report_mail` through a webserver, restrict it to a single IP address
here (e.g. the machine that will actually call the URL). Leave blank to not
restrict by IP.

### Runtime configuration options

<a name="mail_delete"></a>
#### Delete incoming mail from POP3 server

Whether processed emails are deleted from the mailbox afterward. This isn't
meant for archiving - the more emails accumulate in the mailbox, the longer
every future run takes, since IMAP mailboxes still process everything not
already marked deleted.

<a name="mail_max_email_body"></a>
#### Maximum size of the description/note

Descriptions/notes longer than this (in bytes) are truncated. The default,
65535, matches the historical MySQL `TEXT` column size some older MantisBT
installations still use for these fields; if yours has since been widened to
`MEDIUMTEXT`, you can safely raise this.

<a name="mail_max_email_body_text"></a>
#### Truncation notice text

Text appended to a description/note when it was cut short by the setting
above.

<a name="mail_max_email_body_add_attach"></a>
#### Attach the complete text when truncated

When a description/note gets truncated, also add the full, untruncated text
as an attachment instead of just losing it. Can still fail if the
attachment itself exceeds MantisBT's own attachment size limit.

### Issue reporter configuration options

<a name="mail_use_reporter"></a>
#### Use a single reporter account for all mail

**On**: every issue/note is reported as the fixed account configured below,
regardless of who actually sent the email. **Off**: EmailReporting instead
tries to identify the reporter from the sender's email address.

<a name="mail_fallback_mail_reporter"></a>
#### Enable fallback to mail reporter

When EmailReporting can't otherwise determine a valid reporter for an
email, this decides whether it falls back to the account below instead of
giving up. If disabled and no other reporter can be found, the email is
dropped instead.

<a name="mail_reporter_id"></a>
#### Mail reporter account

The fallback/default account used to report issues when no better match is
found (or always, if "Use a single reporter account" above is on). Make sure
this account exists and is enabled, so incoming mail always has somewhere to
go.

<a name="mail_auto_signup"></a>
#### Signup new users automatically

Automatically creates a new MantisBT account for senders that don't already
have one. **Possible security risk**: if your mailbox address is publicly
known, this can let anyone with an email address create an account on your
MantisBT instance.

<a name="mail_preferred_username"></a>
#### Preferred username for new user creations

How the username is derived when auto-signup creates a new account: from the
sender's email address, the email address without its domain, from LDAP (if
configured), or the display name found in the email's `From` header.

<a name="mail_preferred_realname"></a>
#### Preferred realname for new user creations

Same choices as above (plus "full From address"), but for the account's
display/real name instead of its username.

<a name="mail_disposable_email_checker"></a>
#### Disposable email checker

MantisBT has its own built-in disposable/throwaway email address checker;
this lets you turn it off specifically for EmailReporting's own reporter
lookups/auto-signup, without affecting the rest of MantisBT.

### Feature configuration options

<a name="mail_add_bug_reports"></a>
#### Create new issues

Whether EmailReporting is allowed to create a brand new issue when an
incoming email doesn't match an existing one. If off (and the email also
isn't recognized as a note on an existing issue), the email is ignored.

<a name="mail_add_bugnotes"></a>
#### Add notes

Whether EmailReporting is allowed to add a note to an existing issue when a
reply is recognized (via Message-ID/References or the issue ID in the
subject). If off, what would have been a note is instead reported as a
brand new issue (subject to the option above).

<a name="mail_rule_system"></a>
#### Rule system

A more advanced, Outlook-style rule wizard for routing/filtering mail. Still
under development in this codebase and intentionally disabled in the UI -
not ready for use yet.

<a name="mail_parse_html"></a>
#### Parse HTML mails

If an HTML email has no plaintext part, this converts the HTML to
plaintext/Markdown for the description/note instead of adding the raw HTML
as an attachment.

<a name="mail_email_receive_own"></a>
#### Users receive their own email notifications

MantisBT can be configured globally to not notify a user about actions they
performed themselves. This lets you override that specifically for actions
performed via EmailReporting, so users still get a confirmation email that
their emailed-in issue/note was received - without changing the global
setting for actions performed through the web UI.

<a name="mail_save_from"></a>
#### Save sender's email

Writes the original sender's email address into the created issue/note.

<a name="mail_save_subject_in_note"></a>
#### Save subject in note

Writes the email's subject line into the note text as well. Not applied to
brand new issues, since the subject already becomes the issue's Summary.

<a name="mail_subject_id_regex"></a>
#### Subject issue-ID regex

Controls how strictly EmailReporting looks for an existing issue ID in the
subject line when deciding whether an email is a reply to an existing issue:
**Strict** only matches MantisBT's own notification subject format;
**Balanced** is a middle ground; **Relaxed** matches the widest range of
subject formats, at higher risk of a false match.

<a name="mail_use_message_id"></a>
#### Use Message-ID to identify notes

Emails carry a unique Message-ID, and replies reference the ID(s) of the
message(s) they're replying to. Using this to detect replies is more
reliable than the subject-line regex alone - particularly useful when the
EmailReporting mailbox is CC'd into an ongoing discussion and you don't want
every reply to spawn its own new issue.

<a name="mail_add_users_from_cc_to"></a>
#### Add users to issue monitoring list from Cc and To fields in mail header

Automatically adds any address found in the email's Cc/To fields as a
monitor on the resulting issue - but only for addresses that already have a
matching MantisBT account (it never auto-creates an account for them). See
the two companion options below.

<a name="mail_monitor_make_public"></a>
#### Make the issue public if a Cc/To monitor was added to it while it was private
*(new in this fork)*

MantisBT only exempts an issue's **reporter** from the private-issue access
check - being a monitor is not enough on its own. Without this option, a
Cc/To recipient added as a monitor on a private issue would be subscribed to
notifications they can't actually receive, unless their project role
already met the private-issue threshold. When this is on (the default),
the issue is automatically switched to public the moment such a monitor is
added, so they actually get notified.

<a name="mail_monitor_exclude_addresses"></a>
#### Never auto-add these addresses as monitors
*(new in this fork)*

A list of addresses (one per line) that "Add users from Cc and To fields"
above should never add as a monitor, even if they match an existing
account. Matching is by **prefix, on any domain** - e.g. an entry of
`helpdesk@` excludes `helpdesk@anydomain.tld` for every domain, not just one
specific address.

Use this for your own monitored mailbox's address(es): the mailbox you're
polling appears in the `To` field of every single email it receives by
definition, so without an exclusion, an account sharing that address would
get auto-subscribed to its own notifications - and since those
notifications land back in the same monitored inbox, get reprocessed as new
mail, this creates a self-sustaining feedback loop.

### Priority feature configuration options

<a name="mail_use_bug_priority"></a>
#### Use email priority header

Whether EmailReporting reads the email's priority header at all and
attempts to map it onto the issue's priority, using the mapping below.

<a name="mail_bug_priority"></a>
#### Bug priority mapping

The conversion table from an email's priority value to a MantisBT priority
level. Accepts both the numeric priority header values used by different
mail clients and their text labels (e.g. `high`, `1 (highest)`).

### Attachments configuration options

<a name="mail_block_attachments_md5"></a>
#### Block attachments matching these MD5 hashes

A list of MD5 hashes (one per line); any attachment whose content hashes to
one of these is rejected instead of being added to the issue. Handy for
blocking things like a company logo embedded in every signature. MantisBT's
own `$g_allowed_files`/`$g_disallowed_files` extension-based filtering still
applies on top of this.

<a name="mail_block_attachments_logging"></a>
#### Log blocked attachments

Whether attachments rejected by the MD5 block-list above (or by other
attachment failures) get logged to a "Rejected files" list, so you can
verify the block-list is actually working as intended and see what got
dropped.

### Strip signature configuration options (Experimental)

<a name="mail_strip_signature"></a>
#### Strip signature

Removes an email signature from the note/description. Looks for the
delimiter configured below; everything from that delimiter onward
(including any other email parts below it) is discarded.

<a name="mail_strip_signature_delim"></a>
#### Signature delimiter

The exact text that marks where a signature begins, for the option above.
It must be the *only* content on its own line to be recognized (the
conventional Usenet/email signature delimiter is `--`, the default here).

### Default texts configuration options

<a name="mail_nosubject"></a>
#### Default text when subject is missing

Fallback issue summary text used when an incoming email has no subject at
all.

<a name="mail_nodescription"></a>
#### Default text when description is missing

Fallback description/note text used when an incoming email has no body -
e.g. someone replying with only an attachment and no message text.

### Remove replies configuration options

<a name="mail_remove_replies"></a>
#### Remove everything after the reply marker

Keeps only the newest message in an email thread, discarding everything from
the marker text below onward (the rest of the quoted conversation history).

<a name="mail_remove_replies_after"></a>
#### Reply marker text

The exact text marking where older, quoted conversation history begins, for
the option above (e.g. a client's own "-----Original Message-----" style
separator).

<a name="mail_strip_gmail_style_replies"></a>
#### Strip Gmail-style replies

Detects Gmail's own style of reply/quote header (English only) and, if
found, strips everything below it - a targeted, single-client alternative to
the more general option below.

<a name="mail_strip_quoted_lines"></a>
#### Cut off quoted reply/history text
*(new in this fork)*

Truncates the description/note once a run of consecutive lines starting
with `>` is found - the generic "quoted reply" marker used by most mail
clients regardless of language, unlike the Gmail-specific and exact-text
options above. Works as a language- and client-agnostic complement to them.

<a name="mail_strip_quoted_lines_min_lines"></a>
#### Minimum number of consecutive quoted lines
*(new in this fork)*

How many consecutive `>`-prefixed lines in a row are required before the
option above treats them as the start of a genuinely quoted block (rather
than, say, a single line that happens to start with `>` for some other
reason). Default is 3.

<a name="mail_remove_mantis_email"></a>
#### Remove MantisBT's own notification email

When a reply quotes MantisBT's own outgoing notification email underneath
the new content, this tries to detect and remove that quoted portion, so it
doesn't get duplicated into the note.

<a name="mail_removed_reply_text"></a>
#### Removed-reply placeholder text

Text substituted in place of whatever the option above removed, so it's
clear something was stripped rather than the note simply being shorter than
expected.

### Debug configuration options

<a name="mail_debug"></a>
#### Debug mode

Turns on verbose output about what EmailReporting is doing while processing
mail - useful when diagnosing why a particular email wasn't handled as
expected.

<a name="mail_debug_directory"></a>
#### Debug output directory

Where debug mode (above) writes its files: a `raw_msg_*` file with the raw
MIME content of each processed email, and a `parsed_msg_*` file with the
parsed PHP data structure EmailReporting built from it.

<a name="mail_add_complete_email"></a>
#### Add complete email as attachment

Attaches the raw, complete original email (full MIME content, not just the
parsed body) to the resulting issue.

<a name="mail_debug_show_memory_usage"></a>
#### Show memory usage in debug mode

Adds memory-usage tracking output at various stages of processing, on top of
debug mode above. Mainly useful when investigating memory consumption;
leave this off otherwise; it adds overhead of its own.

## Mailbox configuration

These options live under "Manage Mailboxes", one set per configured mailbox.

<a name="select_mailbox"></a>
#### Select mailbox

Pick an existing mailbox here to load it into the form below, revealing
**Save**, **Copy**, **Test**, **Complete test**, and **Delete**. The **Add**
button next to the picker starts a brand new mailbox instead (it relabels
itself to **Save** once you're actively filling one in). **Test** checks the
connection only, without altering anything; **Complete test** runs the
mailbox as thoroughly as the real scheduled job would (it asks for
confirmation first, since unlike **Test** it can actually change things,
e.g. mark messages read/deleted).

### Mailbox settings

<a name="enabled"></a>
#### Enabled

Turns this mailbox on or off without deleting its configuration - handy
while you're still testing it, or to temporarily pause fetching from it.

<a name="description"></a>
#### Description

A label for this mailbox so you can identify it later in the picker above -
purely for your own reference, not used for anything functional.

<a name="mailbox_type"></a>
#### Mailbox type

**POP3**: reads only unread messages and marks them read/deletes them per
the settings below. **IMAP**: reads every message except ones already
marked deleted, and unlocks the IMAP-only settings below (subfolder
support).

<a name="hostname"></a>
#### Hostname

The mail server's hostname or IP address.

<a name="port"></a>
#### TCP port

Leave blank to use the standard port for the selected type/encryption
(POP3: 110 plain / 995 encrypted; IMAP: 143 plain / 993 encrypted), or set
a custom one if your server uses something else.

<a name="encryption"></a>
#### Connection encryption

None, SSL, SSLv2/v3, TLS/TLSv1.0-1.2, or STARTTLS. Available options depend
on your PHP's OpenSSL support and, for STARTTLS, on the mailbox type (IMAP
only).

<a name="ssl_cert_verify"></a>
#### Verify SSL certificate

Whether the mail server's SSL/TLS certificate is validated when connecting
with encryption enabled. Turn this off only if you know you're connecting
to a server with a self-signed or otherwise unverifiable certificate you
trust anyway.

<a name="erp_username"></a>
#### Username

The mailbox account's username on the mail server.

<a name="erp_password"></a>
#### Password

The mailbox account's password. Leaving this blank when editing an existing
mailbox keeps the currently stored password unchanged - it is never sent
back to your browser to be shown/re-typed.

<a name="auth_method"></a>
#### Authentication method

DIGEST-MD5, CRAM-MD5, APOP, PLAIN, LOGIN, or USER - support for each varies
between POP3 and IMAP and by mail server.

### Mailbox IMAP only settings

<a name="imap_basefolder"></a>
#### Basefolder

The IMAP folder EmailReporting reads from. Leave blank to use the Inbox.

<a name="imap_createfolderstructure"></a>
#### Create project subfolder structure

If enabled, creates (and files incoming mail into) a subfolder per MantisBT
project under the basefolder above, instead of leaving everything in one
folder. Note some IMAP servers don't allow a folder to contain both
messages and subfolders at the same time.

### Mailbox issue settings

<a name="project_id"></a>
#### Select a project

The project new issues from this mailbox are created in. Ignored for IMAP
mailboxes using the project-subfolder feature above (the subfolder
determines the project instead). Only affects newly created issues, not
notes added to existing ones.

<a name="global_category_id"></a>
#### Select a category

The category assigned to new issues from this mailbox. Especially worth
setting deliberately if you're using the IMAP subfolder-per-project feature.
Only affects newly created issues, not notes.

## License

GPL-2.0, same as the rest of this plugin - see `LICENSE` in the plugin's
folder. This
page is rendered using [Parsedown](https://parsedown.org/) (MIT-licensed;
vendored at `core/Markdown/Parsedown.php`, used only if MantisBT's own
transitively-bundled copy isn't already loaded).

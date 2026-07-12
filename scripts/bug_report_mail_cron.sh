#!/bin/bash
# Wrapper around bug_report_mail.php for use in cron: runs the normal
# EmailReporting job, and if it exits non-zero (PHP fatal error, timeout,
# out-of-memory kill, or - as of this fork - a mailbox that failed to
# process) emails the captured output to ALERT_EMAIL.
#
# A PHP fatal error halts the script before it reaches its own exit(),
# and bug_report_mail.php itself now also exits non-zero if any mailbox
# failed to process, so the process exit code alone is enough to detect
# a failure - no further changes to bug_report_mail.php are needed here.
#
# Usage: point cron at this script instead of bug_report_mail.php directly,
# e.g.:
#   */5 * * * * /path/to/mantis/plugins/EmailReporting/scripts/bug_report_mail_cron.sh
#
# Pass -s (or set NOTIFY_ON_SUCCESS=1) to also send an email when the job
# succeeds - useful while testing, to confirm alerting itself is wired up
# correctly. Leave it off for normal operation, or you'll get an email
# every run.
#
# Since this typically runs every few minutes, a failure that persists
# across runs would otherwise send one alert per run. Once an alert is
# sent, further alerts for the same ongoing failure are held off for
# ALERT_COOLDOWN_SECONDS (default 6 hours). The cooldown resets as soon
# as a run succeeds, so the next failure after a recovery alerts right
# away instead of waiting out a stale cooldown.
#
# Server-specific settings (PHP_BIN, ALERT_EMAIL) live in
# bug_report_mail_cron.env, next to this script, which is gitignored so
# they never get committed or clobbered by a future pull. Copy
# bug_report_mail_cron.env.example to bug_report_mail_cron.env and fill
# it in before first use.

set -u

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ENV_FILE="$SCRIPT_DIR/bug_report_mail_cron.env"
STATE_FILE="$SCRIPT_DIR/.bug_report_mail_cron_last_alert"

PHP_BIN="/usr/local/bin/php"
SCRIPT="$SCRIPT_DIR/bug_report_mail.php"
ALERT_EMAIL=""
NOTIFY_ON_SUCCESS="${NOTIFY_ON_SUCCESS:-0}"
ALERT_COOLDOWN_SECONDS="${ALERT_COOLDOWN_SECONDS:-21600}"

if [ -f "$ENV_FILE" ]; then
	# shellcheck disable=SC1090
	source "$ENV_FILE"
fi

while getopts "s" opt; do
	case "$opt" in
		s) NOTIFY_ON_SUCCESS=1 ;;
	esac
done

if [ -z "$ALERT_EMAIL" ]; then
	echo "ALERT_EMAIL is not set. Copy $SCRIPT_DIR/bug_report_mail_cron.env.example to bug_report_mail_cron.env and fill it in." >&2
	exit 1
fi

# Sends a plain-text alert via sendmail (no "mail"/"mailx" command required).
# ALERT_FROM is optional; if unset sendmail falls back to its own default.
send_alert() {
	local subject="$1"
	local message="$2"

	{
		[ -n "${ALERT_FROM:-}" ] && echo "From: $ALERT_FROM"
		echo "To: $ALERT_EMAIL"
		echo "Subject: $subject"
		echo "Content-Type: text/plain; charset=UTF-8"
		echo
		echo "$message"
	} | sendmail -t
}

LOGFILE="$(mktemp)"
trap 'rm -f "$LOGFILE"' EXIT

"$PHP_BIN" "$SCRIPT" > "$LOGFILE" 2>&1
STATUS=$?

if [ "$STATUS" -ne 0 ]; then
	NOW="$(date +%s)"
	LAST_ALERT=0
	if [ -f "$STATE_FILE" ]; then
		LAST_ALERT="$(cat "$STATE_FILE" 2>/dev/null)"
		[[ "$LAST_ALERT" =~ ^[0-9]+$ ]] || LAST_ALERT=0
	fi

	if [ "$(( NOW - LAST_ALERT ))" -ge "$ALERT_COOLDOWN_SECONDS" ]; then
		send_alert "EmailReporting cron FAILED (exit $STATUS)" \
			"bug_report_mail.php exited with status $STATUS on $(hostname) at $(date)

$(cat "$LOGFILE")"
		echo "$NOW" > "$STATE_FILE"
	fi
else
	# Recovered (or never failed) - clear the cooldown so the next
	# failure is treated as a new incident and alerts immediately.
	rm -f "$STATE_FILE"

	if [ "$NOTIFY_ON_SUCCESS" -eq 1 ]; then
		send_alert "EmailReporting cron OK" \
			"bug_report_mail.php completed successfully on $(hostname) at $(date)

$(cat "$LOGFILE")"
	fi
fi

exit "$STATUS"

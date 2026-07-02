#!/bin/bash
# Wrapper around bug_report_mail.php for use in cron: runs the normal
# EmailReporting job, and if it exits non-zero (PHP fatal error, timeout,
# out-of-memory kill, etc.) emails the captured output to ALERT_EMAIL.
#
# A PHP fatal error halts the script before it reaches its own exit(0),
# so the process exit code alone is enough to detect a crash - no changes
# to bug_report_mail.php are needed.
#
# Usage: point cron at this script instead of bug_report_mail.php directly,
# e.g.:
#   */5 * * * * /path/to/mantis/plugins/EmailReporting/scripts/bug_report_mail_cron.sh
#
# Pass -s (or set NOTIFY_ON_SUCCESS=1) to also send an email when the job
# succeeds - useful while testing, to confirm alerting itself is wired up
# correctly. Leave it off for normal operation, or you'll get an email
# every run.

set -u

PHP_BIN="/usr/local/bin/php"
SCRIPT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/bug_report_mail.php"
ALERT_EMAIL="you@example.com"
NOTIFY_ON_SUCCESS="${NOTIFY_ON_SUCCESS:-0}"

while getopts "s" opt; do
	case "$opt" in
		s) NOTIFY_ON_SUCCESS=1 ;;
	esac
done

LOGFILE="$(mktemp)"
trap 'rm -f "$LOGFILE"' EXIT

"$PHP_BIN" "$SCRIPT" > "$LOGFILE" 2>&1
STATUS=$?

if [ "$STATUS" -ne 0 ]; then
	{
		echo "bug_report_mail.php exited with status $STATUS on $(hostname) at $(date)"
		echo
		cat "$LOGFILE"
	} | mail -s "EmailReporting cron FAILED (exit $STATUS)" "$ALERT_EMAIL"
elif [ "$NOTIFY_ON_SUCCESS" -eq 1 ]; then
	{
		echo "bug_report_mail.php completed successfully on $(hostname) at $(date)"
		echo
		cat "$LOGFILE"
	} | mail -s "EmailReporting cron OK" "$ALERT_EMAIL"
fi

exit "$STATUS"

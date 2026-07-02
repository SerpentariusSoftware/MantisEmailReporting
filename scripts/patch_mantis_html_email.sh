#!/usr/bin/env bash

set -euo pipefail

MANTIS_ROOT="${1:-public_html}"

EMAIL_API="$MANTIS_ROOT/core/email_api.php"
PHPMAILER_SENDER="$MANTIS_ROOT/core/classes/EmailSenderPhpMailer.class.php"

timestamp="$(date +%Y%m%d_%H%M%S)"

echo "Using Mantis root: $MANTIS_ROOT"

if [[ ! -f "$EMAIL_API" ]]; then
    echo "ERROR: File not found: $EMAIL_API"
    exit 1
fi

if [[ ! -f "$PHPMAILER_SENDER" ]]; then
    echo "ERROR: File not found: $PHPMAILER_SENDER"
    exit 1
fi

echo "Creating backups..."
cp -p "$EMAIL_API" "$EMAIL_API.bak.$timestamp"
cp -p "$PHPMAILER_SENDER" "$PHPMAILER_SENDER.bak.$timestamp"

echo "Patching EmailSenderPhpMailer.class.php..."

if grep -q '\$t_mail->isHTML( true );' "$PHPMAILER_SENDER"; then
    echo "  Already patched: PHPMailer isHTML(true)"
else
    sed -i \
        -e 's/# set email format to plain text and word wrap to 80 characters/# set email format to HTML and word wrap to 80 characters/' \
        -e 's/\$t_mail->isHTML( false );/\$t_mail->isHTML( true );/' \
        "$PHPMAILER_SENDER"
fi

echo "Patching email_api.php body formatting..."

if grep -q "make_lf_br( \$t_body )" "$EMAIL_API"; then
    echo "  Already patched: HTML body wrapping exists"
else
    sed -i \
        "s|\$t_body = make_lf_crlf( \$t_body );|\$t_body = '<html><body>' . make_lf_br( \$t_body ) . '</body></html>';|" \
        "$EMAIL_API"
fi

echo "Adding make_lf_br() helper if missing..."

if grep -q '^function make_lf_br' "$EMAIL_API"; then
    echo "  Already patched: make_lf_br() exists"
else
    python3 - "$EMAIL_API" <<'PY'
import sys
from pathlib import Path

path = Path(sys.argv[1])
text = path.read_text()

needle = """function make_lf_crlf( $p_string ) {
\t$t_string = str_replace( "\\n", "\\r\\n", $p_string );
\treturn str_replace( "\\r\\r\\n", "\\r\\n", $t_string );
}
"""

insert = needle + """
function make_lf_br( $p_string ) {
\t$t_string = str_replace( "\\n", "<br />", $p_string );
\treturn str_replace( "\\r\\r\\n", "<br />", $t_string );
}
"""

if "function make_lf_br" in text:
    sys.exit(0)

if needle not in text:
    print("ERROR: Could not find make_lf_crlf() block. File format may differ.", file=sys.stderr)
    sys.exit(1)

path.write_text(text.replace(needle, insert))
PY
fi

echo
echo "Patch complete."
echo
echo "Backups created:"
echo "  $EMAIL_API.bak.$timestamp"
echo "  $PHPMAILER_SENDER.bak.$timestamp"
echo
echo "Verify with:"
echo "  diff -u $EMAIL_API.bak.$timestamp $EMAIL_API"
echo "  diff -u $PHPMAILER_SENDER.bak.$timestamp $PHPMAILER_SENDER"

<?php
auth_reauthenticate( );
access_ensure_global_level( config_get( 'manage_plugin_threshold' ) );

plugin_require_api( 'core/config_api.php' );
plugin_require_api( 'core_pear/PEAR.php' );
plugin_require_api( 'core/mail_api.php' );

// AJAX-only endpoint backing the async Test/Complete test buttons on
// manage_mailbox.php (files/manage_mailbox.js). Deliberately separate from
// manage_mailbox_edit.php's synchronous add/edit/copy/delete handling: this
// never redirects, never calls plugin_config_set() (nothing is ever saved
// here, matching the existing Test/Complete test semantics), and always
// responds with JSON instead of a full HTML page - so it can run against a
// brand new, not-yet-saved mailbox's in-progress form values without losing
// them to a page navigation either way.

header( 'Content-Type: application/json' );

$f_mailbox_action = gpc_get_string( 'mailbox_action' );
$f_select_mailbox = gpc_get_int( 'select_mailbox', -1 );

if ( $f_mailbox_action !== 'test' && $f_mailbox_action !== 'complete_test' )
{
	http_response_code( 400 );
	echo json_encode( array( 'success' => FALSE, 'html' => 'Invalid action' ) );
	exit;
}

$t_mailboxes = plugin_config_get( 'mailboxes' );

$t_mailbox = array(
	'enabled'				=> gpc_get_int( 'enabled', ON ),
	'description'			=> gpc_get_string( 'description', '' ),
	'mailbox_type'			=> gpc_get_string( 'mailbox_type' ),
	'hostname'				=> gpc_get_string( 'hostname', '' ),
	'port'					=> gpc_get_string( 'port', '' ),
	'encryption'			=> gpc_get_string( 'encryption' ),
	'ssl_cert_verify'		=> gpc_get_int( 'ssl_cert_verify', ON ),
	'erp_username'			=> gpc_get_string( 'erp_username', '' ),
	'erp_password'			=> base64_encode( gpc_get_string( 'erp_password', '' ) ),
	'auth_method'			=> gpc_get_string( 'auth_method' ),
	'project_id'			=> gpc_get_int( 'project_id' ),
	'global_category_id'	=> gpc_get_int( 'global_category_id' ),
);

// Same "blank password means keep the stored one" rule as
// manage_mailbox_edit.php - only meaningful here when testing an
// already-saved mailbox; a brand new one has nothing to fall back to.
if ( $t_mailbox[ 'erp_password' ] === '' && $f_select_mailbox >= 0 && isset( $t_mailboxes[ $f_select_mailbox ][ 'erp_password' ] ) )
{
	$t_mailbox[ 'erp_password' ] = $t_mailboxes[ $f_select_mailbox ][ 'erp_password' ];
}

if ( $t_mailbox[ 'mailbox_type' ] === 'IMAP' )
{
	$t_mailbox[ 'imap_basefolder' ] = ERP_prepare_directory_string( gpc_get_string( 'imap_basefolder', '' ), TRUE );
	$t_mailbox[ 'imap_createfolderstructure' ] = gpc_get_int( 'imap_createfolderstructure' );
}

// process_mailbox() echoes debug/progress output directly - capture it
// instead of letting it leak into the JSON response body.
ob_start();
$t_mailbox_api = new ERP_mailbox_api( ( $f_mailbox_action === 'test' ) );
$t_result = $t_mailbox_api->process_mailbox( $t_mailbox );
$t_debug_output = ob_get_clean();

$t_is_custom_error = ( ( is_array( $t_result ) && isset( $t_result[ 'ERROR_TYPE' ] ) && $t_result[ 'ERROR_TYPE' ] === 'NON-PEAR-ERROR' ) || ( is_bool( $t_result ) && $t_result === FALSE ) );
$t_success = !( $t_is_custom_error || PEAR::isError( $t_result ) );

$t_html = '';

if ( $t_debug_output !== '' )
{
	$t_html .= '<pre>' . string_display( $t_debug_output ) . '</pre>';
}

$t_html .= '<strong>' . plugin_lang_get( $t_success ? 'test_success' : 'test_failure' ) . '</strong><br /><br />';
$t_html .= plugin_lang_get( 'description' ) . ': ' . string_display( $t_mailbox_api->_mailbox[ 'description' ] ) . '<br />';
$t_html .= plugin_lang_get( 'mailbox_type' ) . ': ' . string_display( $t_mailbox_api->_mailbox[ 'mailbox_type' ] ) . '<br />';
$t_html .= plugin_lang_get( 'hostname' ) . ': ' . string_display( $t_mailbox_api->_mailbox[ 'hostname' ] ) . '<br />';
$t_html .= plugin_lang_get( 'port' ) . ': ' . string_display( $t_mailbox_api->_mailbox[ 'port' ] ) . '<br />';
$t_html .= plugin_lang_get( 'encryption' ) . ': ' . string_display( $t_mailbox_api->_mailbox[ 'encryption' ] ) . '<br />';
$t_html .= plugin_lang_get( 'ssl_cert_verify' ) . ': ' . string_display( $t_mailbox_api->_mailbox[ 'ssl_cert_verify' ] ) . '<br />';
$t_html .= plugin_lang_get( 'erp_username' ) . ': ' . string_display( $t_mailbox_api->_mailbox[ 'erp_username' ] ) . '<br />';
$t_html .= plugin_lang_get( 'erp_password' ) . ': ******<br />';
$t_html .= plugin_lang_get( 'auth_method' ) . ': ' . string_display( $t_mailbox_api->_mailbox[ 'auth_method' ] ) . '<br />';

if ( $t_mailbox_api->_mailbox[ 'mailbox_type' ] === 'IMAP' )
{
	$t_html .= plugin_lang_get( 'imap_basefolder' ) . ': ' . string_display( $t_mailbox_api->_mailbox[ 'imap_basefolder' ] ) . '<br />';
}

if ( !$t_success )
{
	$t_html .= '<br />' . nl2br( string_display( $t_is_custom_error ? $t_result[ 'ERROR_MESSAGE' ] : ( 'Location: ' . $t_result->ERP_location . "\n" . $t_result->toString() ) ) );
}

echo json_encode( array( 'success' => $t_success, 'html' => $t_html ) );

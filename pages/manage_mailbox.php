<?php
auth_reauthenticate( );
access_ensure_global_level( config_get( 'manage_plugin_threshold' ) );

plugin_require_api( 'core/config_api.php' );

$t_this_page = 'manage_mailbox';
ERP_page_begin( $t_this_page );

$GLOBALS[ 't_mailboxes' ] = plugin_config_get( 'mailboxes' );
$t_rules = plugin_config_get( 'rules' );

$f_mailbox_action = gpc_get_string( 'mailbox_action', 'add' );
$f_select_mailbox = gpc_get_int( 'select_mailbox', -1 );

// the defaults different from the default NULL value
$t_mailbox = ERP_get_default_mailbox();

if ( $f_mailbox_action !== 'add' )
{
	if ( isset( $GLOBALS[ 't_mailboxes' ][ $f_select_mailbox ] ) )
	{
		// merge existing selected mailbox into the default mailbox overwriting existing default values
		$t_mailbox = $GLOBALS[ 't_mailboxes' ][ $f_select_mailbox ] + $t_mailbox;

		// Add "Copy of" text if necessary to mailboxes being copied
		if ( $f_mailbox_action === 'copy' )
		{
			$t_mailbox[ 'description' ] = plugin_lang_get( 'copy_of') . ' ' . $t_mailbox[ 'description' ];
		}
	}
	else
	{
		$f_mailbox_action = 'add';
		$f_select_mailbox = -1;
	}
}

// Whether an existing mailbox is currently loaded (Edit/Copy/Test/Complete
// test/Delete apply) vs a brand new one is being created (only Add applies)
$t_is_new_mailbox = ( $f_select_mailbox < 0 || !isset( $GLOBALS[ 't_mailboxes' ][ $f_select_mailbox ] ) );

// Data for the client-side mailbox switcher in files/manage_mailbox.js -
// passed via data-* attributes rather than an inline <script>, since
// MantisBT's default CSP (script-src 'self', no nonce) silently blocks any
// inline script. The stored password is intentionally never included - only
// whether one is set - since leaving the password field blank now means
// "keep the existing password" (see manage_mailbox_edit.php), so there is no
// reason to send it to the browser at all.
$t_js_mailboxes = array();
foreach ( $GLOBALS[ 't_mailboxes' ] AS $t_js_index => $t_js_mailbox )
{
	$t_js_mailbox[ 'has_password' ] = !is_blank( base64_decode( $t_js_mailbox[ 'erp_password' ] ?? '' ) );
	unset( $t_js_mailbox[ 'erp_password' ] );

	$t_js_mailboxes[ $t_js_index ] = $t_js_mailbox;
}

?>

<form id="erp_mailbox_edit_form" action="<?php echo plugin_page( $t_this_page . '_edit' )?>" method="post"
	data-mailboxes="<?php echo string_attribute( json_encode( $t_js_mailboxes ) ) ?>"
	data-default-mailbox="<?php echo string_attribute( json_encode( ERP_get_default_mailbox() ) ) ?>"
	data-initial-select-mailbox="<?php echo string_attribute( $f_select_mailbox ) ?>"
	data-password-hint="<?php echo string_attribute( plugin_lang_get( 'erp_password_hint' ) ) ?>"
	data-copy-of-text="<?php echo string_attribute( plugin_lang_get( 'copy_of' ) ) ?>"
	data-delete-confirm-text="<?php echo string_attribute( plugin_lang_get( 'delete_mailbox_confirm' ) ) ?>"
	data-complete-test-confirm-text="<?php echo string_attribute( strip_tags( plugin_lang_get( 'complete_test_action_note' ) ) ) ?>"
	data-add-blank-confirm-text="<?php echo string_attribute( plugin_lang_get( 'add_mailbox_blank_confirm' ) ) ?>"
	data-start-new-confirm-text="<?php echo string_attribute( plugin_lang_get( 'start_new_mailbox_confirm' ) ) ?>"
	data-test-url="<?php echo string_attribute( plugin_page( 'manage_mailbox_test' ) ) ?>"
	data-testing-text="<?php echo string_attribute( plugin_lang_get( 'testing_in_progress' ) ) ?>"
	data-test-error-text="<?php echo string_attribute( plugin_lang_get( 'test_ajax_error' ) ) ?>"
	data-add-label="<?php echo string_attribute( plugin_lang_get( 'add_action' ) ) ?>"
	data-save-label="<?php echo string_attribute( plugin_lang_get( 'mailbox_save_action' ) ) ?>"
>
<?php

ERP_output_table_open( 'mailboxes' );
?>
<tr class='mytrr'>
	<td class="category width-50">
		<?php ERP_print_documentation_link( 'select_mailbox' ) ?>
	</td>
	<td colspan="2">
		<?php
		// Deliberately NOT using the "btn-toolbar inline" classes here: ace's
		// theme sets ".inline{display:inline-block!important}", which beats
		// a plain (non-!important) inline style, so this container never
		// actually became a flex box - and once it fell back to inline-block,
		// Bootstrap's ".btn-toolbar .btn{float:left}" floated ONLY the button
		// (the <select> isn't a .btn), visually reversing them regardless of
		// DOM order. A plain div with our own styling sidesteps both rules.
		?>
		<div style="display:flex; align-items:center; gap:12px;">
			<select class="input-sm" name="select_mailbox" id="erp_select_mailbox">
				<option value="-1"<?php check_selected( (string) $f_select_mailbox, '-1' ); ?>><?php echo plugin_lang_get( 'select_mailbox_new_option' ) ?></option>
				<?php
				// Same sort/selected/enabled-marker logic as
				// ERP_custom_function_print_descriptions_option_list(), but with an
				// actual "new mailbox" placeholder option added, since the generic
				// helper has no concept of an unselected/blank state to switch to.
				$t_mailboxes_sorted = array();
				foreach ( $GLOBALS[ 't_mailboxes' ] AS $t_mb_key => $t_mb )
				{
					$t_mailboxes_sorted[ $t_mb_key ] = $t_mb[ 'description' ];
				}
				natcasesort( $t_mailboxes_sorted );

				foreach ( $t_mailboxes_sorted AS $t_mb_key => $t_mb_description )
				{
					echo '<option value="' . string_attribute( $t_mb_key ) . '"';
					check_selected( (string) $f_select_mailbox, (string) $t_mb_key );
					echo '>' . ( ( isset( $GLOBALS[ 't_mailboxes' ][ $t_mb_key ][ 'enabled' ] ) && $GLOBALS[ 't_mailboxes' ][ $t_mb_key ][ 'enabled' ] == FALSE ) ? '* ' : NULL ) . string_attribute( $t_mb_description ) . '</option>';
				}
				?>
			</select>
			<?php
			// Always type="button" on the initial render, even in new-mailbox
			// mode: server-side, "new mailbox" and "form sections visible"
			// are always opposites (there's no server-renderable "blank form
			// actively being edited" state - that only exists after a
			// client-side Add click reveals it, see hasStarted in
			// files/manage_mailbox.js). Only that client-side state flips
			// this to type="submit"; otherwise a first click on the pristine
			// screen would immediately create a near-blank mailbox instead
			// of just revealing the form to fill in. This - not DOM order -
			// is also what keeps Enter in a text field from hitting this
			// button while editing: a non-submit button can never be a
			// form's implicit Enter target, regardless of where it sits
			// relative to Save/Copy/etc.
			?>
			<button type="button" name="mailbox_action" value="add" id="erp_action_add" class="btn btn-sm btn-success"><?php echo plugin_lang_get( 'add_action' ) ?></button>
		</div>
	</td>
</tr>
<tr class='mytrr' id="erp_actions_row"<?php echo ( $t_is_new_mailbox ) ? ' style="display:none"' : '' ?>>
	<td class="center" colspan="3">
		<div style="margin-top:8px;">
			<?php
			// Disabled (not just hidden) so a form-submitting Enter keypress
			// can't implicitly hit a button from an inactive group instead of
			// Save while in new-mailbox mode. Only "disabled" on the buttons
			// themselves though, NOT "display:none" - the wrapping spans
			// already fully hide their group when needed, and
			// applySelection() (files/manage_mailbox.js) only ever toggles
			// the SPANS' visibility, never each button's own inline style -
			// a redundant display:none baked into the buttons themselves
			// would never get cleared again once shown.
			$t_existing_attrs = ( $t_is_new_mailbox ) ? ' disabled="disabled"' : '';
			?>
			<span id="erp_actions_test" style="display:inline-flex; gap:12px;">
				<button type="button" id="erp_action_test" class="btn btn-sm btn-white"><?php echo plugin_lang_get( 'test_action' ) ?></button>
				<button type="button" id="erp_action_complete_test" class="btn btn-sm btn-warning"><?php echo plugin_lang_get( 'complete_test_action' ) ?></button>
			</span>
			<span id="erp_actions_existing" style="display:<?php echo ( $t_is_new_mailbox ) ? 'none' : 'inline-flex' ?>; gap:12px; margin-left:12px;">
				<button type="submit" name="mailbox_action" value="edit" class="btn btn-sm btn-primary"<?php echo $t_existing_attrs ?>><?php echo plugin_lang_get( 'mailbox_save_action' ) ?></button>
				<button type="submit" name="mailbox_action" value="copy" id="erp_action_copy" class="btn btn-sm btn-info"<?php echo $t_existing_attrs ?>><?php echo plugin_lang_get( 'copy_action' ) ?></button>
				<button type="submit" name="mailbox_action" value="delete" id="erp_action_delete" class="btn btn-sm btn-danger"<?php echo $t_existing_attrs ?>><?php echo plugin_lang_get( 'delete_action' ) ?></button>
			</span>
			<div id="erp_test_result" style="display:none; margin-top:10px; text-align:left;"></div>
		</div>
	</td>
</tr>
<?php
ERP_output_table_close();

// The settings sections below (and the test/existing-mailbox action buttons
// above) start hidden on a pristine page load - only the mailbox picker and
// Add button show until a mailbox is selected or Add is clicked. Once
// hasStarted flips to true client-side (files/manage_mailbox.js), this stays
// visible for the rest of the page's life; there's no reason to hide it
// again once the user has begun.
?>
<div id="erp_mailbox_form_sections"<?php echo ( $t_is_new_mailbox ) ? ' style="display:none"' : '' ?>>
<?php

// Loading this one here to throw a error if necessary and notifying the user of the issue
plugin_require_api( 'core_pear/PEAR.php' );
if ( !defined( 'PEAR_OS' ) )
{
	ERP_output_note_open();
?>
<p><i class="fa fa-warning"></i>
<?php echo plugin_lang_get( 'pear_load_error' ); ?>
</p>
<?php
	ERP_output_note_close();
}
?>

<?php

ERP_output_table_open( 'mailbox_settings' );
ERP_output_config_option( 'enabled', 'boolean', $t_mailbox );
ERP_output_config_option( 'description', 'string', $t_mailbox );
ERP_output_config_option( 'mailbox_type', 'dropdown', $t_mailbox, 'print_descriptions_option_list', array( 'IMAP', 'POP3' ) );
ERP_output_config_option( 'hostname', 'string', $t_mailbox );
ERP_output_config_option( 'port', 'string', $t_mailbox );
ERP_output_config_option( 'encryption', 'dropdown', $t_mailbox, 'print_encryption_option_list' );
ERP_output_config_option( 'ssl_cert_verify', 'boolean', $t_mailbox );
ERP_output_config_option( 'erp_username', 'string', $t_mailbox );
ERP_output_config_option( 'erp_password', 'string_password', $t_mailbox );
ERP_output_config_option( 'auth_method', 'dropdown', $t_mailbox, 'print_auth_method_option_list' );
ERP_output_table_close();

?>
<div id="erp_mailbox_settings_imap">
<?php
ERP_output_table_open( 'mailbox_settings_imap' );
ERP_output_config_option( 'imap_basefolder', 'string', $t_mailbox );
ERP_output_config_option( 'imap_createfolderstructure', 'boolean', $t_mailbox );
ERP_output_table_close();
?>
</div>
<?php

ERP_output_table_open( 'mailbox_settings_issue' );
ERP_output_config_option( 'project_id', 'dropdown', $t_mailbox, 'print_projects_option_list' );
ERP_output_config_option( 'global_category_id', 'dropdown', $t_mailbox, 'print_global_category_option_list' );
//ERP_output_config_option( 'link_rules', 'dropdown_multiselect', $t_mailbox, 'print_descriptions_option_list', $t_rules ); // Should we use this here or from the rules page?
ERP_output_table_close();

event_signal( 'EVENT_ERP_OUTPUT_MAILBOX_FIELDS', $f_select_mailbox );

?>
</div>
</form>

<script src="<?php echo plugin_file( 'manage_mailbox.js' ) ?>"></script>
<?php
ERP_page_end( __FILE__ );
?>

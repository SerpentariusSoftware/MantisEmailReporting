/*
 * Client-side mailbox switcher for pages/manage_mailbox.php.
 *
 * Served as a real external file (not an inline <script> block) because
 * MantisBT's default Content-Security-Policy is "script-src 'self'" with no
 * nonce/'unsafe-inline' escape hatch (see core/http_api.php's
 * http_security_headers()) - any inline <script> is silently blocked by the
 * browser. All dynamic per-request data is passed in via data-* attributes
 * on #erp_mailbox_edit_form instead of PHP-interpolated JS literals.
 */
jQuery( function( $ )
{
	var $editForm = $( '#erp_mailbox_edit_form' );
	var $selectMailbox = $editForm.find( '[name="select_mailbox"]' );

	var mailboxes = $editForm.data( 'mailboxes' ) || {};
	var defaultMailbox = $editForm.data( 'default-mailbox' ) || {};
	var passwordHint = $editForm.data( 'password-hint' ) || '';
	var copyOfText = $editForm.data( 'copy-of-text' ) || '';
	var deleteConfirmText = $editForm.data( 'delete-confirm-text' ) || '';
	var completeTestConfirmText = $editForm.data( 'complete-test-confirm-text' ) || '';
	var addBlankConfirmText = $editForm.data( 'add-blank-confirm-text' ) || '';
	var startNewConfirmText = $editForm.data( 'start-new-confirm-text' ) || '';
	var testUrl = $editForm.data( 'test-url' ) || '';
	var testingText = $editForm.data( 'testing-text' ) || '';
	var testErrorText = $editForm.data( 'test-error-text' ) || '';
	var addLabel = $editForm.data( 'add-label' ) || 'Add';
	var saveLabel = $editForm.data( 'save-label' ) || 'Save';
	var initialSelectMailbox = String( $editForm.data( 'initial-select-mailbox' ) );

	// True once the form has anything to show at all - an existing mailbox
	// loaded, or a blank one revealed via Add. Starts true only if the page
	// itself loaded with an existing mailbox already selected (e.g. right
	// after a successful Add/Edit redirect); false means the pristine
	// "nothing chosen yet" screen, which shows only the picker and Add.
	// Once true, it never goes back to false - there's no reason to hide
	// the form again just because the user switched what it's showing.
	var hasStarted = mailboxes.hasOwnProperty( initialSelectMailbox );

	function setField( name, value )
	{
		var $field = $editForm.find( '[name="' + name + '"]' );

		if ( $field.length === 0 )
		{
			return;
		}

		if ( $field.is( ':radio' ) )
		{
			// Always reset first: unlike a full page reload, the DOM persists
			// across client-side switches, so a value missing from the newly
			// selected mailbox (e.g. imap_createfolderstructure on a POP3
			// mailbox) must not leave a previous mailbox's checked state behind.
			$field.prop( 'checked', false );

			if ( value !== undefined && value !== null )
			{
				$editForm.find( '[name="' + name + '"][value="' + value + '"]' ).prop( 'checked', true );
			}
		}
		else if ( value !== undefined )
		{
			$field.val( value );
		}
	}

	function toggleImapFields()
	{
		$( '#erp_mailbox_settings_imap' ).toggle( $editForm.find( '[name="mailbox_type"]' ).val() === 'IMAP' );
	}

	function applySelection()
	{
		var selectedIndex = $selectMailbox.val();
		var isNew = !mailboxes.hasOwnProperty( selectedIndex );
		var mailbox = $.extend( {}, defaultMailbox );
		var hasPassword = false;

		if ( !isNew )
		{
			mailbox = $.extend( mailbox, mailboxes[ selectedIndex ] );
			hasPassword = !!mailbox.has_password;
		}

		setField( 'enabled', mailbox.enabled );
		setField( 'description', mailbox.description || '' );
		setField( 'mailbox_type', mailbox.mailbox_type );
		setField( 'hostname', mailbox.hostname || '' );
		setField( 'port', mailbox.port || '' );
		setField( 'encryption', mailbox.encryption );
		setField( 'ssl_cert_verify', mailbox.ssl_cert_verify );
		setField( 'erp_username', mailbox.erp_username || '' );
		$editForm.find( '[name="erp_password"]' ).val( '' ).attr( 'placeholder', hasPassword ? passwordHint : '' );
		setField( 'auth_method', mailbox.auth_method );
		setField( 'imap_basefolder', mailbox.imap_basefolder || '' );
		setField( 'imap_createfolderstructure', mailbox.imap_createfolderstructure );
		setField( 'project_id', mailbox.project_id );
		setField( 'global_category_id', mailbox.global_category_id );

		// Disabled (not just hidden) so that pressing Enter in a text field
		// can't implicitly submit the wrong, inactive button - browsers pick
		// the first non-disabled submit button in DOM order as the implicit
		// target, and CSS display:none alone would not exclude it.
		// Using .css('display', ...) rather than .toggle()/.show()/.hide():
		// this span starts out already hidden via inline style on the very
		// first render, and jQuery's show/hide remembers whatever display
		// value was already present as the value to restore later - calling
		// .hide() on an already-"none" element would cache "none" as the
		// "old" value, making a later .show() restore to "none" again
		// (i.e. silently do nothing). An explicit value sidesteps that.
		$( '#erp_actions_existing' ).css( 'display', isNew ? 'none' : 'inline-flex' ).find( 'button' ).prop( 'disabled', isNew );

		// The Add button lives next to the mailbox picker, ahead of Save in
		// DOM order, so it can't rely on DOM-order-based Enter-key safety.
		// It's only type="submit" once genuinely in an active blank-mailbox
		// edit (isNew AND hasStarted) - never on the pristine screen (isNew
		// but not hasStarted yet), where a first click must just reveal the
		// form rather than immediately create a near-blank mailbox. In every
		// other state it's a plain type="button" (never an implicit Enter
		// target, regardless of DOM position).
		//
		// Relabeled to match: while actively filling in a new, unsaved
		// mailbox (the only state where this button actually persists
		// anything) it reads "Save", same as the existing-mailbox button -
		// otherwise it's still just "Add" (reveal/reset, not a save).
		var addIsSaving = isNew && hasStarted;
		$( '#erp_action_add' ).attr( 'type', addIsSaving ? 'submit' : 'button' ).text( addIsSaving ? saveLabel : addLabel );

		updateStartedVisibility();
		toggleImapFields();
	}

	function updateStartedVisibility()
	{
		$( '#erp_mailbox_form_sections' ).css( 'display', hasStarted ? 'block' : 'none' );
		$( '#erp_actions_row' ).css( 'display', hasStarted ? 'table-row' : 'none' );

		if ( !hasStarted )
		{
			$( '#erp_test_result' ).hide();
		}
	}

	$selectMailbox.on( 'change', function()
	{
		hasStarted = true;
		applySelection();
	} );

	$editForm.on( 'change', '[name="mailbox_type"]', toggleImapFields );

	// One Add button serves both purposes, distinguished by its current
	// type (kept in sync by applySelection()): while an existing mailbox is
	// loaded it's type="button" (reset only - never submits on its own);
	// once already in blank/new-mailbox mode it's type="submit" and this
	// handler only needs to guard against creating a mailbox with blank
	// essential fields.
	$( '#erp_action_add' ).on( 'click', function( e )
	{
		var selectedIndex = $selectMailbox.val();

		if ( mailboxes.hasOwnProperty( selectedIndex ) )
		{
			// An existing mailbox is currently loaded (or the form was
			// otherwise already showing something) - confirm before
			// discarding it, unless this is the very first, pristine click.
			if ( hasStarted && !confirm( startNewConfirmText ) )
			{
				return;
			}

			$selectMailbox.val( -1 );
			hasStarted = true;
			// Deferred: applySelection() flips this button's own type from
			// "button" to "submit" (now entering blank/new-mailbox mode).
			// Browsers evaluate a clicked button's type when running its
			// default action, which happens right after this handler
			// returns but still within the same click - so changing the
			// type synchronously here would make THIS SAME click submit the
			// form the instant it becomes type="submit". Pushing it to the
			// next tick lets the current (still type="button") click finish
			// first, with no default action to run at all.
			setTimeout( applySelection, 0 );
			return;
		}

		if ( !hasStarted )
		{
			// Pristine screen: first-ever Add click just reveals the blank
			// form - nothing to discard yet, so no confirmation needed.
			// Deferred for the same reason as above.
			hasStarted = true;
			setTimeout( applySelection, 0 );
			return;
		}

		var description = $editForm.find( '[name="description"]' ).val();
		var username = $editForm.find( '[name="erp_username"]' ).val();
		var password = $editForm.find( '[name="erp_password"]' ).val();

		if ( ( !description || !username || !password ) && !confirm( addBlankConfirmText ) )
		{
			e.preventDefault();
		}
	} );

	$( '#erp_action_copy' ).on( 'click', function()
	{
		var $description = $editForm.find( '[name="description"]' );

		if ( $description.val().indexOf( copyOfText ) !== 0 )
		{
			$description.val( copyOfText + ' ' + $description.val() );
		}
	} );

	$( '#erp_action_delete' ).on( 'click', function( e )
	{
		if ( !confirm( deleteConfirmText ) )
		{
			e.preventDefault();
		}
	} );

	// Test/Complete test never submit the form or navigate anywhere - they
	// run in the background against whatever is currently in the fields
	// (saved or not) and show the result inline, so in-progress data on a
	// brand new, not-yet-saved mailbox is never lost or silently persisted
	// regardless of the test's outcome.
	// Colors mirror the existing Add (success/green) and Delete
	// (danger/red) buttons, so a passed/failed test reads the same way.
	var testResultStyles = {
		success: { background: 'rgba(40, 167, 69, 0.15)', borderColor: '#28a745' },
		failure: { background: 'rgba(217, 83, 79, 0.15)', borderColor: '#d9534f' },
		pending: { background: 'transparent', borderColor: 'transparent' }
	};

	function setTestResultStyle( state )
	{
		var style = testResultStyles[ state ];
		$( '#erp_test_result' ).css( {
			backgroundColor: style.background,
			borderLeft: '4px solid ' + style.borderColor,
			padding: state === 'pending' ? '0' : '10px',
		} );
	}

	function runTest( action )
	{
		var $result = $( '#erp_test_result' );

		setTestResultStyle( 'pending' );
		$result.show().text( testingText );

		// $editForm.serialize() already only includes input/select/textarea
		// fields - the mailbox_action buttons are <button> elements, which
		// jQuery's serialize() excludes by design, so there's no name clash
		// with the action value appended below.
		$.ajax( {
			url: testUrl,
			method: 'POST',
			dataType: 'json',
			data: $editForm.serialize() + '&mailbox_action=' + encodeURIComponent( action )
		} ).done( function( response )
		{
			setTestResultStyle( response && response.success ? 'success' : 'failure' );
			$result.html( response && response.html !== undefined ? response.html : testErrorText );
		} ).fail( function()
		{
			setTestResultStyle( 'failure' );
			$result.text( testErrorText );
		} );
	}

	$( '#erp_action_test' ).on( 'click', function()
	{
		runTest( 'test' );
	} );

	$( '#erp_action_complete_test' ).on( 'click', function()
	{
		if ( confirm( completeTestConfirmText ) )
		{
			runTest( 'complete_test' );
		}
	} );

	// Force the dropdown (and everything derived from it) back to what the
	// server actually rendered. Browsers commonly restore a <select>'s last
	// user-picked value across a reload/redirect on their own, independent of
	// which <option> the server marked as selected="selected", and that
	// silent restoration does not fire a change event - so without this, the
	// visible dropdown could show one mailbox while the fields/buttons still
	// reflected a completely different (or blank/new) state underneath.
	$selectMailbox.val( initialSelectMailbox );
	applySelection();
} );

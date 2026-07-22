<?php
auth_reauthenticate( );
access_ensure_global_level( config_get( 'manage_plugin_threshold' ) );

plugin_require_api( 'core/config_api.php' );

// MantisBT itself pulls in Parsedown as a transitive Composer dependency (not
// a guaranteed, directly-declared one), so it may or may not already be
// autoloaded depending on the installation/version. Use it if present;
// otherwise fall back to our own vendored copy rather than assume either way.
if ( !class_exists( 'Parsedown' ) )
{
	plugin_require_api( 'core/Markdown/Parsedown.php' );
}

$t_this_page = 'view_readme';
ERP_page_begin( $t_this_page );

$t_doc_path = config_get_global( 'plugin_path' ) . plugin_get_current() . '/doc/DOCUMENTATION.md';
$t_doc_md = file_get_contents( $t_doc_path );

$t_parsedown = new Parsedown();
// Safe mode is deliberately left off here (unlike view_changelog.php): this
// document is entirely authored by us, not a years-long accumulation of
// free-text contributor entries, and it relies on raw <a name="..."></a>
// anchors before each heading so the "[?]" links throughout "Manage
// Configuration Options"/"Manage Mailboxes" (ERP_print_documentation_link())
// land on the right property. Safe mode would escape those anchors away.

?>

<?php echo $t_parsedown->text( $t_doc_md ); ?>

<?php
ERP_page_end( __FILE__ );
?>

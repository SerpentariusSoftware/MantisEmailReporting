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

$t_this_page = 'view_changelog';
ERP_page_begin( $t_this_page );

$t_changelog_path = config_get_global( 'plugin_path' ) . plugin_get_current() . '/doc/CHANGELOG.md';
$t_changelog_md = file_get_contents( $t_changelog_path );

$t_parsedown = new Parsedown();
// Safe mode escapes any raw HTML found in the source instead of passing it
// through - several changelog entries mention literal tags like "<form>" or
// "<script>" as prose, which should render as visible text, not be
// interpreted as actual markup.
$t_parsedown->setSafeMode( TRUE );

?>

<?php echo $t_parsedown->text( $t_changelog_md ); ?>

<?php
ERP_page_end( __FILE__ );
?>

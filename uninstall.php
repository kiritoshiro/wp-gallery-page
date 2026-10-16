<?php
/** Removes the gallery settings and the cached release check when the plugin is deleted. */
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'pg_gallery_settings' );
delete_site_transient( 'wpgp_latest_release' );

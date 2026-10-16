<?php
/* Small WordPress stubs exercise settings sanitizing, 2.x compatibility, translations and release gating. */
define( 'ABSPATH', __DIR__ );
define( 'WPGP_VERSION', '3.0.0' );
define( 'WPGP_FILE', __DIR__ . '/../wp-gallery-page.php' );
define( 'HOUR_IN_SECONDS', 3600 );

$GLOBALS['options']  = array();
$GLOBALS['release']  = null;
$GLOBALS['errors']   = array();
function __( $v, $domain ) { return $v; }
function absint( $v ) { return abs( (int) $v ); }
function sanitize_text_field( $v ) { return trim( strip_tags( (string) $v ) ); }
function esc_url_raw( $v, $protocols = null ) { return preg_match( '#^https?://#i', (string) $v ) ? (string) $v : ''; }
function get_option( $key, $default = false ) { return array_key_exists( $key, $GLOBALS['options'] ) ? $GLOBALS['options'][ $key ] : $default; }
function add_settings_error( $setting, $code, $message ) { $GLOBALS['errors'][] = $code; }
function get_site_transient( $key ) { return $GLOBALS['release']; }
function is_wp_error( $v ) { return $v instanceof WP_Error; }
class WP_Error { public $code; public function __construct( $code ) { $this->code = $code; } }
function download_url( $url, $timeout ) { $file = tempnam( sys_get_temp_dir(), 'wpgp' ); file_put_contents( $file, 'tampered zip' ); return $file; }
function wp_delete_file( $file ) { unlink( $file ); }

$failures = 0;
function check( $condition, $message ) {
	global $failures;
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: $message\n" );
		++$failures;
	}
}

require __DIR__ . '/../includes/class-settings.php';
require __DIR__ . '/../includes/class-updater.php';

/* ---- 2.x settings are read as they were, new keys get defaults */
$GLOBALS['options']['pg_gallery_settings'] = array(
	'page_ids'         => array( 12, 7, 30 ),
	'columns'          => 3,
	'gap'              => 20,
	'big_page_id'      => 7,
	'latest_category'  => 4,
	'youtube_playlist' => 'PLabc',
	'custom_images'    => array( 12 => 'https://example.com/a.jpg' ),
);
$opts = WPGP_Settings::get();
check( array( 12, 7, 30 ) === $opts['page_ids'] && 3 === $opts['columns'] && 7 === $opts['big_page_id'], '2.x values kept' );
check( 1200 === $opts['max_width'] && 620 === $opts['big_max'] && 5 === $opts['latest_count'] && 1 === $opts['show_latest'], 'new keys default' );
check( 'Naujausi įrašai' === $opts['latest_title'] && 'Praeitos pamokos' === $opts['grid_title'], '2.x headings are the defaults' );
$GLOBALS['options']['pg_gallery_settings'] = 'garbage';
check( array() === WPGP_Settings::get()['page_ids'], 'non-array option ignored' );

/* ---- sanitizing the form */
$out = WPGP_Settings::sanitize(
	array(
		'page_ids_csv'     => '5, 9,x,5,0,3',
		'big_page_id'      => '9',
		'custom_images'    => array(
			'9'  => ' https://example.com/b.jpg ',
			'3'  => 'javascript:alert(1)',
			'44' => 'https://example.com/not-in-list.jpg',
		),
		'columns'          => '99',
		'gap'              => '-4',
		'max_width'        => '',
		'ratio_w'          => '2',
		'ratio_h'          => '3',
		'latest_count'     => '0',
		'grid_title'       => '<b>Pamokos</b>',
		'video_title'      => '',
		'latest_title'     => 'Įrašai',
		'youtube_playlist' => 'https://www.youtube.com/watch?v=abc&list=PLjcvwNsWxJC5zpLT0rb3VIVyhsEV02uPp',
		'latest_category'  => '7',
	)
);
check( array( 5, 9, 3 ) === $out['page_ids'], 'ordered unique ids' );
check( 9 === $out['big_page_id'], 'featured page kept when in list' );
check( array( 9 => 'https://example.com/b.jpg' ) === $out['custom_images'], 'images: http(s) only, only for listed pages' );
check( 8 === $out['columns'] && 0 === $out['gap'] && 1200 === $out['max_width'] && 1 === $out['latest_count'], 'numbers clamped, empty → default' );
check( 2 === $out['ratio_w'] && 3 === $out['ratio_h'], 'ratio stored' );
check( 'Pamokos' === $out['grid_title'] && '' === $out['video_title'] && 'Įrašai' === $out['latest_title'], 'headings sanitized; empty hides' );
check( 'PLjcvwNsWxJC5zpLT0rb3VIVyhsEV02uPp' === $out['youtube_playlist'], 'playlist taken from a pasted URL' );
check( 0 === $out['show_latest'] && 7 === $out['latest_category'], 'unticked checkbox is off' );
check( array() === $GLOBALS['errors'], 'no error for a valid playlist' );

$out = WPGP_Settings::sanitize( array( 'page_ids_csv' => '5', 'big_page_id' => '8', 'youtube_playlist' => '"><script>', 'show_latest' => '1' ) );
check( 0 === $out['big_page_id'], 'featured page outside the list → automatic' );
check( '' === $out['youtube_playlist'] && in_array( 'wpgp_playlist', $GLOBALS['errors'], true ), 'bad playlist rejected with a notice' );
check( 1 === $out['show_latest'], 'ticked checkbox is on' );
check( array( 4, 2 ) === WPGP_Settings::sanitize( array( 'page_ids' => array( '4', '2', '4' ) ) )['page_ids'], '2.x array form accepted' );
check( 'PLx_y-1' === WPGP_Settings::playlist( ' PLx_y-1 ' ) && '' === WPGP_Settings::playlist( 'PL x' ), 'playlist ID charset' );

/* ---- translations: every PHP string has a Lithuanian translation */
$l10n = include __DIR__ . '/../languages/wp-gallery-page-lt_LT.l10n.php';
$re   = '/\b(?:__|esc_html__|esc_html_e|esc_attr_e|esc_attr__|_e)\(\s*\'([^\']*)\'\s*,\s*\'wp-gallery-page\'/';
$seen = 0;
foreach ( glob( __DIR__ . '/../includes/*.php' ) as $file ) {
	preg_match_all( $re, file_get_contents( $file ), $m );
	foreach ( $m[1] as $string ) {
		++$seen;
		check( isset( $l10n['messages'][ $string ] ), 'Lithuanian translation for: ' . $string );
	}
}
check( $seen > 70 && count( $l10n['messages'] ) > 70, 'translation file loaded and strings found' );

/* ---- updater */
$asset   = 'wp-gallery-page-3.1.0.zip';
$release = array(
	'tag_name' => 'v3.1.0',
	'assets'   => array(
		array(
			'name'                 => $asset,
			'state'                => 'uploaded',
			'browser_download_url' => 'https://github.com/kiritoshiro/wp-gallery-page/releases/download/v3.1.0/' . $asset,
			'digest'               => 'sha256:' . str_repeat( 'a', 64 ),
		),
	),
);
$parsed = WPGP_Updater::parse( $release );
check( '3.1.0' === $parsed['version'] && str_repeat( 'a', 64 ) === $parsed['digest'], 'release parsed' );
$bad = $release;
$bad['assets'][0]['browser_download_url'] = 'https://evil.test/' . $asset;
check( null === WPGP_Updater::parse( $bad ), 'foreign download host rejected' );
$bad = $release;
$bad['prerelease'] = true;
check( null === WPGP_Updater::parse( $bad ), 'prerelease ignored' );
$bad = $release;
$bad['assets'][0]['name'] = 'other-3.1.0.zip';
check( null === WPGP_Updater::parse( $bad ), 'wrong asset name ignored' );
$GLOBALS['release'] = $parsed;
check( WPGP_Updater::download( false, $parsed['package'], null ) instanceof WP_Error, 'tampered download rejected' );

/* ---- version is the same everywhere */
$main = file_get_contents( WPGP_FILE );
check( false !== strpos( $main, ' * Version: ' . WPGP_VERSION . "\n" ) && false !== strpos( $main, "define( 'WPGP_VERSION', '" . WPGP_VERSION . "' );" ), 'header and constant match the test version' );

if ( $failures ) {
	fwrite( STDERR, "$failures check(s) failed\n" );
	exit( 1 );
}
echo "All checks passed.\n";

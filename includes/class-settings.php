<?php
/** The stored gallery settings: defaults, reading and sanitizing. */
defined( 'ABSPATH' ) || exit;

final class WPGP_Settings {
	/** Same option as the 2.x single-file plugin, so existing settings carry over. */
	const OPTION = 'pg_gallery_settings';

	/** Value limits shared by the settings form, sanitizing and the shortcode. */
	const LIMITS = array(
		'columns'      => array( 1, 8 ),
		'gap'          => array( 0, 64 ),
		'max_width'    => array( 600, 2400 ),
		'big_max'      => array( 360, 1600 ),
		'ratio_w'      => array( 1, 10000 ),
		'ratio_h'      => array( 1, 10000 ),
		'latest_count' => array( 1, 10 ),
	);

	public static function defaults() {
		return array(
			'page_ids'         => array(),
			'big_page_id'      => 0,
			'custom_images'    => array(),
			'columns'          => 4,
			'gap'              => 16,
			'max_width'        => 1200,
			'big_max'          => 620,
			'ratio_w'          => 1304,
			'ratio_h'          => 1864,
			'grid_title'       => 'Praeitos pamokos',
			'youtube_playlist' => '',
			'video_title'      => __( 'Videos', 'wp-gallery-page' ),
			'show_latest'      => 1,
			'latest_title'     => 'Naujausi įrašai',
			'latest_category'  => 0,
			'latest_count'     => 5,
		);
	}

	/** Saved settings merged over the defaults (keys added after 2.x get their default). */
	public static function get() {
		$saved = get_option( self::OPTION, array() );
		return array_merge( self::defaults(), is_array( $saved ) ? $saved : array() );
	}

	public static function clamp( $key, $value ) {
		list( $min, $max ) = self::LIMITS[ $key ];
		return max( $min, min( $max, (int) $value ) );
	}

	/** Comma-separated or array IDs → unique positive integers, order kept. */
	public static function ids( $value ) {
		$list = is_array( $value ) ? $value : explode( ',', (string) $value );
		$ids  = array();
		foreach ( $list as $id ) {
			$id = absint( is_scalar( $id ) ? trim( (string) $id ) : 0 );
			if ( $id && ! in_array( $id, $ids, true ) ) {
				$ids[] = $id;
			}
		}
		return $ids;
	}

	/** A playlist ID, or the list= parameter of a pasted YouTube URL; '' when invalid. */
	public static function playlist( $value ) {
		$value = trim( (string) $value );
		if ( preg_match( '/[?&]list=([A-Za-z0-9_-]+)/', $value, $match ) ) {
			$value = $match[1];
		}
		return preg_match( '/^[A-Za-z0-9_-]{2,64}$/D', $value ) ? $value : '';
	}

	public static function sanitize( $in ) {
		$in       = is_array( $in ) ? $in : array();
		$defaults = self::defaults();
		$out      = array();

		// The form sends the ordered list as CSV; 2.x data may be an array.
		$out['page_ids']    = self::ids( isset( $in['page_ids_csv'] ) ? $in['page_ids_csv'] : ( isset( $in['page_ids'] ) ? $in['page_ids'] : array() ) );
		$big                = isset( $in['big_page_id'] ) ? absint( $in['big_page_id'] ) : 0;
		$out['big_page_id'] = in_array( $big, $out['page_ids'], true ) ? $big : 0;

		// Custom images only for pages that are in the gallery.
		$out['custom_images'] = array();
		if ( isset( $in['custom_images'] ) && is_array( $in['custom_images'] ) ) {
			foreach ( $in['custom_images'] as $page_id => $url ) {
				$page_id = absint( $page_id );
				$url     = is_string( $url ) ? esc_url_raw( trim( $url ), array( 'http', 'https' ) ) : '';
				if ( $page_id && $url && in_array( $page_id, $out['page_ids'], true ) ) {
					$out['custom_images'][ $page_id ] = $url;
				}
			}
		}

		foreach ( array_keys( self::LIMITS ) as $key ) {
			$out[ $key ] = self::clamp( $key, isset( $in[ $key ] ) && '' !== $in[ $key ] ? $in[ $key ] : $defaults[ $key ] );
		}

		// An emptied heading is stored as '' and hides that heading.
		foreach ( array( 'grid_title', 'video_title', 'latest_title' ) as $key ) {
			$out[ $key ] = isset( $in[ $key ] ) ? sanitize_text_field( (string) $in[ $key ] ) : $defaults[ $key ];
		}

		$playlist                = isset( $in['youtube_playlist'] ) ? (string) $in['youtube_playlist'] : '';
		$out['youtube_playlist'] = self::playlist( $playlist );
		if ( '' !== trim( $playlist ) && '' === $out['youtube_playlist'] && function_exists( 'add_settings_error' ) ) {
			add_settings_error( self::OPTION, 'wpgp_playlist', __( 'The YouTube playlist was not recognised, so the video panel is off. Paste a playlist ID (PL…) or a link that contains list=…', 'wp-gallery-page' ) );
		}
		$out['show_latest']      = empty( $in['show_latest'] ) ? 0 : 1;
		$out['latest_category']  = isset( $in['latest_category'] ) ? absint( $in['latest_category'] ) : 0;

		return $out;
	}
}

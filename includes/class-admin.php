<?php
/** The Page Gallery admin screen (its own menu item) and the plugin row link. */
defined( 'ABSPATH' ) || exit;

final class WPGP_Admin {
	const SLUG  = 'wp-gallery-page';
	const GROUP = 'wpgp_settings_group';

	public static function register() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'settings' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'admin_notices', array( __CLASS__, 'legacy_notice' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( WPGP_FILE ), array( __CLASS__, 'action_links' ) );
	}

	public static function url() {
		return admin_url( 'admin.php?page=' . self::SLUG );
	}

	public static function menu() {
		add_menu_page(
			__( 'Page Gallery', 'wp-gallery-page' ),
			__( 'Page Gallery', 'wp-gallery-page' ),
			'manage_options',
			self::SLUG,
			array( __CLASS__, 'page' ),
			'dashicons-format-gallery',
			21
		);
	}

	public static function settings() {
		register_setting( self::GROUP, WPGP_Settings::OPTION, array( 'sanitize_callback' => array( 'WPGP_Settings', 'sanitize' ) ) );
	}

	public static function action_links( $links ) {
		array_unshift( $links, '<a href="' . esc_url( self::url() ) . '">' . esc_html__( 'Settings', 'wp-gallery-page' ) . '</a>' );
		return $links;
	}

	/** The 2.x single-file plugin registers the same shortcode; only one should be active. */
	public static function legacy_notice() {
		if ( class_exists( 'PG_Page_Gallery_Grid', false ) && current_user_can( 'activate_plugins' ) ) {
			echo '<div class="notice notice-warning"><p>' . esc_html__( 'Page Gallery Grid 3 is active next to the old single-file Page Gallery plugin. Deactivate the old one; your settings are shared and stay in place.', 'wp-gallery-page' ) . '</p></div>';
		}
	}

	public static function assets( $hook ) {
		if ( 'toplevel_page_' . self::SLUG !== $hook ) {
			return;
		}
		wp_enqueue_media();
		wp_enqueue_style( 'wpgp-admin', plugins_url( 'assets/admin.css', WPGP_FILE ), array(), WPGP_VERSION );
		wp_enqueue_script( 'wpgp-admin', plugins_url( 'assets/admin.js', WPGP_FILE ), array( 'jquery', 'jquery-ui-sortable' ), WPGP_VERSION, true );
		wp_localize_script(
			'wpgp-admin',
			'wpgpAdmin',
			array(
				'chooseImage' => __( 'Image for this page', 'wp-gallery-page' ),
				'useImage'    => __( 'Use this image', 'wp-gallery-page' ),
				/* translators: %s: page title */
				'auto'        => __( 'automatic, the newest page (now “%s”). Click a star to choose one.', 'wp-gallery-page' ),
				'autoNone'    => __( 'automatic, the newest page in the gallery.', 'wp-gallery-page' ),
				/* translators: %s: page title */
				'chosen'      => __( '“%s”, chosen with the star.', 'wp-gallery-page' ),
				'copied'      => __( 'Copied', 'wp-gallery-page' ),
				'unsaved'     => __( 'You have unsaved changes.', 'wp-gallery-page' ),
			)
		);
	}

	private static function status_label( $status ) {
		$labels = array(
			'draft'   => __( 'Draft', 'wp-gallery-page' ),
			'pending' => __( 'Pending', 'wp-gallery-page' ),
			'private' => __( 'Private', 'wp-gallery-page' ),
			'future'  => __( 'Scheduled', 'wp-gallery-page' ),
		);
		return isset( $labels[ $status ] ) ? $labels[ $status ] : '';
	}

	/** All pages (newest first) with what the picker needs; one query plus primed thumbnails. */
	private static function pages() {
		$query = new WP_Query(
			array(
				'post_type'              => 'page',
				'post_status'            => array( 'publish', 'future', 'draft', 'pending', 'private' ),
				'orderby'                => 'date',
				'order'                  => 'DESC',
				'posts_per_page'         => -1,
				'no_found_rows'          => true,
				'update_post_term_cache' => false,
			)
		);
		update_post_thumbnail_cache( $query );
		$pages = array();
		foreach ( $query->posts as $post ) {
			$pages[ $post->ID ] = array(
				'id'     => $post->ID,
				'title'  => '' !== $post->post_title ? $post->post_title : __( '(no title)', 'wp-gallery-page' ),
				'status' => self::status_label( $post->post_status ),
				// post_date: drafts have no GMT date yet.
				'date'   => (int) strtotime( $post->post_date ),
				'thumb'  => (string) get_the_post_thumbnail_url( $post, 'thumbnail' ),
			);
		}
		return $pages;
	}

	private static function field( $key ) {
		return WPGP_Settings::OPTION . '[' . $key . ']';
	}

	private static function number( $key, $value, $label, $unit = '' ) {
		list( $min, $max ) = WPGP_Settings::LIMITS[ $key ];
		printf(
			'<label class="wpgp-field"><span class="wpgp-label">%s</span><span class="wpgp-input"><input type="number" name="%s" value="%d" min="%d" max="%d" class="small-text">%s</span></label>',
			esc_html( $label ),
			esc_attr( self::field( $key ) ),
			(int) $value,
			(int) $min,
			(int) $max,
			$unit ? ' <span class="wpgp-unit">' . esc_html( $unit ) . '</span>' : ''
		);
	}

	private static function text( $key, $value, $label, $help = '', $placeholder = '' ) {
		printf(
			'<label class="wpgp-field wpgp-field--wide"><span class="wpgp-label">%s</span><input type="text" name="%s" value="%s" class="regular-text" placeholder="%s">%s</label>',
			esc_html( $label ),
			esc_attr( self::field( $key ) ),
			esc_attr( $value ),
			esc_attr( $placeholder ),
			$help ? '<span class="description">' . esc_html( $help ) . '</span>' : ''
		);
	}

	/** One row of the "In the gallery" list; also used as the JavaScript template. */
	private static function chip( $page, $big_id, $images ) {
		$id     = (int) $page['id'];
		$custom = isset( $images[ $id ] ) ? (string) $images[ $id ] : '';
		$thumb  = $custom ? $custom : $page['thumb'];
		$big    = $id && $id === (int) $big_id;
		?>
		<li class="wpgp-chip<?php echo $big ? ' is-featured' : ''; ?><?php echo $custom ? ' has-custom' : ''; ?>" data-id="<?php echo esc_attr( $id ); ?>" data-date="<?php echo esc_attr( $page['date'] ); ?>" data-thumb="<?php echo esc_url( $page['thumb'] ); ?>">
			<span class="wpgp-handle dashicons dashicons-menu" title="<?php esc_attr_e( 'Drag to reorder', 'wp-gallery-page' ); ?>" aria-hidden="true"></span>
			<span class="wpgp-thumb"><?php echo $thumb ? '<img src="' . esc_url( $thumb ) . '" alt="">' : '<span class="dashicons dashicons-format-image" aria-hidden="true"></span>'; ?></span>
			<span class="wpgp-chip-text">
				<span class="wpgp-chip-title"><?php echo esc_html( $page['title'] ); ?></span>
				<span class="wpgp-chip-meta">
					<span class="wpgp-badge wpgp-badge--featured"><?php esc_html_e( 'Featured', 'wp-gallery-page' ); ?></span>
					<?php if ( $page['status'] ) : ?>
						<span class="wpgp-badge wpgp-badge--status" title="<?php esc_attr_e( 'Only published pages are shown in the gallery.', 'wp-gallery-page' ); ?>"><?php echo esc_html( $page['status'] ); ?></span>
					<?php endif; ?>
					<span class="wpgp-badge wpgp-badge--custom"><?php esc_html_e( 'Custom image', 'wp-gallery-page' ); ?></span>
				</span>
			</span>
			<span class="wpgp-chip-actions">
				<button type="button" class="button-link wpgp-star" aria-pressed="<?php echo $big ? 'true' : 'false'; ?>" title="<?php esc_attr_e( 'Show as the big featured page', 'wp-gallery-page' ); ?>"><span class="dashicons dashicons-star-<?php echo $big ? 'filled' : 'empty'; ?>" aria-hidden="true"></span><span class="screen-reader-text"><?php esc_html_e( 'Featured page', 'wp-gallery-page' ); ?></span></button>
				<button type="button" class="button button-small wpgp-image"><?php esc_html_e( 'Image…', 'wp-gallery-page' ); ?></button>
				<button type="button" class="button-link wpgp-reset" title="<?php esc_attr_e( 'Use the Featured Image again', 'wp-gallery-page' ); ?>"><?php esc_html_e( 'Reset', 'wp-gallery-page' ); ?></button>
				<button type="button" class="button-link wpgp-remove" title="<?php esc_attr_e( 'Remove from the gallery', 'wp-gallery-page' ); ?>"><span class="dashicons dashicons-no-alt" aria-hidden="true"></span><span class="screen-reader-text"><?php esc_html_e( 'Remove from the gallery', 'wp-gallery-page' ); ?></span></button>
			</span>
			<input type="hidden" class="wpgp-image-url" name="<?php echo esc_attr( self::field( 'custom_images' ) . '[' . $id . ']' ); ?>" value="<?php echo esc_attr( $custom ); ?>">
		</li>
		<?php
	}

	public static function page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$opts     = WPGP_Settings::get();
		$pages    = self::pages();
		$selected = WPGP_Settings::ids( $opts['page_ids'] );
		$big_id   = absint( $opts['big_page_id'] );
		// A 2.x featured page outside the list joins the list, where the star shows it.
		if ( $big_id && ! in_array( $big_id, $selected, true ) && isset( $pages[ $big_id ] ) ) {
			array_unshift( $selected, $big_id );
		}
		// Deleted pages drop out; they are removed from the option on the next save.
		$selected = array_values(
			array_filter(
				$selected,
				function ( $id ) use ( $pages ) {
					return isset( $pages[ $id ] );
				}
			)
		);
		$images   = (array) $opts['custom_images'];
		?>
		<div class="wrap wpgp">
			<div class="wpgp-header">
				<h1><?php esc_html_e( 'Page Gallery', 'wp-gallery-page' ); ?></h1>
				<span class="wpgp-shortcode"><code>[page_gallery]</code> <button type="button" class="button button-small wpgp-copy" data-copy="[page_gallery]"><?php esc_html_e( 'Copy shortcode', 'wp-gallery-page' ); ?></button></span>
			</div>
			<?php settings_errors(); // Top-level pages do not print "Settings saved." by themselves. ?>
			<form method="post" action="options.php" id="wpgp-form">
				<?php settings_fields( self::GROUP ); ?>
				<input type="hidden" name="<?php echo esc_attr( self::field( 'page_ids_csv' ) ); ?>" id="wpgp-ids" value="<?php echo esc_attr( implode( ',', $selected ) ); ?>">
				<input type="hidden" name="<?php echo esc_attr( self::field( 'big_page_id' ) ); ?>" id="wpgp-big" value="<?php echo esc_attr( in_array( $big_id, $selected, true ) ? $big_id : 0 ); ?>">

				<div class="wpgp-layout">
					<div class="wpgp-main">
						<section class="wpgp-card">
							<h2><?php esc_html_e( '1. Pages in the gallery', 'wp-gallery-page' ); ?></h2>
							<p class="description"><?php esc_html_e( 'Tick pages on the left. On the right, drag to set the order, click the star to choose the big featured page, and pick a custom image if the Featured Image does not fit.', 'wp-gallery-page' ); ?></p>
							<div class="wpgp-picker">
								<div class="wpgp-box">
									<div class="wpgp-box-head">
										<strong><?php esc_html_e( 'All pages', 'wp-gallery-page' ); ?></strong>
										<span class="wpgp-count"><?php echo esc_html( number_format_i18n( count( $pages ) ) ); ?></span>
										<label class="screen-reader-text" for="wpgp-search"><?php esc_html_e( 'Search pages', 'wp-gallery-page' ); ?></label>
										<input type="search" id="wpgp-search" class="wpgp-search" placeholder="<?php esc_attr_e( 'Search pages…', 'wp-gallery-page' ); ?>">
									</div>
									<div class="wpgp-box-body" id="wpgp-all">
										<?php if ( ! $pages ) : ?>
											<p class="wpgp-empty"><?php esc_html_e( 'There are no pages yet.', 'wp-gallery-page' ); ?></p>
										<?php endif; ?>
										<?php foreach ( $pages as $page ) : ?>
											<label class="wpgp-item" data-date="<?php echo esc_attr( $page['date'] ); ?>" data-thumb="<?php echo esc_url( $page['thumb'] ); ?>" data-search="<?php echo esc_attr( function_exists( 'mb_strtolower' ) ? mb_strtolower( $page['title'] . ' #' . $page['id'] ) : strtolower( $page['title'] . ' #' . $page['id'] ) ); ?>">
												<input type="checkbox" class="wpgp-toggle" value="<?php echo esc_attr( $page['id'] ); ?>"<?php checked( in_array( $page['id'], $selected, true ) ); ?>>
												<span class="wpgp-item-title"><?php echo esc_html( $page['title'] ); ?></span>
												<?php if ( $page['status'] ) : ?>
													<span class="wpgp-badge wpgp-badge--status"><?php echo esc_html( $page['status'] ); ?></span>
												<?php endif; ?>
												<span class="wpgp-item-date"><?php echo esc_html( $page['date'] ? date_i18n( get_option( 'date_format' ), $page['date'] ) : '' ); ?></span>
											</label>
										<?php endforeach; ?>
										<p class="wpgp-empty wpgp-no-match" hidden><?php esc_html_e( 'No pages match.', 'wp-gallery-page' ); ?></p>
									</div>
								</div>
								<div class="wpgp-box">
									<div class="wpgp-box-head">
										<strong><?php esc_html_e( 'In the gallery', 'wp-gallery-page' ); ?></strong>
										<span class="wpgp-count" id="wpgp-selected-count"><?php echo esc_html( number_format_i18n( count( $selected ) ) ); ?></span>
									</div>
									<ul class="wpgp-box-body wpgp-selected" id="wpgp-selected">
										<?php
										foreach ( $selected as $id ) {
											self::chip( $pages[ $id ], $big_id, $images );
										}
										?>
									</ul>
									<p class="wpgp-empty wpgp-selected-empty"<?php echo $selected ? ' hidden' : ''; ?>><?php esc_html_e( 'Nothing selected yet. Tick pages on the left.', 'wp-gallery-page' ); ?></p>
									<div class="wpgp-box-foot">
										<span class="dashicons dashicons-star-filled" aria-hidden="true"></span>
										<span><strong><?php esc_html_e( 'Featured page:', 'wp-gallery-page' ); ?></strong> <span id="wpgp-featured-label"></span></span>
									</div>
								</div>
							</div>
							<template id="wpgp-chip-template">
								<?php
								self::chip(
									array(
										'id'     => 0,
										'title'  => '',
										'status' => '',
										'date'   => 0,
										'thumb'  => '',
									),
									0,
									array()
								);
								?>
							</template>
						</section>

						<section class="wpgp-card">
							<h2><?php esc_html_e( '2. Side panel', 'wp-gallery-page' ); ?></h2>
							<p class="description"><?php esc_html_e( 'Shown next to the featured page: the video on top, the latest posts below it.', 'wp-gallery-page' ); ?></p>
							<div class="wpgp-columns">
								<fieldset class="wpgp-group">
									<legend><?php esc_html_e( 'YouTube playlist', 'wp-gallery-page' ); ?></legend>
									<?php
									self::text( 'youtube_playlist', $opts['youtube_playlist'], __( 'Playlist ID or link', 'wp-gallery-page' ), __( 'Leave empty to hide the video.', 'wp-gallery-page' ), 'PLxxxxxxxxxxxxxxxx' );
									self::text( 'video_title', $opts['video_title'], __( 'Heading', 'wp-gallery-page' ), __( 'Empty: no heading.', 'wp-gallery-page' ) );
									?>
								</fieldset>
								<fieldset class="wpgp-group">
									<legend><?php esc_html_e( 'Latest posts', 'wp-gallery-page' ); ?></legend>
									<label class="wpgp-field wpgp-check"><input type="checkbox" name="<?php echo esc_attr( self::field( 'show_latest' ) ); ?>" value="1"<?php checked( ! empty( $opts['show_latest'] ) ); ?>> <?php esc_html_e( 'Show the latest posts', 'wp-gallery-page' ); ?></label>
									<?php self::text( 'latest_title', $opts['latest_title'], __( 'Heading', 'wp-gallery-page' ), __( 'Empty: no heading.', 'wp-gallery-page' ) ); ?>
									<label class="wpgp-field wpgp-field--wide"><span class="wpgp-label"><?php esc_html_e( 'Category', 'wp-gallery-page' ); ?></span>
										<?php
										wp_dropdown_categories(
											array(
												'show_option_all' => __( 'All categories', 'wp-gallery-page' ),
												'taxonomy'        => 'category',
												'name'            => self::field( 'latest_category' ),
												'orderby'         => 'name',
												'hide_empty'      => 0,
												'selected'        => (int) $opts['latest_category'],
											)
										);
										?>
									</label>
									<?php self::number( 'latest_count', $opts['latest_count'], __( 'Number of posts', 'wp-gallery-page' ) ); ?>
								</fieldset>
							</div>
						</section>

						<section class="wpgp-card">
							<h2><?php esc_html_e( '3. Layout', 'wp-gallery-page' ); ?></h2>
							<div class="wpgp-columns">
								<fieldset class="wpgp-group">
									<legend><?php esc_html_e( 'Grid of pages', 'wp-gallery-page' ); ?></legend>
									<?php
									self::text( 'grid_title', $opts['grid_title'], __( 'Heading above the grid', 'wp-gallery-page' ), __( 'Empty: no heading.', 'wp-gallery-page' ) );
									self::number( 'columns', $opts['columns'], __( 'Columns on wide screens', 'wp-gallery-page' ) );
									self::number( 'gap', $opts['gap'], __( 'Space between cards', 'wp-gallery-page' ), 'px' );
									?>
									<p class="description"><?php esc_html_e( 'Tablets show 2 columns and phones 1.', 'wp-gallery-page' ); ?></p>
								</fieldset>
								<fieldset class="wpgp-group">
									<legend><?php esc_html_e( 'Sizes', 'wp-gallery-page' ); ?></legend>
									<?php
									self::number( 'max_width', $opts['max_width'], __( 'Gallery width (max)', 'wp-gallery-page' ), 'px' );
									self::number( 'big_max', $opts['big_max'], __( 'Featured image width (max)', 'wp-gallery-page' ), 'px' );
									?>
									<div class="wpgp-field"><span class="wpgp-label"><?php esc_html_e( 'Image shape (width × height)', 'wp-gallery-page' ); ?></span>
										<span class="wpgp-input">
											<input type="number" name="<?php echo esc_attr( self::field( 'ratio_w' ) ); ?>" value="<?php echo esc_attr( $opts['ratio_w'] ); ?>" min="1" max="10000" class="small-text" aria-label="<?php esc_attr_e( 'Image width', 'wp-gallery-page' ); ?>">
											×
											<input type="number" name="<?php echo esc_attr( self::field( 'ratio_h' ) ); ?>" value="<?php echo esc_attr( $opts['ratio_h'] ); ?>" min="1" max="10000" class="small-text" aria-label="<?php esc_attr_e( 'Image height', 'wp-gallery-page' ); ?>">
										</span>
									</div>
									<p class="description"><?php esc_html_e( 'Images are cropped to this shape. 1304 × 1864 is a portrait poster.', 'wp-gallery-page' ); ?></p>
								</fieldset>
							</div>
						</section>
					</div>

					<aside class="wpgp-aside">
						<div class="wpgp-card">
							<h2><?php esc_html_e( 'How it works', 'wp-gallery-page' ); ?></h2>
							<ol class="wpgp-steps">
								<li><?php esc_html_e( 'The featured page is shown big at the top, with the side panel next to it.', 'wp-gallery-page' ); ?></li>
								<li><?php esc_html_e( 'The other pages follow in the grid, in your order.', 'wp-gallery-page' ); ?></li>
								<li><?php esc_html_e( 'Image: the custom image, else the Featured Image from the page editor, else a grey “No image” box.', 'wp-gallery-page' ); ?></li>
								<li><?php esc_html_e( 'Drafts and private pages are kept in the list but appear only once published.', 'wp-gallery-page' ); ?></li>
							</ol>
						</div>
						<details class="wpgp-card wpgp-attrs">
							<summary><?php esc_html_e( 'Shortcode options', 'wp-gallery-page' ); ?></summary>
							<p class="description"><?php esc_html_e( 'Override a setting for one gallery, e.g.', 'wp-gallery-page' ); ?> <code>[page_gallery columns="3"]</code></p>
							<ul>
								<li><code>columns</code>, <code>gap</code></li>
								<li><code>max_width</code>, <code>big_max</code></li>
								<li><code>ratio_w</code>, <code>ratio_h</code></li>
								<li><code>ids="12,34,56"</code> — <?php esc_html_e( 'other pages', 'wp-gallery-page' ); ?></li>
								<li><code>big_id="123"</code> — <?php esc_html_e( 'other featured page', 'wp-gallery-page' ); ?></li>
								<li><code>category="7"</code> — <?php esc_html_e( 'posts category ID', 'wp-gallery-page' ); ?></li>
								<li><code>playlist="PL…"</code>, <code>show_yt="0"</code></li>
							</ul>
						</details>
					</aside>
				</div>
				<div class="wpgp-savebar">
					<?php submit_button( __( 'Save changes', 'wp-gallery-page' ), 'primary large', 'submit', false ); ?>
					<span class="wpgp-dirty" hidden><?php esc_html_e( 'Unsaved changes', 'wp-gallery-page' ); ?></span>
				</div>
			</form>
		</div>
		<?php
	}
}

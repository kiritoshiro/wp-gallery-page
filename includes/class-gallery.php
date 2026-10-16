<?php
/** The [page_gallery] shortcode: featured page, side panel and page grid. */
defined( 'ABSPATH' ) || exit;

final class WPGP_Gallery {
	const HANDLE = 'wpgp-gallery';

	public static function register() {
		add_action( 'init', array( __CLASS__, 'load_translations' ), 1 );
		add_action( 'after_setup_theme', array( __CLASS__, 'thumbnails' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_shortcode( 'page_gallery', array( __CLASS__, 'shortcode' ) );
	}

	public static function load_translations() {
		load_plugin_textdomain( 'wp-gallery-page', false, dirname( plugin_basename( WPGP_FILE ) ) . '/languages' );
	}

	/** Pages need a Featured Image box for the gallery's default images. */
	public static function thumbnails() {
		add_theme_support( 'post-thumbnails' );
		add_post_type_support( 'page', 'thumbnail' );
	}

	/** Load the stylesheet in <head> on pages whose content has the shortcode. */
	public static function assets() {
		wp_register_style( self::HANDLE, plugins_url( 'assets/gallery.css', WPGP_FILE ), array(), WPGP_VERSION );
		$post = get_post();
		if ( is_singular() && $post && has_shortcode( $post->post_content, 'page_gallery' ) ) {
			wp_enqueue_style( self::HANDLE );
		}
	}

	/** Published pages among $ids, in the given order. */
	private static function published( $ids ) {
		if ( ! $ids ) {
			return array();
		}
		$query = new WP_Query(
			array(
				'post_type'      => 'page',
				'post_status'    => 'publish',
				'post__in'       => $ids,
				'orderby'        => 'post__in',
				'posts_per_page' => count( $ids ),
				'has_password'   => false,
				'no_found_rows'  => true,
			)
		);
		return $query->posts;
	}

	public static function shortcode( $atts ) {
		$opts = WPGP_Settings::get();
		$atts = shortcode_atts(
			array(
				'columns'   => $opts['columns'],
				'gap'       => $opts['gap'],
				'ids'       => '',
				'big_id'    => $opts['big_page_id'],
				'category'  => $opts['latest_category'],
				'playlist'  => $opts['youtube_playlist'],
				'show_yt'   => '1',
				'ratio_w'   => $opts['ratio_w'],
				'ratio_h'   => $opts['ratio_h'],
				'max_width' => $opts['max_width'],
				'big_max'   => $opts['big_max'],
			),
			$atts,
			'page_gallery'
		);

		$ids    = '' !== trim( (string) $atts['ids'] ) ? WPGP_Settings::ids( $atts['ids'] ) : WPGP_Settings::ids( $opts['page_ids'] );
		$big_id = absint( $atts['big_id'] );
		// A big_id outside the list is still shown as the featured page (as in 2.x).
		$pages = self::published( $big_id ? WPGP_Settings::ids( array_merge( $ids, array( $big_id ) ) ) : $ids );
		if ( ! $pages ) {
			return current_user_can( 'manage_options' ) ? '<div class="pg-gallery-empty">' . esc_html__( 'No published pages are selected for the gallery.', 'wp-gallery-page' ) . '</div>' : '';
		}

		// The featured page: the chosen one if it is published, else the newest.
		$big = null;
		foreach ( $pages as $page ) {
			if ( $page->ID === $big_id ) {
				$big = $page;
			}
		}
		if ( ! $big ) {
			$big = $pages[0];
			foreach ( $pages as $page ) {
				if ( $page->post_date_gmt > $big->post_date_gmt ) {
					$big = $page;
				}
			}
		}
		$rest = array_values(
			array_filter(
				$pages,
				function ( $page ) use ( $big ) {
					return $page->ID !== $big->ID;
				}
			)
		);

		$playlist = WPGP_Settings::playlist( $atts['playlist'] );
		$show_yt  = '0' !== (string) $atts['show_yt'] && '' !== $playlist;
		$latest   = null;
		if ( ! empty( $opts['show_latest'] ) ) {
			$args = array(
				'post_type'           => 'post',
				'post_status'         => 'publish',
				'posts_per_page'      => WPGP_Settings::clamp( 'latest_count', $opts['latest_count'] ),
				'orderby'             => 'date',
				'order'               => 'DESC',
				'ignore_sticky_posts' => true,
				'no_found_rows'       => true,
			);
			if ( absint( $atts['category'] ) ) {
				$args['cat'] = absint( $atts['category'] );
			}
			$latest = new WP_Query( $args );
		}

		wp_enqueue_style( self::HANDLE );
		$images = (array) $opts['custom_images'];
		$style  = sprintf(
			'--pg-cols:%d;--pg-gap:%dpx;--pg-w:%d;--pg-h:%d;--pg-max:%dpx;--pg-big-max:%dpx',
			WPGP_Settings::clamp( 'columns', $atts['columns'] ),
			WPGP_Settings::clamp( 'gap', $atts['gap'] ),
			WPGP_Settings::clamp( 'ratio_w', $atts['ratio_w'] ),
			WPGP_Settings::clamp( 'ratio_h', $atts['ratio_h'] ),
			WPGP_Settings::clamp( 'max_width', $atts['max_width'] ),
			WPGP_Settings::clamp( 'big_max', $atts['big_max'] )
		);

		ob_start();
		?>
		<div class="pg-wrap" style="<?php echo esc_attr( $style ); ?>">
			<section class="pg-top<?php echo ( $show_yt || $latest ) ? '' : ' pg-top--solo'; ?>">
				<div class="pg-top-left"><?php echo self::card( $big, true, $images ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- card() escapes. ?></div>
				<?php if ( $show_yt || $latest ) : ?>
					<aside class="pg-side">
						<?php if ( $show_yt ) : ?>
							<div class="pg-panel pg-video-panel">
								<?php if ( '' !== $opts['video_title'] ) : ?>
									<h3><?php echo esc_html( $opts['video_title'] ); ?></h3>
								<?php endif; ?>
								<div class="pg-yt">
									<iframe src="<?php echo esc_url( 'https://www.youtube-nocookie.com/embed/videoseries?list=' . rawurlencode( $playlist ) ); ?>" title="<?php echo esc_attr( '' !== $opts['video_title'] ? $opts['video_title'] : __( 'YouTube playlist', 'wp-gallery-page' ) ); ?>" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" referrerpolicy="strict-origin-when-cross-origin" allowfullscreen loading="lazy"></iframe>
								</div>
							</div>
						<?php endif; ?>
						<?php if ( $latest ) : ?>
							<div class="pg-panel pg-latest-panel">
								<?php if ( '' !== $opts['latest_title'] ) : ?>
									<h3><?php echo esc_html( $opts['latest_title'] ); ?></h3>
								<?php endif; ?>
								<ol class="pg-latest-list">
									<?php if ( $latest->have_posts() ) : ?>
										<?php
										while ( $latest->have_posts() ) :
											$latest->the_post();
											?>
											<li class="pg-latest-item">
												<span class="pg-dot" aria-hidden="true"></span>
												<a class="pg-latest-link" href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
												<p class="pg-latest-excerpt"><?php echo esc_html( wp_trim_words( get_the_excerpt(), 26, '…' ) ); ?></p>
											</li>
										<?php endwhile; ?>
									<?php else : ?>
										<li class="pg-latest-item"><span class="pg-dot" aria-hidden="true"></span><?php esc_html_e( 'No posts found.', 'wp-gallery-page' ); ?></li>
									<?php endif; ?>
									<?php wp_reset_postdata(); ?>
								</ol>
							</div>
						<?php endif; ?>
					</aside>
				<?php endif; ?>
			</section>
			<?php if ( $rest ) : ?>
				<?php if ( '' !== $opts['grid_title'] ) : ?>
					<h3 class="pg-section-heading"><?php echo esc_html( $opts['grid_title'] ); ?></h3>
				<?php endif; ?>
				<section class="pg-grid">
					<?php
					foreach ( $rest as $page ) {
						echo self::card( $page, false, $images ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- card() escapes.
					}
					?>
				</section>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Image for a page: custom image from the settings, else the Featured Image
	 * (large), else ''. The admin screen uses the same order.
	 */
	public static function image( $page_id, $images, $size = 'large' ) {
		if ( ! empty( $images[ $page_id ] ) ) {
			return (string) $images[ $page_id ];
		}
		$url = get_the_post_thumbnail_url( $page_id, $size );
		return $url ? $url : '';
	}

	private static function card( $page, $big, $images ) {
		$title = get_the_title( $page );
		$url   = self::image( $page->ID, $images );
		$img   = $url
			? sprintf( '<img src="%s" alt="" %s decoding="async">', esc_url( $url ), $big ? 'fetchpriority="high"' : 'loading="lazy"' )
			: '<span class="pg-noimg" aria-hidden="true">' . esc_html__( 'No image', 'wp-gallery-page' ) . '</span>';
		return sprintf(
			'<a class="pg-card%s" href="%s"><div class="pg-imgbox">%s</div><span class="pg-title">%s</span></a>',
			$big ? ' pg-card--big' : '',
			esc_url( get_permalink( $page ) ),
			$img,
			esc_html( $title )
		);
	}
}

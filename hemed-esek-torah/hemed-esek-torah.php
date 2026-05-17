<?php
/**
 * Plugin Name: Hemed Esek Torah
 * Plugin URI: https://hemed.chepti.com/
 * Description: שיתוף פעילויות "עסק תורה" בחמ"ד עם טופס קדמי, גריד, ACF ופעמון אישורים.
 * Version: 1.0.2
 * Author: Chepti
 * Text Domain: hemed-esek-torah
 * Requires at least: 6.0
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'HET_VERSION', '1.0.2' );
define( 'HET_POST_TYPE', 'het_activity' );
define( 'HET_PLUGIN_FILE', __FILE__ );
define( 'HET_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'HET_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once HET_PLUGIN_DIR . 'includes/class-acf-fields.php';
require_once HET_PLUGIN_DIR . 'includes/class-submission.php';
require_once HET_PLUGIN_DIR . 'includes/class-render.php';

final class Hemed_Esek_Torah_Plugin {
	private Hemed_Esek_Torah_Submission $submission;
	private Hemed_Esek_Torah_Render $render;

	public function __construct() {
		$this->submission = new Hemed_Esek_Torah_Submission();
		$this->render     = new Hemed_Esek_Torah_Render( $this->submission );

		add_action( 'init', array( $this, 'register_post_type' ) );
		add_action( 'init', array( $this, 'maybe_flush_rewrite_rules' ), 99 );
		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
		add_action( 'admin_notices', array( $this, 'maybe_show_acf_notice' ) );
		add_action( 'admin_bar_menu', array( $this, 'add_pending_bell' ), 90 );
		add_action( 'wp_head', array( $this, 'print_admin_bar_styles' ) );
		add_action( 'admin_head', array( $this, 'print_admin_bar_styles' ) );

		add_shortcode( 'hemed_esek_torah_home', array( $this->render, 'home_shortcode' ) );
		add_shortcode( 'hemed_esek_torah_grid', array( $this->render, 'grid_shortcode' ) );
		add_shortcode( 'hemed_esek_torah_submit', array( $this->render, 'submit_shortcode' ) );

		add_filter( 'the_content', array( $this->render, 'filter_single_content' ) );

		new Hemed_Esek_Torah_ACF_Fields();
	}

	public function register_post_type(): void {
		$labels = array(
			'name'               => 'פעילויות עסק תורה',
			'singular_name'      => 'פעילות עסק תורה',
			'add_new'            => 'הוספת פעילות',
			'add_new_item'       => 'הוספת פעילות עסק תורה',
			'edit_item'          => 'עריכת פעילות',
			'new_item'           => 'פעילות חדשה',
			'view_item'          => 'צפייה בפעילות',
			'search_items'       => 'חיפוש פעילויות',
			'not_found'          => 'לא נמצאו פעילויות',
			'not_found_in_trash' => 'לא נמצאו פעילויות בפח',
			'all_items'          => 'כל הפעילויות',
			'menu_name'          => 'עסק תורה',
		);

		register_post_type(
			HET_POST_TYPE,
			array(
				'labels'             => $labels,
				'public'             => true,
				'publicly_queryable' => true,
				'has_archive'        => true,
				'menu_icon'          => 'dashicons-book-alt',
				'rewrite'            => array(
					'slug'       => 'esek-torah',
					'with_front' => false,
				),
				'show_in_rest'       => true,
				'supports'           => array( 'title', 'thumbnail', 'comments' ),
				'capability_type'    => 'post',
			)
		);
	}

	public function maybe_flush_rewrite_rules(): void {
		$stored = (string) get_option( 'het_rewrite_rules_version', '' );
		if ( $stored === HET_VERSION ) {
			return;
		}

		flush_rewrite_rules( true );
		update_option( 'het_rewrite_rules_version', HET_VERSION );
	}

	public function register_assets(): void {
		$style_url  = plugins_url( 'assets/css/frontend.css', HET_PLUGIN_FILE );
		$script_url = plugins_url( 'assets/js/frontend.js', HET_PLUGIN_FILE );

		wp_register_style(
			'hemed-esek-torah-frontend',
			$style_url,
			array(),
			HET_VERSION
		);

		wp_register_script(
			'hemed-esek-torah-frontend',
			$script_url,
			array(),
			HET_VERSION,
			true
		);

		wp_localize_script(
			'hemed-esek-torah-frontend',
			'HemedEsekTorah',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'het_like_activity' ),
			)
		);

		if ( $this->should_enqueue_frontend_assets() ) {
			wp_enqueue_style( 'hemed-esek-torah-frontend' );
			wp_enqueue_script( 'hemed-esek-torah-frontend' );
		}
	}

	private function should_enqueue_frontend_assets(): bool {
		if ( is_admin() || wp_doing_ajax() || wp_is_json_request() ) {
			return false;
		}

		if ( apply_filters( 'het_force_enqueue_assets', false ) ) {
			return true;
		}

		if ( is_singular( HET_POST_TYPE ) || is_post_type_archive( HET_POST_TYPE ) ) {
			return true;
		}

		if ( is_singular() ) {
			$obj = get_queried_object();
			if ( $obj instanceof WP_Post && $this->post_has_het_shortcode( $obj ) ) {
				return true;
			}
		}

		if ( get_option( 'show_on_front' ) === 'page' ) {
			$front_id = (int) get_option( 'page_on_front' );
			if ( $front_id > 0 ) {
				$front = get_post( $front_id );
				if ( $front instanceof WP_Post && $this->post_has_het_shortcode( $front ) ) {
					return true;
				}
			}
		}

		if ( is_front_page() ) {
			return true;
		}

		return (bool) apply_filters( 'het_enqueue_assets', false );
	}

	private function post_has_het_shortcode( WP_Post $post ): bool {
		if ( false !== strpos( $post->post_content, '[hemed_esek_torah_' ) ) {
			return true;
		}

		if ( function_exists( 'has_block' ) && has_block( 'core/shortcode', $post ) && false !== strpos( $post->post_content, 'hemed_esek_torah_' ) ) {
			return true;
		}

		return false;
	}

	public function maybe_show_acf_notice(): void {
		if ( function_exists( 'acf_add_local_field_group' ) ) {
			return;
		}

		echo '<div class="notice notice-warning"><p dir="rtl">תוסף עסק תורה פעיל, אבל ACF לא פעיל. יש להפעיל את ACF כדי לערוך את שדות הפעילות בממשק הניהול.</p></div>';
	}

	public function add_pending_bell( WP_Admin_Bar $admin_bar ): void {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}

		$pending = wp_count_posts( HET_POST_TYPE )->pending ?? 0;
		$count   = (int) $pending;
		$title   = '<span class="ab-icon dashicons dashicons-bell" aria-hidden="true"></span><span class="screen-reader-text">פעילויות ממתינות לאישור</span>';

		if ( $count > 0 ) {
			$title .= '<span class="het-admin-badge">' . esc_html( (string) $count ) . '</span>';
		}

		$admin_bar->add_node(
			array(
				'id'    => 'het-pending-activities',
				'title' => $title,
				'href'  => admin_url( 'edit.php?post_type=' . HET_POST_TYPE . '&post_status=pending' ),
				'meta'  => array(
					'title' => 'פעילויות עסק תורה ממתינות לאישור',
				),
			)
		);
	}

	public function print_admin_bar_styles(): void {
		if ( ! is_admin_bar_showing() ) {
			return;
		}
		?>
		<style>
			#wpadminbar #wp-admin-bar-het-pending-activities .ab-item {
				position: relative;
			}
			#wpadminbar #wp-admin-bar-het-pending-activities .ab-icon::before {
				top: 2px;
				color: #11a0db;
			}
			#wpadminbar .het-admin-badge {
				position: absolute;
				top: 3px;
				left: 2px;
				min-width: 18px;
				height: 18px;
				padding: 0 5px;
				border-radius: 999px;
				background: #f36f21;
				color: #fff;
				font-size: 11px;
				font-weight: 700;
				line-height: 18px;
				text-align: center;
			}
		</style>
		<?php
	}

	public static function activate(): void {
		$plugin = new self();
		$plugin->register_post_type();
		flush_rewrite_rules( true );
		update_option( 'het_rewrite_rules_version', HET_VERSION );
	}

	public static function deactivate(): void {
		flush_rewrite_rules();
	}
}

register_activation_hook( __FILE__, array( 'Hemed_Esek_Torah_Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Hemed_Esek_Torah_Plugin', 'deactivate' ) );

add_action(
	'plugins_loaded',
	static function (): void {
		new Hemed_Esek_Torah_Plugin();
	}
);

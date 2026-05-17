<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Hemed_Esek_Torah_Render {
	private Hemed_Esek_Torah_Submission $submission;
	private static array $viewed_posts = array();
	private static int $modal_output_count = 0;
	private static bool $modal_button_shortcode_used = false;

	public function __construct( Hemed_Esek_Torah_Submission $submission ) {
		$this->submission = $submission;

		add_action( 'wp_ajax_het_like_activity', array( $this, 'ajax_like_activity' ) );
		add_action( 'wp_ajax_nopriv_het_like_activity', array( $this, 'ajax_like_activity' ) );
		add_action( 'wp_footer', array( $this, 'maybe_print_orphan_modal' ), 19 );
	}

	public function home_shortcode(): string {
		$this->enqueue_assets();

		ob_start();
		?>
		<section class="het-home" dir="rtl">
			<div class="het-hero">
				<div class="het-hero__logo"><?php echo wp_kses_post( $this->get_logo_html() ); ?></div>
				<div class="het-hero__content">
					<p class="het-kicker">עסק תורה בחמ״ד</p>
					<h1>משתפים פעילויות קודש, לומדים אחד מהשני</h1>
					<p>מרחב ידידותי למורים לשיתוף רעיונות, הכנות, קבצי עזר ותובנות מהשטח.</p>
				</div>
				<button class="het-button het-button--upload-modal" type="button" data-het-open-modal>העלאת פעילות</button>
			</div>

			<?php echo $this->render_modal(); ?>
			<?php echo $this->grid_shortcode(); ?>
		</section>
		<?php
		return (string) ob_get_clean();
	}

	public function modal_button_shortcode( $atts = array(), $content = null ): string {
		unset( $content );
		$this->enqueue_assets();

		self::$modal_button_shortcode_used = true;

		$atts = shortcode_atts(
			array(
				'label' => 'העלאת פעילות',
			),
			$atts,
			'hemed_esek_torah_modal_button'
		);

		return sprintf(
			'<span class="het-header-upload-wrap"><button type="button" class="het-button het-button--upload-modal" data-het-open-modal>%s</button></span>',
			esc_html( $atts['label'] )
		);
	}

	public function maybe_print_orphan_modal(): void {
		if ( ! self::$modal_button_shortcode_used || self::$modal_output_count > 0 ) {
			return;
		}

		echo $this->render_modal();
	}

	public function grid_shortcode(): string {
		$this->enqueue_assets();

		$query = $this->get_activity_query();

		ob_start();
		?>
		<div class="het-grid-shell" dir="rtl">
			<?php echo $this->render_filters(); ?>

			<div class="het-grid" aria-live="polite">
				<?php if ( $query->have_posts() ) : ?>
					<?php
					while ( $query->have_posts() ) :
						$query->the_post();
						echo $this->render_card( get_the_ID() );
					endwhile;
					wp_reset_postdata();
					?>
				<?php else : ?>
					<div class="het-empty">לא נמצאו פעילויות שמתאימות לסינון הנוכחי.</div>
				<?php endif; ?>
			</div>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	public function submit_shortcode(): string {
		$this->enqueue_assets();

		return '<div class="het-submit-page" dir="rtl">' . $this->submission->render_form() . '</div>';
	}

	public function filter_single_content( string $content ): string {
		if ( ! is_singular( HET_POST_TYPE ) || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}

		$post_id = get_the_ID();
		$this->enqueue_assets();
		$this->increment_views( $post_id );

		ob_start();
		?>
		<article class="het-single" dir="rtl">
			<header class="het-single__header">
				<div class="het-single__logo"><?php echo wp_kses_post( $this->get_logo_html() ); ?></div>
				<div>
					<p class="het-kicker">פעילות עסק תורה</p>
					<h1><?php the_title(); ?></h1>
					<?php echo $this->render_meta_chips( $post_id ); ?>
				</div>
			</header>

			<?php echo $this->render_gallery( $post_id ); ?>

			<section class="het-panel">
				<h2>פירוט הפעילות</h2>
				<div class="het-text"><?php echo wp_kses_post( wpautop( $this->get_meta( $post_id, 'het_short_description' ) ) ); ?></div>
			</section>

			<?php echo $this->render_checklist( $post_id ); ?>
			<?php echo $this->render_resources( $post_id ); ?>
			<?php echo $this->render_people_block( $post_id, 'het_partners', 'שותפים', 'שם ותפקיד' ); ?>
			<?php echo $this->render_people_block( $post_id, 'het_feedback_quotes', 'ציטוטי משובים', 'משוב מהשטח' ); ?>

			<section class="het-panel het-comments">
				<h2>תגובות</h2>
				<?php echo $this->get_comments_html(); ?>
			</section>
		</article>
		<?php

		return (string) ob_get_clean();
	}

	public function ajax_like_activity(): void {
		check_ajax_referer( 'het_like_activity', 'nonce' );

		$post_id = isset( $_POST['postId'] ) ? absint( $_POST['postId'] ) : 0;
		if ( ! $post_id || HET_POST_TYPE !== get_post_type( $post_id ) ) {
			wp_send_json_error( array( 'message' => 'פעילות לא תקינה.' ), 400 );
		}

		$likes = (int) get_post_meta( $post_id, '_het_likes', true );
		$likes++;
		update_post_meta( $post_id, '_het_likes', $likes );

		wp_send_json_success( array( 'likes' => $likes ) );
	}

	private function enqueue_assets(): void {
		wp_enqueue_style( 'hemed-esek-torah-frontend' );
		wp_enqueue_script( 'hemed-esek-torah-frontend' );
	}

	private function render_modal(): string {
		self::$modal_output_count++;

		ob_start();
		?>
		<div class="het-modal" data-het-modal hidden>
			<div class="het-modal__backdrop" data-het-close-modal></div>
			<div class="het-modal__dialog" role="dialog" aria-modal="true" aria-label="העלאת תוכן חדש">
				<button class="het-modal__close" type="button" aria-label="סגירת חלונית" data-het-close-modal>×</button>
				<h2>העלאת תוכן חדש</h2>
				<?php echo $this->submission->render_form(); ?>
			</div>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	private function render_filters(): string {
		$current = array(
			'het_search'        => isset( $_GET['het_search'] ) ? sanitize_text_field( wp_unslash( $_GET['het_search'] ) ) : '',
			'het_district'      => isset( $_GET['het_district'] ) ? sanitize_text_field( wp_unslash( $_GET['het_district'] ) ) : '',
			'het_age_stage'     => isset( $_GET['het_age_stage'] ) ? sanitize_text_field( wp_unslash( $_GET['het_age_stage'] ) ) : '',
			'het_gender_track'  => isset( $_GET['het_gender_track'] ) ? sanitize_text_field( wp_unslash( $_GET['het_gender_track'] ) ) : '',
			'het_activity_type' => isset( $_GET['het_activity_type'] ) ? sanitize_text_field( wp_unslash( $_GET['het_activity_type'] ) ) : '',
		);

		ob_start();
		?>
		<form class="het-filters" method="get" dir="rtl">
			<label class="het-filters__search">
				<span>חיפוש</span>
				<input type="search" name="het_search" value="<?php echo esc_attr( $current['het_search'] ); ?>" placeholder="חפשו פעילות, מוסד או תיאור">
			</label>
			<?php
			$this->render_filter_select( 'het_district', 'מחוז', Hemed_Esek_Torah_ACF_Fields::district_choices(), $current['het_district'] );
			$this->render_filter_select( 'het_age_stage', 'שלב גיל', Hemed_Esek_Torah_ACF_Fields::age_stage_choices(), $current['het_age_stage'] );
			$this->render_filter_select( 'het_gender_track', 'בנים / בנות', Hemed_Esek_Torah_ACF_Fields::gender_choices(), $current['het_gender_track'] );
			$this->render_filter_select( 'het_activity_type', 'סוג פעילות', Hemed_Esek_Torah_ACF_Fields::activity_type_choices(), $current['het_activity_type'] );
			?>
			<div class="het-filters__actions">
				<button class="het-button" type="submit">סינון</button>
				<a class="het-button het-button--ghost" href="<?php echo esc_url( remove_query_arg( array_keys( $current ) ) ); ?>">ניקוי</a>
			</div>
		</form>
		<?php
		return (string) ob_get_clean();
	}

	private function render_filter_select( string $name, string $label, array $choices, string $current ): void {
		?>
		<label>
			<span><?php echo esc_html( $label ); ?></span>
			<select name="<?php echo esc_attr( $name ); ?>">
				<option value="">הכל</option>
				<?php foreach ( $choices as $value => $choice_label ) : ?>
					<option value="<?php echo esc_attr( (string) $value ); ?>" <?php selected( $current, (string) $value ); ?>>
						<?php echo esc_html( (string) $choice_label ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</label>
		<?php
	}

	private function get_activity_query(): WP_Query {
		$args = array(
			'post_type'      => HET_POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => 12,
			'paged'          => max( 1, get_query_var( 'paged' ) ? (int) get_query_var( 'paged' ) : (int) ( $_GET['paged'] ?? 1 ) ),
			'meta_query'     => array(),
		);

		if ( ! empty( $_GET['het_search'] ) ) {
			$args['s'] = sanitize_text_field( wp_unslash( $_GET['het_search'] ) );
		}

		foreach ( array( 'het_district', 'het_age_stage', 'het_gender_track', 'het_activity_type' ) as $field ) {
			if ( empty( $_GET[ $field ] ) ) {
				continue;
			}

			$args['meta_query'][] = array(
				'key'     => $field,
				'value'   => sanitize_text_field( wp_unslash( $_GET[ $field ] ) ),
				'compare' => '=',
			);
		}

		if ( count( $args['meta_query'] ) > 1 ) {
			$args['meta_query']['relation'] = 'AND';
		}

		return new WP_Query( $args );
	}

	private function render_card( int $post_id ): string {
		$image = get_the_post_thumbnail_url( $post_id, 'large' );
		if ( ! $image ) {
			$ids   = $this->get_image_ids( $post_id );
			$image = $ids ? wp_get_attachment_image_url( $ids[0], 'large' ) : '';
		}

		$comments = get_comments_number( $post_id );
		$views    = (int) get_post_meta( $post_id, '_het_views', true );
		$likes    = (int) get_post_meta( $post_id, '_het_likes', true );

		ob_start();
		?>
		<article class="het-card">
			<a class="het-card__image" href="<?php echo esc_url( get_permalink( $post_id ) ); ?>">
				<?php if ( $image ) : ?>
					<img src="<?php echo esc_url( $image ); ?>" alt="">
				<?php else : ?>
					<span>עסק תורה</span>
				<?php endif; ?>
			</a>
			<div class="het-card__body">
				<a class="het-card__title" href="<?php echo esc_url( get_permalink( $post_id ) ); ?>">
					<?php echo esc_html( get_the_title( $post_id ) ); ?>
				</a>
				<p><?php echo esc_html( $this->get_meta( $post_id, 'het_school_name' ) ); ?></p>
				<div class="het-card__chips">
					<span><?php echo esc_html( $this->get_meta( $post_id, 'het_district' ) ); ?></span>
					<span>סמל <?php echo esc_html( $this->get_meta( $post_id, 'het_institution_code' ) ); ?></span>
				</div>
			</div>
			<footer class="het-card__stats">
				<span title="צפיות"><?php echo esc_html( (string) $views ); ?> צפיות</span>
				<button type="button" data-het-like="<?php echo esc_attr( (string) $post_id ); ?>">
					<span data-het-like-count><?php echo esc_html( (string) $likes ); ?></span> לבבות
				</button>
				<span><?php echo esc_html( (string) $comments ); ?> תגובות</span>
			</footer>
		</article>
		<?php
		return (string) ob_get_clean();
	}

	private function render_meta_chips( int $post_id ): string {
		$items = array_filter(
			array(
				$this->get_meta( $post_id, 'het_school_name' ),
				'סמל ' . $this->get_meta( $post_id, 'het_institution_code' ),
				$this->get_meta( $post_id, 'het_district' ),
				$this->get_meta( $post_id, 'het_age_stage' ),
				$this->get_meta( $post_id, 'het_gender_track' ),
				$this->get_meta( $post_id, 'het_activity_type' ),
			)
		);

		$html = '<div class="het-chips">';
		foreach ( $items as $item ) {
			$html .= '<span>' . esc_html( $item ) . '</span>';
		}
		$html .= '</div>';

		return $html;
	}

	private function render_gallery( int $post_id ): string {
		$ids = $this->get_image_ids( $post_id );
		if ( empty( $ids ) ) {
			return '';
		}

		ob_start();
		?>
		<section class="het-gallery" data-het-carousel>
			<button type="button" class="het-gallery__nav" data-het-carousel-prev aria-label="תמונה קודמת">‹</button>
			<div class="het-gallery__track">
				<?php foreach ( $ids as $index => $id ) : ?>
					<figure class="het-gallery__slide <?php echo 0 === $index ? 'is-active' : ''; ?>">
						<?php echo wp_get_attachment_image( $id, 'large' ); ?>
					</figure>
				<?php endforeach; ?>
			</div>
			<button type="button" class="het-gallery__nav" data-het-carousel-next aria-label="תמונה הבאה">›</button>
		</section>
		<?php
		return (string) ob_get_clean();
	}

	private function render_checklist( int $post_id ): string {
		$items = $this->lines( $this->get_meta( $post_id, 'het_prep_checklist' ) );
		if ( empty( $items ) ) {
			return '';
		}

		ob_start();
		?>
		<section class="het-panel">
			<h2>צ׳קליסט הכנות</h2>
			<ul class="het-checklist">
				<?php foreach ( $items as $item ) : ?>
					<li>
						<label>
							<input type="checkbox">
							<span><?php echo esc_html( $item ); ?></span>
						</label>
					</li>
				<?php endforeach; ?>
			</ul>
		</section>
		<?php
		return (string) ob_get_clean();
	}

	private function render_resources( int $post_id ): string {
		$resources = $this->get_resources( $post_id );
		if ( empty( $resources ) ) {
			return '';
		}

		ob_start();
		?>
		<section class="het-panel">
			<h2>קבצי עזר וקישורים</h2>
			<div class="het-resources">
				<?php foreach ( $resources as $resource ) : ?>
					<a href="<?php echo esc_url( $resource['url'] ); ?>" target="_blank" rel="noopener">
						<?php echo esc_html( $resource['label'] ); ?>
					</a>
				<?php endforeach; ?>
			</div>
		</section>
		<?php
		return (string) ob_get_clean();
	}

	private function render_people_block( int $post_id, string $field, string $title, string $fallback_label ): string {
		$items = $this->lines( $this->get_meta( $post_id, $field ) );
		if ( empty( $items ) ) {
			return '';
		}

		ob_start();
		?>
		<section class="het-panel">
			<h2><?php echo esc_html( $title ); ?></h2>
			<div class="het-people">
				<?php foreach ( $items as $item ) : ?>
					<?php $parts = array_map( 'trim', explode( '|', $item, 2 ) ); ?>
					<div>
						<strong><?php echo esc_html( $parts[0] ); ?></strong>
						<span><?php echo esc_html( $parts[1] ?? $fallback_label ); ?></span>
					</div>
				<?php endforeach; ?>
			</div>
		</section>
		<?php
		return (string) ob_get_clean();
	}

	private function get_comments_html(): string {
		if ( ! comments_open() && 0 === (int) get_comments_number() ) {
			return '<p>התגובות סגורות לפעילות זו.</p>';
		}

		ob_start();
		comments_template();
		return (string) ob_get_clean();
	}

	private function get_resources( int $post_id ): array {
		$resources = array();

		for ( $i = 1; $i <= 5; $i++ ) {
			$file_id = (int) $this->get_meta( $post_id, 'het_resource_file_' . $i );
			if ( $file_id ) {
				$resources[] = array(
					'url'   => wp_get_attachment_url( $file_id ),
					'label' => get_the_title( $file_id ) ?: 'קובץ עזר ' . $i,
				);
			}

			$url = $this->get_meta( $post_id, 'het_resource_url_' . $i );
			if ( $url ) {
				$resources[] = array(
					'url'   => $url,
					'label' => 'קישור עזר ' . $i,
				);
			}
		}

		return array_filter(
			$resources,
			static fn( $resource ) => ! empty( $resource['url'] )
		);
	}

	private function get_image_ids( int $post_id ): array {
		$ids = array();
		for ( $i = 1; $i <= 5; $i++ ) {
			$id = (int) $this->get_meta( $post_id, 'het_image_' . $i );
			if ( $id ) {
				$ids[] = $id;
			}
		}
		return $ids;
	}

	private function get_meta( int $post_id, string $key ): string {
		if ( function_exists( 'get_field' ) ) {
			$value = get_field( $key, $post_id );
		} else {
			$value = get_post_meta( $post_id, $key, true );
		}

		return is_scalar( $value ) ? (string) $value : '';
	}

	private function lines( string $value ): array {
		$lines = preg_split( '/\r\n|\r|\n/', $value );
		if ( ! is_array( $lines ) ) {
			return array();
		}

		return array_values( array_filter( array_map( 'trim', $lines ) ) );
	}

	private function increment_views( int $post_id ): void {
		if ( isset( self::$viewed_posts[ $post_id ] ) || is_admin() ) {
			return;
		}

		self::$viewed_posts[ $post_id ] = true;
		$views = (int) get_post_meta( $post_id, '_het_views', true );
		update_post_meta( $post_id, '_het_views', $views + 1 );
	}

	private function get_logo_html(): string {
		$custom_logo_id = (int) get_theme_mod( 'custom_logo' );
		if ( $custom_logo_id ) {
			return wp_get_attachment_image( $custom_logo_id, 'medium', false, array( 'class' => 'het-logo' ) );
		}

		return '<img class="het-logo" src="https://i.imgur.com/9xCiffu.png" alt="חמ״ד">';
	}
}

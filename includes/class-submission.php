<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Hemed_Esek_Torah_Submission {
	private array $messages = array();

	public function __construct() {
		add_action( 'init', array( $this, 'handle_submission' ) );
	}

	public function handle_submission(): void {
		if ( empty( $_POST['het_submit_activity'] ) ) {
			return;
		}

		if ( ! isset( $_POST['het_submission_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['het_submission_nonce'] ) ), 'het_submit_activity' ) ) {
			$this->redirect_with_message( 'error', 'פג תוקף הטופס. נא לרענן ולנסות שוב.' );
		}

		$data   = $this->sanitize_submission();
		$errors = $this->validate_submission( $data );

		if ( ! empty( $errors ) ) {
			$this->redirect_with_message( 'error', implode( ' ', $errors ) );
		}

		$post_id = wp_insert_post(
			array(
				'post_type'      => HET_POST_TYPE,
				'post_title'     => $data['title'],
				'post_content'   => $data['het_school_name'] . "\n\n" . $data['het_short_description'],
				'post_status'    => 'pending',
				'post_author'    => get_current_user_id(),
				'comment_status' => 'open',
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			$this->redirect_with_message( 'error', 'לא הצלחנו לשמור את הפעילות. נא לנסות שוב.' );
		}

		$this->save_meta_fields( (int) $post_id, $data );
		$this->handle_uploads( (int) $post_id );

		$this->redirect_with_message( 'success', 'תודה! הפעילות נשלחה וממתינה לאישור עורך.' );
	}

	public function render_form(): string {
		$message = $this->get_current_message();

		ob_start();
		?>
		<form class="het-form" method="post" enctype="multipart/form-data" dir="rtl">
			<?php wp_nonce_field( 'het_submit_activity', 'het_submission_nonce' ); ?>
			<input type="hidden" name="het_submit_activity" value="1">

			<?php if ( $message ) : ?>
				<div class="het-message het-message--<?php echo esc_attr( $message['type'] ); ?>">
					<?php echo esc_html( $message['text'] ); ?>
				</div>
			<?php endif; ?>

			<div class="het-form__grid">
				<label>
					<span>כותרת הפעילות*</span>
					<input type="text" name="het_title" required maxlength="120" placeholder="שם קצר וברור לפעילות">
				</label>

				<label>
					<span>סמל מוסד*</span>
					<input type="text" name="het_institution_code" required inputmode="numeric" maxlength="6" pattern="\d{6}" placeholder="123456">
				</label>

				<label>
					<span>שם בית הספר*</span>
					<input type="text" name="het_school_name" required maxlength="120">
				</label>

				<label>
					<span>מחוז*</span>
					<select name="het_district" required>
						<?php $this->render_options( Hemed_Esek_Torah_ACF_Fields::district_choices() ); ?>
					</select>
				</label>

				<label>
					<span>שלב גיל*</span>
					<select name="het_age_stage" required>
						<?php $this->render_options( Hemed_Esek_Torah_ACF_Fields::age_stage_choices() ); ?>
					</select>
				</label>

				<label>
					<span>בנים / בנות*</span>
					<select name="het_gender_track" required>
						<?php $this->render_options( Hemed_Esek_Torah_ACF_Fields::gender_choices() ); ?>
					</select>
				</label>

				<label>
					<span>סוג פעילות</span>
					<select name="het_activity_type">
						<option value="">ללא סינון מיוחד</option>
						<?php $this->render_options( Hemed_Esek_Torah_ACF_Fields::activity_type_choices() ); ?>
					</select>
				</label>

				<label class="het-form__wide">
					<span>פירוט הפעילות* <small>5-7 שורות</small></span>
					<textarea name="het_short_description" required rows="6" placeholder="מה עשיתם, איך זה עבד, ומה הערך למורים אחרים?"></textarea>
				</label>

				<label class="het-form__wide">
					<span>צ׳קליסט הכנות</span>
					<textarea name="het_prep_checklist" rows="5" placeholder="כל פעולה בשורה נפרדת"></textarea>
				</label>

				<div class="het-form__wide het-upload-group">
					<strong>תמונות*</strong>
					<p>יש להעלות 2-5 תמונות. נא לא להעלות תמונות עם פנים של תלמידים.</p>
					<?php for ( $i = 1; $i <= 5; $i++ ) : ?>
						<label>
							<span>תמונה <?php echo esc_html( (string) $i ); ?><?php echo $i <= 2 ? '*' : ''; ?></span>
							<input type="file" name="het_image_<?php echo esc_attr( (string) $i ); ?>" accept="image/*" <?php echo $i <= 2 ? 'required' : ''; ?>>
						</label>
					<?php endfor; ?>
				</div>

				<div class="het-form__wide het-upload-group">
					<strong>קבצי עזר וקישורים</strong>
					<?php for ( $i = 1; $i <= 5; $i++ ) : ?>
						<div class="het-form__pair">
							<label>
								<span>קובץ עזר <?php echo esc_html( (string) $i ); ?></span>
								<input type="file" name="het_resource_file_<?php echo esc_attr( (string) $i ); ?>">
							</label>
							<label>
								<span>קישור עזר <?php echo esc_html( (string) $i ); ?></span>
								<input type="url" name="het_resource_url_<?php echo esc_attr( (string) $i ); ?>" placeholder="https://">
							</label>
						</div>
					<?php endfor; ?>
				</div>

				<label class="het-form__wide">
					<span>שותפים</span>
					<textarea name="het_partners" rows="4" placeholder="כל שותף בשורה נפרדת: שם | תפקיד"></textarea>
				</label>

				<label class="het-form__wide">
					<span>ציטוטי משובים</span>
					<textarea name="het_feedback_quotes" rows="4" placeholder="כל ציטוט בשורה נפרדת"></textarea>
				</label>
			</div>

			<button class="het-button het-button--primary" type="submit">שליחת פעילות לאישור</button>
		</form>
		<?php
		return (string) ob_get_clean();
	}

	private function render_options( array $choices ): void {
		foreach ( $choices as $value => $label ) {
			printf(
				'<option value="%s">%s</option>',
				esc_attr( (string) $value ),
				esc_html( (string) $label )
			);
		}
	}

	private function sanitize_submission(): array {
		return array(
			'title'                 => isset( $_POST['het_title'] ) ? sanitize_text_field( wp_unslash( $_POST['het_title'] ) ) : '',
			'het_institution_code'  => isset( $_POST['het_institution_code'] ) ? preg_replace( '/\D+/', '', (string) wp_unslash( $_POST['het_institution_code'] ) ) : '',
			'het_school_name'       => isset( $_POST['het_school_name'] ) ? sanitize_text_field( wp_unslash( $_POST['het_school_name'] ) ) : '',
			'het_district'          => isset( $_POST['het_district'] ) ? sanitize_text_field( wp_unslash( $_POST['het_district'] ) ) : '',
			'het_age_stage'         => isset( $_POST['het_age_stage'] ) ? sanitize_text_field( wp_unslash( $_POST['het_age_stage'] ) ) : '',
			'het_gender_track'      => isset( $_POST['het_gender_track'] ) ? sanitize_text_field( wp_unslash( $_POST['het_gender_track'] ) ) : '',
			'het_activity_type'     => isset( $_POST['het_activity_type'] ) ? sanitize_text_field( wp_unslash( $_POST['het_activity_type'] ) ) : '',
			'het_short_description' => isset( $_POST['het_short_description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['het_short_description'] ) ) : '',
			'het_prep_checklist'    => isset( $_POST['het_prep_checklist'] ) ? sanitize_textarea_field( wp_unslash( $_POST['het_prep_checklist'] ) ) : '',
			'het_partners'          => isset( $_POST['het_partners'] ) ? sanitize_textarea_field( wp_unslash( $_POST['het_partners'] ) ) : '',
			'het_feedback_quotes'   => isset( $_POST['het_feedback_quotes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['het_feedback_quotes'] ) ) : '',
			'resource_urls'         => $this->sanitize_resource_urls(),
		);
	}

	private function sanitize_resource_urls(): array {
		$urls = array();
		for ( $i = 1; $i <= 5; $i++ ) {
			$key          = 'het_resource_url_' . $i;
			$urls[ $key ] = isset( $_POST[ $key ] ) ? esc_url_raw( wp_unslash( $_POST[ $key ] ) ) : '';
		}
		return $urls;
	}

	private function validate_submission( array $data ): array {
		$errors = array();

		if ( '' === $data['title'] ) {
			$errors[] = 'נא להזין כותרת פעילות.';
		}

		if ( ! preg_match( '/^\d{6}$/', $data['het_institution_code'] ) ) {
			$errors[] = 'סמל מוסד חייב לכלול 6 ספרות בדיוק.';
		}

		foreach ( array( 'het_school_name', 'het_district', 'het_age_stage', 'het_gender_track', 'het_short_description' ) as $key ) {
			if ( '' === trim( (string) $data[ $key ] ) ) {
				$errors[] = 'נא למלא את כל שדות החובה.';
				break;
			}
		}

		for ( $i = 1; $i <= 2; $i++ ) {
			$key = 'het_image_' . $i;
			if ( empty( $_FILES[ $key ]['name'] ) ) {
				$errors[] = 'יש להעלות לפחות שתי תמונות.';
				break;
			}
		}

		return $errors;
	}

	private function save_meta_fields( int $post_id, array $data ): void {
		$fields = array(
			'het_institution_code',
			'het_school_name',
			'het_district',
			'het_age_stage',
			'het_gender_track',
			'het_activity_type',
			'het_short_description',
			'het_prep_checklist',
			'het_partners',
			'het_feedback_quotes',
		);

		foreach ( $fields as $field ) {
			$this->save_field( $post_id, $field, $data[ $field ] ?? '' );
		}

		foreach ( $data['resource_urls'] as $field => $url ) {
			$this->save_field( $post_id, $field, $url );
		}
	}

	private function handle_uploads( int $post_id ): void {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';

		for ( $i = 1; $i <= 5; $i++ ) {
			$field = 'het_image_' . $i;
			if ( empty( $_FILES[ $field ]['name'] ) ) {
				continue;
			}

			$attachment_id = media_handle_upload( $field, $post_id );
			if ( is_wp_error( $attachment_id ) ) {
				continue;
			}

			$this->save_field( $post_id, $field, (int) $attachment_id );
			if ( 1 === $i ) {
				set_post_thumbnail( $post_id, (int) $attachment_id );
			}
		}

		for ( $i = 1; $i <= 5; $i++ ) {
			$field = 'het_resource_file_' . $i;
			if ( empty( $_FILES[ $field ]['name'] ) ) {
				continue;
			}

			$attachment_id = media_handle_upload( $field, $post_id );
			if ( ! is_wp_error( $attachment_id ) ) {
				$this->save_field( $post_id, $field, (int) $attachment_id );
			}
		}
	}

	private function save_field( int $post_id, string $field, $value ): void {
		if ( function_exists( 'update_field' ) ) {
			update_field( $field, $value, $post_id );
			return;
		}

		update_post_meta( $post_id, $field, $value );
	}

	private function redirect_with_message( string $type, string $text ): void {
		$key = 'het_message_' . wp_generate_uuid4();
		set_transient(
			$key,
			array(
				'type' => $type,
				'text' => $text,
			),
			MINUTE_IN_SECONDS * 5
		);

		$url = wp_get_referer() ? wp_get_referer() : home_url( '/' );
		wp_safe_redirect( add_query_arg( 'het_message', rawurlencode( $key ), $url ) );
		exit;
	}

	private function get_current_message(): ?array {
		if ( empty( $_GET['het_message'] ) ) {
			return null;
		}

		$key     = sanitize_text_field( wp_unslash( $_GET['het_message'] ) );
		$message = get_transient( $key );
		delete_transient( $key );

		return is_array( $message ) ? $message : null;
	}
}

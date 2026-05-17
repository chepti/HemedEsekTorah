<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Hemed_Esek_Torah_ACF_Fields {
	public function __construct() {
		add_action( 'acf/init', array( $this, 'register_fields' ) );
		add_filter( 'acf/validate_value/name=het_institution_code', array( $this, 'validate_institution_code' ), 10, 4 );
	}

	public function register_fields(): void {
		if ( ! function_exists( 'acf_add_local_field_group' ) ) {
			return;
		}

		acf_add_local_field_group(
			array(
				'key'                   => 'group_het_activity_details',
				'title'                 => 'פרטי פעילות עסק תורה',
				'fields'                => $this->get_fields(),
				'location'              => array(
					array(
						array(
							'param'    => 'post_type',
							'operator' => '==',
							'value'    => HET_POST_TYPE,
						),
					),
				),
				'menu_order'            => 0,
				'position'              => 'normal',
				'style'                 => 'default',
				'label_placement'       => 'top',
				'instruction_placement' => 'label',
				'active'                => true,
				'show_in_rest'          => 1,
			)
		);
	}

	public function validate_institution_code( $valid, $value, $field, $input ) {
		unset( $field, $input );

		if ( true !== $valid ) {
			return $valid;
		}

		if ( '' === trim( (string) $value ) ) {
			return 'נא להזין סמל מוסד.';
		}

		return preg_match( '/^\d{6}$/', (string) $value ) ? true : 'סמל מוסד חייב לכלול 6 ספרות בדיוק.';
	}

	private function get_fields(): array {
		$fields = array(
			array(
				'key'           => 'field_het_institution_code',
				'label'         => 'סמל מוסד',
				'name'          => 'het_institution_code',
				'type'          => 'text',
				'instructions'  => 'יש להזין 6 ספרות בדיוק.',
				'required'      => 1,
				'maxlength'     => 6,
				'placeholder'   => '123456',
				'wrapper'       => array( 'width' => '25' ),
			),
			array(
				'key'           => 'field_het_school_name',
				'label'         => 'שם בית הספר',
				'name'          => 'het_school_name',
				'type'          => 'text',
				'required'      => 1,
				'wrapper'       => array( 'width' => '25' ),
			),
			array(
				'key'           => 'field_het_district',
				'label'         => 'מחוז',
				'name'          => 'het_district',
				'type'          => 'select',
				'required'      => 1,
				'choices'       => self::district_choices(),
				'ui'            => 1,
				'wrapper'       => array( 'width' => '25' ),
			),
			array(
				'key'           => 'field_het_age_stage',
				'label'         => 'שלב גיל',
				'name'          => 'het_age_stage',
				'type'          => 'select',
				'required'      => 1,
				'choices'       => self::age_stage_choices(),
				'ui'            => 1,
				'wrapper'       => array( 'width' => '25' ),
			),
			array(
				'key'           => 'field_het_gender_track',
				'label'         => 'בנים / בנות',
				'name'          => 'het_gender_track',
				'type'          => 'select',
				'required'      => 1,
				'choices'       => self::gender_choices(),
				'ui'            => 1,
				'wrapper'       => array( 'width' => '25' ),
			),
			array(
				'key'           => 'field_het_activity_type',
				'label'         => 'סוג פעילות',
				'name'          => 'het_activity_type',
				'type'          => 'select',
				'required'      => 0,
				'choices'       => self::activity_type_choices(),
				'allow_null'    => 1,
				'ui'            => 1,
				'wrapper'       => array( 'width' => '25' ),
			),
			array(
				'key'          => 'field_het_short_description',
				'label'        => 'פירוט הפעילות',
				'name'         => 'het_short_description',
				'type'         => 'textarea',
				'instructions' => 'מומלץ לכתוב 5-7 שורות.',
				'required'     => 1,
				'rows'         => 6,
				'new_lines'    => 'br',
			),
			array(
				'key'          => 'field_het_prep_checklist',
				'label'        => 'צ׳קליסט הכנות',
				'name'         => 'het_prep_checklist',
				'type'         => 'textarea',
				'instructions' => 'כתבו כל פעולת הכנה בשורה נפרדת. הסימון באתר הוא מקומי ואינו נשמר.',
				'required'     => 0,
				'rows'         => 6,
				'new_lines'    => '',
			),
		);

		for ( $i = 1; $i <= 5; $i++ ) {
			$fields[] = array(
				'key'           => 'field_het_image_' . $i,
				'label'         => 'תמונה ' . $i,
				'name'          => 'het_image_' . $i,
				'type'          => 'image',
				'instructions'  => 1 === $i ? 'נא לא להעלות תמונות עם פנים של תלמידים.' : '',
				'required'      => $i <= 2 ? 1 : 0,
				'return_format' => 'id',
				'preview_size'  => 'medium',
				'library'       => 'all',
				'wrapper'       => array( 'width' => '20' ),
			);
		}

		for ( $i = 1; $i <= 5; $i++ ) {
			$fields[] = array(
				'key'           => 'field_het_resource_file_' . $i,
				'label'         => 'קובץ עזר ' . $i,
				'name'          => 'het_resource_file_' . $i,
				'type'          => 'file',
				'required'      => 0,
				'return_format' => 'id',
				'library'       => 'all',
				'wrapper'       => array( 'width' => '50' ),
			);
			$fields[] = array(
				'key'           => 'field_het_resource_url_' . $i,
				'label'         => 'קישור עזר ' . $i,
				'name'          => 'het_resource_url_' . $i,
				'type'          => 'url',
				'required'      => 0,
				'wrapper'       => array( 'width' => '50' ),
			);
		}

		$fields[] = array(
			'key'          => 'field_het_partners',
			'label'        => 'שותפים',
			'name'         => 'het_partners',
			'type'         => 'textarea',
			'instructions' => 'כל שותף בשורה נפרדת, למשל: רחל כהן | רכזת חברתית.',
			'required'     => 0,
			'rows'         => 5,
			'new_lines'    => '',
		);

		$fields[] = array(
			'key'          => 'field_het_feedback_quotes',
			'label'        => 'ציטוטי משובים',
			'name'         => 'het_feedback_quotes',
			'type'         => 'textarea',
			'instructions' => 'כל ציטוט בשורה נפרדת.',
			'required'     => 0,
			'rows'         => 5,
			'new_lines'    => '',
		);

		return $fields;
	}

	public static function district_choices(): array {
		return array(
			'צפון'     => 'צפון',
			'חיפה'     => 'חיפה',
			'מרכז'     => 'מרכז',
			'תל אביב'  => 'תל אביב',
			'ירושלים'  => 'ירושלים',
			'דרום'     => 'דרום',
			'התיישבות' => 'התיישבות',
			'מנח"י'    => 'מנח"י',
			'אחר'      => 'אחר',
		);
	}

	public static function age_stage_choices(): array {
		return array(
			'יסודי' => 'יסודי',
			'חטיבה' => 'חטיבה',
			'תיכון' => 'תיכון',
		);
	}

	public static function gender_choices(): array {
		return array(
			'בנים'  => 'בנים',
			'בנות'  => 'בנות',
			'מעורב' => 'מעורב',
		);
	}

	public static function activity_type_choices(): array {
		return array(
			'שיעור'      => 'שיעור',
			'יום שיא'    => 'יום שיא',
			'חקר'        => 'חקר',
			'משחק'       => 'משחק',
			'תערוכה'     => 'תערוכה',
			'קהילה'      => 'קהילה',
			'למידת עמיתים' => 'למידת עמיתים',
			'אחר'        => 'אחר',
		);
	}
}

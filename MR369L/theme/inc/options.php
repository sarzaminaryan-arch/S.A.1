<?php
/**
 * MR369L — صفحهٔ تنظیمات مالک (Settings API).
 *
 * قاعده‌های این ماژول:
 * - هر گزینهٔ ثبت‌شده باید sanitize_callback داشته باشد.
 * - هر فیلد باید برچسب (label) و توضیح داشته باشد و با کیبورد قابل استفاده باشد.
 * - فقط گزینه‌هایی اینجا ثبت می‌شوند که **پیاده‌سازی و آزمون** شده‌اند
 *   (قاعدهٔ «بدون تنظیم برای قابلیت پیاده‌نشده»).
 * - دسترسی: edit_theme_options.
 *
 * @package MR369L
 * @since   0.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * مقادیر پیش‌فرض گزینه‌ها.
 *
 * @return array<string,string>
 */
function mr369l_default_options() {
	return array(
		'motion_level' => 'subtle',
	);
}

/**
 * خواندن یک گزینه با مقدار پیش‌فرض.
 *
 * @param string $key نام گزینه.
 * @return string|null
 */
function mr369l_get_option( $key ) {
	$defaults = mr369l_default_options();
	$options  = get_option( 'mr369l_options', array() );

	if ( ! is_array( $options ) ) {
		$options = array();
	}

	if ( isset( $options[ $key ] ) && is_scalar( $options[ $key ] ) ) {
		return (string) $options[ $key ];
	}

	return isset( $defaults[ $key ] ) ? $defaults[ $key ] : null;
}

/**
 * پاک‌سازی گزینه‌ها پیش از ذخیره.
 *
 * هر مقدار با فهرست مجاز بررسی می‌شود؛ مقدار نامعتبر به پیش‌فرض برمی‌گردد.
 *
 * @param mixed $input ورودی خام فرم.
 * @return array<string,string>
 */
function mr369l_sanitize_options( $input ) {
	$defaults = mr369l_default_options();
	$output   = $defaults;

	if ( ! is_array( $input ) ) {
		return $output;
	}

	$allowed_motion = array( 'off', 'subtle', 'full' );

	if ( isset( $input['motion_level'] ) && is_string( $input['motion_level'] ) ) {
		$motion = sanitize_key( wp_unslash( $input['motion_level'] ) );

		if ( in_array( $motion, $allowed_motion, true ) ) {
			$output['motion_level'] = $motion;
		}
	}

	return $output;
}

/**
 * ثبت تنظیمات، بخش‌ها و فیلدها.
 *
 * @return void
 */
function mr369l_register_settings() {
	register_setting(
		'mr369l_settings',
		'mr369l_options',
		array(
			'type'              => 'array',
			'sanitize_callback' => 'mr369l_sanitize_options',
			'default'           => mr369l_default_options(),
			'show_in_rest'      => false,
		)
	);

	add_settings_section(
		'mr369l_section_appearance',
		__( 'ظاهر و حرکت', 'mr369l' ),
		'mr369l_section_appearance_intro',
		'mr369l-settings'
	);

	add_settings_field(
		'mr369l_field_motion',
		__( 'سطح انیمیشن تایپوگرافی', 'mr369l' ),
		'mr369l_field_motion',
		'mr369l-settings',
		'mr369l_section_appearance',
		array( 'label_for' => 'mr369l-motion-level' )
	);
}
add_action( 'admin_init', 'mr369l_register_settings' );

/**
 * توضیح بخش ظاهر و حرکت.
 *
 * @return void
 */
function mr369l_section_appearance_intro() {
	echo '<p>' . esc_html__( 'این تنظیمات ظاهر قالب را کنترل می‌کند و روی محتوای فروشگاه اثری ندارد.', 'mr369l' ) . '</p>';
}

/**
 * فیلد انتخاب سطح موشن.
 *
 * @param array $args آرگومان‌های add_settings_field.
 * @return void
 */
function mr369l_field_motion( $args ) {
	$current = mr369l_get_option( 'motion_level' );
	$choices = array(
		'off'    => __( 'خاموش — بدون انیمیشن', 'mr369l' ),
		'subtle' => __( 'ملایم (پیش‌فرض) — ورود نرم عنوان‌ها و کارت‌ها', 'mr369l' ),
		'full'   => __( 'نمایشی — حرکت پویا در بخش‌های اصلی صفحهٔ نخست', 'mr369l' ),
	);

	printf( '<select id="mr369l-motion-level" name="mr369l_options[motion_level]" aria-describedby="mr369l-motion-help">' );

	foreach ( $choices as $value => $label ) {
		printf(
			'<option value="%s"%s>%s</option>',
			esc_attr( $value ),
			selected( $current, $value, false ),
			esc_html( $label )
		);
	}

	echo '</select>';

	printf(
		'<p class="description" id="mr369l-motion-help">%s</p>',
		esc_html__( 'در هر سه حالت، اگر کاربر سیستم‌عامل خود را روی «کاهش حرکت» تنظیم کرده باشد، انیمیشن‌ها خودکار خاموش می‌شوند.', 'mr369l' )
	);

	if ( isset( $args['label_for'] ) && $args['label_for'] ) {
		printf( '<span class="screen-reader-text">%s</span>', esc_html__( 'انتخاب سطح انیمیشن', 'mr369l' ) );
	}
}

/**
 * افزودن صفحهٔ تنظیمات به منوی «ظاهر».
 *
 * @return void
 */
function mr369l_admin_menu() {
	add_theme_page(
		__( 'تنظیمات MR369L', 'mr369l' ),
		__( 'تنظیمات MR369L', 'mr369l' ),
		'edit_theme_options',
		'mr369l-settings',
		'mr369l_render_settings_page'
	);
}
add_action( 'admin_menu', 'mr369l_admin_menu' );

/**
 * نمایش صفحهٔ تنظیمات.
 *
 * @return void
 */
function mr369l_render_settings_page() {
	if ( ! current_user_can( 'edit_theme_options' ) ) {
		wp_die( esc_html__( 'شما اجازهٔ دسترسی به این صفحه را ندارید.', 'mr369l' ) );
	}
	?>
	<div class="wrap">
		<h1><?php echo esc_html__( 'تنظیمات MR369L', 'mr369l' ); ?></h1>
		<p><?php echo esc_html__( 'گزینه‌های نمایشی قالب. برای چیدمان و رنگ‌ها از ویرایشگر سایت (ظاهر ← ویرایشگر) استفاده کنید.', 'mr369l' ); ?></p>
		<form action="options.php" method="post">
			<?php
			settings_fields( 'mr369l_settings' );
			do_settings_sections( 'mr369l-settings' );
			submit_button( __( 'ذخیرهٔ تنظیمات', 'mr369l' ) );
			?>
		</form>
	</div>
	<?php
}

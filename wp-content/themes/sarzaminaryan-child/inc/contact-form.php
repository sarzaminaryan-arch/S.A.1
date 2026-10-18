<?php
/**
 * فرم تماس داخلی قالب — ارسال پیام به ایمیل تماس سایت.
 *
 * صفحهٔ «تماس با ما» تا نسخهٔ ۲٫۱۰ فقط لینک ایمیل داشت و دکمهٔ ارسال کار نمی‌کرد؛
 * این ماژول فرم واقعی می‌سازد (شورت‌کد [sa_contact_form] و الحاق خودکار به صفحهٔ تماس)
 * و پیام‌ها را با wp_mail به ایمیل تماس (پیش‌فرض: Mail@sarzaminaryan.ir) می‌فرستد.
 *
 * @package Sarzaminaryan_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Recipient of contact messages: customizer value, falling back to Mail@sarzaminaryan.ir.
 *
 * @return string
 */
function sa_contact_recipient() {
	$email = trim( (string) get_theme_mod( 'sa_contact_email', '' ) );
	return is_email( $email ) ? $email : 'Mail@sarzaminaryan.ir';
}

/**
 * Render the contact form.
 *
 * @return string
 */
function sa_contact_form_html() {
	$notice = '';
	if ( isset( $_GET['sa_contact'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$flag   = sanitize_key( wp_unslash( $_GET['sa_contact'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$notice = 'sent' === $flag
			? '<p class="sa-contact__msg sa-contact__msg--ok">پیام شما ارسال شد؛ سپاس از همراهی‌تان. پاسخ از طریق ایمیل داده می‌شود.</p>'
			: '<p class="sa-contact__msg sa-contact__msg--err">ارسال انجام نشد. لطفاً همهٔ خانه‌ها را درست پُر کنید و دوباره تلاش کنید.</p>';
	}
	$subjects = array(
		'اصلاح'      => 'اصلاح اشتباه',
		'روایت'     => 'روایت محلی',
		'عکس'       => 'عکس واقعی',
		'معرفی'     => 'اقامتگاه و کسب‌وکار محلی',
		'همکاری'    => 'همکاری و تبلیغات',
		'مشکل فنی'  => 'گزارش مشکل فنی',
		'سایر'      => 'سایر موضوع‌ها',
	);
	$sel = isset( $_GET['subject'] ) ? sanitize_text_field( wp_unslash( $_GET['subject'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

	ob_start();
	?>
	<section class="sa-contact" id="sa-contact-form">
		<h2 class="sa-contact__title">ارسال پیام</h2>
		<p class="sa-contact__lead">پیام شما مستقیم به <b dir="ltr"><?php echo esc_html( antispambot( sa_contact_recipient() ) ); ?></b> می‌رود و خوانده می‌شود.</p>
		<?php echo $notice; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="sa-contact__form">
			<input type="hidden" name="action" value="sa_contact_send">
			<?php wp_nonce_field( 'sa_contact', 'sa_contact_nonce' ); ?>
			<input class="cc-hp" name="website" tabindex="-1" autocomplete="off" aria-hidden="true">
			<label>نام شما
				<input type="text" name="sa_name" required minlength="2" maxlength="100" value="<?php echo esc_attr( isset( $_GET['sa_name'] ) ? sanitize_text_field( wp_unslash( $_GET['sa_name'] ) ) : '' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>">
			</label>
			<label>ایمیل شما (برای پاسخ)
				<input type="email" name="sa_email" required maxlength="190" dir="ltr" value="<?php echo esc_attr( isset( $_GET['sa_email'] ) ? sanitize_email( wp_unslash( $_GET['sa_email'] ) ) : '' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>">
			</label>
			<label>موضوع
				<select name="sa_subject" required>
					<?php foreach ( $subjects as $key => $label ) : ?>
						<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $sel, $key ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
			<label>متن پیام
				<textarea name="sa_message" required minlength="10" maxlength="5000" rows="6" placeholder="نام شهرستان یا نشانی صفحهٔ مربوط را هم بنویسید…"></textarea>
			</label>
			<button type="submit" class="sa-contact__submit">ارسال پیام</button>
		</form>
	</section>
	<?php
	return ob_get_clean();
}

add_shortcode( 'sa_contact_form', 'sa_contact_form_html' );

/**
 * Auto-append the form to the contact page (no manual page editing needed).
 *
 * @param string $content Page content.
 * @return string
 */
function sa_contact_autoload( $content ) {
	if ( ! is_page() || ! is_main_query() || ! in_the_loop() ) {
		return $content;
	}
	$post = get_post();
	if ( ! $post || false !== strpos( $content, 'sa-contact-form' ) || has_shortcode( $content, 'sa_contact_form' ) ) {
		return $content;
	}
	$is_contact = in_array( $post->post_name, array( 'contact', 'contact-us', 'tamaskhodmai' ), true ) || false !== mb_strpos( get_the_title( $post ), 'تماس' );
	return $is_contact ? $content . sa_contact_form_html() : $content;
}
add_filter( 'the_content', 'sa_contact_autoload', 30 );

/**
 * Handle contact form submissions (logged-in and guests).
 */
function sa_contact_handle() {
	$back = wp_get_referer() ? wp_get_referer() : home_url( '/' );
	$back = remove_query_arg( array( 'sa_contact', 'sa_name', 'sa_email', 'subject' ), $back ) . '#sa-contact-form';

	if ( ! isset( $_POST['sa_contact_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['sa_contact_nonce'] ) ), 'sa_contact' ) ) {
		wp_safe_redirect( add_query_arg( 'sa_contact', 'failed', $back ) );
		exit;
	}
	if ( ! empty( $_POST['website'] ) ) { // Honeypot.
		wp_safe_redirect( add_query_arg( 'sa_contact', 'sent', $back ) );
		exit;
	}
	$ip_key = 'sa_contact_rate_' . md5( isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '' );
	if ( (int) get_transient( $ip_key ) >= 5 ) {
		wp_safe_redirect( add_query_arg( 'sa_contact', 'failed', $back ) );
		exit;
	}

	$name    = isset( $_POST['sa_name'] ) ? sanitize_text_field( wp_unslash( $_POST['sa_name'] ) ) : '';
	$email   = isset( $_POST['sa_email'] ) ? sanitize_email( wp_unslash( $_POST['sa_email'] ) ) : '';
	$subject = isset( $_POST['sa_subject'] ) ? sanitize_text_field( wp_unslash( $_POST['sa_subject'] ) ) : 'سایر';
	$message = isset( $_POST['sa_message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['sa_message'] ) ) : '';

	if ( mb_strlen( $name ) < 2 || ! is_email( $email ) || mb_strlen( $message ) < 10 ) {
		wp_safe_redirect( add_query_arg( array( 'sa_contact' => 'failed', 'sa_name' => $name, 'sa_email' => $email, 'subject' => $subject ), $back ) );
		exit;
	}

	$to      = sa_contact_recipient();
	$subject = sprintf( '[%s] %s — %s', $subject, $name, wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) );
	$body    = "پیام تازه از فرم تماس سایت:\n\n" . $message . "\n\n---\nنام: {$name}\nایمیل: {$email}\nموضوع: {$subject}\nتاریخ: " . wp_date( 'Y/m/d H:i' ) . "\nنشانی سایت: " . home_url( '/' );
	$headers = array( 'Reply-To: ' . $name . ' <' . $email . '>' );

	$sent = wp_mail( $to, $subject, $body, $headers );
	set_transient( $ip_key, (int) get_transient( $ip_key ) + 1, 6 * HOUR_IN_SECONDS );

	wp_safe_redirect( add_query_arg( 'sa_contact', $sent ? 'sent' : 'failed', $back ) );
	exit;
}
add_action( 'admin_post_sa_contact_send', 'sa_contact_handle' );
add_action( 'admin_post_nopriv_sa_contact_send', 'sa_contact_handle' );

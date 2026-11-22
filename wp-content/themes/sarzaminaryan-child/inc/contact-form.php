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

	/*
	 * Owner 2026-11-22 (two reports): this line printed «&#115;ar&#122;a…» as literal code.
	 * antispambot() returns character references; any later escaping in the render pipeline
	 * (or a cached copy of the escaped markup) turns them back into visible code. A plain,
	 * once-escaped address can never break that way, and the address is already public on
	 * this very page — so no obfuscation here.
	 */
	$sa_lead_email = (string) sa_contact_recipient();
	$sa_lead_html  = esc_html( $sa_lead_email );

	ob_start();
	?>
	<section class="sa-contact" id="sa-contact-form">
		<h2 class="sa-contact__title">ارسال پیام</h2>
		<p class="sa-contact__lead">پیام شما مستقیم به <b dir="ltr"><?php echo $sa_lead_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above. ?></b> می‌رود و خوانده می‌شود.</p>
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
	if ( ! $post ) {
		return $content;
	}
	$is_contact = in_array( $post->post_name, array( 'contact', 'contact-us', 'tamaskhodmai' ), true ) || false !== mb_strpos( get_the_title( $post ), 'تماس' );
	if ( ! $is_contact ) {
		return $content;
	}
	$content = (string) preg_replace(
		'#<p[^>]*>\s*ایمیل و راه‌های تماس را اینجا بنویسید\.?\s*</p>#isu',
		'',
		(string) $content
	);
	$content = str_replace( 'ایمیل و راه‌های تماس را اینجا بنویسید.', '', $content );
	if ( false !== strpos( $content, 'sa-contact-form' ) || has_shortcode( $content, 'sa_contact_form' ) ) {
		return $content;
	}
	return $content . sa_contact_form_html();
}
add_filter( 'the_content', 'sa_contact_autoload', 30 );

/**
 * Repair an address that was saved or cached as numeric character references.
 *
 * Owner report 2026-11-22 (twice): /contact/ printed «&#115;ar&#122;a…» instead of the
 * address. The theme no longer obfuscates it, but a page body (or a stale page cache)
 * can still hold the old escaped markup. Any character-reference run that decodes to a
 * valid email is normalised to plain, once-escaped text here.
 *
 * @param string $content Post content.
 * @return string
 */
function sa_contact_decode_stray_address( $content ) {
	$content = (string) $content;
	if ( false === strpos( $content, '&#' ) ) {
		return $content;
	}

	return (string) preg_replace_callback(
		'/(?=[A-Za-z0-9._%+\-@&;]*&(?:amp;)?#\d{2,3};)(?:&(?:amp;)?#\d{2,3};|[A-Za-z0-9._%+\-@]){7,}/',
		static function ( $match ) {
			$flags = ENT_QUOTES | ENT_HTML5;
			$plain = html_entity_decode( html_entity_decode( $match[0], $flags, 'UTF-8' ), $flags, 'UTF-8' );
			return is_email( $plain ) ? esc_html( $plain ) : $match[0];
		},
		$content
	);
}
add_filter( 'the_content', 'sa_contact_decode_stray_address', 32 );

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

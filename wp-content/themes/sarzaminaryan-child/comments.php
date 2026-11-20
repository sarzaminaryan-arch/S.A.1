<?php
/**
 * Comments — compact 2026 style.
 *
 * Owner request (۲۹ آبان ۱۴۰۵): comments must be on across the site, look like the
 * new design and be **more compact** than the old block. This file replaces the
 * parent's verbose English template with a Persian, dense layout; all styling lives
 * in `.sa-comments` (plain-CSS block of the 2026 theme build, never purged).
 *
 * @package Sarzaminaryan_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( post_password_required() ) {
	return;
}

$sa_comment_count = (int) get_comments_number();
$sa_comments_open = comments_open();
$sa_has_comments  = have_comments();

// Nothing to show and no form: print nothing at all (keeps pages tight).
if ( ! $sa_has_comments && ! $sa_comments_open ) {
	return;
}
?>
<section id="comments" class="sa-comments">
	<h2 class="sa-comments__title">
		<span>تجربه‌ها و دیدگاه‌ها</span>
		<span class="sa-comments__count"><?php echo esc_html( number_format_i18n( $sa_comment_count ) ); ?></span>
	</h2>

	<?php if ( $sa_has_comments ) : ?>
		<?php
		wp_list_comments(
			array(
				'style'       => 'ol',
				'short_ping'  => true,
				'avatar_size' => 32,
				'max_depth'   => 2,
			)
		);
		?>

		<?php
		the_comments_pagination(
			array(
				'prev_text' => 'قبلی',
				'next_text' => 'بعدی',
			)
		);
		?>
	<?php endif; ?>

	<?php if ( $sa_comments_open ) : ?>
		<?php
		comment_form(
			array(
				'title_reply'          => $sa_has_comments ? 'دیدگاه تازه' : 'اولین تجربه را شما بنویسید',
				'title_reply_to'       => 'پاسخ به %s',
				'cancel_reply_link'    => 'انصراف',
				'label_submit'         => 'ارسال دیدگاه',
				'comment_notes_before' => '<p class="sa-comments__hint">نشانی ایمیل منتشر نمی‌شود. دیدگاه‌ها پس از بررسی تحریریه نمایش داده می‌شوند.</p>',
				'comment_notes_after'  => '',
				'comment_field'        => '<p class="comment-form-comment"><label for="comment">تجربهٔ شما</label><textarea id="comment" name="comment" cols="45" rows="4" maxlength="65525" required="required" placeholder="چه چیزی دیدید؟ چه نکته‌ای به کار مسافر بعدی می‌آید؟"></textarea></p>',
				'fields'               => array(
					'author' => '<p class="comment-form-author"><label for="author">نام</label><input id="author" name="author" type="text" value="' . esc_attr( '' ) . '" size="30" maxlength="245" autocomplete="name" required="required" /></p>',
					'email'  => '<p class="comment-form-email"><label for="email">ایمیل</label><input id="email" name="email" type="email" value="" size="30" maxlength="100" autocomplete="email" required="required" /></p>',
					'url'    => '',
					'cookies' => '',
				),
				'class_submit'         => 'sa-comments__submit',
			)
		);
		?>
	<?php else : ?>
		<p class="sa-comments__closed">دیدگاه‌ها برای این صفحه بسته است.</p>
	<?php endif; ?>
</section>

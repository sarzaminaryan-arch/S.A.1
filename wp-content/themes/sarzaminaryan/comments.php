<?php
/**
 * The template for displaying comments.
 *
 * @package Sarzaminaryan
 * @since   1.0.0
 */

if ( post_password_required() ) {
	return;
}
?>

<div id="comments" class="comments-area">
	<?php if ( have_comments() ) : ?>
		<h2 class="comments-title">
			<?php
			$sarzaminaryan_comment_count = get_comments_number();
			if ( '1' === $sarzaminaryan_comment_count ) {
				esc_html_e( 'One comment', 'sarzaminaryan' );
			} else {
				printf(
					/* translators: %s: comment count number. */
					esc_html( _n( '%s comment', '%s comments', $sarzaminaryan_comment_count, 'sarzaminaryan' ) ),
					esc_html( number_format_i18n( $sarzaminaryan_comment_count ) )
				);
			}
			?>
		</h2>

		<ol class="comment-list">
			<?php
			wp_list_comments(
				array(
					'style'      => 'ol',
					'short_ping' => true,
					'avatar_size' => 50,
				)
			);
			?>
		</ol>

		<?php
		the_comments_navigation();

		if ( ! comments_open() ) :
			?>
			<p class="no-comments"><?php esc_html_e( 'Comments are closed.', 'sarzaminaryan' ); ?></p>
			<?php
		endif;
		?>
	<?php endif; ?>

	<?php comment_form(); ?>
</div>
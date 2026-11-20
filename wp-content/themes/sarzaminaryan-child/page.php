<?php
/**
 * Page template — 2026 design refresh.
 *
 * Every standalone page (about, contact, policy, a new page the owner creates)
 * renders inside the new layout. Two owner requirements are covered here:
 *   1. A page can be replaced or removed freely — the front page no longer
 *      depends on a page, and this template has no hidden page dependency.
 *   2. A page can embed other pages: its child pages are listed automatically
 *      as cards, so a section landing page is built by nesting pages only.
 *
 * @package Sarzaminaryan_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Safety switch: with the 2026 design off, render the previous template verbatim.
if ( function_exists( 'sa_2026_render_legacy' ) && sa_2026_render_legacy( 'page' ) ) {
	return;
}

get_header();
?>

<main id="primary" class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 sm:py-10 space-y-10">

	<?php
	while ( have_posts() ) :
		the_post();

		$sa_page_id    = get_the_ID();
		$sa_slug       = get_post_field( 'post_name', $sa_page_id );
		$sa_code       = 'PGE-' . ( $sa_slug ? strtoupper( $sa_slug ) : $sa_page_id );
		$sa_children   = get_pages(
			array(
				'child_of'    => $sa_page_id,
				'parent'      => $sa_page_id,
				'sort_column' => 'menu_order,post_title',
				'post_status' => 'publish',
			)
		);
		$sa_parent_id  = wp_get_post_parent_id( $sa_page_id );
		$sa_parent_url = $sa_parent_id ? get_permalink( $sa_parent_id ) : '';
		$sa_summary    = function_exists( 'sa_summary' ) ? sa_summary( get_post(), 42 ) : '';
		?>

		<nav aria-label="مسیر صفحه" class="flex items-center justify-between gap-3 text-[11px] sm:text-xs text-muted border-b border-line pb-3">
			<ol class="flex items-center gap-1.5 flex-wrap">
				<li><a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="hover:text-petrol transition-colors">خانه</a></li>
				<?php if ( $sa_parent_url ) : ?>
					<li aria-hidden="true">/</li>
					<li><a href="<?php echo esc_url( $sa_parent_url ); ?>" class="hover:text-petrol transition-colors"><?php echo esc_html( get_the_title( $sa_parent_id ) ); ?></a></li>
				<?php endif; ?>
				<li aria-hidden="true">/</li>
				<li aria-current="page" class="text-ink font-semibold"><?php echo esc_html( get_the_title() ); ?></li>
			</ol>
			<span class="font-mono text-[10px] text-slate-400 bg-canvas border border-line px-2 py-1 rounded-lg shrink-0 hidden sm:inline">SYS.ID // <?php echo esc_html( $sa_code ); ?></span>
		</nav>

		<header class="bg-surface rounded-3xl border border-line p-6 sm:p-8 lg:p-10 shadow-card space-y-3">
			<h1 class="text-2xl sm:text-3xl lg:text-4xl font-extrabold text-ink tracking-tight leading-[1.3]">
				<?php the_title(); ?>
			</h1>
			<?php if ( $sa_summary ) : ?>
				<p class="text-sm sm:text-base text-muted leading-relaxed max-w-3xl"><?php echo esc_html( $sa_summary ); ?></p>
			<?php endif; ?>
			<div class="flex flex-wrap items-center gap-2 pt-1 text-[11px] text-muted">
				<span class="px-2.5 py-1 rounded-lg bg-canvas border border-line font-semibold"><?php echo esc_html( get_the_date() ); ?></span>
				<?php if ( get_the_modified_date() && get_the_modified_date() !== get_the_date() ) : ?>
					<span class="px-2.5 py-1 rounded-lg bg-canvas border border-line">آخرین ویرایش: <?php echo esc_html( get_the_modified_date() ); ?></span>
				<?php endif; ?>
			</div>
		</header>

		<div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
			<article id="post-<?php the_ID(); ?>" <?php post_class( 'lg:col-span-8 bg-surface rounded-3xl border border-line p-6 sm:p-8 shadow-card' ); ?>>
				<div class="sa-2026-prose">
					<?php
					the_content();

					wp_link_pages(
						array(
							'before' => '<nav class="pt-6 flex flex-wrap gap-2 text-xs text-petrol">',
							'after'  => '</nav>',
						)
					);
					?>
				</div>

				<?php if ( $sa_children ) : ?>
					<section aria-labelledby="child-pages-heading" class="pt-8 mt-8 border-t border-line space-y-4">
						<h2 id="child-pages-heading" class="text-sm font-extrabold text-ink">بخش‌های این صفحه</h2>
						<div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
							<?php
							foreach ( $sa_children as $sa_child ) :
								$sa_excerpt = function_exists( 'sa_summary' ) ? sa_summary( $sa_child, 18 ) : '';
								?>
								<a href="<?php echo esc_url( get_permalink( $sa_child ) ); ?>"
									class="group bg-canvas rounded-2xl border border-line hover:border-petrol hover:bg-petrolSoft/40 p-4 space-y-1.5 transition-colors">
									<strong class="block text-sm font-extrabold text-ink group-hover:text-petrol transition-colors"><?php echo esc_html( get_the_title( $sa_child ) ); ?></strong>
									<?php if ( $sa_excerpt ) : ?>
										<span class="block text-[11px] text-muted leading-relaxed"><?php echo esc_html( $sa_excerpt ); ?></span>
									<?php endif; ?>
								</a>
							<?php endforeach; ?>
						</div>
					</section>
				<?php endif; ?>
			</article>

			<aside class="lg:col-span-4 space-y-5">
				<div class="bg-petrol text-white rounded-3xl p-6 shadow-floating relative overflow-hidden grid-pattern space-y-4">
					<span class="font-mono text-[10px] text-amber-200 bg-white/10 px-2.5 py-1 rounded-lg inline-block">SYS.ID // <?php echo esc_html( $sa_code ); ?></span>
					<h2 class="text-lg font-extrabold leading-snug">جای این بخش، تجربهٔ شماست</h2>
					<p class="text-xs text-white/80 leading-relaxed">
						اگر اطلاعات این صفحه کهنه است یا تجربه‌ای برای افزودن دارید، آن را با تحریریه در میان بگذارید.
					</p>
					<button type="button" data-sa-open-experience
						class="w-full inline-flex items-center justify-center gap-2 px-4 py-3 rounded-2xl bg-ochre hover:bg-ochreDark text-white text-xs font-bold transition-colors">
						<span>ثبت تجربه یا پیشنهاد</span>
						<span aria-hidden="true">←</span>
					</button>
				</div>

				<?php
				$sa_quick = function_exists( 'sa_2026_footer_explore' ) ? sa_2026_footer_explore() : array();
				if ( $sa_quick ) :
					?>
					<div class="bg-surface rounded-2xl border border-line p-5 space-y-3">
						<h2 class="text-xs font-extrabold text-ink uppercase tracking-wider">کاوش در دانشنامه</h2>
						<ul class="space-y-2 text-xs text-slate-600">
							<?php foreach ( $sa_quick as $sa_link ) : ?>
								<li>
									<a href="<?php echo esc_url( $sa_link['url'] ); ?>" class="inline-flex items-center gap-1.5 hover:text-petrol font-semibold transition-colors">
										<span class="w-1.5 h-1.5 rounded-full bg-ochre" aria-hidden="true"></span>
										<?php echo esc_html( $sa_link['label'] ); ?>
									</a>
								</li>
							<?php endforeach; ?>
						</ul>
					</div>
				<?php endif; ?>
			</aside>
		</div>

		<?php
	endwhile;
	?>

</main>

<?php
get_footer();

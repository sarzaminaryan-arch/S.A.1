<?php
/**
 * Template Name: صفحه نخست سرزمین آریان
 *
 * A landing page for discovering the site's published travel content.
 *
 * @package Sarzaminaryan_Child
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

$sa_home_types = sa_nav_entity_types();
$sa_home_education = function_exists( 'sa_education_nav_item' ) ? sa_education_nav_item() : null;
$sa_home_dashboard = function_exists( 'sa_dashboard_page_url' ) ? sa_dashboard_page_url() : '';
$sa_home_sections = array(
	'province'   => array( 'number' => '۰۱', 'title' => 'استان‌های ایران', 'text' => 'از شمال تا جنوب، سرزمین‌های گوناگون ایران را بشناسید.', 'icon' => '✳' ),
	'city'       => array( 'number' => '۰۲', 'title' => 'شهرستان‌ها', 'text' => 'به شهرها و شهرستان‌ها نزدیک‌تر شوید و قصه هر منطقه را بخوانید.', 'icon' => '⌁' ),
	'attraction' => array( 'number' => '۰۳', 'title' => 'دیدنی‌ها', 'text' => 'طبیعت، میراث و جاهای دیدنی را برای سفر بعدی کشف کنید.', 'icon' => '◇' ),
);
$sa_home_attractions = in_array( 'attraction', $sa_home_types, true ) ? get_posts( array(
	'post_type' => 'attraction',
	'post_status' => 'publish',
	'posts_per_page' => 3,
	'orderby' => 'date',
	'order' => 'DESC',
	'no_found_rows' => true,
) ) : array();
?>
<main id="primary" class="sa-landing" dir="rtl">
	<section class="sa-landing-hero" aria-labelledby="sa-landing-title">
		<div class="sa-landing-hero__image" aria-hidden="true"></div>
		<div class="sa-landing-wrap sa-landing-hero__inner">
			<div class="sa-landing-hero__copy">
				<p class="sa-landing-eyebrow"><span></span> دانشنامه سفر و شناخت ایران</p>
				<h1 id="sa-landing-title">ایران را<br><em>دوباره کشف کن.</em></h1>
				<p class="sa-landing-hero__intro">از کوه و کویر تا شهرها و قصه‌هایشان؛ سفر به شناخت سرزمین‌مان از همین‌جا آغاز می‌شود.</p>
				<form class="sa-landing-search" role="search" method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>">
					<label class="screen-reader-text" for="sa-landing-search-input">جست‌وجوی مقصد، شهر یا دیدنی</label>
					<svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="10.5" cy="10.5" r="6.5"/><path d="m16 16 4.5 4.5"/></svg>
					<input id="sa-landing-search-input" type="search" name="s" placeholder="کجا را می‌خواهید کشف کنید؟" />
					<button type="submit">جست‌وجو <span aria-hidden="true">←</span></button>
				</form>
				<div class="sa-landing-quick"><span>شروع کاوش:</span>
					<?php foreach ( $sa_home_sections as $sa_type => $sa_section ) : ?>
						<?php if ( in_array( $sa_type, $sa_home_types, true ) ) : ?><a href="<?php echo esc_url( sa_archive_url( $sa_type ) ); ?>"><?php echo esc_html( $sa_section['title'] ); ?></a><?php endif; ?>
					<?php endforeach; ?>
				</div>
			</div>
			<div class="sa-landing-hero__note"><span>سرزمین آریان</span><small>برای آن‌ها که می‌خواهند بیشتر ببینند.</small></div>
		</div>
		<div class="sa-landing-hero__bottom sa-landing-wrap"><span>هر گوشه، داستانی تازه</span><a href="#sa-explore">برای کشف بیشتر <span aria-hidden="true">↓</span></a></div>
	</section>

	<section class="sa-landing-explore sa-landing-wrap" id="sa-explore" aria-labelledby="sa-explore-title">
		<div class="sa-landing-section-head">
			<div><p class="sa-landing-kicker">راه خودت را پیدا کن <span> / ۰۱</span></p><h2 id="sa-explore-title">از کجا شروع کنیم؟</h2></div>
			<p>برای شناخت ایران، مسیرهای زیادی هست.<br>یکی را انتخاب کنید و به دل سرزمین بزنید.</p>
		</div>
		<div class="sa-landing-paths">
			<?php foreach ( $sa_home_sections as $sa_type => $sa_section ) : ?>
				<?php if ( ! in_array( $sa_type, $sa_home_types, true ) ) { continue; } ?>
				<a class="sa-landing-path" href="<?php echo esc_url( sa_archive_url( $sa_type ) ); ?>">
					<div class="sa-landing-path__top"><span class="sa-landing-path__icon" aria-hidden="true"><?php echo esc_html( $sa_section['icon'] ); ?></span><span><?php echo esc_html( $sa_section['number'] ); ?> /</span></div>
					<h3><?php echo esc_html( $sa_section['title'] ); ?></h3><p><?php echo esc_html( $sa_section['text'] ); ?></p>
					<span class="sa-landing-path__link">مشاهده و کاوش <b aria-hidden="true">←</b></span>
				</a>
			<?php endforeach; ?>
			<?php if ( $sa_home_education ) : ?>
				<a class="sa-landing-path" href="<?php echo esc_url( $sa_home_education['url'] ); ?>"><div class="sa-landing-path__top"><span class="sa-landing-path__icon" aria-hidden="true">✧</span><span>۰۴ /</span></div><h3>آموزش سفر</h3><p>برای طبیعت‌گردی آگاهانه‌تر، راهنماها و آموزش‌ها را بخوانید.</p><span class="sa-landing-path__link">مشاهده و کاوش <b aria-hidden="true">←</b></span></a>
			<?php endif; ?>
		</div>
	</section>

	<?php if ( $sa_home_attractions ) : ?>
	<section class="sa-landing-discover" aria-labelledby="sa-discover-title"><div class="sa-landing-wrap">
		<div class="sa-landing-section-head"><div><p class="sa-landing-kicker">ایران، از نزدیک <span> / ۰۲</span></p><h2 id="sa-discover-title">دیدنی‌هایی برای کشف کردن</h2></div><a class="sa-landing-text-link" href="<?php echo esc_url( sa_archive_url( 'attraction' ) ); ?>">همه دیدنی‌ها <span aria-hidden="true">←</span></a></div>
		<div class="sa-landing-cards">
			<?php foreach ( $sa_home_attractions as $sa_attraction ) : ?>
				<a class="sa-landing-card" href="<?php echo esc_url( get_permalink( $sa_attraction->ID ) ); ?>">
					<div class="sa-landing-card__image"><?php if ( has_post_thumbnail( $sa_attraction->ID ) ) { echo get_the_post_thumbnail( $sa_attraction->ID, 'large', array( 'loading' => 'lazy', 'alt' => '' ) ); } else { ?><span aria-hidden="true">✳</span><?php } ?></div>
					<div class="sa-landing-card__body"><span>دیدنی‌های ایران</span><h3><?php echo esc_html( get_the_title( $sa_attraction->ID ) ); ?></h3><b aria-hidden="true">←</b></div>
				</a>
			<?php endforeach; ?>
		</div>
	</div></section>
	<?php endif; ?>

	<section class="sa-landing-story"><div class="sa-landing-wrap sa-landing-story__inner">
		<div><p class="sa-landing-kicker">همراه سفرهای شما <span> / ۰۳</span></p><h2>هر سفر، فرصتی برای<br><em>بیشتر شناختن است.</em></h2><p>سرزمین آریان جایی برای آشنایی با جغرافیا، دیدنی‌ها و تنوع ایران است؛ همراهی برای آن‌که سفر بعدی را با شناخت بیشتری آغاز کنید.</p>
		<?php if ( $sa_home_dashboard ) : ?><a class="sa-landing-button" href="<?php echo esc_url( $sa_home_dashboard ); ?>">کاوش در نقشه ایران <span aria-hidden="true">←</span></a><?php elseif ( in_array( 'province', $sa_home_types, true ) ) : ?><a class="sa-landing-button" href="<?php echo esc_url( sa_archive_url( 'province' ) ); ?>">کاوش استان‌های ایران <span aria-hidden="true">←</span></a><?php endif; ?></div>
		<div class="sa-landing-story__aside"><span class="sa-landing-story__mark" aria-hidden="true">✳</span><p>ایران را باید<br>زندگی کرد.</p><small>SARZAMIN ARYAN &nbsp; / &nbsp; IRAN</small></div>
	</div></section>
</main>
<?php get_footer(); ?>

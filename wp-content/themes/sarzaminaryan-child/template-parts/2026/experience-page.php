<?php
/**
 * «ثبت تجربه» page (2026 design).
 *
 * Rendered by `page.php` when the owner creates a page with the slug `city-contrib`,
 * and by the virtual route in `inc/experience-form.php` when no such page exists —
 * so the call to action can never land on a 404 again.
 *
 * The form posts to `admin_post_sa_experience_submit` (nonce-checked), stores the
 * story as a **pending** `sa_experience` post and attaches one photo through
 * `media_handle_upload()`. Everything lands in wp-admin for review.
 *
 * @package Sarzaminaryan_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$sa_flash = function_exists( 'sa_experience_flash' ) ? sa_experience_flash() : '';

$sa_messages = array(
	'sent'    => array( 'ok', 'تجربهٔ شما ثبت شد. پس از بررسی تحریریه، با نام خودتان منتشر می‌شود.' ),
	'photo'   => array( 'warn', 'متن شما ثبت شد، اما عکس پذیرفته نشد. فقط JPG، PNG یا WebP تا ۶ مگابایت.' ),
	'photo_no' => array( 'warn', 'متن ثبت شد؛ عکس بارگذاری نشد.' ),
	'expired' => array( 'bad', 'فرم منقضی شده بود. لطفاً دوباره ارسال کنید.' ),
	'short'   => array( 'bad', 'متن تجربه خیلی کوتاه است؛ دست‌کم دو جمله بنویسید.' ),
	'consent' => array( 'bad', 'برای انتشار، تأیید مالکیت تجربه لازم است.' ),
	'limit'   => array( 'bad', 'تعداد ارسال‌ها از این دستگاه زیاد بود. کمی بعد دوباره تلاش کنید.' ),
	'failed'  => array( 'bad', 'ثبت نشد. اگر تکرار شد با فرم تماس خبر دهید.' ),
);

$sa_form_url = function_exists( 'sa_experience_form_url' ) ? sa_experience_form_url() : home_url( '/city-contrib/' );

// Optional context: ?city=tbriz or ?from=<permalink> pre-fills the place field.
$sa_place_prefill = '';
if ( isset( $_GET['city'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$sa_place_prefill = sanitize_text_field( wp_unslash( $_GET['city'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
}
?>

<main id="primary" class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 sm:py-10 space-y-8">

	<nav aria-label="مسیر صفحه" class="flex items-center justify-between gap-3 text-[11px] sm:text-xs text-muted border-b border-line pb-3">
		<ol class="flex items-center gap-1.5 flex-wrap">
			<li><a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="hover:text-brand transition-colors">خانه</a></li>
			<li aria-hidden="true">/</li>
			<li aria-current="page" class="text-ink font-semibold">ثبت تجربه</li>
		</ol>
		<span class="font-mono text-[10px] text-slate-400 bg-canvas border border-line px-2 py-1 rounded-lg shrink-0 hidden sm:inline">FORM // EXPERIENCE</span>
	</nav>

	<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

		<div class="lg:col-span-2 space-y-5">
			<header class="space-y-2">
				<span class="text-[11px] font-bold text-brand">شهر من</span>
				<h1 class="text-2xl sm:text-3xl font-extrabold text-ink leading-snug">تجربه‌تان را ثبت کنید</h1>
				<p class="text-xs sm:text-sm text-slate-600 leading-relaxed">
					هر چیزی که در متن نیست و به مسافر بعدی کمک می‌کند: ساعت کاری تازه، مسیر دسترسی، پارکینگ،
					کیفیت اقامت یا یک جاذبهٔ کمترشناخته‌شده. متن و یک عکس را همین‌جا بفرستید؛ تحریریه بررسی
					می‌کند و پس از تأیید، با نام خودتان منتشر می‌شود.
				</p>
			</header>

			<?php if ( $sa_flash && isset( $sa_messages[ $sa_flash ] ) ) : ?>
				<?php list( $sa_kind, $sa_text ) = $sa_messages[ $sa_flash ]; ?>
				<div class="sa-notice sa-notice--<?php echo esc_attr( $sa_kind ); ?>" role="status" tabindex="-1" id="sa-notice">
					<span class="sa-notice__icon" aria-hidden="true"><?php echo 'ok' === $sa_kind ? '✓' : ( 'warn' === $sa_kind ? '!' : '×' ); ?></span>
					<span><?php echo esc_html( $sa_text ); ?></span>
				</div>
			<?php endif; ?>

			<form class="sa-exp-form" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post" enctype="multipart/form-data">
				<input type="hidden" name="action" value="sa_experience_submit" />
				<?php wp_nonce_field( 'sa_experience_submit', 'sa_experience_nonce' ); ?>

				<div class="sa-exp-form__hp" aria-hidden="true">
					<label for="sa_exp_website">این فیلد را خالی بگذارید</label>
					<input type="text" id="sa_exp_website" name="sa_exp_website" tabindex="-1" autocomplete="off" />
				</div>

				<div class="sa-exp-form__grid">
					<p>
						<label for="sa_exp_place">شهر، شهرستان یا جاذبه</label>
						<input type="text" id="sa_exp_place" name="sa_exp_place" value="<?php echo esc_attr( $sa_place_prefill ); ?>" list="sa_places" placeholder="مثلاً تبریز یا بازار تبریز" maxlength="120" />
						<datalist id="sa_places">
							<option value="تبریز"></option>
							<option value="اصفهان"></option>
							<option value="شیراز"></option>
							<option value="مشهد"></option>
							<option value="یزد"></option>
						</datalist>
					</p>
					<p>
						<label for="sa_exp_name">نام شما <span class="sa-exp-form__opt">(اختیاری)</span></label>
						<input type="text" id="sa_exp_name" name="sa_exp_name" maxlength="80" autocomplete="name" placeholder="برای درج زیر تجربه" />
					</p>
					<p>
						<label for="sa_exp_email">ایمیل <span class="sa-exp-form__opt">(اختیاری)</span></label>
						<input type="email" id="sa_exp_email" name="sa_exp_email" maxlength="120" autocomplete="email" placeholder="فقط برای هماهنگی؛ منتشر نمی‌شود" />
					</p>
					<p>
						<label for="sa_exp_photo">عکس <span class="sa-exp-form__opt">(اختیاری)</span></label>
						<input type="file" id="sa_exp_photo" name="sa_exp_photo" accept="image/jpeg,image/png,image/webp" />
						<span class="sa-exp-form__hint">JPG، PNG یا WebP تا ۶ مگابایت.</span>
					</p>
				</div>

				<p>
					<label for="sa_exp_story">متن تجربه</label>
					<textarea id="sa_exp_story" name="sa_exp_story" rows="7" maxlength="6000" required placeholder="از زبان خودتان بنویسید: چه دیدید، چه چیزی غافلگیرتان کرد و چه نکته‌ای به کار مسافر بعدی می‌آید؟"></textarea>
				</p>

				<label class="sa-exp-form__consent">
					<input type="checkbox" name="sa_exp_consent" value="1" required />
					<span>این تجربه از خودم است و با انتشار آن با نام یا بی‌نام، پس از بررسی تحریریه موافقم.</span>
				</label>

				<div class="sa-exp-form__actions">
					<button type="submit" class="sa-exp-form__submit">ارسال برای بررسی</button>
					<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="sa-exp-form__cancel">بازگشت به صفحهٔ اول</a>
				</div>
			</form>
		</div>

		<aside class="space-y-4">
			<section class="sa-side-card">
				<h2 class="sa-side-card__title">چه چیزی می‌آید؟</h2>
				<ol class="sa-side-card__list">
					<li>متن شما در فهرست «تجربه‌های ارسالی» پیشخوان ذخیره می‌شود.</li>
					<li>عکس، اگر بفرستید، در کتابخانهٔ رسانه به همان تجربه می‌چسبد.</li>
					<li>تحریریه پیش از انتشار بررسی و در صورت نیاز کوتاه‌سازی می‌کند.</li>
					<li>لینک مکان را می‌توانید در متن بنویسید تا به صفحهٔ شهرستان وصل شود.</li>
				</ol>
			</section>

			<section class="sa-side-card">
				<h2 class="sa-side-card__title">حریم خصوصی</h2>
				<p class="sa-side-card__text">
					ایمیل شما هرگز منتشر نمی‌شود و فقط برای هماهنگی تحریریه استفاده می‌شود.
					متن و عکس ارسالی پس از تأیید، تحت مجوز سایت منتشر می‌شوند.
				</p>
			</section>

			<section class="sa-side-card">
				<h2 class="sa-side-card__title">نشد ثبت کنید؟</h2>
				<p class="sa-side-card__text">
					اگر فرم خطا داد، تجربه را با ایمیل برای تحریریه بفرستید یا از
					<a href="<?php echo esc_url( home_url( '/contact/' ) ); ?>" class="text-brand font-semibold">فرم تماس</a> استفاده کنید.
				</p>
			</section>
		</aside>
	</div>
</main>

<script>
	// No alert(): scroll the result notice into view so the visitor sees it.
	(function () {
		var notice = document.getElementById('sa-notice');
		if (notice) {
			notice.focus({ preventScroll: true });
			notice.scrollIntoView({ behavior: 'smooth', block: 'center' });
		}
	})();
</script>

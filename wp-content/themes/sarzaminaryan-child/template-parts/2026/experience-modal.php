<?php
/**
 * Shared "ثبت تجربه" dialog (2026 refresh).
 *
 * The mockups show a four-field form. The real site already has a moderated
 * contribution flow ("شهر من") with OTP login, media upload and editorial
 * review, so this dialog routes to that verified flow instead of posting a
 * second, unmoderated form. It keeps the mockup look and never loses input.
 *
 * @package Sarzaminaryan_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$sa_contrib_args = array();
if ( is_singular( 'city' ) || is_singular( 'attraction' ) ) {
	$sa_contrib_args['city'] = get_the_title();
}

$sa_contrib = function_exists( 'sa_experience_form_url' ) ? sa_experience_form_url() : ( function_exists( 'sa_2026_contrib_url' ) ? sa_2026_contrib_url() : home_url( '/city-contrib/' ) );
if ( $sa_contrib_args ) {
	$sa_contrib = add_query_arg( $sa_contrib_args, $sa_contrib );
}
?>
<div id="experience-modal"
	class="fixed inset-0 z-50 hidden items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs"
	role="dialog" aria-modal="true" aria-labelledby="experience-modal-title" data-sa-experience-modal>
	<div class="bg-surface rounded-3xl border border-line max-w-lg w-full p-6 sm:p-8 shadow-xl space-y-5 relative animate-fadeIn">
		<div class="flex items-start justify-between gap-3 border-b border-line pb-3">
			<div class="space-y-0.5">
				<span class="text-[11px] font-bold text-ochre">بخش شهر من</span>
				<h2 id="experience-modal-title" class="text-lg font-extrabold text-ink">ثبت تجربه یا پیشنهاد جدید</h2>
			</div>
			<button type="button" class="w-9 h-9 rounded-xl border border-line text-muted hover:text-ink hover:bg-sand transition-colors" data-sa-close-experience>
				<span class="screen-reader-text">بستن</span>
				<span aria-hidden="true">✕</span>
			</button>
		</div>

		<div class="space-y-3 text-xs sm:text-sm text-slate-600 leading-relaxed">
			<p>
				تجربهٔ شما دربارهٔ مسیر، ساعت کاری، پارکینگ یا جاذبه‌ای که در متن نیست، پس از بررسی تحریریه و با نام خودتان منتشر می‌شود.
			</p>
			<ul class="space-y-1.5 list-disc pr-4">
				<li>بدون ثبت‌نام؛ فقط متن تجربه و نام دلخواه.</li>
				<li>یک عکس از مکان (اختیاری، تا ۶ مگابایت).</li>
				<li>بررسی تحریریه پیش از انتشار، با نام خودتان.</li>
			</ul>
		</div>

		<div class="flex flex-col sm:flex-row items-center gap-2 pt-1">
			<a href="<?php echo esc_url( $sa_contrib ); ?>"
				class="w-full sm:flex-1 inline-flex items-center justify-center gap-2 px-4 py-3 rounded-2xl bg-ochre hover:bg-ochreDark text-white text-sm font-bold transition-colors">
				<span>رفتن به فرم ثبت تجربه</span>
				<span aria-hidden="true">←</span>
			</a>
			<button type="button" class="w-full sm:w-auto px-4 py-3 rounded-2xl bg-canvas hover:bg-sand border border-line text-sm font-semibold text-ink transition-colors" data-sa-close-experience>
				انصراف
			</button>
		</div>
	</div>
</div>

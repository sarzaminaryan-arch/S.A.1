<?php
/**
 * Plugin Name:       بازگردانی یک‌بارهٔ FAQ مقالات (فاز ۴ ـ بستهٔ A)
 * Plugin URI:        https://github.com/sarzaminaryan-arch/S.A.1/releases/tag/faq-restore-v1.0.0
 * Description:       افزونهٔ یک‌بارمصرف برای بازگرداندن متای sa_faq هشت راهنمایی که بدنه‌شان با مخزن هم‌جهت است ولی FAQ زنده‌شان خالی است. فقط وقتی sa_faq خالی باشد می‌نویسد؛ به متن و هیچ فیلد دیگری دست نمی‌زند.
 * Version:           1.0.0
 * Author:            Sarzaminaryan
 * Author URI:        https://sarzaminaryan.ir/
 * Text Domain:       sa-faq-restore
 * Requires at least: 6.0
 * Requires PHP:      7.4
 *
 * @package SA_FAQ_Restore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SA_FAQ_Restore {

	const VERSION         = '1.0.0';
	const BACKUP_OPTION   = 'sa_faq_restore_20261111_backup';
	const REPORT_OPTION   = 'sa_faq_restore_20261111_report';
	const ROLLBACK_OPTION = 'sa_faq_restore_20261111_rollback_report';
	const PAGE_SLUG       = 'sa-faq-restore';
	const META_KEY        = 'sa_faq';

	/**
	 * FAQ rows are the repository (ready-guides) rows for each article and
	 * are derived from the article text itself; see
	 * docs/live-site/phase-4-plan.md (package A).
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private static function records() {
		return array(
			array(
				'slug' => 'diy-hammock-trees',
				'faq' => array(
					array(
						'q' => 'اگر دو درخت با فاصلهٔ مناسب پیدا نکردم چه کنم؟',
						'a' => 'فاصله را با طول بندها تنظیم کن؛ بندهای بلندتر فاصلهٔ بیشتر را جبران می‌کنند. اگر فقط یک درخت هست، از تخت معلق صرف‌نظر کن و سرپناه زمینی بساز؛ <a href="/build-shelter-branches/">ساخت سرپناه با شاخه و برگ</a> راهش را نشان می‌دهد.',
					),
					array(
						'q' => 'در سرما زیر هاموک می‌شود خوابید؟',
						'a' => 'با عایق مناسب و کیسه‌خواب، بله؛ اما سرمای هوای عبوری از زیر بدن در هاموک بیشتر از چادر است. زیرانداز فومی داخل هاموک، ضروری‌ترین قطعهٔ زمستانی است؛ جزئیات در <a href="/ground-insulation-sleeping/">عایق‌کاری زمین برای خواب</a> آمده است.',
					),
					array(
						'q' => 'آیا می‌توانم هاموک را به یک درخت و یک تیر ببندم؟',
						'a' => 'تیرهای فلزی و چوبی ساختمانی ممکن است تاب بخورند یا لبهٔ تیز داشته باشند؛ فقط به تنه‌های سالم و عمودی ببند و اگر مطمئن نیستی، همان روش دو درخت را نگه دار.',
					),
					array(
						'q' => 'هاموک دو‌نفره برای یک نفر بهتر نیست؟',
						'a' => 'نه؛ عرض زیاد بدون فاصلهٔ درست، خواب را به‌هم می‌ریزد و تاب می‌خورد. هاموک یک‌نفره با بندهای استاندارد، برای خواب انفرادی پایدارتر است.',
					),
					array(
						'q' => 'چطور جلوی تاب‌خوردن زیاد را بگیرم؟',
						'a' => 'شیب بندها را ملایم و ارتفاع را درست کن؛ تخت سفت‌بسته‌شده کمتر تاب می‌خورد. کوله را با طناب کوتاه به تنه نزدیک نگه دار تا وزن معلق اضافه نشود.',
					),
				),
			),
			array(
				'slug' => 'drinking-water-in-nature',
				'faq' => array(
					array(
						'q' => 'آیا واقعاً هیچ آب طبیعی‌ای وجود نداره که بشه مستقیم خورد؟',
						'a' => 'با حضور گستردهٔ دام، حیوانات و انسان‌ها در طبیعت، فرض بر این باشه که هیچ آبی بدون تصفیه امن نیست. حتی زلال‌ترین چشمه‌ها هم می‌تونن ناقل انگل باشن. ریسک کردن روی دستگاه گوارشت تو طبیعت، ارزشش رو نداره.',
					),
					array(
						'q' => 'اگه آب خیلی گل‌آلود باشه و از پارچه هم رد نشه چیکار کنیم؟',
						'a' => 'اول بذار ظرف آب کاملاً ساکن بمونه تا ذرات سنگین ته‌نشین بشن. بعد آب زلال رویی رو خیلی آروم به ظرف دیگه‌ای منتقل کن. اگه هنوز کدره، می‌تونی از روش "انعقاد طبیعی" استفاده کنی: یه مقدار خاک رس تمیز یا تفاله خرد شده گیاهانی مثل پنیرک (اگه می‌شناسی) به آب اضافه کن و هم بزن. این مواد ذرات ریز معلق رو به هم می‌چسبونن و ته‌نشین می‌کنن. بعد آب زلال رو جدا کن.',
					),
					array(
						'q' => 'چطور بفهمیم یه چشمه فعاله و خشک نمی‌شه؟',
						'a' => 'به دنبال نشانه‌های حیات باش. جایی که گیاهان سبز و شاداب (مثل سرخس یا بید مجنون) رشد کردن، یا صدای جریان آب از زیر سنگ‌ها میاد، نشونه خوبی از وجود آب پایدار زیرزمینیه. چشمه‌هایی که از دل شکاف سنگ‌های بزرگ می‌جوشن معمولاً پایدارتر از اونایی هستن که از خاک سست بیرون میان.',
					),
					array(
						'q' => 'آیا آب بارون قابل شربنه؟',
						'a' => 'آب بارونی که مستقیم از آسمون میاد و تو یه ظرف تمیز جمع میشه، یکی از تمیزترین منابع آب طبیعته. اما اگه از روی سقف چادر، برگ درختان یا سطوح آلوده جمع‌آوری بشه، آلوده می‌شه. با این حال، حتی آب بارون مستقیم هم بهتره قبل از مصرف جوشانده بشه، چون ممکنه تو مسیر سقوط، آلودگی‌های جوی رو جذب کرده باشه.',
					),
					array(
						'q' => 'روزانه چقدر آب باید همراه داشته باشیم؟',
						'a' => 'قانون کلی اینه: حداقل ۲ تا ۳ لیتر برای هر نفر در روز، فقط برای نوشیدن و پخت‌وپز سبک. اگه هوا گرمه یا فعالیت سنگین داری، این مقدار به ۴ لیتر هم می‌رسه. همیشه برنامه‌ریزی کن که بتونی این مقدار رو تو مسیر از منابع مطمئن تأمین یا جایگزین کنی.',
					),
				),
			),
			array(
				'slug' => 'how-to-choose-camping-tent',
				'faq' => array(
					array(
						'q' => 'چادر ۳ فصل بهتره یا ۴ فصل؟',
						'a' => 'اگه تو برف و کولاک زمستونی نمیری، قطعاً ۳ فصل. چادر ۳ فصل سبک‌تره، تهویه بهتری داره و تو تابستون اذیت نمیشی.',
					),
					array(
						'q' => 'آیا چادر ضدآب واقعاً آب رو رد نمی‌کنه؟',
						'a' => 'هیچ چادری ۱۰۰٪ ضدآب نیست. چادرها "مقاوم در برابر آب" (Water-resistant) هستن. اگه بارون شدید باشه و آب زیر چادر جمع بشه، از درزها نشت می‌کنه. همیشه زیر چادر زیرانداز بنداز.',
					),
					array(
						'q' => 'چادر چند نفره برای یک خانواده ۴ نفره مناسبه؟',
						'a' => 'ابعاد کف را با زیراندازهای چهار نفر و جای وسایل مقایسه کن. ظرفیت اسمی بزرگ‌تر می‌تواند فضای بیشتری بدهد، ولی ابعاد واقعی، وزن و نوع سفر هم در انتخاب مهم‌اند.',
					),
					array(
						'q' => 'تفاوت چادر کوهنوردی و کمپینگ معمولی چیه؟',
						'a' => 'چادر کوهنوردی سبک‌تره، ست‌های ظریف‌تری داره و در برابر بادهای شدید مقاومه. چادر کمپینگ معمولی سنگین‌تر، جادارتر و راحت‌تر برپا میشه.',
					),
					array(
						'q' => 'چطور عمر چادر رو زیاد کنیم؟',
						'a' => 'هرگز چادر خیس رو جمع نکن (کپک می‌زنه). حتماً زیرش زیرانداز بنداز تا کفش پاره نشه. موقع برپا کردن، جای سنگ‌ریزه رو تمیز کن.',
					),
				),
			),
			array(
				'slug' => 'leg-fracture-first-aid',
				'faq' => array(
					array(
						'q' => 'تا رسیدن امداد، مصدوم را حرکت بدهم یا نه؟',
						'a' => 'اگر جای شما امن است، نه؛ همان‌جا بمان، گرم و آرام نگه دار و منتظر امداد باش. حرکت فقط وقتی ضروری است که محل خطرناک باشد (سیل، ریزش، سرما شدید) و در آن صورت با آتل و چند نفر و استراحت‌های مکرر.',
					),
					array(
						'q' => 'کفش پای آسیب‌دیده را دربیاورم؟',
						'a' => 'اگر بدون حرکت‌دادن پا ممکن است، بله؛ تورم زیر کفش فشار می‌آورد. اگر کفش تنگ است و درنمی‌آید، همان‌جا بماند و آتل را روی کفش ببند؛ کشیدن کفش می‌تواند شکستگی را جابه‌جا کند.',
					),
					array(
						'q' => 'چطور درد را کم کنم؟',
						'a' => 'ثابت‌کردن پا خودش درد را کم می‌کند. کمپرس سرد با پارچه (نه یخ مستقیم روی پوست) در ۲۴ ساعت اول کمک‌کننده است؛ دارو فقط با نظر امداد یا پزشک و بدون دوز خودسرانه.',
					),
					array(
						'q' => 'اگر آنتن نداشتم چه کنم؟',
						'a' => 'یک نفر با سوت و آینه سیگنال بدهد و یک نفر به‌سمت جاده یا آبادی حرکت کند تا به امداد برسد؛ مصدوم را تنها نگذار. مسیر سیگنال‌دهی در <a href="/rescue-signaling-guide/">سیگنال‌دهی برای نجات</a> آمده است.',
					),
					array(
						'q' => 'تفاوت شکستگی باز و بسته چیست؟',
						'a' => 'در شکستگی باز، استخوان از پوست بیرون زده یا زخم باز روی آسیب است؛ در هر دو نوع، ثابت‌سازی لازم است، ولی شکستگی باز خطر عفونت و خونریزی بیشتری دارد و اولویت با پانسمان تمیز و تماس فوری امداد است.',
					),
				),
			),
			array(
				'slug' => 'maintain-fire-in-rain',
				'faq' => array(
					array(
						'q' => 'داکوتا فایر هول (Dakota Fire Hole) دقیقاً چیه و چرا انقدر خوبه؟',
						'a' => 'یه سیستم حفره زیرزمینیه که از دو چاله و یه تونل اتصال تشکیل شده. چاله اول محل آتیشه و چاله دوم مثل یه دمپر (Blower) عمل می‌کنه. باد از چاله دوم وارد تونل میشه و مستقیم از زیر به آتیش اکسیژن می‌رسونه. چون آتیش تو حفره‌ست، بادِ سطح زمین هیچ تاثیری روش نداره، دودش کمتره و حرارتش مستقیم به سمت بالا (زیر قابلمه) متمرکز میشه.',
					),
					array(
						'q' => 'هیزم خیس رو چطور سریع خشک کنیم؟',
						'a' => 'اگه وقت نداری، هیزم‌های نازک رو با چاقو یا تبر بشکن (نه ببر، چون شکستن باعث میشه لایه خیس جدا بشه و مغز خشک بیرون بزنه). این تیکه‌های خشک رو بذار نزدیک آتیش (نه داخلش) تا بخار بشن. وقتی خشک شدن، اون‌ها رو تبدیل به هیزم اصلی کن.',
					),
					array(
						'q' => 'اگه تو برف شدید گیر کردیم، چطور آتیش روشن نگه داریم؟',
						'a' => 'برف همون بارانه، فقط یخ زده. اول باید یه پلتفرم از چوب‌های تر یا سنگ بسازی تا آتیش تو برف فرو نره. بعد از چوب‌های رزینی (کاج) استفاده کن چون تو سرما و رطوبت بهتر می‌سوزن. داکوتا فایر هول تو برف جواب نمیده چون برف حفره رو پر می‌کنه، پس باید از آتش‌های سطحی و پلتفرمی استفاده کنی.',
					),
					array(
						'q' => 'چرا آتیش تو باد و بارون مدام دود می‌کنه و تو چشم می‌ره؟',
						'a' => 'چون فشار پایینِ باد، دود رو به سمت زمین هل میده و راه خروج عمودی رو می‌بنده. راه‌حل اینه که یه بادگیر فقط در یک سمت آتیش بساز تا باد بپیچه و یه جریان هوای عمودی (Updraft) ایجاد کنه که دود رو مستقیم به آسمون بفرسته.',
					),
					array(
						'q' => 'چطور بفهمیم یه کنده چوب از داخل خشکه یا خیس؟',
						'a' => 'با دسته چاقو یا یه سنگ محکم بکوب بهش. اگه صدای "تق" توخالی و بلند داد، خشکه. اگه صدای "بم" و خفه داد، یعنی از داخل پوسیده و خیس یا پر از قارچه. همچنین، چوب خشک معمولاً ترک‌های عمیق داره و سبک‌تره.',
					),
				),
			),
			array(
				'slug' => 'outdoor-cooking-minimal',
				'faq' => array(
					array(
						'q' => 'ساده‌ترین غذای کمپ چیست؟',
						'a' => 'عدس‌پلو، سوپ و غذاهای تک‌قابلمه‌ای با مواد خشک؛ سریع، سبک و کم‌کثیف.',
					),
					array(
						'q' => 'آیا می‌توانم در چادر آشپزی کنم؟',
						'a' => 'هرگز؛ مونوکسیدکربن و آتش‌سوزی. پخت‌وپز فقط بیرون و دور از چادر.',
					),
					array(
						'q' => 'چطور ظرف را کمتر کثیف کنم؟',
						'a' => 'غذای تک‌قابلمه‌ای بپز، آب را اندازه بگیر و با غذای چرب کمتر کار کن؛ فویل هم تمیزکاری را ساده می‌کند.',
					),
					array(
						'q' => 'آب چشمه قابل‌نوشیدن است؟',
						'a' => 'نه؛ هر آب سطحی باید جوشانده یا تصفیه شود. روش‌ها در <a href="/emergency-water-purification/">تصفیه آب</a> توضیح داده شده.',
					),
					array(
						'q' => 'زبالهٔ غذا را چه کنم؟',
						'a' => 'در کیسهٔ درپوش‌دار، دور از چادر و با خودت از طبیعت ببر؛ سوزاندن و دفن‌کردن، مشکل را حل نمی‌کند.',
					),
				),
			),
			array(
				'slug' => 'portable-water-filter-review',
				'faq' => array(
					array(
						'q' => 'آیا می‌تونم مستقیم از چشمه کوهستان آب بخورم و فیلتر نبرم؟',
						'a' => 'مگر اینکه بخوای سه روز آینده رو در آغوش نزدیک‌ترین درخت به عنوان توالت بگذرونی، نه. چشمه‌های کوهستان هم در معرض آلودگی حیوانات وحشی، دام‌ها و حتی لاشه حیوانات افتاده تو مسیرش هستن. ریسک نکن رفیق، یه فیلتر سبک تو جیبت جا میشه.',
					),
					array(
						'q' => 'تفاوت فیلتر (Filter) و پوریفایر (Purifier) دقیقاً چیه؟',
						'a' => 'فیلتر مثل یه الک عمل می‌کنه. سوراخ‌هاش اونقدر ریزه که باکتری‌ها و انگل‌ها رد نمی‌شن، ولی ویروس‌ها چون خیلی کوچیکن، از سوراخ‌ها رد می‌شن. پوریفایر یا ویروس‌ها رو با مواد شیمیایی/اشعه می‌کشه، یا سوراخ‌هاش اونقدر ریزه (مثل نانوفیلتراسیون) که ویروس هم پشت در می‌مونه.',
					),
					array(
						'q' => 'فیلترم وسط مسیر کیپ شد و آب رد نمیشه. چیکار کنم؟',
						'a' => 'اگه فیلتر پمپی یا فشاری داری، باید "Backflush" یا شست‌وشوی معکوس انجام بدی. یعنی آب تمیز رو برعکس از داخل فیلتر فشار بدی تا آشغال‌هایی که تو بافتش گیر کردن خارج بشن. همیشه یه سرنگ تمیز یا یه بطری آب تمیز برای همین کار تو کوله‌ت داشته باش. فیلتر نی‌شکل را با آب تمیز از سمت دهانهٔ خروجی شست‌وشوی معکوس بده (یا طبق دفترچهٔ سازنده)؛ دهانهٔ فیلتر را به دهان نگذار.',
					),
					array(
						'q' => 'آیا برف و بارون رو هم باید فیلتر کرد؟',
						'a' => 'بارون مستقیم از آسمون میاد و نیازی به فیلتر فیزیکی نداره، ولی بهتره قبل از خوردن بجوشونیش یا قرص کلر بزنی چون تو مسیر سقوط ممکنه آلوده بشه. برف تازه هم همینه. ولی برفی که روی زمین نشسته و یخ زده، مثل یه اسفنج آلودگی‌های محیط، فضله حیوانات و گرد و غبار رو جذب کرده. حتماً اول آبش کن و بعد فیلتر یا جوشوند.',
					),
					array(
						'q' => 'چطور بفهمم فیلترم دیگه کار نمی‌کنه و تموم شده؟',
						'a' => 'به عدد "لیتر" ظرفیت فیلتر نگاه کن. هر فیلتر یه عمر مفید داره (مثلاً ۱۰۰۰ لیتر یا ۴۰۰۰ لیتر). اگه نمی‌دونی چقدر استفاده کردی، به تغییر طعم آب یا کاهش سرعت عبور آب دقت کن. اگه فیلتر سرامیکی ضربه خورده یا فیلتر الیاف توخالی یخ زده، بدون معطلی دورش بنداز. با کلیه‌هات شوخی نکن.',
					),
				),
			),
			array(
				'slug' => 'water-safety-children',
				'faq' => array(
					array(
						'q' => 'برای بچه‌ای که شنا بلد است هم جلیقه لازم است؟',
						'a' => 'بله؛ شنا در استخر با شنا در رودخانهٔ جریان‌دار یکی نیست. جریان، سرمای آب و خستگی، حتی شناگر خوب را هم از پا می‌اندازد. در آب طبیعت، جلیقه برای همهٔ بچه‌ها قانون است.',
					),
					array(
						'q' => 'جلیقهٔ نجات خوب چه ویژگی‌هایی دارد؟',
						'a' => 'برچسب وزن و سن دارد، بندهای سینه‌ای و فاق‌دار، سگک‌های سالم و رویهٔ بدون پارگی؛ وقتی می‌بندی، با کشیدن از شانه بالا نمی‌آید. از جلیقه‌های بدون گواهی و بادی استفاده نکن.',
					),
					array(
						'q' => 'اگر بچه‌ای را در آب بی‌حرکت دیدم چه کنم؟',
						'a' => 'فریاد بزن تا دیگران کمک کنند، از ساحل دست یا چوب دراز کن و بیرون بکش؛ اگر نفس نمی‌کشد یا هوشیار نیست، بلافاصله ۱۱۵ و احیای قلبی‌ریوی را شروع کن. مسیر کامل در <a href="/unconscious-drowning-cpr/">نجات فرد بیهوش از آب</a> آمده است.',
					),
					array(
						'q' => 'چقدر باید به بچه نزدیک باشم؟',
						'a' => 'در حدی که هر لحظه دستت به او برسد؛ این فاصله برای بچهٔ کوچک‌تر یعنی یک قدم. برای آب جریان‌دار، نظارت باید از کناره و با دسترسی فوری باشد، نه از فاصلهٔ چند متری.',
					),
					array(
						'q' => 'شب هم بازی آبی برای بچه‌ها اشکالی ندارد؟',
						'a' => 'نه؛ تاریکی دیدن نشانه‌های غرق‌شدن را تقریباً غیرممکن می‌کند. بازی آبی را به روز و به محدودهٔ روشن محدود کن و بعد از تاریکی، فقط کنار آتش و چادر بمانید.',
					),
				),
			),
		);
	}

	public static function boot() {
		register_activation_hook( __FILE__, array( __CLASS__, 'activate' ) );
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
		add_action( 'admin_post_sa_faq_restore', array( __CLASS__, 'handle_action' ) );
		add_action( 'admin_notices', array( __CLASS__, 'admin_notice' ) );
	}

	public static function activate() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		self::apply_changes( 'activation' );
	}

	public static function register_menu() {
		add_management_page(
			'بازگردانی یک‌بارهٔ FAQ مقالات',
			'بازگردانی FAQ (یک‌باره)',
			'manage_options',
			self::PAGE_SLUG,
			array( __CLASS__, 'render_page' )
		);
	}

	/**
	 * Decode a raw sa_faq value into a clean list of {q,a} rows.
	 *
	 * @param mixed $raw Raw meta value.
	 * @return array<int,array{q:string,a:string}>
	 */
	private static function decode_faq( $raw ) {
		if ( is_string( $raw ) && '' !== trim( $raw ) ) {
			$decoded = json_decode( $raw, true );
		} elseif ( is_array( $raw ) ) {
			$decoded = $raw;
		} else {
			$decoded = array();
		}
		$out = array();
		if ( is_array( $decoded ) ) {
			foreach ( $decoded as $row ) {
				if ( is_array( $row ) && isset( $row['q'], $row['a'] ) ) {
					$out[] = array(
						'q' => (string) $row['q'],
						'a' => (string) $row['a'],
					);
				}
			}
		}
		return $out;
	}

	/**
	 * Apply: write the repository FAQ only where the live sa_faq is empty.
	 *
	 * @param string $source activation|manual.
	 * @return array<string,mixed>
	 */
	public static function apply_changes( $source = 'manual' ) {
		$backup = get_option( self::BACKUP_OPTION, array() );
		if ( ! is_array( $backup ) ) {
			$backup = array();
		}
		$backup_dirty = false;

		$report = array(
			'source'  => (string) $source,
			'time'    => current_time( 'mysql' ),
			'user_id' => get_current_user_id(),
			'counts'  => array(
				'updated'          => 0,
				'already_target'   => 0,
				'skipped_nonempty' => 0,
				'not_found'        => 0,
				'failed'           => 0,
			),
			'records' => array(),
		);

		foreach ( self::records() as $record ) {
			$slug   = (string) $record['slug'];
			$target = self::decode_faq( $record['faq'] );
			$post   = self::find_post( $slug );
			$log    = array(
				'slug'         => $slug,
				'post_id'      => $post ? (int) $post->ID : 0,
				'before_count' => 0,
				'target_count' => count( $target ),
				'after_count'  => 0,
				'status'       => '',
				'questions'    => array_map(
					function ( $row ) {
						return $row['q'];
					},
					$target
				),
			);

			if ( ! $post ) {
				++$report['counts']['not_found'];
				$log['status'] = 'not_found';
				$report['records'][] = $log;
				continue;
			}

			if ( ! isset( $backup[ $slug ] ) ) {
				$backup[ $slug ] = array(
					'post_id' => (int) $post->ID,
					'exists'  => metadata_exists( 'post', $post->ID, self::META_KEY ),
					'raw'     => (string) get_post_meta( $post->ID, self::META_KEY, true ),
				);
				$backup_dirty = true;
			}

			$raw    = (string) get_post_meta( $post->ID, self::META_KEY, true );
			$before = self::decode_faq( $raw );
			$log['before_count'] = count( $before );

			if ( $before === $target ) {
				$log['status'] = 'already_target';
				$log['after_count'] = count( $before );
			} elseif ( '' !== trim( $raw ) ) {
				// Anything non-empty belongs to the owner; never overwrite it.
				$log['status'] = 'skipped_nonempty';
				$log['after_count'] = count( $before );
			} else {
				update_post_meta(
					$post->ID,
					self::META_KEY,
					wp_json_encode( $target, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES )
				);
				$after = self::decode_faq( (string) get_post_meta( $post->ID, self::META_KEY, true ) );
				$log['after_count'] = count( $after );
				$log['status'] = ( $after === $target ) ? 'updated' : 'failed';
			}

			if ( isset( $report['counts'][ $log['status'] ] ) ) {
				++$report['counts'][ $log['status'] ];
			}
			$report['records'][] = $log;
		}

		if ( $backup_dirty ) {
			add_option( self::BACKUP_OPTION, $backup, '', 'no' );
			if ( false === get_option( self::BACKUP_OPTION, false ) ) {
				update_option( self::BACKUP_OPTION, $backup );
			}
		}
		update_option( self::REPORT_OPTION, $report, false );
		return $report;
	}

	/**
	 * Rollback: restore the captured raw sa_faq (usually: delete it again),
	 * only while the current value is still exactly what this plugin wrote.
	 *
	 * @return array<string,mixed>
	 */
	public static function rollback() {
		$backup = get_option( self::BACKUP_OPTION, array() );
		$report = array(
			'time'    => current_time( 'mysql' ),
			'user_id' => get_current_user_id(),
			'counts'  => array(
				'restored'        => 0,
				'already_backup'  => 0,
				'skipped_changed' => 0,
				'not_found'       => 0,
				'failed'          => 0,
			),
			'records' => array(),
		);

		if ( ! is_array( $backup ) || ! $backup ) {
			$report['error'] = 'no_backup';
			update_option( self::ROLLBACK_OPTION, $report, false );
			return $report;
		}

		foreach ( self::records() as $record ) {
			$slug = (string) $record['slug'];
			if ( ! isset( $backup[ $slug ] ) ) {
				continue;
			}
			$target = self::decode_faq( $record['faq'] );
			$post   = self::find_post( $slug );
			$log    = array(
				'slug'         => $slug,
				'post_id'      => $post ? (int) $post->ID : 0,
				'before_count' => 0,
				'target_count' => count( $target ),
				'after_count'  => 0,
				'status'       => '',
				'questions'    => array(),
			);
			if ( ! $post || (int) $backup[ $slug ]['post_id'] !== (int) $post->ID ) {
				++$report['counts']['not_found'];
				$log['status'] = 'not_found';
				$report['records'][] = $log;
				continue;
			}

			$current = self::decode_faq( (string) get_post_meta( $post->ID, self::META_KEY, true ) );
			$log['before_count'] = count( $current );
			$restore_raw = isset( $backup[ $slug ]['raw'] ) ? (string) $backup[ $slug ]['raw'] : '';
			$restore     = self::decode_faq( $restore_raw );

			if ( $current === $restore ) {
				$log['status'] = 'already_backup';
				$log['after_count'] = count( $current );
			} elseif ( $current !== $target ) {
				$log['status'] = 'skipped_changed';
				$log['after_count'] = count( $current );
			} else {
				if ( ! empty( $backup[ $slug ]['exists'] ) && '' !== $restore_raw ) {
					update_post_meta( $post->ID, self::META_KEY, $restore_raw );
				} else {
					delete_post_meta( $post->ID, self::META_KEY );
				}
				$after = self::decode_faq( (string) get_post_meta( $post->ID, self::META_KEY, true ) );
				$log['after_count'] = count( $after );
				$log['status'] = ( $after === $restore ) ? 'restored' : 'failed';
			}

			if ( isset( $report['counts'][ $log['status'] ] ) ) {
				++$report['counts'][ $log['status'] ];
			}
			$report['records'][] = $log;
		}

		update_option( self::ROLLBACK_OPTION, $report, false );
		return $report;
	}

	private static function find_post( $slug ) {
		$posts = get_posts(
			array(
				'name'                => $slug,
				'post_type'           => 'post',
				'post_status'         => array( 'publish', 'draft', 'pending', 'private', 'future' ),
				'posts_per_page'      => 1,
				'no_found_rows'       => true,
				'suppress_filters'    => false,
				'ignore_sticky_posts' => true,
			)
		);
		if ( ! $posts ) {
			return null;
		}
		$post = $posts[0];
		return ( $post && $slug === (string) $post->post_name ) ? $post : null;
	}

	public static function handle_action() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'دسترسی کافی ندارید.', 'sa-faq-restore' ) );
		}
		check_admin_referer( 'sa_faq_restore_action' );

		$action = isset( $_POST['sa_action'] ) ? sanitize_key( wp_unslash( $_POST['sa_action'] ) ) : '';
		if ( 'apply' === $action ) {
			self::apply_changes( 'manual' );
			$done = 'applied';
		} elseif ( 'rollback' === $action ) {
			self::rollback();
			$done = 'rolled_back';
		} else {
			$done = 'none';
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'        => self::PAGE_SLUG,
					'sa_faq_done' => $done,
				),
				admin_url( 'tools.php' )
			)
		);
		exit;
	}

	public static function admin_notice() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$report = get_option( self::REPORT_OPTION, array() );
		if ( ! is_array( $report ) || empty( $report['counts'] ) ) {
			return;
		}
		$c   = $report['counts'];
		$url = admin_url( 'tools.php?page=' . self::PAGE_SLUG );
		echo '<div class="notice notice-success is-dismissible"><p>';
		echo esc_html(
			sprintf(
				'بازگردانی یک‌بارهٔ FAQ اجرا شده است: %1$d مقاله به‌روزرسانی، %2$d مقاله از قبل درست، %3$d مقاله ردشده به‌دلیل خالی‌نبودن FAQ. پس از اجرای ممیزی مقالات و اطمینان از نتیجه، این افزونه را غیرفعال و حذف کنید.',
				(int) $c['updated'],
				(int) $c['already_target'],
				(int) $c['skipped_nonempty']
			)
		);
		echo ' <a href="' . esc_url( $url ) . '">' . esc_html__( 'مشاهدهٔ گزارش', 'sa-faq-restore' ) . '</a>';
		echo '</p></div>';
	}

	private static function status_label( $status ) {
		$labels = array(
			'updated'          => 'FAQ نوشته شد',
			'already_target'   => 'از قبل FAQ هدف بود',
			'skipped_nonempty' => 'رد شد: FAQ زنده خالی نیست',
			'failed'           => 'خطا در ذخیره/راستی‌آزمایی',
			'not_found'        => 'نوشته پیدا نشد',
			'restored'         => 'بازگردانده شد',
			'already_backup'   => 'از قبل مقدار پشتیبان بود',
			'skipped_changed'  => 'رد شد: مقدار فعلی تغییر کرده است',
		);
		return isset( $labels[ $status ] ) ? $labels[ $status ] : (string) $status;
	}

	/**
	 * @param array<string,mixed>|false $report Saved report.
	 */
	private static function render_report_table( $report ) {
		if ( ! is_array( $report ) || empty( $report['records'] ) ) {
			echo '<p>' . esc_html__( 'هنوز گزارشی ثبت نشده است.', 'sa-faq-restore' ) . '</p>';
			return;
		}
		echo '<p>' . esc_html( sprintf( 'زمان اجرا: %s', isset( $report['time'] ) ? $report['time'] : '—' ) ) . '</p>';
		echo '<table class="widefat striped" style="max-width:1000px"><thead><tr>';
		echo '<th>' . esc_html__( 'نامک', 'sa-faq-restore' ) . '</th>';
		echo '<th>' . esc_html__( 'وضعیت', 'sa-faq-restore' ) . '</th>';
		echo '<th>' . esc_html__( 'FAQ قبل ← هدف ← بعد', 'sa-faq-restore' ) . '</th>';
		echo '<th>' . esc_html__( 'پرسش‌ها', 'sa-faq-restore' ) . '</th>';
		echo '</tr></thead><tbody>';
		foreach ( $report['records'] as $record ) {
			echo '<tr>';
			echo '<td><code>' . esc_html( $record['slug'] ) . '</code><br>#' . esc_html( (string) $record['post_id'] ) . '</td>';
			echo '<td>' . esc_html( self::status_label( $record['status'] ) ) . '</td>';
			echo '<td>' . esc_html( (string) $record['before_count'] . ' ← ' . (string) $record['target_count'] . ' ← ' . (string) $record['after_count'] ) . '</td>';
			echo '<td>' . esc_html( implode( '؛ ', (array) $record['questions'] ) ) . '</td>';
			echo '</tr>';
		}
		echo '</tbody></table>';
	}

	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'دسترسی کافی ندارید.', 'sa-faq-restore' ) );
		}
		$report     = get_option( self::REPORT_OPTION, array() );
		$rollback   = get_option( self::ROLLBACK_OPTION, array() );
		$has_backup = (bool) get_option( self::BACKUP_OPTION, array() );

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'بازگردانی یک‌بارهٔ FAQ مقالات (فاز ۴ ـ بستهٔ A)', 'sa-faq-restore' ) . '</h1>';
		echo '<p style="max-width:900px">' . esc_html__( 'این افزونه فقط متای sa_faq هشت راهنمای مشخص را، فقط وقتی روی سایت خالی است، از مقادیر مخزن پر می‌کند. اگر FAQ فعلی هر مقاله‌ای خالی نباشد، دست نمی‌خورد. متن، عنوان، تصویر، منابع و هیچ فیلد دیگری تغییر نمی‌کند.', 'sa-faq-restore' ) . '</p>';

		if ( isset( $_GET['sa_faq_done'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display flag only.
			$done = sanitize_key( wp_unslash( $_GET['sa_faq_done'] ) );
			if ( 'applied' === $done ) {
				echo '<div class="notice notice-success"><p>' . esc_html__( 'اجرای بازگردانی انجام شد؛ گزارش تازه در ادامه آمده است.', 'sa-faq-restore' ) . '</p></div>';
			} elseif ( 'rolled_back' === $done ) {
				echo '<div class="notice notice-warning"><p>' . esc_html__( 'بازگردانی نسخهٔ پشتیبان اجرا شد؛ گزارش آن در ادامه آمده است.', 'sa-faq-restore' ) . '</p></div>';
			}
		}

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="margin:16px 0">';
		echo '<input type="hidden" name="action" value="sa_faq_restore">';
		wp_nonce_field( 'sa_faq_restore_action' );
		echo '<button class="button button-primary" type="submit" name="sa_action" value="apply">' . esc_html__( 'اجرای دوبارهٔ بازگردانی', 'sa-faq-restore' ) . '</button> ';
		if ( $has_backup ) {
			echo '<button class="button" type="submit" name="sa_action" value="rollback" onclick="return confirm(\'' . esc_js( __( 'FAQهای نوشته‌شده حذف و مقدار پیش از اصلاح برگردانده شود؟', 'sa-faq-restore' ) ) . '\')">' . esc_html__( 'بازگردانی نسخهٔ پشتیبان', 'sa-faq-restore' ) . '</button>';
		}
		echo '</form>';

		echo '<h2>' . esc_html__( 'گزارش آخرین بازگردانی', 'sa-faq-restore' ) . '</h2>';
		self::render_report_table( is_array( $report ) ? $report : false );

		if ( is_array( $rollback ) && ! empty( $rollback['records'] ) ) {
			echo '<h2>' . esc_html__( 'گزارش آخرین بازگشت', 'sa-faq-restore' ) . '</h2>';
			self::render_report_table( $rollback );
		}

		echo '<h2>' . esc_html__( 'پس از اطمینان', 'sa-faq-restore' ) . '</h2>';
		echo '<ol style="max-width:900px">';
		echo '<li>' . esc_html__( 'افزونهٔ «ممیزی مقالات زنده» را اجرا کنید تا خالی‌نبودن FAQ این هشت مقاله تأیید شود.', 'sa-faq-restore' ) . '</li>';
		echo '<li>' . esc_html__( 'اگر نتیجه درست بود، همین افزونهٔ یک‌بارمصرف را غیرفعال و حذف کنید؛ حذف افزونه گزارش و پشتیبان موقتش را هم پاک می‌کند.', 'sa-faq-restore' ) . '</li>';
		echo '<li>' . esc_html__( 'اگر نتیجه نادرست بود، پیش از حذف افزونه از دکمهٔ «بازگردانی نسخهٔ پشتیبان» در همین صفحه استفاده کنید.', 'sa-faq-restore' ) . '</li>';
		echo '</ol>';
		echo '</div>';
	}
}

SA_FAQ_Restore::boot();

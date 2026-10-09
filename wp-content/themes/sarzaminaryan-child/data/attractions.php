<?php
/**
 * جاذبه‌های شاخص گردشگری ایران برای داشبورد (گزینش تحریریه).
 *
 * وضعیت منبع: فهرست تحریریهٔ جاذبه‌های شناخته‌شدهٔ ملی است؛ استان/شهرستان هر مورد
 * با فهرست رسمی ۴۸۳ شهرستان (data/counties.php) راستی‌آزمایی شد. امتیاز «محبوبیت»
 * عمدی درج نشده چون دادهٔ منبعی برای آن در مخزن وجود ندارد (سیاست: دادهٔ نساخته).
 * نشان‌ها فقط برای ثبت‌های قطعیِ جهانی/ملی درج شده‌اند:
 *   - «میراث جهانی یونسکو» برای پرونده‌های ثبت‌شدهٔ قطعی؛
 *   - «ژئوپارک جهانی یونسکو» برای ژئوسایت‌های ژئوپارک قشم.
 *
 * کلید `province` = نامک استان در طبقه‌بندی `province_tax`.
 * کلید `cat` = historical | natural | religious | cultural (برچسب فارسی در داشبورد نگاشت می‌شود).
 *
 * @package Sarzaminaryan_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return array(
	'note' => 'گزینش تحریریهٔ جاذبه‌های شاخص ملی؛ بدون دادهٔ محبوبیت (امتیاز نساخته‌ایم).',
	'items'  => array(
		// فارس
		array( 'slug' => 'persepolis',        'name' => 'تخت جمشید',                 'province' => 'fars',                    'county' => 'مرودشت',        'cat' => 'historical', 'badge' => 'میراث جهانی یونسکو' ),
		array( 'slug' => 'pasargadae',        'name' => 'پاسارگاد',                  'province' => 'fars',                    'county' => 'پاسارگاد',      'cat' => 'historical', 'badge' => 'میراث جهانی یونسکو' ),
		array( 'slug' => 'naqsh-e-rostam',    'name' => 'نقش رستم',                  'province' => 'fars',                    'county' => 'مرودشت',        'cat' => 'historical', 'badge' => '' ),
		array( 'slug' => 'eram-garden',       'name' => 'باغ ارم',                   'province' => 'fars',                    'county' => 'شیراز',         'cat' => 'historical', 'badge' => 'میراث جهانی یونسکو (باغ ایرانی)' ),
		array( 'slug' => 'vakil-complex',     'name' => 'مجموعه وکیل (بازار، مسجد و حمام)', 'province' => 'fars',            'county' => 'شیراز',         'cat' => 'historical', 'badge' => '' ),
		array( 'slug' => 'shah-cheragh',      'name' => 'حرم شاهچراغ',               'province' => 'fars',                    'county' => 'شیراز',         'cat' => 'religious',  'badge' => '' ),
		array( 'slug' => 'bishapur',          'name' => 'شهر ساسانی بیشاپور',        'province' => 'fars',                    'county' => 'کازرون',        'cat' => 'historical', 'badge' => 'میراث جهانی یونسکو (منظر ساسانی فارس)' ),
		array( 'slug' => 'margoon-falls',     'name' => 'آبشار مارگون',              'province' => 'fars',                    'county' => 'سپیدان',        'cat' => 'natural',    'badge' => '' ),
		// اصفهان
		array( 'slug' => 'naqsh-e-jahan',     'name' => 'میدان نقش جهان',            'province' => 'isfahan',                 'county' => 'اصفهان',        'cat' => 'historical', 'badge' => 'میراث جهانی یونسکو' ),
		array( 'slug' => 'si-o-se-pol',       'name' => 'سی‌وسه‌پل',                  'province' => 'isfahan',                 'county' => 'اصفهان',        'cat' => 'historical', 'badge' => '' ),
		array( 'slug' => 'khaju-bridge',      'name' => 'پل خواجو',                  'province' => 'isfahan',                 'county' => 'اصفهان',        'cat' => 'historical', 'badge' => '' ),
		array( 'slug' => 'vank-cathedral',    'name' => 'کلیسای وانک',               'province' => 'isfahan',                 'county' => 'اصفهان',        'cat' => 'religious',  'badge' => '' ),
		array( 'slug' => 'minar-jonban',      'name' => 'منارجنبان',                 'province' => 'isfahan',                 'county' => 'اصفهان',        'cat' => 'historical', 'badge' => '' ),
		array( 'slug' => 'fin-garden',        'name' => 'باغ فین کاشان',             'province' => 'isfahan',                 'county' => 'کاشان',         'cat' => 'historical', 'badge' => 'میراث جهانی یونسکو (باغ ایرانی)' ),
		array( 'slug' => 'borujerdi-house',   'name' => 'خانه تاریخی بروجردی‌ها',    'province' => 'isfahan',                 'county' => 'کاشان',         'cat' => 'historical', 'badge' => '' ),
		array( 'slug' => 'sialk-hills',       'name' => 'تپه سیلک',                  'province' => 'isfahan',                 'county' => 'کاشان',         'cat' => 'historical', 'badge' => '' ),
		array( 'slug' => 'abyaneh',           'name' => 'روستای تاریخی ابیانه',      'province' => 'isfahan',                 'county' => 'نطنز',          'cat' => 'historical', 'badge' => '' ),
		array( 'slug' => 'maranjab-desert',   'name' => 'کویر و کاروانسرای مرنجاب',  'province' => 'isfahan',                 'county' => 'آران و بیدگل',  'cat' => 'natural',    'badge' => '' ),
		// تهران
		array( 'slug' => 'golestan-palace',   'name' => 'کاخ گلستان',                'province' => 'tehran',                  'county' => 'تهران',         'cat' => 'historical', 'badge' => 'میراث جهانی یونسکو' ),
		array( 'slug' => 'national-museum',   'name' => 'موزه ملی ایران',            'province' => 'tehran',                  'county' => 'تهران',         'cat' => 'cultural',   'badge' => '' ),
		array( 'slug' => 'niavaran-palace',   'name' => 'کاخ نیاوران',               'province' => 'tehran',                  'county' => 'تهران',         'cat' => 'historical', 'badge' => '' ),
		// کرمان
		array( 'slug' => 'bam-citadel',       'name' => 'ارگ بم',                    'province' => 'kerman',                  'county' => 'بم',            'cat' => 'historical', 'badge' => 'میراث جهانی یونسکو' ),
		array( 'slug' => 'shazdeh-garden',    'name' => 'باغ شاهزده (ماهان)',        'province' => 'kerman',                  'county' => 'کرمان',         'cat' => 'historical', 'badge' => 'میراث جهانی یونسکو (باغ ایرانی)' ),
		array( 'slug' => 'shah-nematollah',   'name' => 'آرامگاه شاه نعمت‌الله ولی (ماهان)', 'province' => 'kerman',          'county' => 'کرمان',         'cat' => 'religious',  'badge' => '' ),
		array( 'slug' => 'maymand',           'name' => 'روستای صخره‌ای میمند',       'province' => 'kerman',                  'county' => 'شهربابک',       'cat' => 'historical', 'badge' => 'میراث جهانی یونسکو' ),
		// خراسان رضوی
		array( 'slug' => 'imam-reza-shrine',  'name' => 'حرم امام رضا (ع)',          'province' => 'razavi-khorasan',         'county' => 'مشهد',          'cat' => 'religious',  'badge' => '' ),
		array( 'slug' => 'goharshad-mosque',  'name' => 'مسجد گوهرشاد',              'province' => 'razavi-khorasan',         'county' => 'مشهد',          'cat' => 'religious',  'badge' => '' ),
		array( 'slug' => 'ferdowsi-tomb',     'name' => 'آرامگاه فردوسی (توس)',      'province' => 'razavi-khorasan',         'county' => 'مشهد',          'cat' => 'cultural',   'badge' => '' ),
		array( 'slug' => 'khayyam-tomb',      'name' => 'آرامگاه خیام',              'province' => 'razavi-khorasan',         'county' => 'نیشابور',       'cat' => 'cultural',   'badge' => '' ),
		// آذربایجان شرقی
		array( 'slug' => 'tabriz-bazaar',     'name' => 'بازار تاریخی تبریز',        'province' => 'east-azerbaijan',         'county' => 'تبریز',         'cat' => 'historical', 'badge' => 'میراث جهانی یونسکو' ),
		array( 'slug' => 'kandovan',          'name' => 'روستای صخره‌ای کندوان',      'province' => 'east-azerbaijan',         'county' => 'اسکو',          'cat' => 'historical', 'badge' => '' ),
		array( 'slug' => 'blue-mosque',       'name' => 'مسجد کبود تبریز',           'province' => 'east-azerbaijan',         'county' => 'تبریز',         'cat' => 'religious',  'badge' => '' ),
		// آذربایجان غربی
		array( 'slug' => 'takht-e-soleiman',  'name' => 'تخت سلیمان',                'province' => 'west-azerbaijan',         'county' => 'تکاب',          'cat' => 'historical', 'badge' => 'میراث جهانی یونسکو' ),
		array( 'slug' => 'urmia-lake',        'name' => 'دریاچه ارومیه',             'province' => 'west-azerbaijan',         'county' => 'ارومیه',        'cat' => 'natural',    'badge' => '' ),
		array( 'slug' => 'qara-kelisa',       'name' => 'قره‌کلیسا',                  'province' => 'west-azerbaijan',         'county' => 'چالدران',       'cat' => 'religious',  'badge' => 'میراث جهانی یونسکو (کلیساهای ارمنی ایران)' ),
		// خوزستان
		array( 'slug' => 'chogha-zanbil',     'name' => 'زیگورات چغازنبیل',          'province' => 'khuzestan',               'county' => 'شوش',           'cat' => 'historical', 'badge' => 'میراث جهانی یونسکو' ),
		array( 'slug' => 'susa',              'name' => 'منظر باستان‌شناختی شوش',     'province' => 'khuzestan',               'county' => 'شوش',           'cat' => 'historical', 'badge' => 'میراث جهانی یونسکو' ),
		array( 'slug' => 'shushtar-hydraulic','name' => 'سازه‌های آبی تاریخی شوشتر',  'province' => 'khuzestan',               'county' => 'شوشتر',         'cat' => 'historical', 'badge' => 'میراث جهانی یونسکو' ),
		// لرستان
		array( 'slug' => 'falak-ol-aflak',    'name' => 'قلعه فلک‌الافلاک',           'province' => 'lorestan',                'county' => 'خرم‌آباد',       'cat' => 'historical', 'badge' => '' ),
		array( 'slug' => 'gahar-lake',        'name' => 'دریاچه گهر',                'province' => 'lorestan',                'county' => 'دورود',         'cat' => 'natural',    'badge' => '' ),
		array( 'slug' => 'bisheh-falls',      'name' => 'آبشار بیشه',                'province' => 'lorestan',                'county' => 'دورود',         'cat' => 'natural',    'badge' => '' ),
		// مازندران
		array( 'slug' => 'damavand',          'name' => 'قله دماوند',                'province' => 'mazandaran',              'county' => 'آمل',           'cat' => 'natural',    'badge' => '' ),
		array( 'slug' => 'badab-e-soort',     'name' => 'باداب سورت',                'province' => 'mazandaran',              'county' => 'ساری',          'cat' => 'natural',    'badge' => '' ),
		// گیلان
		array( 'slug' => 'masouleh',          'name' => 'ماسوله',                    'province' => 'gilan',                   'county' => 'فومن',          'cat' => 'historical', 'badge' => '' ),
		array( 'slug' => 'rudkhan-castle',    'name' => 'قلعه رودخان',               'province' => 'gilan',                   'county' => 'فومن',          'cat' => 'historical', 'badge' => '' ),
		// همدان
		array( 'slug' => 'ali-sadr-cave',     'name' => 'غار علیصدر',                'province' => 'hamadan',                 'county' => 'کبودرآهنگ',     'cat' => 'natural',    'badge' => '' ),
		array( 'slug' => 'avicenna-tomb',     'name' => 'آرامگاه بوعلی سینا',        'province' => 'hamadan',                 'county' => 'همدان',         'cat' => 'cultural',   'badge' => '' ),
		array( 'slug' => 'ganjnameh',         'name' => 'گنجنامه',                   'province' => 'hamadan',                 'county' => 'همدان',         'cat' => 'historical', 'badge' => '' ),
		// زنجان
		array( 'slug' => 'soltaniyeh-dome',   'name' => 'گنبد سلطانیه',              'province' => 'zanjan',                  'county' => 'سلطانیه',       'cat' => 'historical', 'badge' => 'میراث جهانی یونسکو' ),
		// اردبیل
		array( 'slug' => 'sheikh-safi',       'name' => 'مجموعه شیخ صفی‌الدین اردبیلی','province' => 'ardabil',                'county' => 'اردبیل',        'cat' => 'historical', 'badge' => 'میراث جهانی یونسکو' ),
		array( 'slug' => 'meshgin-swbridge',  'name' => 'پل معلق مشگین‌شهر',          'province' => 'ardabil',                 'county' => 'مشگین‌شهر',     'cat' => 'natural',    'badge' => '' ),
		// گلستان
		array( 'slug' => 'qabus-tower',       'name' => 'برج قابوس',                 'province' => 'golestan',                'county' => 'گنبد کاووس',    'cat' => 'historical', 'badge' => 'میراث جهانی یونسکو' ),
		// سمنان
		array( 'slug' => 'abr-forest',        'name' => 'جنگل ابر',                  'province' => 'semnan',                  'county' => 'شاهرود',        'cat' => 'natural',    'badge' => '' ),
		// کردستان
		array( 'slug' => 'zarivar-lake',      'name' => 'دریاچه زریوار',             'province' => 'kurdistan',               'county' => 'مریوان',        'cat' => 'natural',    'badge' => '' ),
		array( 'slug' => 'uramanat',          'name' => 'منظر فرهنگی اورامانات',     'province' => 'kurdistan',               'county' => 'کامیاران',      'cat' => 'historical', 'badge' => 'میراث جهانی یونسکو' ),
		// کرمانشاه
		array( 'slug' => 'taq-e-bostan',      'name' => 'طاق بستان',                 'province' => 'kermanshah',              'county' => 'کرمانشاه',      'cat' => 'historical', 'badge' => '' ),
		array( 'slug' => 'bisotun',           'name' => 'کتیبه و محوطه بیستون',      'province' => 'kermanshah',              'county' => 'صحنه',          'cat' => 'historical', 'badge' => 'میراث جهانی یونسکو', 'note' => 'محوطهٔ بیستون؛ شهرستان بیستون در فهرست ۴۸۳تایی نبود، نزدیک‌ترین شهرستان ثبت‌شده درج شد.' ),
		// قزوین
		array( 'slug' => 'alamut-castle',     'name' => 'قلعه الموت',                'province' => 'qazvin',                  'county' => 'قزوین',         'cat' => 'historical', 'badge' => '' ),
		// قم
		array( 'slug' => 'fatima-masumeh',    'name' => 'حرم فاطمه معصومه (س)',      'province' => 'qom',                     'county' => 'قم',            'cat' => 'religious',  'badge' => '' ),
		array( 'slug' => 'jamkaran',          'name' => 'مسجد جمکران',               'province' => 'qom',                     'county' => 'قم',            'cat' => 'religious',  'badge' => '' ),
		// مرکزی
		array( 'slug' => 'meyghan-wetland',   'name' => 'تالاب میقان',               'province' => 'markazi',                 'county' => 'اراک',          'cat' => 'natural',    'badge' => '' ),
		// البرز
		array( 'slug' => 'morvarid-palace',   'name' => 'کاخ مروارید (شمس‌الاماره)', 'province' => 'alborz',                  'county' => 'کرج',           'cat' => 'historical', 'badge' => '' ),
		array( 'slug' => 'karaj-chalus-road', 'name' => 'جاده کرج–چالوس',            'province' => 'alborz',                  'county' => 'کرج',           'cat' => 'natural',    'badge' => '' ),
		// بوشهر
		array( 'slug' => 'bushehr-oldtown',   'name' => 'بافت تاریخی بندر بوشهر',    'province' => 'bushehr',                 'county' => 'بوشهر',         'cat' => 'historical', 'badge' => '' ),
		// ایلام
		array( 'slug' => 'darrehshahr',       'name' => 'شهر تاریخی دره‌شهر',         'province' => 'ilam',                    'county' => 'دره‌شهر',       'cat' => 'historical', 'badge' => '' ),
		// کهگیلویه و بویراحمد
		array( 'slug' => 'yasuj-waterfall',   'name' => 'آبشار یاسوج',               'province' => 'kohgiluyeh-boyer-ahmad',  'county' => 'بویراحمد',      'cat' => 'natural',    'badge' => '' ),
		array( 'slug' => 'kohgol-lake',       'name' => 'دریاچه کوه‌گل',              'province' => 'kohgiluyeh-boyer-ahmad',  'county' => 'بویراحمد',      'cat' => 'natural',    'badge' => '', 'note' => 'در ناحیهٔ دنا؛ شهرستان دنا در فهرست ۴۸۳تایی نبود.' ),
		// چهارمحال و بختیاری
		array( 'slug' => 'inverted-tulips',   'name' => 'دشت لاله‌های واژگون کوهرنگ', 'province' => 'chaharmahal-bakhtiari',   'county' => 'کوهرنگ',        'cat' => 'natural',    'badge' => '' ),
		array( 'slug' => 'atashgah-falls',    'name' => 'آبشار آتشگاه',              'province' => 'chaharmahal-bakhtiari',   'county' => 'لردگان',        'cat' => 'natural',    'badge' => '' ),
		// خراسان جنوبی
		array( 'slug' => 'kal-jeni',          'name' => 'دره کال جنی',               'province' => 'south-khorasan',          'county' => 'طبس',           'cat' => 'natural',    'badge' => '' ),
		// سیستان و بلوچستان
		array( 'slug' => 'shahr-e-sukhteh',   'name' => 'شهر سوخته',                 'province' => 'sistan-baluchestan',      'county' => 'زابل',          'cat' => 'historical', 'badge' => 'میراث جهانی یونسکو' ),
		array( 'slug' => 'khajeh-mountain',   'name' => 'کوه خواجه',                 'province' => 'sistan-baluchestan',      'county' => 'هامون',         'cat' => 'historical', 'badge' => '' ),
		array( 'slug' => 'lipar-pink-lake',   'name' => 'دریاچه صورتی لیپار',        'province' => 'sistan-baluchestan',      'county' => 'چابهار',        'cat' => 'natural',    'badge' => '' ),
		// هرمزگان
		array( 'slug' => 'stars-valley',      'name' => 'دره ستارگان قشم',           'province' => 'hormozgan',               'county' => 'قشم',           'cat' => 'natural',    'badge' => 'ژئوپارک جهانی یونسکو' ),
		array( 'slug' => 'hengam-island',     'name' => 'جزیره هنگام',               'province' => 'hormozgan',               'county' => 'قشم',           'cat' => 'natural',    'badge' => '' ),
		array( 'slug' => 'portuguese-castle', 'name' => 'قلعه پرتغالی‌ها (هرمز)',     'province' => 'hormozgan',               'county' => 'قشم',           'cat' => 'historical', 'badge' => '' ),
		// یزد
		array( 'slug' => 'yazd-oldtown',      'name' => 'شهر تاریخی یزد',            'province' => 'yazd',                    'county' => 'یزد',           'cat' => 'historical', 'badge' => 'میراث جهانی یونسکو' ),
		array( 'slug' => 'dolat-abad-garden', 'name' => 'باغ دولت‌آباد',              'province' => 'yazd',                    'county' => 'یزد',           'cat' => 'historical', 'badge' => 'میراث جهانی یونسکو (باغ ایرانی)' ),
		array( 'slug' => 'amir-chakhmaq',     'name' => 'میدان امیرچخماق',           'province' => 'yazd',                    'county' => 'یزد',           'cat' => 'historical', 'badge' => '' ),
		array( 'slug' => 'yazd-fire-temple',  'name' => 'آتشکده بهرام یزد',          'province' => 'yazd',                    'county' => 'یزد',           'cat' => 'religious',  'badge' => '' ),
	),
);

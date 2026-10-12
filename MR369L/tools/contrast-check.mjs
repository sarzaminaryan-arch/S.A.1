#!/usr/bin/env node
/**
 * MR369L — محاسبهٔ نسبت کنتراست WCAG برای پالت theme.json.
 *
 * این ابزار «سنجش» بستهٔ کاری WP-2.2 است: هیچ رنگی در قالب پذیرفته نمی‌شود
 * مگر عدد کنتراست آن ثبت شده باشد.
 *
 * استفاده:
 *   node MR369L/tools/contrast-check.mjs --theme ./MR369L/theme
 *
 * آستانه‌ها (WCAG 2.1):
 *   - متن معمولی: ۴٫۵:۱
 *   - متن بزرگ (≥ 24px یا ≥ 19px بولد): ۳:۱
 *   - اجزای رابط/مرز کنترل: ۳:۱
 */

import fs from 'node:fs';
import path from 'node:path';

function parseArgs( argv ) {
	const args = { theme: './MR369L/theme' };
	for ( let i = 0; i < argv.length; i++ ) {
		if ( argv[ i ] === '--theme' ) {
			args.theme = argv[ i + 1 ];
			i++;
		}
	}
	return args;
}

/**
 * تبدیل رنگ HEX به RGB.
 *
 * @param {string} hex رنگ شش‌رقمی یا سه‌رقمی.
 * @return {{r:number,g:number,b:number}} اجزای رنگ.
 */
function hexToRgb( hex ) {
	let value = hex.replace( '#', '' ).trim();
	if ( value.length === 3 ) {
		value = value.split( '' ).map( ( c ) => c + c ).join( '' );
	}
	return {
		r: parseInt( value.slice( 0, 2 ), 16 ),
		g: parseInt( value.slice( 2, 4 ), 16 ),
		b: parseInt( value.slice( 4, 6 ), 16 ),
	};
}

/**
 * روشنایی نسبی بر پایهٔ WCAG.
 *
 * @param {{r:number,g:number,b:number}} rgb رنگ.
 * @return {number} روشنایی نسبی.
 */
function luminance( rgb ) {
	const channel = ( c ) => {
		const v = c / 255;
		return v <= 0.03928 ? v / 12.92 : Math.pow( ( v + 0.055 ) / 1.055, 2.4 );
	};
	return 0.2126 * channel( rgb.r ) + 0.7152 * channel( rgb.g ) + 0.0722 * channel( rgb.b );
}

/**
 * نسبت کنتراست دو رنگ.
 *
 * @param {string} a رنگ اول.
 * @param {string} b رنگ دوم.
 * @return {number} نسبت کنتراست.
 */
function contrast( a, b ) {
	const la = luminance( hexToRgb( a ) );
	const lb = luminance( hexToRgb( b ) );
	const lighter = Math.max( la, lb );
	const darker = Math.min( la, lb );
	return ( lighter + 0.05 ) / ( darker + 0.05 );
}

const args = parseArgs( process.argv.slice( 2 ) );
const themeJsonPath = path.resolve( args.theme, 'theme.json' );

if ( ! fs.existsSync( themeJsonPath ) ) {
	console.error( `theme.json پیدا نشد: ${ themeJsonPath }` );
	process.exit( 2 );
}

const themeJson = JSON.parse( fs.readFileSync( themeJsonPath, 'utf8' ) );
const palette = {};

for ( const item of themeJson?.settings?.color?.palette || [] ) {
	palette[ item.slug ] = item.color;
}

/**
 * جفت‌های مصرف‌شده در قالب با آستانهٔ لازم.
 * هر جفت باید در CSS/PHP واقعاً استفاده شود؛ در غیر این صورت از فهرست حذف می‌شود.
 */
const pairs = [
	{ fg: 'contrast', bg: 'base', min: 4.5, note: 'متن اصلی روی پس‌زمینهٔ صفحه' },
	{ fg: 'contrast', bg: 'surface', min: 4.5, note: 'متن اصلی روی سطح/کارت' },
	{ fg: 'muted', bg: 'base', min: 4.5, note: 'متن فرعی (یادداشت پاصفحه، تاریخ)' },
	{ fg: 'muted', bg: 'surface', min: 4.5, note: 'متن فرعی روی سطح' },
	{ fg: 'accent', bg: 'base', min: 4.5, note: 'لینک درون متن' },
	{ fg: 'base', bg: 'accent', min: 4.5, note: 'متن دکمهٔ اصلی روی رنگ تأکیدی' },
	{ fg: 'accent', bg: 'accent-soft', min: 4.5, note: 'نشان (badge) روی پس‌زمینهٔ تأکیدی روشن' },
	{ fg: 'danger', bg: 'base', min: 4.5, note: 'پیام خطا/ناموجود' },
	{ fg: 'success', bg: 'base', min: 4.5, note: 'پیام موفقیت' },
	{ fg: 'base', bg: 'contrast', min: 4.5, note: 'نوار اعلان (متن روشن روی تیره)' },
	{ fg: 'line', bg: 'base', min: 0, note: 'خط جداکنندهٔ تزئینی — الزام WCAG 1.4.11 شامل تزئین نمی‌شود' },
	{ fg: 'line-strong', bg: 'base', min: 3, note: 'مرز کنترل‌های فرم (الزام WCAG 1.4.11)' },
	{ fg: 'line-strong', bg: 'surface', min: 3, note: 'مرز کنترل روی سطح' },
];

console.log( 'MR369L contrast-check (WCAG 2.1)' );
console.log( `theme.json: ${ themeJsonPath }`);
console.log( `تعداد رنگ پالت: ${ Object.keys( palette ).length }\n` );
console.log( 'جفت رنگ'.padEnd( 26 ) + 'نسبت'.padEnd( 10 ) + 'آستانه'.padEnd( 10 ) + 'نتیجه' );

let failed = 0;

for ( const pair of pairs ) {
	const fg = palette[ pair.fg ];
	const bg = palette[ pair.bg ];

	if ( ! fg || ! bg ) {
		console.log( `${ pair.fg } on ${ pair.bg }`.padEnd( 26 ) + '—'.padEnd( 10 ) + String( pair.min ).padEnd( 10 ) + '⛔ رنگ تعریف نشده' );
		failed++;
		continue;
	}

	const ratio = contrast( fg, bg );
	const pass = ratio >= pair.min;

	if ( ! pass ) {
		failed++;
	}

	console.log(
		`${ pair.fg } on ${ pair.bg }`.padEnd( 26 ) +
			ratio.toFixed( 2 ).padEnd( 10 ) +
			String( pair.min ).padEnd( 10 ) +
			( pass ? '✅ قبول' : '❌ رد' ) +
			`  — ${ pair.note }`
	);
}

console.log( `\nجمع: ${ pairs.length } جفت، ${ failed } ناموفق.` );
console.log( 'یادآوری: این ابزار کنتراست «تعریف رنگ» را می‌سنجد، نه خروجی نهایی مرورگر.' );

process.exit( failed > 0 ? 1 : 0 );

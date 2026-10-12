#!/usr/bin/env node
/**
 * MR369L — بررسی ایستای قالب (بدون نیاز به وردپرس و بدون وابستگی بیرونی).
 *
 * قاعده‌ها با شناسهٔ AUD-nn مستند شده‌اند در:
 *   MR369L/docs/TEST_AND_QUALITY_FA.md §۴
 *
 * استفاده:
 *   node MR369L/tools/theme-audit.mjs --dir ./MR369L/theme
 *
 * خروجی: فهرست یافته‌ها به تفکیک شدت (error/warning/info) و کد خروج ۱ اگر error باشد.
 */

import fs from 'node:fs';
import path from 'node:path';

/* ------------------------------------------------------------------ *
 * ابزارهای پایه
 * ------------------------------------------------------------------ */

function parseArgs( argv ) {
	const args = { dir: './MR369L/theme' };
	for ( let i = 0; i < argv.length; i++ ) {
		if ( argv[ i ] === '--dir' ) {
			args.dir = argv[ i + 1 ];
			i++;
		}
	}
	return args;
}

/**
 * فهرست بازگشتی فایل‌های یک پوشه.
 *
 * @param {string} root ریشه.
 * @return {string[]} مسیرهای نسبی مرتب‌شده.
 */
function walk( root ) {
	const out = [];
	const visit = ( dir ) => {
		for ( const entry of fs.readdirSync( dir, { withFileTypes: true } ).sort( ( a, b ) => a.name.localeCompare( b.name ) ) ) {
			if ( entry.name === 'node_modules' || entry.name === '.git' ) {
				continue;
			}
			const full = path.join( dir, entry.name );
			if ( entry.isDirectory() ) {
				visit( full );
			} else {
				out.push( full );
			}
		}
	};
	visit( root );
	return out;
}

const findings = [];

/**
 * ثبت یافته.
 *
 * @param {string} severity شدت: error | warning | info.
 * @param {string} rule     شناسهٔ قاعده.
 * @param {string} file     فایل.
 * @param {string} message  توضیح.
 * @return {void}
 */
function add( severity, rule, file, message ) {
	findings.push( { severity, rule, file, message } );
}

/* ------------------------------------------------------------------ *
 * قاعده‌ها
 * ------------------------------------------------------------------ */

const DANGEROUS_FUNCTIONS = [ 'eval(', 'base64_decode(', 'unserialize(', 'exec(', 'shell_exec(', 'system(', 'passthru(', 'proc_open(' ];
// دامنهٔ خودِ پروژه فقط در متادیتای قالب (Theme URI / Author URI) می‌آید و درخواست شبکه نیست.
const ALLOWED_EXTERNAL_HOSTS = [ 'wordpress.org', 'www.wordpress.org', 'gnu.org', 'www.gnu.org', 'schemas.wp.org', 'www.w3.org', 'github.com', 'sarzaminaryan.ir', 'www.sarzaminaryan.ir' ];
const PHYSICAL_CSS = [ 'margin-left', 'margin-right', 'padding-left', 'padding-right', 'border-left', 'border-right', 'left:', 'right:' ];

/**
 * بررسی یک فایل PHP.
 *
 * @param {string} file مسیر مطلق.
 * @param {string} code محتوا.
 * @param {string} rel  مسیر نسبی.
 * @return {void}
 */
function auditPhp( file, code, rel ) {
	if ( ! code.startsWith( '<?php' ) ) {
		add( 'error', 'AUD-01', rel, 'فایل PHP باید با <?php شروع شود.' );
	}

	if ( /\/inc\/|\/patterns\//.test( file ) && ! /defined\(\s*'ABSPATH'\s*\)/.test( code ) ) {
		add( 'error', 'AUD-02', rel, 'نگهبان ABSPATH وجود ندارد.' );
	}

	for ( const needle of DANGEROUS_FUNCTIONS ) {
		if ( code.includes( needle ) ) {
			add( 'error', 'AUD-03', rel, `تابع خطرناک یافت شد: ${ needle }` );
		}
	}

	// هر echo خروجی باید escape شده باشد (بررسی ساده و محافظه‌کارانه).
	const echoMatches = code.match( /echo\s+[^;]+;/g ) || [];
	for ( const statement of echoMatches ) {
		const literalOnly = /^echo\s+'[^'$]*';$/.test( statement.trim() ) || /^echo\s+"[^"$]*";$/.test( statement.trim() );
		const safe = literalOnly || ! /\$/.test( statement ) || /esc_(html|attr|url|js|textarea)|wp_kses|wp_json_encode|number_format_i18n|absint|intval|\(\s*int\s*\)|selected\(|checked\(|submit_button|printf\(/.test( statement );
		if ( ! safe ) {
			add( 'warning', 'AUD-16', rel, `echo بدون escape مشخص: ${ statement.slice( 0, 60 ) }` );
		}
	}
}

/**
 * بررسی یک فایل CSS.
 *
 * @param {string} code محتوا.
 * @param {string} rel  مسیر نسبی.
 * @return {void}
 */
function auditCss( code, rel ) {
	const stripped = code.replace( /\/\*[\s\S]*?\*\//g, '' );

	for ( const prop of PHYSICAL_CSS ) {
		const re = new RegExp( `(^|[\\s;{])${ prop.replace( ':', '\\s*:' ) }`, 'm' );
		if ( re.test( stripped ) ) {
			add( 'warning', 'AUD-05', rel, `ویژگی فیزیکی یافت شد (باید منطقی باشد): ${ prop }` );
		}
	}

	if ( /row-reverse/.test( stripped ) ) {
		add( 'warning', 'AUD-06', rel, 'row-reverse در RTL ترتیب را دوباره برمی‌گرداند (تلهٔ I-101).' );
	}

	if ( /\.(wc-block-|woocommerce-)/.test( stripped ) ) {
		add( 'error', 'AUD-07', rel, 'سلکتور روی ساختار داخلی بلوک‌های ووکامرس ممنوع است (تلهٔ I-103).' );
	}

	if ( /@keyframes/.test( stripped ) && ! /prefers-reduced-motion/.test( stripped ) ) {
		add( 'error', 'AUD-09', rel, 'فایل دارای @keyframes باید بلوک prefers-reduced-motion داشته باشد.' );
	}

	if ( /@keyframes/.test( stripped ) && ! rel.endsWith( 'typography-motion.css' ) ) {
		add( 'warning', 'AUD-10', rel, '@keyframes بیرون از فایل موشن تعریف شده است.' );
	}

	const importantCount = ( stripped.match( /!important/g ) || [] ).length;
	if ( importantCount > 6 ) {
		add( 'warning', 'AUD-11', rel, `تعداد !important بالا: ${ importantCount }` );
	}
}

/**
 * بررسی آدرس‌های بیرونی در متن.
 *
 * @param {string} code محتوا.
 * @param {string} rel  مسیر نسبی.
 * @return {void}
 */
function auditExternalUrls( code, rel ) {
	const matches = code.match( /https?:\/\/[^\s"'<>)]+/g ) || [];
	for ( const url of matches ) {
		if ( /^https?:\/\/www\.w3\.org\/(2000\/svg|1999\/xlink)/.test( url ) ) {
			continue; // فضای‌نام SVG، درخواست شبکه نیست.
		}
		let host = '';
		try {
			host = new URL( url ).host;
		} catch ( e ) {
			continue;
		}
		if ( ! ALLOWED_EXTERNAL_HOSTS.includes( host ) ) {
			add( 'error', 'AUD-04', rel, `آدرس بیرونی مجاز نیست: ${ url }` );
		}
	}
}

/**
 * بررسی توازن نشانه‌های بلوکی در قالب‌های HTML.
 *
 * @param {string} code محتوا.
 * @param {string} rel  مسیر نسبی.
 * @return {void}
 */
function auditBlockMarkup( code, rel ) {
	const opens = ( code.match( /<!--\s*wp:[a-z0-9-]+\/([a-z0-9-]+)/g ) || [] ).length;
	const selfClosers = ( code.match( /<!--\s*wp:[a-z0-9-]+\/[a-z0-9-]+[^>]*\/-->/g ) || [] ).length;
	const closes = ( code.match( /<!--\s*\/wp:[a-z0-9-]+\/[a-z0-9-]+\s*-->/g ) || [] ).length;

	if ( opens - selfClosers !== closes ) {
		add(
			'error',
			'AUD-13',
			rel,
			`توازن بلوک‌ها برقرار نیست: ${ opens } باز، ${ selfClosers } خودبسته، ${ closes } بسته.`
		);
	}

	if ( /<script/i.test( code ) ) {
		add( 'error', 'AUD-15', rel, 'تگ script در قالب/بخش/الگو ممنوع است (صفر جاوااسکریپت اختصاصی).' );
	}
}

/* ------------------------------------------------------------------ *
 * اجرا
 * ------------------------------------------------------------------ */

const args = parseArgs( process.argv.slice( 2 ) );
const root = path.resolve( args.dir );

if ( ! fs.existsSync( root ) ) {
	console.error( `پوشه پیدا نشد: ${ root }` );
	process.exit( 2 );
}

const files = walk( root );
const counters = { php: 0, css: 0, json: 0, html: 0, svg: 0, other: 0 };

for ( const file of files ) {
	const rel = path.relative( root, file );
	const ext = path.extname( file );
	const code = fs.readFileSync( file, 'utf8' );

	switch ( ext ) {
		case '.php':
			counters.php++;
			auditPhp( file, code, rel );
			if ( /\/patterns\//.test( file ) ) {
				for ( const field of [ 'Title:', 'Slug:', 'Categories:' ] ) {
					if ( ! code.includes( field ) ) {
						add( 'error', 'AUD-14', rel, `سرآیند الگو فیلد «${ field }» را ندارد.` );
					}
				}
			}
			auditExternalUrls( code, rel );
			break;
		case '.css':
			counters.css++;
			auditCss( code, rel );
			auditExternalUrls( code, rel );
			if ( rel.endsWith( 'typography-motion.css' ) ) {
				const bytes = Buffer.byteLength( code, 'utf8' );
				if ( bytes > 6144 ) {
					add( 'warning', 'AUD-10', rel, `حجم فایل موشن از بودجهٔ ۶KB گذشت: ${ bytes } بایت.` );
				} else {
					add( 'info', 'AUD-10', rel, `حجم فایل موشن: ${ bytes } بایت (بودجه ۶۱۴۴).` );
				}
			}
			break;
		case '.json': {
			counters.json++;
			try {
				const parsed = JSON.parse( code );
				if ( rel === 'theme.json' ) {
					if ( parsed.version !== 3 ) {
						add( 'error', 'AUD-12', rel, `نسخهٔ theme.json باید ۳ باشد، هست: ${ parsed.version }` );
					} else {
						add( 'info', 'AUD-12', rel, 'theme.json معتبر و نسخهٔ ۳.' );
					}
				}
			} catch ( error ) {
				add( 'error', 'AUD-12', rel, `JSON نامعتبر: ${ error.message }` );
			}
			break;
		}
		case '.html':
			counters.html++;
			auditBlockMarkup( code, rel );
			auditExternalUrls( code, rel );
			break;
		case '.svg':
			counters.svg++;
			if ( /<script|xlink:href\s*=\s*"http/.test( code ) ) {
				add( 'error', 'AUD-17', rel, 'SVG نباید script یا ارجاع بیرونی داشته باشد.' );
			}
			break;
		default:
			counters.other++;
			break;
	}
}

/* ------------------------------------------------------------------ *
 * گزارش
 * ------------------------------------------------------------------ */

const order = { error: 0, warning: 1, info: 2 };
const labels = { error: '❌ error', warning: '⚠️  warning', info: 'ℹ️  info' };

console.log( 'MR369L theme-audit' );
console.log( `پوشه: ${ root }` );
console.log(
	`فایل‌ها → PHP: ${ counters.php } | CSS: ${ counters.css } | JSON: ${ counters.json } | HTML: ${ counters.html } | SVG: ${ counters.svg } | سایر: ${ counters.other }\n`
);

const visible = findings.filter( ( f ) => f.severity !== 'info' || process.env.MR369L_AUDIT_VERBOSE === '1' );

if ( visible.length === 0 ) {
	console.log( 'هیچ یافته‌ای ثبت نشد.' );
} else {
	visible
		.sort( ( a, b ) => order[ a.severity ] - order[ b.severity ] || a.rule.localeCompare( b.rule ) )
		.forEach( ( f ) => {
			console.log( `${ labels[ f.severity ] } [${ f.rule }] ${ f.file } → ${ f.message }` );
		} );
}

const errors = findings.filter( ( f ) => f.severity === 'error' ).length;
const warnings = findings.filter( ( f ) => f.severity === 'warning' ).length;

console.log( `\nجمع: ${ errors } خطا، ${ warnings } هشدار، ${ findings.length - errors - warnings } اطلاع.` );

process.exit( errors > 0 ? 1 : 0 );

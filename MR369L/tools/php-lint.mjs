#!/usr/bin/env node
/**
 * MR369L — lint نحو PHP روی چند نسخه، بدون نیاز به PHP سیستمی.
 *
 * روش: PHP کامپایل‌شده به WebAssembly (@php-wasm/node) + token_get_all(TOKEN_PARSE)
 * که خطای نحو را به‌صورت ParseError گزارش می‌کند (معادل عملی php -l برای نحو).
 *
 * چرا نه `php -l`؟ در این محیط popen/proc_open در WASM پشتیبانی نمی‌شود.
 *
 * استفاده:
 *   node MR369L/tools/php-lint.mjs --dir ./MR369L/theme --php 7.4,8.1,8.2,8.3
 *
 * خروجی: گزارش هر فایل و در پایان کد خروج ۰ (سالم) یا ۱ (خطا).
 * وابستگی: @php-wasm/node (نصب یک‌باره در ./tools أو پوشهٔ مجاور).
 */

import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';

const __dirname = path.dirname( fileURLToPath( import.meta.url ) );

/**
 * یافتن پوشهٔ node_modules حاوی @php-wasm/node.
 *
 * Node ماژول‌ها را نسبت به محل فایل اسکریپت حل می‌کند؛ چون این ابزار داخل مخزن
 * است و وابستگی بیرون از آن نصب می‌شود، مسیرها دستی بررسی می‌شوند.
 *
 * @return {string} مسیر پوشهٔ node_modules.
 */
function findModulesDir() {
	const candidates = [
		process.env.MR369L_NODE_MODULES,
		path.join( __dirname, 'node_modules' ),
		path.join( __dirname, '..', 'node_modules' ),
		path.join( process.cwd(), 'node_modules' ),
		path.join( process.env.HOME || '', '.tools', 'phptest', 'node_modules' ),
	].filter( Boolean );

	for ( const dir of candidates ) {
		if ( fs.existsSync( path.join( dir, '@php-wasm', 'node', 'package.json' ) ) ) {
			return dir;
		}
	}

	throw new Error(
		'پکیج @php-wasm/node پیدا نشد. نصب: npm i --prefix MR369L/tools @php-wasm/node  ' +
			'یا تعیین مسیر با متغیر MR369L_NODE_MODULES=…/node_modules'
	);
}

let modulesDir = null;

function parseArgs( argv ) {
	const args = { dir: './MR369L/theme', php: '7.4,8.2', extensions: 'php' };
	for ( let i = 0; i < argv.length; i++ ) {
		const key = argv[ i ];
		const value = argv[ i + 1 ];
		if ( key === '--dir' ) {
			args.dir = value;
			i++;
		} else if ( key === '--php' ) {
			args.php = value;
			i++;
		} else if ( key === '--extensions' ) {
			args.extensions = value;
			i++;
		}
	}
	return args;
}

/**
 * فهرست بازگشتی فایل‌ها با پسوندهای خواسته‌شده.
 *
 * @param {string}   root پوشهٔ ریشه.
 * @param {string[]} exts پسوندهای مجاز.
 * @return {string[]} مسیرهای مطلق.
 */
function collectFiles( root, exts ) {
	const out = [];
	const walk = ( dir ) => {
		for ( const entry of fs.readdirSync( dir, { withFileTypes: true } ) ) {
			const full = path.join( dir, entry.name );
			if ( entry.isDirectory() ) {
				if ( entry.name === 'node_modules' || entry.name === '.git' ) {
					continue;
				}
				walk( full );
			} else if ( exts.includes( path.extname( entry.name ).replace( '.', '' ) ) ) {
				out.push( full );
			}
		}
	};
	walk( root );
	return out.sort();
}

/**
 * اجرای lint با یک نسخهٔ PHP داده‌شده.
 *
 * @param {string}   version نسخهٔ PHP (مثلاً '8.2').
 * @param {string[]} files   مسیرهای مطلق فایل‌ها.
 * @return {Promise<{php:string, failures:Array, checked:number}>} نتیجه.
 */
async function lintWithVersion( version, files ) {
	modulesDir = modulesDir || findModulesDir();
	const { loadNodeRuntime, useHostFilesystem } = await import(
		pathToFileURL( path.join( modulesDir, '@php-wasm', 'node', 'index.js' ) ).href
	);
	const { PHP } = await import(
		pathToFileURL( path.join( modulesDir, '@php-wasm', 'universal', 'index.js' ) ).href
	);

	const runtime = await loadNodeRuntime( version, { emscriptenOptions: { processId: 1 } } );
	const php = new PHP( runtime );
	useHostFilesystem( php );

	// فهرست فایل‌ها به‌صورت JSON داخل کد PHP جای می‌گیرد (بدون argv، چون SAPI در WASM محدود است).
	const payload = JSON.stringify( files );

	const code = `<?php
$files = json_decode( '${ payload.replace( /\\/g, '\\\\' ).replace( /'/g, "\\'" ) }', true );
$failures = array();
$checked  = 0;
foreach ( (array) $files as $f ) {
	if ( ! is_readable( $f ) ) {
		$failures[] = array( 'file' => $f, 'error' => 'خوانده نشد', 'line' => 0 );
		continue;
	}
	$checked++;
	$src = file_get_contents( $f );
	try {
		token_get_all( $src, TOKEN_PARSE );
	} catch ( ParseError $e ) {
		$failures[] = array( 'file' => $f, 'error' => $e->getMessage(), 'line' => $e->getLine() );
	}
}
echo json_encode( array(
	'php'      => PHP_VERSION,
	'checked'  => $checked,
	'failures' => $failures,
) );
`;

	const res = await php.run( { code } );
	const text = ( res.text || '' ).trim();

	if ( ! text ) {
		throw new Error( `PHP ${ version } خروجی نداد. errors: ${ String( res.errors ).slice( 0, 400 ) }` );
	}

	return JSON.parse( text );
}

const args = parseArgs( process.argv.slice( 2 ) );
const target = path.resolve( args.dir );

if ( ! fs.existsSync( target ) ) {
	console.error( `پوشه پیدا نشد: ${ target }` );
	process.exit( 2 );
}

const exts = args.extensions.split( ',' ).map( ( e ) => e.trim() );
const files = collectFiles( target, exts );
const versions = args.php.split( ',' ).map( ( v ) => v.trim() );

console.log( `MR369L php-lint | پوشه: ${ target }` );
console.log( `فایل‌ها (${ exts.join( ',' ) }): ${ files.length }` );
console.log( `نسخه‌ها: ${ versions.join( ', ' ) }\n` );

let failed = false;

for ( const version of versions ) {
	try {
		const result = await lintWithVersion( version, files );
		const label = `PHP ${ result.php }`;
		if ( result.checked !== files.length ) {
			console.log( `${ label } | هشدار: فقط ${ result.checked } فایل خوانده شد` );
		}
		if ( result.failures.length === 0 ) {
			console.log( `${ label } | ✅ ${ result.checked } فایل، صفر خطای نحو` );
		} else {
			failed = true;
			console.log( `${ label } | ❌ ${ result.failures.length } خطا` );
			for ( const f of result.failures ) {
				console.log( `   - ${ f.file }:${ f.line } → ${ f.error }` );
			}
		}
	} catch ( error ) {
		failed = true;
		console.log( `PHP ${ version } | ⚠️ اجرا نشد: ${ error.message }` );
	}
}

process.exit( failed ? 1 : 0 );

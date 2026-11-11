<?php
/**
 * Standalone test: entity bars can never widen their grid past the viewport
 * (2.22.44, reported by the owner on /province/ at tablet widths).
 *
 * The archive bars (.sa-bars/.sa-bar, since 2.13.3) render the province,
 * city, attraction and education archives as white shadowed bars with a
 * Persian title and a nowrap secondary label (English name / province).
 * Two missing shrink guards made the grid overflow its container at
 * mid-range widths (tablet / mobile "desktop site" view): the grid tracks
 * were plain `1fr` — which is minmax(auto, 1fr), so a track refuses to
 * shrink below the longest label — and the secondary label had no
 * min-width: 0, so it imposed its full width on the bar. In the RTL layout
 * the overflow spilled to the left and the English names were clipped.
 *
 * The fix, guarded here against regressions in assets/css/child.css:
 *  1. The bars grids use minmax(0, 1fr) tracks.
 *  2. The bar itself is min-width: 0 with overflow: hidden.
 *  3. The secondary label (.sa-bar em) ellipsizes (min-width: 0 +
 *     overflow: hidden + text-overflow: ellipsis); the Persian title
 *     keeps priority.
 *
 * Assertions are scoped to the exact rule blocks so unrelated grids
 * elsewhere in the stylesheet cannot satisfy (or break) them.
 *
 * Run with: php wp-content/themes/sarzaminaryan-child/tests/test-bars-overflow.php
 *
 * @package Sarzaminaryan_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

function sa_bars_assert( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
}

/**
 * Extract the declaration block of the first rule matching a selector.
 *
 * @param string $css      Stylesheet text.
 * @param string $selector Selector including the opening brace.
 * @return string|null
 */
function sa_bars_rule_block( $css, $selector ) {
	$pos = strpos( $css, $selector );
	if ( false === $pos ) {
		return null;
	}
	$end = strpos( $css, '}', $pos );
	if ( false === $end ) {
		return null;
	}
	return substr( $css, $pos, $end - $pos );
}

$css = file_get_contents( dirname( __DIR__ ) . '/assets/css/child.css' );
sa_bars_assert( is_string( $css ) && '' !== $css, 'child.css is readable' );

// 1. Grid tracks may shrink below their content's minimum size.
$bars = sa_bars_rule_block( $css, '.sa-bars {' );
sa_bars_assert( null !== $bars, '.sa-bars rule exists' );
sa_bars_assert( false !== strpos( $bars, 'grid-template-columns: repeat(2, minmax(0, 1fr));' ), 'province bars grid tracks can shrink (minmax(0, 1fr))' );
sa_bars_assert( false === strpos( $bars, 'grid-template-columns: repeat(2, 1fr);' ), 'province bars grid has no plain 1fr track' );

$bars_city = sa_bars_rule_block( $css, '.sa-bars--city {' );
sa_bars_assert( null !== $bars_city, '.sa-bars--city rule exists' );
sa_bars_assert( false !== strpos( $bars_city, 'grid-template-columns: repeat(3, minmax(0, 1fr));' ), 'city bars grid tracks can shrink (minmax(0, 1fr))' );
sa_bars_assert( false === strpos( $bars_city, 'grid-template-columns: repeat(3, 1fr);' ), 'city bars grid has no plain 1fr track' );

// 2. The bar clips at its own border instead of painting outside it.
$bar = sa_bars_rule_block( $css, '.sa-bar {' );
sa_bars_assert( null !== $bar, '.sa-bar rule exists' );
sa_bars_assert( false !== strpos( $bar, 'min-width: 0;' ), '.sa-bar may shrink inside its grid track' );
sa_bars_assert( false !== strpos( $bar, 'overflow: hidden;' ), '.sa-bar clips its content at the border' );

// 3. The secondary label yields (ellipsis) before it can widen the bar.
$em = sa_bars_rule_block( $css, '.sa-bar em {' );
sa_bars_assert( null !== $em, '.sa-bar em rule exists' );
sa_bars_assert( false !== strpos( $em, 'white-space: nowrap;' ), '.sa-bar em stays on one line' );
sa_bars_assert( false !== strpos( $em, 'min-width: 0;' ), '.sa-bar em may shrink' );
sa_bars_assert( false !== strpos( $em, 'overflow: hidden;' ), '.sa-bar em hides its overflow' );
sa_bars_assert( false !== strpos( $em, 'text-overflow: ellipsis;' ), '.sa-bar em ellipsizes instead of clipping mid-word' );

echo "test-bars-overflow: OK\n";

<?php
/**
 * Owner policy: editorial workflow belongs outside the public article.
 * Display-only cleanup; no database writes, fabricated facts or verification claims.
 *
 * @package Sarzaminaryan_Child
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Narrow workflow vocabulary; ordinary travel/safety warnings are not matched. */
function sa_editorial_note_detected( $text ) {
	$text = strtr( html_entity_decode( strip_tags( (string) $text ), ENT_QUOTES, 'UTF-8' ), array( 'ي' => 'ی', 'ك' => 'ک', 'أ' => 'ا', 'إ' => 'ا', '‌' => ' ' ) );
	$patterns = array(
		'هوش\s*مصنوعی|در\s+دست\s+بازبینی|پیش\s*نویس',
		'(?:^|[\[(\x22\x27])[\s*#✅✓✔⚠️❌:—-]*نیاز(?:مند|\s+به)\s+(?:بررسی|بازبینی|تایید|تأیید|راستی[ -]*آزمایی|تکمیل|منبع)',
		'(?:متن|مقاله|مطلب|داده|اطلاعات|پاراگراف|منبع)(?:\s+[^.!؟؛\n]{0,40})?\s+نیاز(?:مند|\s+به)\s+(?:بررسی|بازبینی|تکمیل|منبع|تأیید|تایید)',
		'منبع\s+(?:لازم|نیست|ندارد|موردنیاز)',
		'کمبود\s+(?:منبع|منابع)(?!\s*(?:آب|غذا|انرژی))|منابع\s+کافی\s+(?:نیست|ندارد)',
		'(?:بازبینی|راستی[ -]*آزمایی)\s*[:：]',
		'(?:اطلاعات|مقاله|متن|داده)(?:\s+[^.!؟؛\n]{0,50})?\s+(?:تأیید|تایید|راستی[ -]*آزمایی)\s*(?:شده|نشده)',
		'(?:یادداشت|نوت)\s*(?:ویراستار|تحریریه|داخلی|نویسنده|هوش)',
		'(?:گزارش|وضعیت|نتیجه|آخرین|تاریخ)\s*(?:صحت|تایید|تأیید|بازبینی|راستی[ -]*آزمایی)',
		'راستی[ -]*آزمایی[ -]*شده|(?:تایید|تأیید)\s*(?:مالک|تحریریه)',
		'(?:لینک|پیوند|منبع(?!\s+(?:آب|غذا|انرژی|سوخت)))(?:\s+[^.!؟؛\n]{0,60})?\s+(?:اضافه|افزوده|ثبت|درج|تکمیل)\s*(?:شود|شوند|کنید|کن)',
		'(?:مطلب|مقاله|متن|داده|اطلاعات|لینک)(?:\s+[^.!؟؛\n]{0,50})?\s+(?:تایید|تأیید|بررسی)\s*نشده',
		'تصویری?\s+(?:تأیید|تایید)\s*نشده',
		'^[\s*#✅✓✔⚠️❌:—-]*(?:تایید|تأیید)\s*(?:نشده|شده)(?:\s*است)?[.!]?$',
		'\b(?:AI[ -]?draft|AI[ -]?generated|ChatGPT|as an AI|editorial note|editor note|fact[ -]?check report|TODO|TBD|insert link here|citation needed|source needed|needs review|pending verification|verified by|reviewed by)\b',
	);
	return (bool) preg_match( '~(?:' . implode( '|', $patterns ) . ')~imu', $text );
}

/** Remove annotations and workflow sentences, leaving ordinary prose unchanged. */
function sa_public_editorial_text( $text ) {
	$text = (string) $text;
	if ( ! sa_editorial_note_detected( $text ) ) {
		return $text;
	}
	$text = preg_replace_callback( '~\[[^\[\]\r\n]{1,200}\]|\([^()\r\n]{1,240}\)~u', static function ( $m ) {
		return sa_editorial_note_detected( $m[0] ) ? '' : $m[0];
	}, $text );
	$text = preg_replace_callback( '~[^\n.!؟؛]+(?:[.!؟؛]+|$)~u', static function ( $m ) {
		return sa_editorial_note_detected( $m[0] ) ? '' : $m[0];
	}, $text );
	return preg_replace( '/[ \t]{2,}/', ' ', $text );
}

/** DOM parser with no external entities/network access. */
function sa_editorial_document( $html ) {
	if ( ! class_exists( 'DOMDocument' ) ) {
		return null;
	}
	$doc = new DOMDocument( '1.0', 'UTF-8' );
	$previous = libxml_use_internal_errors( true );
	$ok = $doc->loadHTML( '<?xml encoding="UTF-8"><html><body><div id="sa-public-editorial-root">' . $html . '</div></body></html>', LIBXML_NONET );
	libxml_clear_errors();
	libxml_use_internal_errors( $previous );
	return $ok ? $doc : null;
}

/** Minimal DOM-less fallback: never alter markup, scripts or code samples. */
function sa_public_editorial_fallback( $html ) {
	$tokens = preg_split( '~(<(?:script|style|pre|code)\b[^>]*>.*?</(?:script|style|pre|code)\s*>|<[^>]*>)~is', $html, -1, PREG_SPLIT_DELIM_CAPTURE );
	foreach ( $tokens as &$token ) {
		if ( '' !== $token && '<' !== $token[0] ) { $token = sa_public_editorial_text( $token ); }
	}
	unset( $token );
	return implode( '', $tokens );
}

/**
 * Clean public HTML, including templates, FAQ, source labels and profile fields.
 * URLs, dates, scripts, form values and licensing credits are never rewritten.
 */
function sa_public_editorial_html( $html ) {
	$html = (string) $html;
	if ( ! sa_editorial_note_detected( preg_replace( '/<[^>]*>/', "\n", $html ) ) && ! sa_editorial_note_detected( str_replace( array( '<', '>' ), ' ', $html ) ) && ! preg_match( '/sa-(?:flag|cyber-id__review|facts-checked|source-warning)/', $html ) ) {
		return $html;
	}
	$doc = sa_editorial_document( $html );
	if ( ! $doc ) {
		return sa_public_editorial_fallback( $html );
	}
	$xpath = new DOMXPath( $doc );
	$root = $doc->getElementById( 'sa-public-editorial-root' );
	if ( ! $root ) { return $html; }
	$nonempty = new SplObjectStorage();
	foreach ( $xpath->query( './/p | .//li | .//span | .//mark | .//strong | .//a', $root ) as $node ) {
		if ( '' !== trim( $node->textContent ) ) { $nonempty[ $node ] = true; }
	}
	// Remove dedicated editorial UI rather than merely hiding it with CSS.
	foreach ( iterator_to_array( $xpath->query( './/*[@class]', $root ) ) as $node ) {
		if ( preg_match( '/(?:^|\s)(?:sa-flag--(?:review|stale)|sa-cyber-id__review|sa-facts-checked|sa-source-warning)(?:\s|$)/', $node->getAttribute( 'class' ) ) && $node->parentNode ) {
			$node->parentNode->removeChild( $node );
		}
	}
	// Private report appendices end at the next heading of the same/higher level.
	foreach ( iterator_to_array( $xpath->query( './/h2 | .//h3 | .//h4', $root ) ) as $heading ) {
		if ( ! $heading->parentNode || ! preg_match( '~^(?:\s|[📋📝⚠️])*?(?:یادداشت (?:ویراستار|داخلی|تحریریه)|گزارش (?:راستی[‌ -]*آزمایی|صحت|بازبینی)|FACT CHECK REPORT|EDITORIAL NOTES?)~iu', trim( $heading->textContent ) ) ) {
			continue;
		}
		$next = $heading->nextSibling;
		while ( $next ) {
			if ( $next instanceof DOMElement && preg_match( '/^h[1-6]$/', $next->tagName ) && (int) substr( $next->tagName, 1 ) <= (int) substr( $heading->tagName, 1 ) ) { break; }
			$remove = $next;
			$next = $next->nextSibling;
			$remove->parentNode->removeChild( $remove );
		}
		$heading->parentNode->removeChild( $heading );
	}
	// Sentence ranges are calculated across inline elements; their markup is retained.
	$blocks = $xpath->query( './/p | .//li[not(.//p or .//li)] | .//td[not(.//p)] | .//th | .//dd[not(.//p)] | .//figcaption | .//h1 | .//h2 | .//h3 | .//h4 | .//h5 | .//h6', $root );
	foreach ( iterator_to_array( $blocks ) as $block ) {
		$nodes = iterator_to_array( $xpath->query( './/text()[not(ancestor::script or ancestor::style or ancestor::code or ancestor::pre)]', $block ) );
		$text = '';
		foreach ( $nodes as $node ) { $text .= $node->nodeValue; }
		if ( ! sa_editorial_note_detected( $text ) ) { continue; }
		$ranges = array();
		preg_match_all( '~\[[^\[\]\r\n]{1,200}\]|\([^()\r\n]{1,240}\)~u', $text, $annotations, PREG_OFFSET_CAPTURE );
		$probe = $text;
		foreach ( $annotations[0] as $annotation ) {
			if ( sa_editorial_note_detected( $annotation[0] ) ) {
				$ranges[] = array( $annotation[1], strlen( $annotation[0] ) );
				$probe = substr_replace( $probe, str_repeat( ' ', strlen( $annotation[0] ) ), $annotation[1], strlen( $annotation[0] ) );
			}
		}
		preg_match_all( '~[^\n.!؟؛]+(?:[.!؟؛]+|$)~u', $probe, $sentences, PREG_OFFSET_CAPTURE );
		foreach ( $sentences[0] as $sentence ) {
			if ( sa_editorial_note_detected( $sentence[0] ) ) { $ranges[] = array( $sentence[1], strlen( $sentence[0] ) ); }
		}
		$offset = 0;
		foreach ( $nodes as $node ) {
			$original = $node->nodeValue;
			$length = strlen( $original );
			$link = $xpath->query( 'ancestor::a[@href]', $node )->item( 0 );
			if ( $link && ! sa_editorial_note_detected( $link->textContent ) ) { $offset += $length; continue; }
			$cuts = array();
			foreach ( $ranges as $range ) {
				$start = max( $offset, $range[0] );
				$end = min( $offset + $length, $range[0] + $range[1] );
				if ( $end > $start ) { $cuts[] = array( $start - $offset, $end - $start ); }
			}
			usort( $cuts, static function ( $a, $b ) { return $b[0] <=> $a[0]; } );
			foreach ( $cuts as $cut ) { $original = substr_replace( $original, '', $cut[0], $cut[1] ); }
			$node->nodeValue = $original;
			$offset += $length;
		}
	}
	// Remaining standalone inline text and accessible labels/tooltips.
	foreach ( $xpath->query( './/text()[not(ancestor::script or ancestor::style or ancestor::code or ancestor::pre)]', $root ) as $node ) {
		$node->nodeValue = sa_public_editorial_text( $node->nodeValue );
	}
	foreach ( $xpath->query( './/*[@title or @alt or @aria-label]', $root ) as $node ) {
		foreach ( array( 'title', 'alt', 'aria-label' ) as $attribute ) {
			if ( $node->hasAttribute( $attribute ) ) {
				$node->setAttribute( $attribute, sa_public_editorial_text( $node->getAttribute( $attribute ) ) );
			}
		}
	}
	foreach ( iterator_to_array( $xpath->query( './/p | .//li | .//span | .//mark | .//strong | .//a', $root ) ) as $node ) {
		if ( isset( $nonempty[ $node ] ) && '' === trim( $node->textContent ) && ! $xpath->query( './/img | .//svg | .//iframe | .//input | .//button | .//video | .//audio', $node )->length && $node->parentNode ) {
			$node->parentNode->removeChild( $node );
		}
	}
	$output = '';
	foreach ( $root->childNodes as $node ) { $output .= $doc->saveHTML( $node ); }
	return $output;
}

/** Filters apply to public rendering only. Editing REST/admin data remains intact. */
function sa_public_editorial_filter( $html ) {
	return is_admin() ? $html : sa_public_editorial_html( $html );
}
add_filter( 'the_content', 'sa_public_editorial_filter', 8 );
add_filter( 'the_content', 'sa_public_editorial_filter', 999 );
add_filter( 'get_the_excerpt', 'sa_public_editorial_filter', 1 );
add_filter( 'get_the_excerpt', 'sa_public_editorial_filter', 999 );
add_filter( 'the_excerpt', 'sa_public_editorial_filter', 999 );

/** Clean textual schema fields only, not identifiers/URLs/dates or numeric facts. */
function sa_public_editorial_schema( $data ) {
	foreach ( $data as $key => $value ) {
		if ( is_array( $value ) ) {
			$data[ $key ] = sa_public_editorial_schema( $value );
		} elseif ( is_string( $value ) && in_array( $key, array( 'description', 'text', 'headline', 'caption' ), true ) ) {
			$data[ $key ] = sa_public_editorial_text( $value );
		}
	}
	return $data;
}
add_filter( 'sa_schema_graph', 'sa_public_editorial_schema', 999 );

/** Render one article with the same policy applied to template-generated metadata. */
function sa_public_article_template( $slug, $name = null ) {
	ob_start();
	get_template_part( $slug, $name );
	echo sa_public_editorial_html( ob_get_clean() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}

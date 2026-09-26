<?php
/**
 * Jalali (Shamsi) dates for display — technique distilled from wp-shamsi (wordpress-fa):
 * hook the front-end `wp_date` filter, skip machine formats, convert digits; the database,
 * feeds, JSON-LD and <time datetime> stay Gregorian.
 *
 * @package Sarzaminaryan_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Gregorian → Jalali.
 *
 * @param int $gy Year.
 * @param int $gm Month.
 * @param int $gd Day.
 * @return int[] [jy, jm, jd]
 */
function sa_g2j( $gy, $gm, $gd ) {
	$g_d_m = array( 0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334 );
	$gy2   = ( $gm > 2 ) ? ( $gy + 1 ) : $gy;
	$days  = 355666 + ( 365 * $gy ) + intdiv( $gy2 + 3, 4 ) - intdiv( $gy2 + 99, 100 ) + intdiv( $gy2 + 399, 400 ) + $gd + $g_d_m[ $gm - 1 ];
	$jy    = -1595 + ( 33 * intdiv( $days, 12053 ) );
	$days %= 12053;
	$jy   += 4 * intdiv( $days, 1461 );
	$days %= 1461;
	if ( $days > 365 ) {
		$jy  += intdiv( $days - 1, 365 );
		$days = ( $days - 1 ) % 365;
	}
	if ( $days < 186 ) {
		$jm = 1 + intdiv( $days, 31 );
		$jd = 1 + ( $days % 31 );
	} else {
		$jm = 7 + intdiv( $days - 186, 30 );
		$jd = 1 + ( ( $days - 186 ) % 30 );
	}
	return array( $jy, $jm, $jd );
}

/**
 * Jalali → Gregorian (for completeness / future date inputs).
 *
 * @param int $jy Year.
 * @param int $jm Month.
 * @param int $jd Day.
 * @return int[] [gy, gm, gd]
 */
function sa_j2g( $jy, $jm, $jd ) {
	$jy   += 1595;
	$days  = -355668 + ( 365 * $jy ) + ( intdiv( $jy, 33 ) * 8 ) + intdiv( ( $jy % 33 ) + 3, 4 ) + $jd + ( ( $jm < 7 ) ? ( $jm - 1 ) * 31 : ( ( $jm - 7 ) * 30 ) + 186 );
	$gy    = 400 * intdiv( $days, 146097 );
	$days %= 146097;
	if ( $days > 36524 ) {
		$gy   += 100 * intdiv( --$days, 36524 );
		$days %= 36524;
		if ( $days >= 365 ) {
			$days++;
		}
	}
	$gy   += 4 * intdiv( $days, 1461 );
	$days %= 1461;
	if ( $days > 365 ) {
		$gy   += intdiv( $days - 1, 365 );
		$days  = ( $days - 1 ) % 365;
	}
	$gd = $days + 1;
	$sal_a = array( 0, 31, ( ( $gy % 4 === 0 && $gy % 100 !== 0 ) || ( $gy % 400 === 0 ) ) ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31 );
	for ( $gm = 0; $gm < 13 && $gd > $sal_a[ $gm ]; $gm++ ) {
		$gd -= $sal_a[ $gm ];
	}
	return array( $gy, $gm, $gd );
}

/**
 * Persian month / weekday names.
 *
 * @param string $what 'months' | 'days'.
 * @return string[]
 */
function sa_jalali_names( $what ) {
	if ( 'days' === $what ) {
		return array( 'یکشنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه', 'پنجشنبه', 'جمعه', 'شنبه' ); // index = PHP 'w'.
	}
	return array( 1 => 'فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند' );
}

/**
 * Format a timestamp as a Jalali date (PHP date() tokens: d j D l w N S z m n F M t L Y y H i s G g A a u v e T P O c U + escapes).
 *
 * @param string            $format    Format.
 * @param int|null          $timestamp Unix timestamp (default now).
 * @param DateTimeZone|null $timezone  Timezone (default site).
 * @return string
 */
function sa_jalali_date( $format, $timestamp = null, $timezone = null ) {
	$timestamp = null === $timestamp ? time() : (int) $timestamp;
	$timezone  = $timezone ? $timezone : wp_timezone();
	$dt        = new DateTime( '@' . $timestamp );
	$dt->setTimezone( $timezone );

	list( $jy, $jm, $jd ) = sa_g2j( (int) $dt->format( 'Y' ), (int) $dt->format( 'n' ), (int) $dt->format( 'j' ) );
	$w      = (int) $dt->format( 'w' );
	$months = sa_jalali_names( 'months' );
	$days   = sa_jalali_names( 'days' );
	$leap   = ( ( ( ( $jy - ( $jy > 0 ? 474 : 473 ) ) % 2820 ) + 474 + 38 ) * 682 ) % 2816 < 682;
	$doy    = $jm <= 6 ? ( $jm - 1 ) * 31 + $jd : 186 + ( $jm - 7 ) * 30 + $jd;

	$out = '';
	$len = strlen( $format );
	for ( $i = 0; $i < $len; $i++ ) {
		$c = $format[ $i ];
		if ( '\\' === $c ) {
			$i++;
			$out .= $i < $len ? $format[ $i ] : '';
			continue;
		}
		switch ( $c ) {
			case 'd':
				$out .= sprintf( '%02d', $jd );
				break;
			case 'j':
				$out .= $jd;
				break;
			case 'D':
			case 'l':
				$out .= $days[ $w ];
				break;
			case 'w':
				$out .= $w;
				break;
			case 'N':
				$out .= ( $w + 1 );
				break;
			case 'S':
				$out .= 'ام';
				break;
			case 'z':
				$out .= $doy - 1;
				break;
			case 'm':
				$out .= sprintf( '%02d', $jm );
				break;
			case 'n':
				$out .= $jm;
				break;
			case 'F':
			case 'M':
				$out .= $months[ $jm ];
				break;
			case 't':
				$out .= $jm <= 6 ? 31 : ( $jm <= 11 ? 30 : ( $leap ? 30 : 29 ) );
				break;
			case 'L':
				$out .= $leap ? 1 : 0;
				break;
			case 'Y':
			case 'o':
				$out .= $jy;
				break;
			case 'y':
				$out .= sprintf( '%02d', $jy % 100 );
				break;
			case 'A':
			case 'a':
				$out .= 'AM' === $dt->format( 'A' ) ? 'ق.ظ' : 'ب.ظ';
				break;
			case 'H':
			case 'i':
			case 's':
			case 'G':
			case 'g':
			case 'h':
			case 'u':
			case 'v':
			case 'e':
			case 'T':
			case 'P':
			case 'O':
			case 'Z':
			case 'U':
			case 'I':
				$out .= $dt->format( $c );
				break;
			default:
				$out .= $c;
		}
	}
	return $out;
}

/**
 * Should a given format be left in Gregorian (machine consumers)?
 *
 * @param string $format Format.
 * @return bool
 */
function sa_is_machine_format( $format ) {
	$machine = array( 'c', 'r', 'U', 'Y-m-d', 'Y-m-d H:i:s', 'Y-m-d\TH:i:s', 'Y-m-d\TH:i:sP', 'Y-m-d\TH:i:s\Z', 'Ymd', 'D, d M Y H:i:s O', 'D, d M Y H:i:s +0000', 'l, F j, Y H:i', 'H:i', 'H:i:s', 'G:i', 'g:i a', 'g:i A', DATE_ATOM, DATE_W3C, DATE_RSS, DATE_RFC2822, DATE_COOKIE, DATE_ISO8601 );
	if ( in_array( $format, $machine, true ) ) {
		return true;
	}
	// Timezone/offset/unix tokens or an unescaped literal T → machine format.
	if ( preg_match( '/(?<!\\\\)[cUOPZeTr]/', $format ) ) {
		return true;
	}
	// No date tokens at all (pure time) → nothing to convert.
	return ! preg_match( '/(?<!\\\\)[dDjlNSwzWFmMntLoYy]/', $format );
}

/**
 * Front-end wp_date filter.
 *
 * @param string       $date      Formatted date.
 * @param string       $format    Format.
 * @param int          $timestamp Timestamp.
 * @param DateTimeZone $timezone  Timezone.
 * @return string
 */
function sa_filter_wp_date( $date, $format, $timestamp, $timezone ) {
	if ( is_admin() || is_feed() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || wp_doing_ajax() || wp_doing_cron() ) {
		return $date;
	}
	if ( ! get_theme_mod( 'sa_jalali', true ) ) {
		return sa_digits( $date );
	}
	if ( sa_is_machine_format( $format ) ) {
		return $date;
	}
	// English default formats read badly in Persian → use the fa_IR defaults.
	$format = str_replace( array( 'F j, Y', 'M j, Y' ), array( 'j F Y', 'j M Y' ), $format );
	return sa_digits( sa_jalali_date( $format, $timestamp, $timezone ) );
}
add_filter( 'wp_date', 'sa_filter_wp_date', 10, 4 );

/**
 * Current Jalali year (for the footer).
 *
 * @return string
 */
function sa_jalali_year() {
	return sa_digits( sa_jalali_date( 'Y' ) );
}

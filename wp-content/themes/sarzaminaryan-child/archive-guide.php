<?php
/**
 * Archive of the «راهنمای سفر» post type — the section's hub (`/travel-guide/`).
 *
 * Since v2.24.0 the hub is a real archive instead of a virtual route, so the template
 * hierarchy asks for this file first. The listing itself lives in
 * `template-parts/2026/guide-hub.php` (shared with the category archives), and
 * `template-guide.php` is the same page for the rollback path.
 *
 * @package Sarzaminaryan_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require __DIR__ . '/template-guide.php';

<?php
/**
 * Loop item (posts & fallback for entities in loops) → reuse the card.
 *
 * @package Sarzaminaryan_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
get_template_part( 'template-parts/card', 'entity' );

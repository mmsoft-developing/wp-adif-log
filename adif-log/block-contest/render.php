<?php
/**
 * Contest napló blokk szerveroldali megjelenítése. Elérhető: $attributes, $content, $block.
 */

defined( 'ABSPATH' ) || exit;

echo ham2k_adif_log_render_html( // phpcs:ignore
	array_merge( $attributes, array( 'variant' => 'contest' ) ),
	get_block_wrapper_attributes( array( 'class' => 'ham2k-adif-log ham2k-adif-log--contest' ) ),
	defined( 'REST_REQUEST' ) && REST_REQUEST
);

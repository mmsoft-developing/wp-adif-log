<?php
/**
 * Gutenberg blokk szerveroldali megjelenítése. Elérhető: $attributes, $content, $block.
 */

defined( 'ABSPATH' ) || exit;

echo ham2k_adif_log_render_html( // phpcs:ignore
	$attributes,
	get_block_wrapper_attributes( array( 'class' => 'ham2k-adif-log' ) ),
	defined( 'REST_REQUEST' ) && REST_REQUEST
);

<?php
/**
 * Plugin Name:       ADIF Log
 * Description:       Gutenberg block and Elementor widget that displays a searchable QSO log table (with POTA support) from an ADIF (.adi / .adif) file in the media library. Available in English, Hungarian, German, French, Spanish and Italian.
 * Version:           1.5.0
 * Requires at least: 6.3
 * Requires PHP:      7.4
 * Plugin URI:        https://github.com/mmsoft-developing/wp-adif-log
 * Author:            HA8AI
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       adif-log
 */

defined( 'ABSPATH' ) || exit;

define( 'HAM2K_ADIF_LOG_VERSION', '1.5.0' );

require_once __DIR__ . '/includes/i18n.php';
require_once __DIR__ . '/includes/class-adif-parser.php';
require_once __DIR__ . '/includes/render.php';

add_action(
	'init',
	function () {
		// Közös stílus és kereső szkript – a blokk (block.json) és az Elementor widget is ezekre hivatkozik.
		wp_register_style( 'ham2k-adif-log', plugins_url( 'assets/style.css', __FILE__ ), array(), HAM2K_ADIF_LOG_VERSION );
		wp_register_script( 'ham2k-adif-log-view', plugins_url( 'assets/view.js', __FILE__ ), array(), HAM2K_ADIF_LOG_VERSION, true );

		// A blokkszerkesztő szkriptje a felhasználó nyelvén kapja meg a feliratokat és a választható nyelvek listáját.
		wp_register_script(
			'ham2k-adif-log-editor',
			plugins_url( 'block/index.js', __FILE__ ),
			array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-server-side-render' ),
			HAM2K_ADIF_LOG_VERSION,
			true
		);
		wp_add_inline_script(
			'ham2k-adif-log-editor',
			'window.ham2kAdifLog = ' . wp_json_encode(
				array(
					'i18n'      => (object) ham2k_adif_log_dictionary( ham2k_adif_log_ui_lang() ),
					'languages' => ham2k_adif_log_languages(),
				)
			) . ';',
			'before'
		);

		register_block_type( __DIR__ . '/block' );
	}
);

add_action(
	'elementor/widgets/register',
	function ( $widgets_manager ) {
		require_once __DIR__ . '/includes/class-elementor-widget.php';
		$widgets_manager->register( new Ham2K_ADIF_Log_Elementor_Widget() );
	}
);

/**
 * Az .adi / .adif fájlok alapból nem tölthetők fel a médiatárba, ezért engedélyezzük őket.
 */
add_filter(
	'upload_mimes',
	function ( $mimes ) {
		$mimes['adi|adif'] = 'text/plain';
		return $mimes;
	}
);

/**
 * Tartalék: ha a szerver a fájl tartalmából nem ismeri fel text/plain-ként, a kiterjesztés alapján engedjük.
 */
add_filter(
	'wp_check_filetype_and_ext',
	function ( $data, $file, $filename ) {
		if ( ! empty( $data['ext'] ) ) {
			return $data;
		}
		$ext = strtolower( pathinfo( (string) $filename, PATHINFO_EXTENSION ) );
		if ( in_array( $ext, array( 'adi', 'adif' ), true ) && current_user_can( 'upload_files' ) ) {
			$data['ext']  = $ext;
			$data['type'] = 'text/plain';
		}
		return $data;
	},
	10,
	3
);

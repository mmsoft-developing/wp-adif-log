<?php
/**
 * Saját, WordPress nyelvi csomagoktól független fordítás: így blokkonként bármelyik
 * támogatott nyelv választható, akkor is, ha az adott nyelv nincs telepítve a webhelyen.
 *
 * Új nyelv: languages/<kód>.php (angol => fordítás tömb), és a kód felvétele az alábbi listába
 * (vagy a 'ham2k_adif_log_languages' szűrővel).
 */

defined( 'ABSPATH' ) || exit;

/**
 * Támogatott nyelvek: kód => saját nyelvén írt név. Az angol a forrásnyelv.
 */
function ham2k_adif_log_languages() {
	return apply_filters(
		'ham2k_adif_log_languages',
		array(
			'en' => 'English',
			'hu' => 'Magyar',
			'de' => 'Deutsch',
			'fr' => 'Français',
			'es' => 'Español',
			'it' => 'Italiano',
		)
	);
}

/**
 * A választott nyelv kódja. Üres választásnál a megadott (alapból a webhely) nyelvét követi.
 */
function ham2k_adif_log_resolve_lang( $lang, $locale = null ) {
	$languages = ham2k_adif_log_languages();
	if ( is_string( $lang ) && isset( $languages[ $lang ] ) ) {
		return $lang;
	}
	$code = strtolower( substr( (string) ( $locale ?? get_locale() ), 0, 2 ) );
	return isset( $languages[ $code ] ) ? $code : 'en';
}

/**
 * Az adott nyelv szótára (angol => fordítás).
 */
function ham2k_adif_log_dictionary( $lang ) {
	static $cache = array();
	if ( 'en' === $lang || ! preg_match( '/^[a-z]{2,3}(_[A-Z]{2})?$/', (string) $lang ) ) {
		return array();
	}
	if ( ! isset( $cache[ $lang ] ) ) {
		$file           = dirname( __DIR__ ) . '/languages/' . $lang . '.php';
		$dict           = is_readable( $file ) ? include $file : array();
		$cache[ $lang ] = apply_filters( 'ham2k_adif_log_dictionary', is_array( $dict ) ? $dict : array(), $lang );
	}
	return $cache[ $lang ];
}

/**
 * Egy szöveg fordítása a megadott nyelvre; ha nincs fordítás, marad az angol.
 */
function ham2k_adif_log_t( $text, $lang ) {
	$dict = ham2k_adif_log_dictionary( $lang );
	return isset( $dict[ $text ] ) && '' !== $dict[ $text ] ? $dict[ $text ] : $text;
}

/**
 * A szerkesztőfelület (beállítópanel) nyelve: a bejelentkezett felhasználó nyelve.
 */
function ham2k_adif_log_ui_lang() {
	return ham2k_adif_log_resolve_lang( '', get_user_locale() );
}

<?php
/**
 * Ország (DXCC-entitás) meghatározása egy QSO-hoz, és a neve a választott nyelven.
 *
 * Sorrend: DXCC mező → COUNTRY mező (angol név) → hívójel prefixe (AD1C Big CTY alapján).
 * Az adattáblák a tools/generate-dxcc.php szkripttel készülnek.
 */

defined( 'ABSPATH' ) || exit;

class Ham2K_DXCC {

	/** A /MM és /AM hajó- és repülőgép-állomás, nem tartozik országhoz. */
	const NO_COUNTRY_SUFFIXES = array( 'MM', 'AM' );

	/** Hordozható és egyéb jelzők, amelyek nem változtatják meg az országot. */
	const IGNORED_SUFFIXES = array( 'P', 'M', 'QRP', 'QRPP', 'A', 'B', 'LH', 'J', 'R', 'T' );

	private static $data  = null;
	private static $names = array();

	/**
	 * A QSO országa: DXCC-szám (int), ismeretlen entitásnál a COUNTRY mező szövege, vagy ''.
	 *
	 * @param array $r ADIF rekord (nagybetűs mezőnevekkel).
	 * @return int|string
	 */
	public static function lookup( array $r ) {
		$dxcc = isset( $r['DXCC'] ) ? trim( $r['DXCC'] ) : '';
		if ( ctype_digit( $dxcc ) && (int) $dxcc > 0 && '' !== self::name( (int) $dxcc, 'en' ) ) {
			return (int) $dxcc;
		}

		$country = isset( $r['COUNTRY'] ) ? trim( $r['COUNTRY'] ) : '';
		if ( '' !== $country ) {
			$data = self::data();
			$key  = self::normalize( $country );
			if ( isset( $data['names'][ $key ] ) ) {
				return $data['names'][ $key ];
			}
		}

		$from_call = self::from_call( isset( $r['CALL'] ) ? $r['CALL'] : '' );
		if ( $from_call ) {
			return $from_call;
		}

		// Ismeretlen név: úgy jelenítjük meg, ahogy a fájlban áll.
		return $country;
	}

	/**
	 * DXCC-szám a hívójelből (pl. HA8AI → 239, DL/HA8AI → 230, HA8AI/P → 239). 0, ha nem állapítható meg.
	 */
	public static function from_call( $call ) {
		$call = strtoupper( trim( (string) $call ) );
		if ( '' === $call ) {
			return 0;
		}
		$data = self::data();
		if ( isset( $data['calls'][ $call ] ) ) {
			return $data['calls'][ $call ];
		}

		$parts = array_values( array_filter( explode( '/', $call ), 'strlen' ) );
		$base  = $call;
		if ( count( $parts ) > 1 ) {
			if ( in_array( end( $parts ), self::NO_COUNTRY_SUFFIXES, true ) ) {
				return 0;
			}
			$parts = array_values(
				array_filter(
					$parts,
					function ( $p ) {
						return ! in_array( $p, self::IGNORED_SUFFIXES, true ) && ! preg_match( '/^\d$/', $p );
					}
				)
			);
			if ( ! $parts ) {
				return 0;
			}
			$base = $parts[0];
			if ( isset( $data['calls'][ $base ] ) ) {
				return $data['calls'][ $base ];
			}
			// Két rész közül a rövidebb a prefix (DL/HA8AI, HA8AI/DL); egyezésnél az első.
			if ( count( $parts ) > 1 && strlen( $parts[1] ) < strlen( $parts[0] ) ) {
				$base = $parts[1];
			}
		}

		for ( $len = strlen( $base ); $len > 0; $len-- ) {
			$pfx = substr( $base, 0, $len );
			if ( isset( $data['prefixes'][ $pfx ] ) ) {
				return $data['prefixes'][ $pfx ];
			}
		}
		return 0;
	}

	/**
	 * Az ország neve a megadott nyelven. Fordítás híján angolul, szöveges kulcsnál maga a szöveg.
	 *
	 * @param int|string $key  A lookup() eredménye.
	 * @param string     $lang Nyelvkód.
	 */
	public static function name( $key, $lang ) {
		if ( ! is_int( $key ) ) {
			return (string) $key;
		}
		foreach ( array( $lang, 'en' ) as $l ) {
			$list = self::names( $l );
			if ( isset( $list[ $key ] ) ) {
				return $list[ $key ];
			}
		}
		return '';
	}

	/**
	 * Nevek ábécérendbe rendezése az adott nyelv szabályai szerint (intl Collator, ha elérhető).
	 */
	public static function sort_names( array $names, $lang ) {
		if ( class_exists( 'Collator' ) ) {
			$collator = new Collator( $lang );
			$collator->sort( $names );
			return $names;
		}
		usort(
			$names,
			function ( $a, $b ) {
				return strcasecmp( remove_accents( $a ), remove_accents( $b ) );
			}
		);
		return $names;
	}

	private static function names( $lang ) {
		if ( ! isset( self::$names[ $lang ] ) ) {
			$file                 = dirname( __DIR__ ) . '/languages/countries/' . $lang . '.php';
			$list                 = preg_match( '/^[a-z]{2,3}$/', $lang ) && is_readable( $file ) ? include $file : array();
			self::$names[ $lang ] = apply_filters( 'ham2k_adif_log_country_names', is_array( $list ) ? $list : array(), $lang );
		}
		return self::$names[ $lang ];
	}

	private static function data() {
		if ( null === self::$data ) {
			self::$data = include __DIR__ . '/dxcc-prefixes.php';
		}
		return self::$data;
	}

	/** Ugyanaz a normalizálás, mint a generátorban: ékezet nélkül, nagybetű, csak betű és szám. */
	private static function normalize( $s ) {
		$s = strtoupper( remove_accents( (string) $s ) );
		$s = str_replace( '&', ' AND ', $s );
		$s = preg_replace( '/\bSAINT\b|\bST\b\.?/', 'ST', $s );
		return preg_replace( '/[^A-Z0-9]/', '', $s );
	}
}

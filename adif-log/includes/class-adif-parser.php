<?php
/**
 * ADIF (.adi) fájl beolvasása és a táblázathoz szükséges összesítés elkészítése.
 */

defined( 'ABSPATH' ) || exit;

class Ham2K_ADIF_Parser {

	/**
	 * Fájl beolvasása és feldolgozása. Hiba esetén null.
	 */
	public static function parse_file( string $path ) {
		$content = @file_get_contents( $path ); // phpcs:ignore
		if ( false === $content ) {
			return null;
		}
		return self::parse( $content );
	}

	/**
	 * Nyers ADIF szöveg feldolgozása.
	 *
	 * @return array{header_text:string, header:array, records:array}
	 */
	public static function parse( string $content ) {
		$content = preg_replace( '/^\xEF\xBB\xBF/', '', (string) $content );
		$len     = strlen( $content );

		// Az ADIF szabvány szerint ha a fájl nem '<'-rel kezdődik, akkor van fejléc.
		$trimmed    = ltrim( $content );
		$in_header  = '' !== $trimmed && '<' !== $trimmed[0];
		$first_tag  = strpos( $content, '<' );
		$header_txt = $in_header ? trim( substr( $content, 0, false === $first_tag ? $len : $first_tag ) ) : '';

		$header  = array();
		$records = array();
		$current = array();
		$i       = 0;

		while ( $i < $len && false !== ( $lt = strpos( $content, '<', $i ) ) ) {
			$gt = strpos( $content, '>', $lt );
			if ( false === $gt ) {
				break;
			}
			$parts = explode( ':', substr( $content, $lt + 1, $gt - $lt - 1 ) );
			$name  = strtoupper( trim( $parts[0] ) );
			$i     = $gt + 1;

			if ( 'EOH' === $name ) {
				$in_header = false;
				$current   = array();
				continue;
			}
			if ( 'EOR' === $name ) {
				if ( $current ) {
					$records[] = $current;
				}
				$current = array();
				continue;
			}
			if ( count( $parts ) < 2 || ! ctype_digit( trim( $parts[1] ) ) ) {
				continue;
			}

			list( $value, $i ) = self::read_value( $content, $i, (int) trim( $parts[1] ) );

			if ( $in_header ) {
				$header[ $name ] = $value;
			} else {
				$current[ $name ] = $value;
			}
		}

		return array(
			'header_text' => $header_txt,
			'header'      => $header,
			'records'     => $records,
		);
	}

	/**
	 * Mező értékének kiolvasása. A hossz a szabvány szerint bájt, de egyes programok
	 * UTF-8 karakterben számolnak – ha a bájtos olvasás nem illeszkedik, karakteresen próbáljuk.
	 */
	private static function read_value( string $content, int $start, int $n ) {
		$value = substr( $content, $start, $n );
		$next  = $start + strlen( $value );

		if ( self::ends_cleanly( $content, $next ) && self::is_utf8( $value ) ) {
			return array( $value, $next );
		}
		if ( function_exists( 'mb_substr' ) ) {
			$alt      = mb_substr( substr( $content, $start, $n * 4 ), 0, $n, 'UTF-8' );
			$alt_next = $start + strlen( $alt );
			if ( self::ends_cleanly( $content, $alt_next ) ) {
				return array( $alt, $alt_next );
			}
		}
		return array( $value, $next );
	}

	private static function ends_cleanly( string $content, int $pos ) {
		$lt   = strpos( $content, '<', $pos );
		$rest = false === $lt ? substr( $content, $pos ) : substr( $content, $pos, $lt - $pos );
		return '' === trim( $rest );
	}

	private static function is_utf8( string $s ) {
		return function_exists( 'mb_check_encoding' ) ? mb_check_encoding( $s, 'UTF-8' ) : (bool) preg_match( '//u', $s );
	}

	/**
	 * A feldolgozott ADIF-ből a megjelenítéshez szükséges adatok.
	 */
	public static function summarize( array $adif ) {
		$records = self::sort_chronologically( $adif['records'] );
		$first   = $records ? $records[0] : array();

		$station = self::field( $first, array( 'STATION_CALLSIGN', 'OPERATOR' ) );
		if ( '' === $station && preg_match( '/ADIF for (\S+?):?\s/i', $adif['header_text'], $m ) ) {
			$station = $m[1];
		}

		$my_refs = array();
		$dates   = array();
		$bands   = array();
		$modes   = array();
		$p2p     = 0;
		$rows    = array();

		foreach ( $records as $idx => $r ) {
			$my_ref = self::field( $r, array( 'MY_POTA_REF' ) );
			if ( '' === $my_ref && 'POTA' === strtoupper( self::field( $r, array( 'MY_SIG' ) ) ) ) {
				$my_ref = self::field( $r, array( 'MY_SIG_INFO' ) );
			}
			if ( '' !== $my_ref ) {
				$my_refs[ $my_ref ] = true;
			}

			$date = self::format_date( self::field( $r, array( 'QSO_DATE' ) ) );
			if ( '' !== $date ) {
				$dates[ $date ] = true;
			}

			$band = strtolower( self::field( $r, array( 'BAND' ) ) );
			if ( '' !== $band ) {
				$bands[ $band ] = true;
			}

			$mode = strtoupper( self::field( $r, array( 'SUBMODE', 'MODE' ) ) );
			if ( '' !== $mode ) {
				$modes[ $mode ] = true;
			}

			// Park-to-park: a másik állomás is parkból adott.
			$their_ref = self::field( $r, array( 'POTA_REF' ) );
			if ( '' === $their_ref && 'POTA' === strtoupper( self::field( $r, array( 'SIG' ) ) ) ) {
				$their_ref = self::field( $r, array( 'SIG_INFO' ) );
			}
			if ( '' !== $their_ref ) {
				++$p2p;
			}

			$sent = self::field( $r, array( 'RST_SENT' ) );
			$rcvd = self::field( $r, array( 'RST_RCVD' ) );

			$rows[] = array(
				'num'  => $idx + 1,
				'date' => $date,
				'time' => self::format_time( self::field( $r, array( 'TIME_ON' ) ) ),
				'call' => strtoupper( self::field( $r, array( 'CALL' ) ) ),
				'freq' => self::format_freq( self::field( $r, array( 'FREQ' ) ), $band ),
				'mode' => $mode,
				'rst'  => ( '' === $sent && '' === $rcvd ) ? '' : ( $sent ?: '–' ) . ' / ' . ( $rcvd ?: '–' ),
				'p2p'  => str_replace( ',', ', ', $their_ref ),
			);
		}

		// Park neve a Ham2K fejlécből: "ADIF for HA8AI: POTA at HU-0119 Park neve on 2026-09-26"
		$park_name = '';
		if ( preg_match( '/\bat\s+(\S+)\s+(.+?)\s+on\s+\d{4}-\d{2}-\d{2}/u', $adif['header_text'], $m ) ) {
			$park_name = $m[2];
			if ( ! $my_refs ) {
				$my_refs[ $m[1] ] = true;
			}
		}

		$bands = array_keys( $bands );
		usort(
			$bands,
			function ( $a, $b ) {
				return self::band_meters( $b ) <=> self::band_meters( $a );
			}
		);

		$dates = array_keys( $dates );
		sort( $dates );

		// Azok az oszlopok, amelyekben legalább egy sorban van adat – a teljesen üreseket nem jelenítjük meg.
		$columns = array();
		foreach ( array( 'date', 'time', 'freq', 'mode', 'rst', 'p2p' ) as $col ) {
			$columns[ $col ] = false;
			foreach ( $rows as $row ) {
				if ( '' !== $row[ $col ] ) {
					$columns[ $col ] = true;
					break;
				}
			}
		}

		return array(
			'station'   => $station,
			'park_refs' => array_keys( $my_refs ),
			'park_name' => $park_name,
			'dates'     => $dates,
			'bands'     => $bands,
			'modes'     => array_keys( $modes ),
			'p2p'       => $p2p,
			'columns'   => $columns,
			'rows'      => $rows,
		);
	}

	/**
	 * QSO-k dátum és idő szerinti sorrendbe rendezése (pl. a LoTW a visszaigazolás sorrendjében adja őket).
	 * Azonos időpontnál az eredeti sorrend marad; dátum nélküli rekordok a végére kerülnek.
	 */
	private static function sort_chronologically( array $records ) {
		$keys = array();
		foreach ( array_values( $records ) as $i => $r ) {
			$date   = preg_replace( '/\D/', '', self::field( $r, array( 'QSO_DATE' ) ) );
			$time   = str_pad( preg_replace( '/\D/', '', self::field( $r, array( 'TIME_ON' ) ) ), 6, '0' );
			$keys[] = array( '' === $date ? '99999999' : $date, $time, $i, $r );
		}
		usort(
			$keys,
			function ( $a, $b ) {
				return array( $a[0], $a[1], $a[2] ) <=> array( $b[0], $b[1], $b[2] );
			}
		);
		return array_column( $keys, 3 );
	}

	private static function field( array $r, array $names ) {
		foreach ( $names as $name ) {
			if ( isset( $r[ $name ] ) && '' !== trim( $r[ $name ] ) ) {
				return trim( $r[ $name ] );
			}
		}
		return '';
	}

	private static function format_date( string $d ) {
		return preg_match( '/^(\d{4})(\d{2})(\d{2})$/', $d, $m ) ? "$m[1]-$m[2]-$m[3]" : $d;
	}

	private static function format_time( string $t ) {
		if ( preg_match( '/^(\d{2})(\d{2})(\d{2})?$/', $t, $m ) ) {
			return $m[1] . ':' . $m[2] . ( isset( $m[3] ) ? ':' . $m[3] : '' );
		}
		return $t;
	}

	private static function format_freq( string $f, string $band ) {
		if ( '' === $f || ! is_numeric( $f ) ) {
			return $band;
		}
		// Legalább 3 tizedes (kHz pontosság), de a pontosabb értéket nem kerekítjük le (pl. 7.1096).
		$exact = rtrim( rtrim( number_format( (float) $f, 6, '.', '' ), '0' ), '.' );
		$dot   = strpos( $exact, '.' );
		if ( false === $dot || strlen( $exact ) - $dot - 1 < 3 ) {
			$exact = number_format( (float) $f, 3, '.', '' );
		}
		return $exact . ' MHz';
	}

	private static function band_meters( string $band ) {
		if ( preg_match( '/^([\d.]+)\s*(mm|cm|m)$/', $band, $m ) ) {
			$mult = array(
				'm'  => 1,
				'cm' => 0.01,
				'mm' => 0.001,
			);
			return (float) $m[1] * $mult[ $m[2] ];
		}
		return 0;
	}
}

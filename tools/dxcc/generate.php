<?php
/**
 * Az országtáblák előállítása a bővítményhez (nem része a telepítő ZIP-nek).
 *
 * Használat (PHP intl bővítménnyel):
 *   curl -sSL -o cty.csv https://www.country-files.com/cty/cty.csv
 *   C:\xampp\php\php.exe tools\dxcc\generate.php cty.csv
 *
 * Bemenetek (ebben a mappában):
 *   manual.txt  – kézi fordítások: DXCC|en|hu|de|fr|es|it (nem önálló országok, pl. Szardínia, és felülírások)
 *   iso.txt     – DXCC-szám → ISO 3166 kód; ezek neve a Unicode CLDR-ből (intl) jön
 *   aliases.txt – a COUNTRY mezőben előforduló további angol nevek (pl. LoTW: FEDERAL REPUBLIC OF GERMANY)
 *
 * Kimenet:
 *   adif-log/languages/countries/{en,hu,de,fr,es,it}.php – DXCC-szám => név
 *   adif-log/includes/dxcc-prefixes.php                   – prefixek, egyedi hívójelek, nevek => DXCC-szám
 */

if ( ! class_exists( 'Locale' ) ) {
	fwrite( STDERR, "A PHP intl bővítménye szükséges.\n" );
	exit( 1 );
}
$cty = $argv[1] ?? '';
if ( ! is_readable( $cty ) ) {
	fwrite( STDERR, "Használat: php generate.php <cty.csv>\n" );
	exit( 1 );
}

$dir   = __DIR__;
$out   = dirname( $dir, 2 ) . '/adif-log';
$langs = array( 'en', 'hu', 'de', 'fr', 'es', 'it' );
$names = array_fill_keys( $langs, array() );

foreach ( file( "$dir/manual.txt", FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES ) as $line ) {
	$p = explode( '|', $line );
	$n = (int) array_shift( $p );
	foreach ( $langs as $i => $lg ) {
		$names[ $lg ][ $n ] = $p[ $i ];
	}
}
foreach ( explode( ';', trim( file_get_contents( "$dir/iso.txt" ) ) ) as $pair ) {
	list( $n, $iso ) = explode( ' ', trim( $pair ) );
	$n = (int) $n;
	if ( isset( $names['en'][ $n ] ) ) {
		continue;
	}
	foreach ( $langs as $lg ) {
		$names[ $lg ][ $n ] = Locale::getDisplayRegion( "-$iso", $lg );
	}
}

// Ugyanaz a normalizálás, mint a Ham2K_DXCC::normalize()-ban.
$norm = function ( $s ) {
	$s = strtoupper( iconv( 'UTF-8', 'ASCII//TRANSLIT', $s ) );
	$s = str_replace( '&', ' AND ', $s );
	$s = preg_replace( '/\bSAINT\b|\bST\b\.?/', 'ST', $s );
	return preg_replace( '/[^A-Z0-9]/', '', $s );
};

$prefixes = array();
$calls    = array();
$cnames   = array();
$missing  = array();
foreach ( $names['en'] as $k => $v ) {
	$cnames[ $norm( $v ) ] = $k;
}
foreach ( file( "$dir/aliases.txt", FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES ) as $line ) {
	list( $k, $v )         = explode( '|', $line );
	$cnames[ $norm( $v ) ] = (int) $k;
}
foreach ( file( $cty, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES ) as $line ) {
	$f                        = str_getcsv( $line );
	$n                        = (int) $f[2];
	$cnames[ $norm( $f[1] ) ] = $n;
	if ( ! isset( $names['en'][ $n ] ) ) {
		$missing[] = "$n {$f[1]}";
	}
	foreach ( preg_split( '/\s+/', trim( rtrim( $f[9], ';' ) ) ) as $tok ) {
		$tok = preg_replace( '/[\(\[<\{~].*$/', '', $tok ); // CQ/ITU zóna és egyéb módosítók le.
		if ( '' === $tok ) {
			continue;
		}
		if ( '=' === $tok[0] ) {
			$calls[ substr( $tok, 1 ) ] = $n;
		} else {
			$prefixes[ $tok ] = $n;
		}
	}
}
if ( $missing ) {
	fwrite( STDERR, 'Fordítás nélküli DXCC-entitások (manual.txt / iso.txt bővítendő): ' . implode( ', ', $missing ) . "\n" );
	exit( 1 );
}

$hdr    = "<?php\n/**\n * %s\n * Automatikusan generálva (tools/dxcc/generate.php) – kézzel ne szerkeszd.\n */\n\ndefined( 'ABSPATH' ) || exit;\n\nreturn ";
$labels = array(
	'en' => 'Angol',
	'hu' => 'Magyar',
	'de' => 'Német',
	'fr' => 'Francia',
	'es' => 'Spanyol',
	'it' => 'Olasz',
);
@mkdir( "$out/languages/countries", 0777, true );
foreach ( $langs as $lg ) {
	ksort( $names[ $lg ] );
	file_put_contents(
		"$out/languages/countries/$lg.php",
		sprintf( $hdr, $labels[ $lg ] . ' DXCC-entitásnevek (DXCC-szám => név). Forrás: Unicode CLDR + kézi fordítás.' ) . var_export( $names[ $lg ], true ) . ";\n"
	);
}
file_put_contents(
	"$out/includes/dxcc-prefixes.php",
	sprintf( $hdr, 'Hívójel-prefixek, egyedi hívójelek és angol országnevek => DXCC-szám. Forrás: AD1C Big CTY (country-files.com), ' . date( 'Y-m-d' ) . '.' )
	. var_export(
		array(
			'prefixes' => $prefixes,
			'calls'    => $calls,
			'names'    => $cnames,
		),
		true
	) . ";\n"
);
echo count( $names['en'] ) . ' entitás, ' . count( $prefixes ) . ' prefix, ' . count( $calls ) . " egyedi hívójel.\n";

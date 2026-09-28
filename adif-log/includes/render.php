<?php
/**
 * A napló táblázat HTML-je – a Gutenberg blokk és az Elementor widget is ezt használja.
 */

defined( 'ABSPATH' ) || exit;

/**
 * @param array  $args          attachmentId, title, location, showStats, showSearch, language.
 * @param string $wrapper_attrs A külső <div> attribútumai (már escape-elve).
 * @param bool   $is_editor     Szerkesztőben hibaüzenetet mutatunk, látogatóknak semmit.
 */
function ham2k_adif_log_render_html( array $args, $wrapper_attrs, $is_editor = false ) {
	$id   = isset( $args['attachmentId'] ) ? (int) $args['attachmentId'] : 0;
	$path = ( $id && 'attachment' === get_post_type( $id ) ) ? get_attached_file( $id ) : '';
	$adif = ( $path && is_readable( $path ) ) ? Ham2K_ADIF_Parser::parse_file( $path ) : null;

	if ( ! $adif || ! $adif['records'] ) {
		if ( ! $is_editor ) {
			return '';
		}
		// A hibaüzenet a szerkesztőnek szól, ezért az ő nyelvén jelenik meg.
		if ( ! $id ) {
			$msg = 'Select or upload an ADIF (.adi) file!';
		} elseif ( $adif ) {
			$msg = 'The selected file contains no QSO records.';
		} else {
			$msg = 'The ADIF file cannot be read. Please choose another file!';
		}
		return sprintf(
			'<div %s><p class="ham2k-adif-log__notice">%s</p></div>',
			$wrapper_attrs,
			esc_html( ham2k_adif_log_t( $msg, ham2k_adif_log_ui_lang() ) )
		);
	}

	$lang = ham2k_adif_log_resolve_lang( $args['language'] ?? '' );
	$t    = function ( $text ) use ( $lang ) {
		return ham2k_adif_log_t( $text, $lang );
	};

	$log  = Ham2K_ADIF_Parser::summarize( $adif );
	$cols = $log['columns'];

	$title = trim( (string) ( $args['title'] ?? '' ) );
	if ( '' === $title ) {
		$title = trim( sprintf( $log['park_refs'] ? $t( '%s POTA Log' ) : $t( '%s Log' ), $log['station'] ) );
	}

	$location = trim( (string) ( $args['location'] ?? '' ) );
	if ( '' === $location ) {
		$location = trim( implode( ', ', $log['park_refs'] ) . ' ' . $log['park_name'] );
	}

	$dates = $log['dates'];
	$date  = count( $dates ) > 1 ? reset( $dates ) . ' – ' . end( $dates ) : implode( '', $dates );

	$search_id = wp_unique_id( 'ham2k-adif-search-' );

	ob_start();
	?>
<div <?php echo $wrapper_attrs; // phpcs:ignore ?> lang="<?php echo esc_attr( $lang ); ?>">
	<h2 class="ham2k-adif-log__title"><?php echo esc_html( $title ); ?></h2>

	<?php if ( '' !== $location || '' !== $date ) : ?>
		<div class="ham2k-adif-log__subtitle">
			<?php if ( '' !== $location ) : ?>
				<strong><?php echo esc_html( $t( 'Location:' ) ); ?></strong> <?php echo esc_html( $location ); ?>
			<?php endif; ?>
			<?php if ( '' !== $location && '' !== $date ) : ?>
				<span class="ham2k-adif-log__sep">|</span>
			<?php endif; ?>
			<?php if ( '' !== $date ) : ?>
				<strong><?php echo esc_html( $t( 'Date:' ) ); ?></strong> <?php echo esc_html( $date ); ?>
			<?php endif; ?>
		</div>
	<?php endif; ?>

	<?php if ( ! empty( $args['showStats'] ) ) : ?>
		<div class="ham2k-adif-log__stats">
			<div><?php echo esc_html( sprintf( $t( 'Total QSOs: %s' ), count( $log['rows'] ) ) ); ?></div>
			<?php if ( $log['bands'] ) : ?>
				<div><?php echo esc_html( sprintf( count( $log['bands'] ) > 1 ? $t( 'Bands: %s' ) : $t( 'Band: %s' ), implode( ', ', $log['bands'] ) ) ); ?></div>
			<?php endif; ?>
			<?php if ( $log['modes'] ) : ?>
				<div><?php echo esc_html( sprintf( count( $log['modes'] ) > 1 ? $t( 'Modes: %s' ) : $t( 'Mode: %s' ), implode( ', ', $log['modes'] ) ) ); ?></div>
			<?php endif; ?>
			<?php if ( $log['p2p'] ) : ?>
				<div><?php echo esc_html( sprintf( $t( 'P2P: %s' ), $log['p2p'] ) ); ?></div>
			<?php endif; ?>
		</div>
	<?php endif; ?>

	<?php if ( ! empty( $args['showSearch'] ) ) : ?>
		<label class="screen-reader-text" for="<?php echo esc_attr( $search_id ); ?>"><?php echo esc_html( $t( 'Search the log' ) ); ?></label>
		<input type="search" id="<?php echo esc_attr( $search_id ); ?>" class="ham2k-adif-log__search" placeholder="<?php echo esc_attr( $t( 'Search by callsign, mode or park...' ) ); ?>" autocomplete="off">
	<?php endif; ?>

	<div class="ham2k-adif-log__table-wrapper">
		<table class="ham2k-adif-log__table<?php echo count( $dates ) > 1 ? ' is-multi-date' : ''; ?>">
			<thead>
				<tr>
					<th class="ham2k-adif-log__num">#</th>
					<?php if ( $cols['date'] ) : ?>
						<th class="ham2k-adif-log__date"><?php echo esc_html( $t( 'Date' ) ); ?></th>
					<?php endif; ?>
					<?php if ( $cols['time'] ) : ?>
						<th><?php echo esc_html( $t( 'Time (UTC)' ) ); ?></th>
					<?php endif; ?>
					<th><?php echo esc_html( $t( 'Callsign' ) ); ?></th>
					<?php if ( $cols['freq'] ) : ?>
						<th><?php echo esc_html( $t( 'Frequency' ) ); ?></th>
					<?php endif; ?>
					<?php if ( $cols['mode'] ) : ?>
						<th><?php echo esc_html( $t( 'Mode' ) ); ?></th>
					<?php endif; ?>
					<?php if ( $cols['rst'] ) : ?>
						<th><?php echo esc_html( $t( 'RST (S/R)' ) ); ?></th>
					<?php endif; ?>
					<?php if ( $cols['p2p'] ) : ?>
						<th><?php echo esc_html( $t( 'P2P' ) ); ?></th>
					<?php endif; ?>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $log['rows'] as $row ) : ?>
					<tr>
						<td class="ham2k-adif-log__num"><?php echo (int) $row['num']; ?></td>
						<?php if ( $cols['date'] ) : ?>
							<td class="ham2k-adif-log__date"><?php echo esc_html( $row['date'] ); ?></td>
						<?php endif; ?>
						<?php if ( $cols['time'] ) : ?>
							<td><?php echo esc_html( $row['time'] ); ?></td>
						<?php endif; ?>
						<td class="ham2k-adif-log__call"><?php echo esc_html( $row['call'] ); ?></td>
						<?php if ( $cols['freq'] ) : ?>
							<td><?php echo esc_html( $row['freq'] ); ?></td>
						<?php endif; ?>
						<?php if ( $cols['mode'] ) : ?>
							<td><?php echo esc_html( $row['mode'] ); ?></td>
						<?php endif; ?>
						<?php if ( $cols['rst'] ) : ?>
							<td><?php echo esc_html( $row['rst'] ); ?></td>
						<?php endif; ?>
						<?php if ( $cols['p2p'] ) : ?>
							<td>
								<?php if ( '' !== $row['p2p'] ) : ?>
									<span class="ham2k-adif-log__badge"><?php echo esc_html( $row['p2p'] ); ?></span>
								<?php endif; ?>
							</td>
						<?php endif; ?>
					</tr>
				<?php endforeach; ?>
				<tr class="ham2k-adif-log__empty" hidden>
					<td colspan="<?php echo (int) ( 2 + count( array_filter( $cols ) ) ); ?>"><?php echo esc_html( $t( 'No results.' ) ); ?></td>
				</tr>
			</tbody>
		</table>
	</div>
</div>
	<?php
	return ob_get_clean();
}

<?php
/**
 * Elementor widgetek – ugyanazt a táblázatot rajzolják ki, mint a Gutenberg blokkok.
 * ADIF POTA log (ham2k_adif_log) és ADIF Contest log (ham2k_adif_contest_log).
 */

defined( 'ABSPATH' ) || exit;

class Ham2K_ADIF_Log_Elementor_Widget extends \Elementor\Widget_Base {

	/** 'pota' vagy 'contest' – a renderelőnek átadott változat. */
	protected function variant() {
		return 'pota';
	}

	public function get_name() {
		return 'ham2k_adif_log';
	}

	public function get_title() {
		return 'ADIF POTA log';
	}

	public function get_icon() {
		return 'eicon-table';
	}

	public function get_categories() {
		return array( 'general' );
	}

	public function get_keywords() {
		return array( 'adif', 'log', 'qso', 'pota', 'lotw', 'ham2k', 'napló' );
	}

	public function get_style_depends() {
		return array( 'ham2k-adif-log' );
	}

	public function get_script_depends() {
		return array( 'ham2k-adif-log-view' );
	}

	protected function register_controls() {
		// A panel feliratai a szerkesztő felhasználó nyelvén.
		$t = function ( $text ) {
			return ham2k_adif_log_t( $text, ham2k_adif_log_ui_lang() );
		};

		$this->start_controls_section(
			'section_file',
			array(
				'label' => $t( 'ADIF file' ),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			)
		);

		$this->add_control(
			'adif_file',
			array(
				'label'       => $t( 'File (.adi / .adif)' ),
				'type'        => \Elementor\Controls_Manager::MEDIA,
				'media_types' => array( 'text/plain' ),
				'description' => $t( 'Upload an .adi / .adif file or choose one from the media library.' ),
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_display',
			array(
				'label' => $t( 'Display' ),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			)
		);

		$this->add_control(
			'language',
			array(
				'label'   => $t( 'Language' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'options' => array( '' => $t( 'Site language (automatic)' ) ) + ham2k_adif_log_languages(),
				'default' => '',
			)
		);

		$this->add_control(
			'title',
			array(
				'label'       => $t( 'Title' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'placeholder' => $t( 'Automatic' ),
				'description' => $t( 'Leave empty to generate it automatically from the file.' ),
			)
		);

		$this->add_control(
			'location',
			array(
				'label'       => $t( 'Location' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'placeholder' => $t( 'Automatic' ),
				'description' => $t( 'Leave empty to generate it automatically from the file.' ),
			)
		);

		$this->add_control(
			'show_stats',
			array(
				'label'        => $t( 'Summary bar (QSO count, bands, modes)' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'label_on'     => $t( 'Yes' ),
				'label_off'    => $t( 'No' ),
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);

		if ( 'contest' === $this->variant() ) {
			$this->add_control(
				'show_countries',
				array(
					'label'        => $t( 'Country summary bar' ),
					'type'         => \Elementor\Controls_Manager::SWITCHER,
					'label_on'     => $t( 'Yes' ),
					'label_off'    => $t( 'No' ),
					'return_value' => 'yes',
					'default'      => 'yes',
				)
			);
		}

		$this->add_control(
			'show_search',
			array(
				'label'        => $t( 'Search field' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'label_on'     => $t( 'Yes' ),
				'label_off'    => $t( 'No' ),
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);

		$this->end_controls_section();
	}

	protected function render() {
		$s    = $this->get_settings_for_display();
		$file = isset( $s['adif_file'] ) && is_array( $s['adif_file'] ) ? $s['adif_file'] : array();

		echo ham2k_adif_log_render_html( // phpcs:ignore
			array(
				'attachmentId'  => isset( $file['id'] ) ? (int) $file['id'] : 0,
				'title'         => $s['title'] ?? '',
				'location'      => $s['location'] ?? '',
				'showStats'     => 'yes' === ( $s['show_stats'] ?? '' ),
				'showSearch'    => 'yes' === ( $s['show_search'] ?? '' ),
				'language'      => $s['language'] ?? '',
				'variant'       => $this->variant(),
				'showCountries' => 'yes' === ( $s['show_countries'] ?? 'yes' ),
			),
			'contest' === $this->variant() ? 'class="ham2k-adif-log ham2k-adif-log--contest"' : 'class="ham2k-adif-log"',
			\Elementor\Plugin::$instance->editor->is_edit_mode()
		);
	}
}

/**
 * ADIF Contest log: P2P helyett Ország oszlop és lenyíló országösszesítő.
 */
class Ham2K_ADIF_Contest_Log_Elementor_Widget extends Ham2K_ADIF_Log_Elementor_Widget {

	protected function variant() {
		return 'contest';
	}

	public function get_name() {
		return 'ham2k_adif_contest_log';
	}

	public function get_title() {
		return 'ADIF Contest log';
	}

	public function get_keywords() {
		return array( 'adif', 'log', 'qso', 'contest', 'dxcc', 'country', 'verseny', 'napló' );
	}
}

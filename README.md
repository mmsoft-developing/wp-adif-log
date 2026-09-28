# ADIF Log – WordPress plugin

Displays a searchable QSO log table from an ADIF (`.adi` / `.adif`) file uploaded to the WordPress media library. Works as a **Gutenberg block** and as an **Elementor widget** (both called “ADIF log”).

Made by HA8AI for POTA activations, but works with any ADIF export (Ham2K Portable Logger, LoTW reports, etc.).

## Features

- Table columns: `# · Date · Time (UTC) · Callsign · Frequency · Mode · RST (S/R) · P2P`
  - columns that are empty in the whole file are hidden automatically
  - QSOs are always sorted by date and time
  - park-to-park contacts show the other park reference as a badge (e.g. `FR-2795`)
- Automatic title (“HA8AI POTA Log” / “HA8AI Log”) and location (park reference and name) from the ADIF file – both can be overridden
- Summary bar: QSO count, bands, modes (optional)
- Instant search field (optional)
- Compact mobile layout
- Interface languages: English, Hungarian, German, French, Spanish, Italian – selectable per block/widget, defaults to the site language

## Requirements

- WordPress 6.3 or newer
- PHP 7.4 or newer
- Elementor (optional, only for the widget)

## Installation

1. Download `adif-log.zip` from the [latest release](https://github.com/mmsoft-developing/wp-adif-log/releases/latest).
2. In WordPress: **Plugins → Add New → Upload Plugin**, choose the ZIP, then activate.

## Usage

1. Upload your `.adi` / `.adif` file to the media library.
2. Add the **ADIF log** block (Gutenberg) or widget (Elementor) to a page.
3. Select the file. Optionally set the title, location, language, and turn the summary bar or the search field on/off.

## License

[GPL-2.0-or-later](LICENSE)

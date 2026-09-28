( function ( wp ) {
	var el = wp.element.createElement;
	var Fragment = wp.element.Fragment;
	var be = wp.blockEditor;
	var c = wp.components;
	var ServerSideRender = wp.serverSideRender;

	// A PHP adja át a felhasználó nyelvén (adif-log.php).
	var config = window.ham2kAdifLog || { i18n: {}, languages: { en: 'English' } };
	function t( text ) {
		return config.i18n[ text ] || text;
	}

	var languageOptions = [ { value: '', label: t( 'Site language (automatic)' ) } ].concat(
		Object.keys( config.languages ).map( function ( code ) {
			return { value: code, label: config.languages[ code ] };
		} )
	);

	// A böngészők az .adi fájlokat általában text/plain-ként kezelik (a bővítmény is így regisztrálja).
	var ALLOWED_TYPES = [ 'text/plain' ];
	var ACCEPT = '.adi,.adif,text/plain';

	function fileLabel( media ) {
		if ( media.filename ) {
			return media.filename;
		}
		if ( media.url ) {
			return media.url.split( '/' ).pop();
		}
		return media.title || '#' + media.id;
	}

	wp.blocks.registerBlockType( 'ham2k/adif-log', {
		edit: function ( props ) {
			var a = props.attributes;
			var setAttributes = props.setAttributes;
			var blockProps = be.useBlockProps();

			function onSelect( media ) {
				if ( media && media.id ) {
					setAttributes( { attachmentId: media.id, fileName: fileLabel( media ) } );
				}
			}

			var inspector = el(
				be.InspectorControls,
				null,
				el(
					c.PanelBody,
					{ title: t( 'ADIF file' ), initialOpen: true },
					a.attachmentId
						? el( 'p', null, el( 'strong', null, a.fileName || '#' + a.attachmentId ) )
						: el( 'p', null, t( 'No file selected.' ) ),
					el(
						be.MediaUploadCheck,
						null,
						el( be.MediaUpload, {
							onSelect: onSelect,
							allowedTypes: ALLOWED_TYPES,
							value: a.attachmentId || undefined,
							render: function ( obj ) {
								return el(
									c.Button,
									{ variant: 'secondary', onClick: obj.open },
									a.attachmentId ? t( 'Replace file' ) : t( 'Select / upload file' )
								);
							},
						} )
					),
					a.attachmentId
						? el(
								c.Button,
								{
									variant: 'link',
									isDestructive: true,
									style: { marginTop: '8px', display: 'block' },
									onClick: function () {
										setAttributes( { attachmentId: 0, fileName: '' } );
									},
								},
								t( 'Remove file' )
						  )
						: null
				),
				el(
					c.PanelBody,
					{ title: t( 'Display' ), initialOpen: true },
					el( c.SelectControl, {
						label: t( 'Language' ),
						value: a.language,
						options: languageOptions,
						onChange: function ( v ) {
							setAttributes( { language: v } );
						},
					} ),
					el( c.TextControl, {
						label: t( 'Title' ),
						help: t( 'Leave empty to generate it automatically from the file.' ),
						value: a.title,
						onChange: function ( v ) {
							setAttributes( { title: v } );
						},
					} ),
					el( c.TextControl, {
						label: t( 'Location' ),
						help: t( 'Leave empty to generate it automatically from the file.' ),
						value: a.location,
						onChange: function ( v ) {
							setAttributes( { location: v } );
						},
					} ),
					el( c.ToggleControl, {
						label: t( 'Summary bar (QSO count, bands, modes)' ),
						checked: a.showStats,
						onChange: function ( v ) {
							setAttributes( { showStats: v } );
						},
					} ),
					el( c.ToggleControl, {
						label: t( 'Search field' ),
						checked: a.showSearch,
						onChange: function ( v ) {
							setAttributes( { showSearch: v } );
						},
					} )
				)
			);

			var body = a.attachmentId
				? el( ServerSideRender, { block: 'ham2k/adif-log', attributes: a } )
				: el( be.MediaPlaceholder, {
						icon: 'list-view',
						labels: {
							title: 'ADIF log',
							instructions: t( 'Upload an .adi / .adif file or choose one from the media library.' ),
						},
						onSelect: onSelect,
						accept: ACCEPT,
						allowedTypes: ALLOWED_TYPES,
						multiple: false,
				  } );

			return el( Fragment, null, inspector, el( 'div', blockProps, body ) );
		},

		// Dinamikus blokk: a HTML-t a render.php állítja elő.
		save: function () {
			return null;
		},
	} );
} )( window.wp );

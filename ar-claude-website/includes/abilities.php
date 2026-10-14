<?php
/**
 * Die Werkzeuge, die Claude sieht (WordPress Abilities API → MCP).
 * Name, Beschreibung und Schema sind das, woran Claude erkennt, wann es welches Werkzeug nutzt
 * und welche Daten Pflicht sind.
 */

defined( 'ABSPATH' ) || exit;

function arcp_ability_names() {
	return apply_filters( 'arcp_ability_names', array(
		ARCP_NS . '/projekte-auflisten',
		ARCP_NS . '/seiten-auflisten',
		ARCP_NS . '/inhalt-lesen',
		ARCP_NS . '/medien-suchen',
		ARCP_NS . '/bild-hochladen',
		ARCP_NS . '/projekt-anlegen',
		ARCP_NS . '/projekt-aendern',
		ARCP_NS . '/inhalt-aendern',
		ARCP_NS . '/aenderungen-auflisten',
		ARCP_NS . '/vorschlag-zuruecknehmen',
		ARCP_NS . '/vorschlag-freigeben',
	) );
}

function arcp_can_use() {
	return current_user_can( 'edit_posts' ) && current_user_can( 'upload_files' );
}

add_action(
	'wp_abilities_api_categories_init',
	function () {
		if ( function_exists( 'wp_register_ability_category' ) ) {
			wp_register_ability_category(
				ARCP_NS,
				array(
					'label'       => 'A/R Website',
					'description' => 'Projekte und Seiten der Website von A/R Architekten pflegen.',
				)
			);
		}
	}
);

add_action( 'wp_abilities_api_init', 'arcp_register_abilities' );

function arcp_register_abilities() {
	if ( ! function_exists( 'wp_register_ability' ) ) {
		return;
	}

	$s      = arcp_settings();
	$common = array(
		'category'            => ARCP_NS,
		'permission_callback' => 'arcp_can_use',
	);
	$ro     = array( 'annotations' => array( 'readonly' => true, 'destructive' => false, 'idempotent' => true ) );
	$rw     = array( 'annotations' => array( 'readonly' => false, 'destructive' => false, 'idempotent' => false ) );

	$notiz = array(
		'type'        => 'string',
		'description' => 'Ein Satz für die Person, die freigibt: was wurde warum geändert.',
	);
	$abschnitte = array(
		'type'        => 'array',
		'description' => 'Bildabschnitte unter der Beschreibung, z. B. {"titel":"Diagramm","bild_ids":[…]} und {"titel":"Pläne","bild_ids":[…]}. Je zwei Bilder pro Zeile.',
		'items'       => array(
			'type'                 => 'object',
			'required'             => array( 'titel', 'bild_ids' ),
			'additionalProperties' => false,
			'properties'           => array(
				'titel'    => array( 'type' => 'string', 'minLength' => 1 ),
				'bild_ids' => array( 'type' => 'array', 'minItems' => 1, 'items' => array( 'type' => 'integer' ) ),
			),
		),
	);

	/* ---------------- Lesen ---------------- */

	wp_register_ability(
		ARCP_NS . '/projekte-auflisten',
		$common + array(
			'label'            => 'Projekte auflisten',
			'description'      => 'Listet die Projekte (Referenzen) der Website mit ID und Stammdaten. Nutze dies, um die ID eines bestehenden Projekts zu finden, bevor du es liest oder änderst, und um Dubletten vor dem Anlegen zu vermeiden.',
			'input_schema'     => array(
				'type'                 => 'object',
				'additionalProperties' => false,
				'properties'           => array(
					'suche'  => array( 'type' => 'string', 'description' => 'Suchbegriff im Titel (optional).' ),
					'anzahl' => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 50 ),
				),
			),
			'execute_callback' => 'arcp_exec_projekte_auflisten',
			'meta'             => $ro,
		)
	);

	wp_register_ability(
		ARCP_NS . '/seiten-auflisten',
		$common + array(
			'label'            => 'Seiten auflisten',
			'description'      => 'Listet die normalen Seiten der Website (Startseite, „A/R Architekten“, Leistungsseiten, Kontakt …) mit ID. Die Startseite ist mit ist_startseite markiert.',
			'input_schema'     => array(
				'type'                 => 'object',
				'additionalProperties' => false,
				'properties'           => array(
					'suche' => array( 'type' => 'string', 'description' => 'Suchbegriff im Titel (optional).' ),
				),
			),
			'execute_callback' => 'arcp_exec_seiten_auflisten',
			'meta'             => $ro,
		)
	);

	wp_register_ability(
		ARCP_NS . '/inhalt-lesen',
		$common + array(
			'label'            => 'Inhalt lesen',
			'description'      => 'Liest eine Seite oder ein Projekt und listet alle änderbaren Elemente (Texte, Textblöcke, Bilder) mit element_id und Kontext. Bei Projekten zusätzlich Stammdaten, Titelbild und Slider-Galerie. Zeigt den Stand inklusive eines evtl. offenen Änderungsvorschlags. Immer vor inhalt-aendern aufrufen und die zurückgegebene „version“ mitschicken.',
			'input_schema'     => array(
				'type'                 => 'object',
				'required'             => array( 'id' ),
				'additionalProperties' => false,
				'properties'           => array(
					'id' => array( 'type' => 'integer', 'description' => 'ID der Seite oder des Projekts.' ),
				),
			),
			'execute_callback' => 'arcp_exec_inhalt_lesen',
			'meta'             => $ro,
		)
	);

	wp_register_ability(
		ARCP_NS . '/medien-suchen',
		$common + array(
			'label'            => 'Bilder in der Mediathek suchen',
			'description'      => 'Sucht Bilder in der WordPress-Mediathek nach Titel/Dateiname (neueste zuerst). Nutze dies, wenn das Büro Bilder bereits hochgeladen hat, um ihre IDs zu finden.',
			'input_schema'     => array(
				'type'                 => 'object',
				'additionalProperties' => false,
				'properties'           => array(
					'suche'  => array( 'type' => 'string', 'description' => 'Suchbegriff, z. B. „gottmadingen“. Leer = neueste Bilder.' ),
					'anzahl' => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 50, 'default' => 20 ),
				),
			),
			'execute_callback' => 'arcp_exec_medien_suchen',
			'meta'             => $ro,
		)
	);

	wp_register_ability(
		ARCP_NS . '/aenderungen-auflisten',
		$common + array(
			'label'            => 'Änderungsvorschläge auflisten',
			'description'      => 'Listet Änderungsvorschläge und neue Projekte, die auf Freigabe warten (oder bereits erledigte), mit Vorschau- und Freigabe-Link.',
			'input_schema'     => array(
				'type'                 => 'object',
				'additionalProperties' => false,
				'properties'           => array(
					'status' => array( 'type' => 'string', 'enum' => array( 'offen', 'freigegeben', 'verworfen' ), 'default' => 'offen' ),
				),
			),
			'execute_callback' => 'arcp_exec_aenderungen_auflisten',
			'meta'             => $ro,
		)
	);

	/* ---------------- Schreiben ---------------- */

	wp_register_ability(
		ARCP_NS . '/bild-hochladen',
		$common + array(
			'label'            => 'Bild per Link hochladen',
			'description'      => 'Lädt ein Bild von einer öffentlichen https-Adresse (z. B. Dropbox-, Drive- oder SharePoint-Freigabelink mit direktem Download) in die Mediathek und gibt die Bild-ID zurück. Die Mediathek ist nicht öffentlich sichtbar; das Bild erscheint erst auf der Website, wenn es in einem freigegebenen Inhalt verwendet wird.',
			'input_schema'     => array(
				'type'                 => 'object',
				'required'             => array( 'url', 'titel' ),
				'additionalProperties' => false,
				'properties'           => array(
					'url'      => array( 'type' => 'string', 'format' => 'uri', 'description' => 'Direkter https-Link auf eine JPG-, PNG- oder WebP-Datei.' ),
					'titel'    => array( 'type' => 'string', 'description' => 'Sprechender Titel, z. B. „Tribüne Konstanz – Ansicht Süd“.' ),
					'alt_text' => array( 'type' => 'string', 'description' => 'Bildbeschreibung für Barrierefreiheit und SEO.' ),
				),
			),
			'execute_callback' => 'arcp_exec_bild_hochladen',
			'meta'             => $rw,
		)
	);

	wp_register_ability(
		ARCP_NS . '/projekt-anlegen',
		$common + array(
			'label'            => 'Neues Projekt anlegen',
			'description'      => 'Legt ein neues Projekt (Referenz) mit allen Pflicht-Stammdaten an. Das Projekt ist danach NICHT öffentlich, sondern wartet auf Freigabe im Chat. Frage fehlende Pflichtangaben beim Nutzer nach, statt sie zu erfinden. Bilder müssen vorher in der Mediathek sein (medien-suchen, upload-link oder bild-hochladen).',
			'input_schema'     => array(
				'type'                 => 'object',
				'required'             => array( 'titel', 'jahr', 'standort', 'kategorie', 'art', 'stand', 'beschreibung', 'titelbild_id' ),
				'additionalProperties' => false,
				'properties'           => array(
					'titel'        => array( 'type' => 'string', 'minLength' => 3, 'description' => 'Projekttitel, z. B. „Stadtvilla Radolfzell“.' ),
					'jahr'         => array( 'type' => 'integer', 'minimum' => 1950, 'maximum' => 2100, 'description' => 'Jahr (Fertigstellung bzw. Entwurfsjahr).' ),
					'standort'     => array( 'type' => 'string', 'minLength' => 2, 'description' => 'Ort, z. B. „Konstanz“.' ),
					'kategorie'    => array( 'type' => 'string', 'enum' => array_values( $s['kategorien'] ) ),
					'art'          => array( 'type' => 'string', 'enum' => array_values( $s['arten'] ), 'description' => 'Art des Projekts.' ),
					'art_zusatz'   => array( 'type' => 'string', 'description' => 'Optionaler Zusatz, z. B. „KfW 55 EE“ → wird zu „Sanierung, KfW 55 EE“.' ),
					'stand'        => array( 'type' => 'string', 'enum' => array_values( $s['staende'] ), 'description' => 'Aktueller Stand.' ),
					'beschreibung' => array( 'type' => 'string', 'minLength' => 40, 'description' => 'Projektbeschreibung, 1–3 Absätze, Absätze durch Leerzeile trennen.' ),
					'titelbild_id' => array( 'type' => 'integer', 'description' => 'Mediathek-ID des Titelbilds (Vorschaubild in Listen).' ),
					'galerie_ids'  => array( 'type' => 'array', 'items' => array( 'type' => 'integer' ), 'description' => 'Bilder für den großen Slider oben auf der Projektseite. Leer = nur Titelbild.' ),
					'abschnitte'   => $abschnitte,
					'notiz'        => $notiz,
				),
			),
			'execute_callback' => 'arcp_exec_projekt_anlegen',
			'meta'             => $rw,
		)
	);

	wp_register_ability(
		ARCP_NS . '/projekt-aendern',
		$common + array(
			'label'            => 'Projekt-Stammdaten ändern',
			'description'      => 'Ändert Titel, Stammdaten (Jahr, Standort, Kategorie, Art, Stand), Titelbild oder Slider-Galerie eines bestehenden Projekts. Nur angegebene Felder werden geändert. Bei veröffentlichten Projekten entsteht ein Änderungsvorschlag mit Vorschau – live geht erst nach Freigabe. Texte und Bilder im Seiteninhalt (Beschreibung, Diagramm, Pläne) änderst du mit inhalt-aendern.',
			'input_schema'     => array(
				'type'                 => 'object',
				'required'             => array( 'id' ),
				'additionalProperties' => false,
				'properties'           => array(
					'id'           => array( 'type' => 'integer' ),
					'titel'        => array( 'type' => 'string', 'minLength' => 3 ),
					'jahr'         => array( 'type' => 'integer', 'minimum' => 1950, 'maximum' => 2100 ),
					'standort'     => array( 'type' => 'string', 'minLength' => 2 ),
					'kategorie'    => array( 'type' => 'string', 'enum' => array_values( $s['kategorien'] ) ),
					'art'          => array( 'type' => 'string', 'enum' => array_values( $s['arten'] ) ),
					'art_zusatz'   => array( 'type' => 'string', 'description' => 'Nur zusammen mit „art“.' ),
					'stand'        => array( 'type' => 'string', 'enum' => array_values( $s['staende'] ) ),
					'titelbild_id' => array( 'type' => 'integer' ),
					'galerie_ids'  => array( 'type' => 'array', 'minItems' => 1, 'items' => array( 'type' => 'integer' ), 'description' => 'Ersetzt die komplette Slider-Galerie in dieser Reihenfolge.' ),
					'notiz'        => $notiz,
				),
			),
			'execute_callback' => 'arcp_exec_projekt_aendern',
			'meta'             => $rw,
		)
	);

	wp_register_ability(
		ARCP_NS . '/inhalt-aendern',
		$common + array(
			'label'            => 'Texte und Bilder ändern',
			'description'      => 'Ändert einzelne Texte, Textblöcke oder Bilder auf einer Seite (z. B. Startseite) oder in einem Projekt. Vorher inhalt-lesen aufrufen, element_id und version von dort übernehmen. Pro Element: typ text/textblock → „text“ (Absätze durch Leerzeile), bei Textblöcken mit Formatierung/Links stattdessen „html“; typ bild → „bild_id“; typ bilder → „bild_ids“. Layout und Gestaltung bleiben unverändert. Bei veröffentlichten Inhalten entsteht ein Änderungsvorschlag mit Vorschau – live geht erst nach Freigabe.',
			'input_schema'     => array(
				'type'                 => 'object',
				'required'             => array( 'id', 'version', 'aenderungen' ),
				'additionalProperties' => false,
				'properties'           => array(
					'id'          => array( 'type' => 'integer' ),
					'version'     => array( 'type' => 'string', 'description' => '„version“ aus inhalt-lesen.' ),
					'aenderungen' => array(
						'type'     => 'array',
						'minItems' => 1,
						'items'    => array(
							'type'                 => 'object',
							'required'             => array( 'element_id' ),
							'additionalProperties' => false,
							'properties'           => array(
								'element_id' => array( 'type' => 'string' ),
								'text'       => array( 'type' => 'string' ),
								'html'       => array( 'type' => 'string' ),
								'bild_id'    => array( 'type' => 'integer' ),
								'bild_ids'   => array( 'type' => 'array', 'items' => array( 'type' => 'integer' ) ),
							),
						),
					),
					'notiz'       => $notiz,
				),
			),
			'execute_callback' => 'arcp_exec_inhalt_aendern',
			'meta'             => $rw,
		)
	);

	wp_register_ability(
		ARCP_NS . '/vorschlag-zuruecknehmen',
		$common + array(
			'label'            => 'Eigenen Vorschlag zurücknehmen',
			'description'      => 'Nimmt einen noch offenen Änderungsvorschlag oder ein noch nicht freigegebenes neues Projekt zurück, z. B. wenn der Nutzer „verwirf das“ sagt. Live-Inhalte bleiben unverändert.',
			'input_schema'     => array(
				'type'                 => 'object',
				'required'             => array( 'aenderung_id' ),
				'additionalProperties' => false,
				'properties'           => array(
					'aenderung_id' => array( 'type' => 'integer' ),
				),
			),
			'execute_callback' => 'arcp_exec_vorschlag_zuruecknehmen',
			'meta'             => $rw,
		)
	);

	wp_register_ability(
		ARCP_NS . '/vorschlag-freigeben',
		$common + array(
			'label'            => 'Vorschlag freigeben (live schalten)',
			'description'      => 'Schaltet einen Änderungsvorschlag bzw. ein neues Projekt LIVE. NUR aufrufen, wenn der Nutzer in seiner letzten Nachricht ausdrücklich zugestimmt hat (z. B. „ja, freigeben“). Nie von dir aus, nie zusammen mit dem Anlegen. Danach den Live-Link nennen.',
			'input_schema'     => array(
				'type'                 => 'object',
				'required'             => array( 'aenderung_id' ),
				'additionalProperties' => false,
				'properties'           => array(
					'aenderung_id'   => array( 'type' => 'integer' ),
					'trotz_konflikt' => array( 'type' => 'boolean', 'default' => false, 'description' => 'Nur true, wenn der Nutzer nach dem Konflikt-Hinweis ausdrücklich trotzdem freigeben will.' ),
				),
			),
			'execute_callback' => 'arcp_exec_vorschlag_freigeben',
			'meta'             => array( 'annotations' => array( 'readonly' => false, 'destructive' => true, 'idempotent' => false ) ),
		)
	);
}

/* ===========================================================================
 * Ausführung
 * ========================================================================= */

function arcp_exec_projekte_auflisten( $input = array() ) {
	$input = (array) $input;
	$q = get_posts(
		array(
			'post_type'      => ARCP_POST_TYPE,
			'post_status'    => array( 'publish', 'pending', 'draft', 'future', 'private' ),
			'posts_per_page' => (int) ( $input['anzahl'] ?? 50 ),
			's'              => (string) ( $input['suche'] ?? '' ),
			'orderby'        => 'date',
			'order'          => 'DESC',
		)
	);
	$out = array();
	foreach ( $q as $p ) {
		$row = array( 'id' => $p->ID, 'titel' => get_the_title( $p ), 'status' => $p->post_status );
		foreach ( arcp_field_map() as $field => $key ) {
			$row[ $field ] = (string) get_post_meta( $p->ID, $key, true );
		}
		$row['offener_vorschlag'] = (bool) arcp_open_change_id( $p->ID );
		$out[]                    = $row;
	}
	return array( 'projekte' => $out );
}

function arcp_exec_seiten_auflisten( $input = array() ) {
	$input = (array) $input;
	$front = (int) get_option( 'page_on_front' );
	$q     = get_posts(
		array(
			'post_type'      => 'page',
			'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
			'posts_per_page' => 100,
			's'              => (string) ( $input['suche'] ?? '' ),
			'orderby'        => 'menu_order title',
			'order'          => 'ASC',
		)
	);
	$out = array();
	foreach ( $q as $p ) {
		$out[] = array(
			'id'                => $p->ID,
			'titel'             => get_the_title( $p ),
			'status'            => $p->post_status,
			'link'              => get_permalink( $p ),
			'ist_startseite'    => $p->ID === $front,
			'offener_vorschlag' => (bool) arcp_open_change_id( $p->ID ),
		);
	}
	return array( 'seiten' => $out );
}

function arcp_exec_inhalt_lesen( $input ) {
	$post = arcp_get_editable( $input['id'] );
	if ( is_wp_error( $post ) ) {
		return $post;
	}
	$content  = (string) arcp_working( $post, 'post_content' );
	$elements = arcp_wpb_elements( $content );
	foreach ( $elements as &$el ) {
		if ( 'bild' === $el['typ'] ) {
			$el['bild'] = arcp_media_item( $el['bild_id'] );
		} elseif ( 'bilder' === $el['typ'] ) {
			$el['bilder'] = array_map( 'arcp_media_item', $el['bild_ids'] );
		}
	}
	unset( $el );

	$cid = arcp_open_change_id( $post->ID );
	$out = array(
		'id'                => $post->ID,
		'art'               => ARCP_POST_TYPE === $post->post_type ? 'projekt' : 'seite',
		'titel'             => (string) arcp_working( $post, 'post_title' ),
		'status'            => $post->post_status,
		'live_link'         => 'publish' === $post->post_status ? get_permalink( $post ) : null,
		'offener_vorschlag' => $cid ? arcp_change_summary( $cid ) : null,
		'version'           => md5( $content ),
		'elemente'          => $elements,
	);
	if ( ! $elements && '' !== trim( $content ) ) {
		$out['hinweis'] = 'Dieser Inhalt ist nicht mit WPBakery-Bausteinen aufgebaut; Texte/Bilder können hier nicht gezielt geändert werden.';
	}

	if ( ARCP_POST_TYPE === $post->post_type ) {
		$stamm = array();
		foreach ( arcp_field_map() as $field => $key ) {
			$stamm[ $field ] = (string) arcp_working_meta( $post->ID, $key );
		}
		$thumb             = (int) arcp_working_meta( $post->ID, '_thumbnail_id' );
		$out['stammdaten'] = $stamm;
		$out['titelbild']  = $thumb ? arcp_media_item( $thumb ) : null;
		$out['galerie']    = array_map( 'arcp_media_item', arcp_gallery_ids_from_options( arcp_working_meta( $post->ID, ARCP_THEME_META ) ) );
	}
	return $out;
}

function arcp_exec_medien_suchen( $input = array() ) {
	$input = (array) $input;
	$q = get_posts(
		array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'post_mime_type' => 'image',
			'posts_per_page' => (int) ( $input['anzahl'] ?? 20 ),
			's'              => (string) ( $input['suche'] ?? '' ),
			'orderby'        => 'date',
			'order'          => 'DESC',
		)
	);
	return array( 'bilder' => array_map( 'arcp_media_item', wp_list_pluck( $q, 'ID' ) ) );
}

function arcp_exec_aenderungen_auflisten( $input = array() ) {
	$input = (array) $input;
	$ids = get_posts(
		array(
			'post_type'      => ARCP_CHANGE_TYPE,
			'post_status'    => 'any',
			'posts_per_page' => 50,
			'fields'         => 'ids',
			'meta_query'     => array( array( 'key' => '_arcp_status', 'value' => $input['status'] ?? 'offen' ) ),
		)
	);
	return array( 'aenderungen' => array_map( 'arcp_change_summary', $ids ) );
}

function arcp_exec_bild_hochladen( $input ) {
	$url = esc_url_raw( (string) $input['url'] );
	if ( 0 !== strpos( $url, 'https://' ) ) {
		return arcp_error( 'bad_url', 'Nur https-Links sind erlaubt.' );
	}
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';

	$id = media_sideload_image( $url, 0, arcp_clean_line( $input['titel'] ), 'id' );
	if ( is_wp_error( $id ) ) {
		return arcp_error( 'upload', 'Bild konnte nicht geladen werden: ' . $id->get_error_message() . ' Tipp: Der Link muss direkt auf die Bilddatei zeigen (Dateiendung .jpg/.png/.webp im Link).' );
	}
	wp_update_post( array( 'ID' => $id, 'post_title' => arcp_clean_line( $input['titel'] ) ) );
	if ( ! empty( $input['alt_text'] ) ) {
		update_post_meta( $id, '_wp_attachment_image_alt', arcp_clean_line( $input['alt_text'] ) );
	}
	return arcp_media_item( $id );
}

/** Prüft und normalisiert die Stammdaten aus der Eingabe. Gibt [field => value] zurück. */
function arcp_collect_stammdaten( $input, $require_all ) {
	$s   = arcp_settings();
	$out = array();

	if ( isset( $input['jahr'] ) ) {
		$out['jahr'] = (string) (int) $input['jahr'];
	}
	if ( isset( $input['standort'] ) ) {
		$out['standort'] = arcp_clean_line( $input['standort'] );
	}
	foreach ( array( 'kategorie' => 'kategorien', 'art' => 'arten', 'stand' => 'staende' ) as $field => $list ) {
		if ( isset( $input[ $field ] ) ) {
			$v = arcp_match_list( $input[ $field ], $s[ $list ], arcp_field_labels()[ $field ] );
			if ( is_wp_error( $v ) ) {
				return $v;
			}
			$out[ $field ] = $v;
		}
	}
	if ( ! empty( $input['art_zusatz'] ) ) {
		if ( ! isset( $out['art'] ) ) {
			return arcp_error( 'missing', '„art_zusatz“ nur zusammen mit „art“ angeben.' );
		}
		$out['art'] .= ', ' . arcp_clean_line( $input['art_zusatz'] );
	}
	if ( $require_all ) {
		foreach ( array_keys( arcp_field_map() ) as $field ) {
			if ( empty( $out[ $field ] ) ) {
				return arcp_error( 'missing', sprintf( 'Pflichtfeld „%s“ fehlt.', arcp_field_labels()[ $field ] ) );
			}
		}
	}
	return $out;
}

function arcp_exec_projekt_anlegen( $input ) {
	if ( ! post_type_exists( ARCP_POST_TYPE ) ) {
		return arcp_error( 'setup', 'Der Projekt-Typ ist nicht vorhanden (Theme-Plugin „Prague Plugins“ aktiv?).', 500 );
	}
	$stamm = arcp_collect_stammdaten( $input, true );
	if ( is_wp_error( $stamm ) ) {
		return $stamm;
	}
	$titel = arcp_clean_line( $input['titel'] );

	$thumb = arcp_check_image_ids( array( $input['titelbild_id'] ), 'Titelbild' );
	if ( is_wp_error( $thumb ) ) {
		return $thumb;
	}
	$galerie = arcp_check_image_ids( $input['galerie_ids'] ?? array(), 'Galerie' );
	if ( is_wp_error( $galerie ) ) {
		return $galerie;
	}
	if ( empty( $galerie ) ) {
		$galerie = $thumb;
	}
	$abschnitte = array();
	foreach ( (array) ( $input['abschnitte'] ?? array() ) as $a ) {
		$ids = arcp_check_image_ids( $a['bild_ids'] ?? array(), 'Abschnitt „' . ( $a['titel'] ?? '' ) . '“' );
		if ( is_wp_error( $ids ) ) {
			return $ids;
		}
		$abschnitte[] = array( 'titel' => $a['titel'], 'bild_ids' => $ids );
	}

	$hinweise = array();
	$dupe     = get_posts( array( 'post_type' => ARCP_POST_TYPE, 'post_status' => 'any', 'title' => $titel, 'fields' => 'ids', 'posts_per_page' => 1 ) );
	if ( $dupe ) {
		$hinweise[] = sprintf( 'Es gibt bereits ein Projekt mit diesem Titel (ID %d). Bitte prüfen, ob das eine Dublette ist.', $dupe[0] );
	}

	$post_id = wp_insert_post(
		wp_slash(
			array(
				'post_type'    => ARCP_POST_TYPE,
				'post_status'  => 'pending',
				'post_title'   => $titel,
				'post_content' => arcp_wpb_build_project( $input['beschreibung'], $abschnitte ),
			)
		),
		true
	);
	if ( is_wp_error( $post_id ) ) {
		return $post_id;
	}

	foreach ( arcp_field_map() as $field => $key ) {
		update_post_meta( $post_id, $key, wp_slash( $stamm[ $field ] ) );
	}
	update_post_meta( $post_id, ARCP_THEME_META, wp_slash( arcp_theme_options_with_gallery( null, $galerie ) ) );
	update_post_meta( $post_id, '_wpb_vc_js_status', 'true' ); // Im Editor als WPBakery-Inhalt öffnen.
	set_post_thumbnail( $post_id, $thumb[0] );
	arcp_set_category( $post_id, $stamm['kategorie'] );

	$items = array( 'titel' => array( 'label' => 'Titel', 'typ' => 'text', 'alt' => '', 'neu' => $titel ) );
	foreach ( $stamm as $field => $value ) {
		$items[ $field ] = array( 'label' => arcp_field_labels()[ $field ], 'typ' => 'text', 'alt' => '', 'neu' => $value );
	}
	$items['beschreibung'] = array( 'label' => 'Beschreibung', 'typ' => 'text', 'alt' => '', 'neu' => trim( $input['beschreibung'] ) );
	$items['titelbild']    = array( 'label' => 'Titelbild', 'typ' => 'bilder', 'alt' => array(), 'neu' => $thumb );
	$items['galerie']      = array( 'label' => 'Slider-Galerie', 'typ' => 'bilder', 'alt' => array(), 'neu' => $galerie );
	foreach ( $abschnitte as $i => $a ) {
		$items[ 'abschnitt' . $i ] = array( 'label' => 'Abschnitt „' . $a['titel'] . '“', 'typ' => 'bilder', 'alt' => array(), 'neu' => $a['bild_ids'] );
	}
	$cid = arcp_register_new( $post_id, $items, $input['notiz'] ?? '' );

	return array(
		'id'                => $post_id,
		'status'            => 'wartet auf Freigabe (nicht öffentlich)',
		'aenderung_id'      => $cid,
		'vorschau'          => $cid ? arcp_preview_link( $cid ) : get_preview_post_link( $post_id ),
		'hinweise'          => $hinweise,
		'naechster_schritt' => arcp_next_step_hint(),
	);
}

function arcp_exec_projekt_aendern( $input ) {
	$post = arcp_get_editable( $input['id'], array( ARCP_POST_TYPE ) );
	if ( is_wp_error( $post ) ) {
		return $post;
	}
	$stamm = arcp_collect_stammdaten( $input, false );
	if ( is_wp_error( $stamm ) ) {
		return $stamm;
	}

	$updates = array( 'meta' => array() );
	$items   = array();
	$labels  = arcp_field_labels();

	if ( isset( $input['titel'] ) ) {
		$titel                 = arcp_clean_line( $input['titel'] );
		$updates['post_title'] = $titel;
		$items['titel']        = array( 'label' => 'Titel', 'typ' => 'text', 'alt' => $post->post_title, 'neu' => $titel );
	}
	foreach ( $stamm as $field => $value ) {
		$key                     = arcp_field_map()[ $field ];
		$updates['meta'][ $key ] = $value;
		$items[ $field ]         = array( 'label' => $labels[ $field ], 'typ' => 'text', 'alt' => (string) get_post_meta( $post->ID, $key, true ), 'neu' => $value );
	}
	if ( isset( $stamm['kategorie'] ) ) {
		$updates['kategorie'] = $stamm['kategorie'];
	}
	if ( isset( $input['titelbild_id'] ) ) {
		$thumb = arcp_check_image_ids( array( $input['titelbild_id'] ), 'Titelbild' );
		if ( is_wp_error( $thumb ) ) {
			return $thumb;
		}
		$updates['meta']['_thumbnail_id'] = $thumb[0];
		$items['titelbild']               = array( 'label' => 'Titelbild', 'typ' => 'bilder', 'alt' => array_filter( array( (int) get_post_thumbnail_id( $post ) ) ), 'neu' => $thumb );
	}
	if ( isset( $input['galerie_ids'] ) ) {
		$galerie = arcp_check_image_ids( $input['galerie_ids'], 'Galerie' );
		if ( is_wp_error( $galerie ) ) {
			return $galerie;
		}
		$live_opts                             = get_post_meta( $post->ID, ARCP_THEME_META, true );
		$updates['meta'][ ARCP_THEME_META ]    = arcp_theme_options_with_gallery( arcp_working_meta( $post->ID, ARCP_THEME_META ), $galerie );
		$items['galerie']                      = array( 'label' => 'Slider-Galerie', 'typ' => 'bilder', 'alt' => arcp_gallery_ids_from_options( $live_opts ), 'neu' => $galerie );
	}

	if ( ! $items ) {
		return arcp_error( 'nothing', 'Keine Änderung angegeben.' );
	}
	if ( ! $updates['meta'] ) {
		unset( $updates['meta'] );
	}
	return arcp_stage( $post, $updates, $items, $input['notiz'] ?? '' );
}

function arcp_exec_inhalt_aendern( $input ) {
	$post = arcp_get_editable( $input['id'] );
	if ( is_wp_error( $post ) ) {
		return $post;
	}
	$working = (string) arcp_working( $post, 'post_content' );
	if ( md5( $working ) !== $input['version'] ) {
		return arcp_error( 'stale', 'Der Inhalt hat sich seit dem Lesen geändert. Bitte inhalt-lesen erneut aufrufen und die neue version verwenden.' );
	}

	$new = arcp_wpb_apply( $working, $input['aenderungen'] );
	if ( is_wp_error( $new ) ) {
		return $new;
	}
	if ( $new === $working ) {
		return arcp_error( 'nothing', 'Die Änderungen entsprechen dem aktuellen Stand – nichts zu tun.' );
	}

	// Anzeige für die Freigabe: alt = Live-Stand, neu = Vorschlag.
	$live_map = array();
	foreach ( arcp_wpb_elements( $post->post_content ) as $el ) {
		$live_map[ $el['element_id'] ] = $el;
	}
	$new_map = array();
	foreach ( arcp_wpb_elements( $new ) as $el ) {
		$new_map[ $el['element_id'] ] = $el;
	}
	$items = array();
	foreach ( $input['aenderungen'] as $c ) {
		$id  = $c['element_id'];
		$el  = $new_map[ $id ] ?? null;
		$old = $live_map[ $id ] ?? null;
		if ( ! $el ) {
			continue;
		}
		$label = $el['baustein'] . ( 'inhalt' !== $el['feld'] ? ' › ' . $el['feld'] : '' ) . ( $el['kontext'] ? ' („' . $el['kontext'] . '“)' : '' );
		if ( in_array( $el['typ'], array( 'text', 'textblock' ), true ) ) {
			$items[ 'el:' . $id ] = array( 'label' => $label, 'typ' => 'text', 'alt' => $old['wert'] ?? '', 'neu' => $el['wert'] );
		} else {
			$items[ 'el:' . $id ] = array(
				'label' => $label,
				'typ'   => 'bilder',
				'alt'   => $old ? ( 'bild' === $old['typ'] ? array( $old['bild_id'] ) : $old['bild_ids'] ) : array(),
				'neu'   => 'bild' === $el['typ'] ? array( $el['bild_id'] ) : $el['bild_ids'],
			);
		}
	}

	return arcp_stage( $post, array( 'post_content' => $new ), $items, $input['notiz'] ?? '' );
}

function arcp_exec_vorschlag_zuruecknehmen( $input ) {
	$cid = (int) $input['aenderung_id'];
	if ( ARCP_CHANGE_TYPE !== get_post_type( $cid ) || 'offen' !== get_post_meta( $cid, '_arcp_status', true ) ) {
		return arcp_error( 'not_found', 'Kein offener Vorschlag mit dieser ID.' );
	}
	if ( (int) get_post_meta( $cid, '_arcp_autor', true ) !== get_current_user_id() && ! current_user_can( 'manage_options' ) ) {
		return arcp_error( 'forbidden', 'Nur eigene Vorschläge können zurückgenommen werden.', 403 );
	}
	$target = (int) get_post_meta( $cid, '_arcp_target', true );
	if ( 'neu' === get_post_meta( $cid, '_arcp_typ', true ) && 'publish' !== get_post_status( $target ) ) {
		wp_trash_post( $target );
	}
	update_post_meta( $cid, '_arcp_status', 'verworfen' );
	update_post_meta( $cid, '_arcp_erledigt_von', get_current_user_id() );
	update_post_meta( $cid, '_arcp_erledigt_am', current_time( 'mysql' ) );
	return array( 'ergebnis' => 'Vorschlag zurückgenommen. Live-Inhalte sind unverändert.' );
}

function arcp_exec_vorschlag_freigeben( $input ) {
	$cid = (int) $input['aenderung_id'];
	if ( ARCP_CHANGE_TYPE !== get_post_type( $cid ) ) {
		return arcp_error( 'not_found', 'Kein Vorschlag mit dieser ID.' );
	}
	$result = arcp_approve( $cid, ! empty( $input['trotz_konflikt'] ), true );
	if ( is_wp_error( $result ) ) {
		if ( 'arcp_conflict' === $result->get_error_code() ) {
			return arcp_error( 'conflict', $result->get_error_message() . ' Frag den Nutzer, ob er trotzdem freigeben will (dann trotz_konflikt: true), oder nimm den Vorschlag zurück und mach die Änderung neu.' );
		}
		return $result;
	}
	$target = (int) get_post_meta( $cid, '_arcp_target', true );
	return array(
		'ergebnis'  => 'Freigegeben – die Änderung ist jetzt live. WordPress hat eine Revision angelegt, ein Zurück ist möglich.',
		'live_link' => get_permalink( $target ),
	);
}

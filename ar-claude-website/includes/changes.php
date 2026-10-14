<?php
/**
 * Freigabe-Workflow.
 *
 * Grundregel: Was live ist, ändert Claude nie direkt.
 * - Veröffentlichte Seiten/Projekte: Änderungen werden als „Änderungsvorschlag“ gespeichert.
 *   Man sieht eine Vorschau der echten Seite mit den Änderungen und gibt dann frei oder verwirft.
 *   Mehrere Änderungen am selben Inhalt werden im selben offenen Vorschlag gesammelt.
 * - Neue Projekte: werden als „Ausstehender Review“ angelegt und erst bei Freigabe veröffentlicht.
 * - Nicht veröffentlichte Inhalte (z. B. ein noch nicht freigegebenes neues Projekt) werden direkt geändert.
 */

defined( 'ABSPATH' ) || exit;

define( 'ARCP_CHANGE_TYPE', 'arcp_aenderung' );

add_action(
	'init',
	function () {
		register_post_type(
			ARCP_CHANGE_TYPE,
			array(
				'label'           => 'Claude-Änderungen',
				'public'          => false,
				'show_ui'         => false,
				'show_in_rest'    => false,
				'supports'        => array( 'title' ),
				'capability_type' => 'post',
			)
		);
	}
);

/* ---------------------------------------------------------------------------
 * Lesen
 * ------------------------------------------------------------------------- */

function arcp_open_change_id( $target_id ) {
	$ids = get_posts(
		array(
			'post_type'      => ARCP_CHANGE_TYPE,
			'post_status'    => 'any',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'meta_query'     => array(
				array( 'key' => '_arcp_target', 'value' => (int) $target_id ),
				array( 'key' => '_arcp_status', 'value' => 'offen' ),
			),
		)
	);
	return $ids ? (int) $ids[0] : 0;
}

function arcp_change_data( $change_id ) {
	$data = get_post_meta( $change_id, '_arcp_data', true );
	return is_array( $data ) ? $data : array();
}

/** Aktueller Arbeitsstand eines Feldes: offener Vorschlag, sonst live. */
function arcp_working( $post, $field ) {
	$cid = arcp_open_change_id( $post->ID );
	if ( $cid ) {
		$data = arcp_change_data( $cid );
		if ( array_key_exists( $field, $data ) ) {
			return $data[ $field ];
		}
	}
	return $post->$field;
}

function arcp_working_meta( $post_id, $key ) {
	$cid = arcp_open_change_id( $post_id );
	if ( $cid ) {
		$data = arcp_change_data( $cid );
		if ( isset( $data['meta'] ) && array_key_exists( $key, $data['meta'] ) ) {
			return $data['meta'][ $key ];
		}
	}
	return get_post_meta( $post_id, $key, true );
}

define( 'ARCP_TOKEN_DAYS', 14 );

/** Geheimer Schlüssel für den Vorschau-Link ohne Login (gilt, solange der Vorschlag offen ist, max. 14 Tage). */
function arcp_change_token( $change_id ) {
	$token = (string) get_post_meta( $change_id, '_arcp_token', true );
	$until = (int) get_post_meta( $change_id, '_arcp_token_bis', true );
	if ( '' === $token || $until < time() + DAY_IN_SECONDS ) {
		$token = wp_generate_password( 24, false, false );
		update_post_meta( $change_id, '_arcp_token', $token );
		update_post_meta( $change_id, '_arcp_token_bis', time() + ARCP_TOKEN_DAYS * DAY_IN_SECONDS );
	}
	return $token;
}

/** Kurzer Fingerabdruck des Vorschlags – macht den Link bei jeder Ergänzung neu (gegen Zwischenspeicher). */
function arcp_change_fingerprint( $change_id ) {
	return substr( md5( maybe_serialize( get_post_meta( $change_id, '_arcp_items', true ) ) . maybe_serialize( arcp_change_data( $change_id ) ) ), 0, 8 );
}

/** Öffentlicher Vorschau-Link (ohne Login), z. B. für den Chat oder das Handy. */
function arcp_preview_link( $change_id ) {
	$target = get_post( (int) get_post_meta( $change_id, '_arcp_target', true ) );
	if ( ! $target ) {
		return '';
	}
	$args = array(
		'arcp_vorschau' => $change_id,
		'arcp_token'    => arcp_change_token( $change_id ),
		'v'             => arcp_change_fingerprint( $change_id ),
	);
	if ( 'publish' === $target->post_status ) {
		return add_query_arg( $args, get_permalink( $target ) );
	}
	// Noch nicht veröffentlicht (z. B. neues Projekt): über die ID aufrufen.
	$id_arg = 'page' === $target->post_type ? array( 'page_id' => $target->ID ) : array( 'p' => $target->ID, 'post_type' => $target->post_type );
	return add_query_arg( $id_arg + $args, home_url( '/' ) );
}

function arcp_change_summary( $change_id ) {
	$target = get_post( (int) get_post_meta( $change_id, '_arcp_target', true ) );
	return array(
		'aenderung_id' => $change_id,
		'inhalt_id'    => $target ? $target->ID : 0,
		'inhalt'       => $target ? get_the_title( $target ) : '(gelöscht)',
		'typ'          => get_post_meta( $change_id, '_arcp_typ', true ),
		'status'       => get_post_meta( $change_id, '_arcp_status', true ),
		'notizen'      => (array) get_post_meta( $change_id, '_arcp_notizen', true ),
		'aenderungen'  => arcp_items_for_chat( $change_id ),
		'vorschau'     => arcp_preview_link( $change_id ),
	);
}

/** Vorher/Nachher als kurze Liste für den Chat. */
function arcp_items_for_chat( $change_id ) {
	$out = array();
	foreach ( (array) get_post_meta( $change_id, '_arcp_items', true ) as $item ) {
		if ( 'bilder' === $item['typ'] ) {
			$names = function ( $ids ) {
				return implode( ', ', array_map( function ( $id ) { return wp_basename( (string) get_attached_file( (int) $id ) ); }, (array) $ids ) );
			};
			$out[] = array( 'bereich' => $item['label'], 'vorher' => $names( $item['alt'] ) ?: '–', 'nachher' => $names( $item['neu'] ) );
		} else {
			$out[] = array( 'bereich' => $item['label'], 'vorher' => (string) $item['alt'] ?: '–', 'nachher' => (string) $item['neu'] );
		}
	}
	return $out;
}

/* ---------------------------------------------------------------------------
 * Schreiben
 * ------------------------------------------------------------------------- */

/**
 * Speichert Änderungen an einem Inhalt.
 *
 * @param WP_Post $post
 * @param array   $updates ['post_title' => …, 'post_content' => …, 'meta' => [key => value], 'kategorie' => name]
 * @param array   $items   Anzeige für die Freigabe: key => ['label','typ','alt','neu']
 * @param string  $notiz   Kurze Zusammenfassung von Claude.
 */
function arcp_stage( $post, $updates, $items, $notiz ) {
	// Nicht live → direkt anwenden (es sieht ja noch niemand).
	if ( 'publish' !== $post->post_status ) {
		arcp_write( $post->ID, $updates );
		$cid = arcp_open_change_id( $post->ID );
		if ( $cid ) {
			arcp_merge_items( $cid, $items, $notiz );
		}
		return array(
			'live'         => false,
			'ergebnis'     => 'Inhalt ist noch nicht veröffentlicht – Änderung direkt übernommen.',
			'aenderung_id' => $cid ?: null,
			'vorschau'     => $cid ? arcp_preview_link( $cid ) : get_preview_post_link( $post ),
			'naechster_schritt' => $cid ? arcp_next_step_hint() : 'Vorschau-Link an den Nutzer geben.',
		);
	}

	$cid = arcp_open_change_id( $post->ID );
	if ( ! $cid ) {
		$cid = wp_insert_post(
			array(
				'post_type'   => ARCP_CHANGE_TYPE,
				'post_status' => 'publish',
				'post_title'  => 'Änderung: ' . get_the_title( $post ),
			),
			true
		);
		if ( is_wp_error( $cid ) ) {
			return $cid;
		}
		update_post_meta( $cid, '_arcp_target', $post->ID );
		update_post_meta( $cid, '_arcp_status', 'offen' );
		update_post_meta( $cid, '_arcp_typ', 'aenderung' );
		update_post_meta( $cid, '_arcp_base_modified', $post->post_modified_gmt );
		update_post_meta( $cid, '_arcp_autor', get_current_user_id() );
	}

	$data = arcp_change_data( $cid );
	foreach ( array( 'post_title', 'post_content', 'kategorie' ) as $k ) {
		if ( array_key_exists( $k, $updates ) ) {
			$data[ $k ] = $updates[ $k ];
		}
	}
	if ( ! empty( $updates['meta'] ) ) {
		$data['meta'] = array_merge( $data['meta'] ?? array(), $updates['meta'] );
	}
	update_post_meta( $cid, '_arcp_data', wp_slash( $data ) );
	arcp_merge_items( $cid, $items, $notiz );

	return array(
		'live'              => true,
		'ergebnis'          => 'Änderungsvorschlag gespeichert. Die Live-Seite ist unverändert, bis der Nutzer freigibt.',
		'aenderung_id'      => $cid,
		'aenderungen'       => arcp_items_for_chat( $cid ),
		'vorschau'          => arcp_preview_link( $cid ),
		'naechster_schritt' => arcp_next_step_hint(),
	);
}

function arcp_next_step_hint() {
	return 'Zeig dem Nutzer jetzt kurz Vorher/Nachher (aus „aenderungen“) und den Vorschau-Link als klickbaren Link und frag, ob du freigeben sollst. Freigeben (vorschlag-freigeben) nur nach ausdrücklichem Ja.';
}

function arcp_merge_items( $cid, $items, $notiz ) {
	$old = get_post_meta( $cid, '_arcp_items', true );
	$old = is_array( $old ) ? $old : array();
	foreach ( $items as $key => $item ) {
		// „alt“ bleibt der Live-Stand vom ersten Vorschlag, „neu“ wird überschrieben.
		if ( isset( $old[ $key ] ) ) {
			$item['alt'] = $old[ $key ]['alt'];
		}
		$old[ $key ] = $item;
	}
	update_post_meta( $cid, '_arcp_items', wp_slash( $old ) );

	if ( '' !== trim( (string) $notiz ) ) {
		$notes   = (array) get_post_meta( $cid, '_arcp_notizen', true );
		$notes[] = wp_strip_all_tags( $notiz );
		update_post_meta( $cid, '_arcp_notizen', wp_slash( array_values( array_filter( $notes ) ) ) );
	}
}

/** Schreibt Änderungen wirklich in den Inhalt (WordPress legt dabei eine Revision an). */
function arcp_write( $post_id, $data ) {
	$postarr = array( 'ID' => $post_id );
	foreach ( array( 'post_title', 'post_content', 'post_status' ) as $k ) {
		if ( array_key_exists( $k, $data ) ) {
			$postarr[ $k ] = $data[ $k ];
		}
	}
	if ( count( $postarr ) > 1 ) {
		$result = wp_update_post( wp_slash( $postarr ), true );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
	}
	foreach ( (array) ( $data['meta'] ?? array() ) as $key => $value ) {
		update_post_meta( $post_id, $key, wp_slash( $value ) );
	}
	if ( ! empty( $data['kategorie'] ) ) {
		arcp_set_category( $post_id, $data['kategorie'] );
	}
	return true;
}

/** Registriert ein neues Projekt für die Freigabeliste. */
function arcp_register_new( $post_id, $items, $notiz ) {
	$cid = wp_insert_post(
		array(
			'post_type'   => ARCP_CHANGE_TYPE,
			'post_status' => 'publish',
			'post_title'  => 'Neues Projekt: ' . get_the_title( $post_id ),
		)
	);
	if ( $cid && ! is_wp_error( $cid ) ) {
		update_post_meta( $cid, '_arcp_target', $post_id );
		update_post_meta( $cid, '_arcp_status', 'offen' );
		update_post_meta( $cid, '_arcp_typ', 'neu' );
		update_post_meta( $cid, '_arcp_autor', get_current_user_id() );
		arcp_merge_items( $cid, $items, $notiz );
	}
	return $cid;
}

/* ---------------------------------------------------------------------------
 * Freigeben / Verwerfen (nur Menschen, siehe admin.php)
 * ------------------------------------------------------------------------- */

/**
 * Darf der aktuelle Benutzer freigeben?
 * Der Claude-Benutzer nur, wenn „Freigabe im Chat“ eingeschaltet ist und die Freigabe über das
 * Werkzeug vorschlag-freigeben kommt (Claude Desktop fragt vorher nach Erlaubnis).
 */
function arcp_can_approve( $target_id, $via_chat = false ) {
	if ( arcp_is_claude_user() && ! $via_chat ) {
		return false;
	}
	$post = get_post( $target_id );
	if ( ! $post ) {
		return false;
	}
	$type = get_post_type_object( $post->post_type );
	return current_user_can( 'edit_post', $target_id ) && current_user_can( $type->cap->publish_posts );
}

function arcp_has_conflict( $change_id ) {
	$target = get_post( (int) get_post_meta( $change_id, '_arcp_target', true ) );
	$base   = get_post_meta( $change_id, '_arcp_base_modified', true );
	return $target && $base && $target->post_modified_gmt !== $base;
}

function arcp_approve( $change_id, $force = false, $via_chat = false ) {
	$target_id = (int) get_post_meta( $change_id, '_arcp_target', true );
	if ( 'offen' !== get_post_meta( $change_id, '_arcp_status', true ) ) {
		return arcp_error( 'closed', 'Diese Änderung ist bereits erledigt.' );
	}
	if ( ! arcp_can_approve( $target_id, $via_chat ) ) {
		return arcp_error( 'forbidden', 'Keine Berechtigung zur Freigabe.', 403 );
	}
	if ( 'neu' !== get_post_meta( $change_id, '_arcp_typ', true ) && ! $force && arcp_has_conflict( $change_id ) ) {
		return arcp_error( 'conflict', 'Der Inhalt wurde seit dem Vorschlag von jemand anderem im Backend geändert. Bei Freigabe würden diese Bereiche mit dem Vorschlag überschrieben.' );
	}

	$GLOBALS['arcp_freigabe_laeuft'] = true; // erlaubt das Veröffentlichen trotz Sperre des Claude-Benutzers.
	try {
		if ( 'neu' === get_post_meta( $change_id, '_arcp_typ', true ) ) {
			$result = arcp_write( $target_id, array( 'post_status' => 'publish' ) );
		} else {
			$result = arcp_write( $target_id, arcp_change_data( $change_id ) );
		}
	} finally {
		unset( $GLOBALS['arcp_freigabe_laeuft'] );
	}
	if ( is_wp_error( $result ) ) {
		return $result;
	}
	update_post_meta( $change_id, '_arcp_status', 'freigegeben' );
	update_post_meta( $change_id, '_arcp_erledigt_von', get_current_user_id() );
	update_post_meta( $change_id, '_arcp_erledigt_am', current_time( 'mysql' ) );
	return true;
}

function arcp_discard( $change_id ) {
	$target_id = (int) get_post_meta( $change_id, '_arcp_target', true );
	if ( 'offen' !== get_post_meta( $change_id, '_arcp_status', true ) ) {
		return arcp_error( 'closed', 'Diese Änderung ist bereits erledigt.' );
	}
	if ( arcp_is_claude_user() || ! current_user_can( 'edit_post', $target_id ) ) {
		return arcp_error( 'forbidden', 'Keine Berechtigung.', 403 );
	}
	if ( 'neu' === get_post_meta( $change_id, '_arcp_typ', true ) ) {
		$target = get_post( $target_id );
		if ( $target && 'publish' !== $target->post_status ) {
			wp_trash_post( $target_id );
		}
	}
	update_post_meta( $change_id, '_arcp_status', 'verworfen' );
	update_post_meta( $change_id, '_arcp_erledigt_von', get_current_user_id() );
	update_post_meta( $change_id, '_arcp_erledigt_am', current_time( 'mysql' ) );
	return true;
}

/* ---------------------------------------------------------------------------
 * Vorschau: echte Seite, aber mit den vorgeschlagenen Änderungen (nur für Eingeloggte mit Rechten).
 * ------------------------------------------------------------------------- */

function arcp_preview_context() {
	static $ctx = null;
	if ( null !== $ctx ) {
		return $ctx;
	}
	$ctx = false;
	if ( is_admin() || empty( $_GET['arcp_vorschau'] ) ) {
		return $ctx;
	}
	$cid    = absint( $_GET['arcp_vorschau'] );
	$target = (int) get_post_meta( $cid, '_arcp_target', true );
	if ( ARCP_CHANGE_TYPE !== get_post_type( $cid ) || ! $target ) {
		return $ctx;
	}
	$status = get_post_meta( $cid, '_arcp_status', true );

	// Zugang: eingeloggt mit Rechten – oder gültiger Vorschau-Schlüssel (nur solange offen und nicht abgelaufen).
	$token_ok = isset( $_GET['arcp_token'] )
		&& 'offen' === $status
		&& (int) get_post_meta( $cid, '_arcp_token_bis', true ) > time()
		&& hash_equals( (string) get_post_meta( $cid, '_arcp_token', true ), (string) wp_unslash( $_GET['arcp_token'] ) );
	if ( ! $token_ok && ! current_user_can( 'edit_post', $target ) ) {
		return $ctx;
	}
	$ctx = array(
		'id'     => $cid,
		'target' => $target,
		'data'   => arcp_change_data( $cid ),
		'status' => $status,
	);
	return $ctx;
}

/* Noch nicht veröffentlichte Inhalte (neue Projekte) in der Vorschau anzeigen. */
add_action(
	'pre_get_posts',
	function ( $query ) {
		if ( is_admin() || ! $query->is_main_query() ) {
			return;
		}
		$ctx = arcp_preview_context();
		if ( $ctx && 'publish' !== get_post_status( $ctx['target'] ) ) {
			$query->set( 'post_status', array( 'publish', 'pending', 'draft', 'future', 'private' ) );
		}
	}
);

add_action(
	'template_redirect',
	function () {
		if ( arcp_preview_context() ) {
			if ( ! defined( 'DONOTCACHEPAGE' ) ) {
				define( 'DONOTCACHEPAGE', true );
			}
			nocache_headers();
			header( 'X-Robots-Tag: noindex, nofollow', true );
		}
	},
	0
);

add_filter(
	'wp_robots',
	function ( $robots ) {
		if ( arcp_preview_context() ) {
			$robots['noindex']  = true;
			$robots['nofollow'] = true;
		}
		return $robots;
	}
);

add_filter(
	'the_posts',
	function ( $posts, $query ) {
		$ctx = arcp_preview_context();
		if ( ! $ctx || is_admin() ) {
			return $posts;
		}
		foreach ( $posts as $i => $p ) {
			if ( (int) $p->ID === $ctx['target'] ) {
				$clone = clone $p;
				if ( isset( $ctx['data']['post_title'] ) ) {
					$clone->post_title = $ctx['data']['post_title'];
				}
				if ( isset( $ctx['data']['post_content'] ) ) {
					$clone->post_content = $ctx['data']['post_content'];
				}
				$posts[ $i ] = $clone;
			}
		}
		return $posts;
	},
	10,
	2
);

add_filter(
	'get_post_metadata',
	function ( $value, $object_id, $meta_key ) {
		$ctx = arcp_preview_context();
		if ( ! $ctx || (int) $object_id !== $ctx['target'] || '' === $meta_key ) {
			return $value;
		}
		if ( isset( $ctx['data']['meta'] ) && array_key_exists( $meta_key, $ctx['data']['meta'] ) ) {
			return array( $ctx['data']['meta'][ $meta_key ] );
		}
		return $value;
	},
	10,
	3
);

add_action(
	'wp_footer',
	function () {
		$ctx = arcp_preview_context();
		if ( ! $ctx ) {
			return;
		}
		$label = 'offen' === $ctx['status'] ? 'VORSCHAU – noch nicht live. Freigabe im Claude-Chat.' : 'Archivierte Vorschau (' . $ctx['status'] . ').';
		$btn   = '';
		echo '<div id="arcp-bar" style="position:fixed;left:12px;right:12px;bottom:12px;z-index:999999;background:#1d2327;color:#fff;padding:10px 16px;border-radius:8px;font:14px/1.4 sans-serif;box-shadow:0 6px 24px rgba(0,0,0,.35);display:flex;gap:16px;align-items:center;justify-content:space-between;flex-wrap:wrap">'
			. '<span><strong>Claude:</strong> ' . esc_html( $label ) . '</span>' . $btn . '</div>';
	}
);

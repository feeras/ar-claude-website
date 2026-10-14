<?php
/**
 * Hilfsfunktionen: Feldzuordnung, Bilder, Theme-Optionen, Kategorien.
 */

defined( 'ABSPATH' ) || exit;

/** Logische Felder → PixFields-Meta-Keys (PixFields speichert als 'pixfield_' . meta_key). */
function arcp_field_map() {
	return array(
		'jahr'      => 'pixfield_year',
		'standort'  => 'pixfield_location',
		'kategorie' => 'pixfield_category',
		'art'       => 'pixfield_art-des-projekts',
		'stand'     => 'pixfield_stand',
	);
}

function arcp_field_labels() {
	return array(
		'titel'     => 'Titel',
		'jahr'      => 'Jahr',
		'standort'  => 'Standort',
		'kategorie' => 'Kategorie',
		'art'       => 'Art des Projekts',
		'stand'     => 'Aktueller Stand',
		'titelbild' => 'Titelbild',
		'galerie'   => 'Slider-Galerie',
	);
}

/** Schlüssel des Theme-Optionsfelds, die projektspezifisch sind und nicht aus der Vorlage übernommen werden. */
function arcp_theme_content_keys() {
	return array( 'subtitle', 'title', 'second_subtitle', 'description', 'pdf_shortcode', 'banner', 'iframe_3d', 'image_before', 'image_after', 'gallery' );
}

function arcp_error( $code, $message, $status = 400 ) {
	return new WP_Error( 'arcp_' . $code, $message, array( 'status' => $status ) );
}

/** Lädt einen Inhalt (Projekt oder Seite) und prüft Typ und Rechte. */
function arcp_get_editable( $id, $types = array( ARCP_POST_TYPE, 'page' ) ) {
	$post = get_post( (int) $id );
	if ( ! $post || ! in_array( $post->post_type, (array) $types, true ) || 'trash' === $post->post_status ) {
		return arcp_error( 'not_found', sprintf( 'Inhalt mit ID %d wurde nicht gefunden (erlaubt: %s). Nutze projekte-auflisten oder seiten-auflisten.', (int) $id, implode( ', ', (array) $types ) ), 404 );
	}
	if ( ! current_user_can( 'edit_post', $post->ID ) ) {
		return arcp_error( 'forbidden', 'Keine Berechtigung, diesen Inhalt zu bearbeiten.', 403 );
	}
	return $post;
}

/** Prüft eine Liste von Mediathek-IDs: müssen existieren und Bilder sein. */
function arcp_check_image_ids( $ids, $label ) {
	$clean = array();
	foreach ( (array) $ids as $id ) {
		$id = (int) $id;
		if ( $id <= 0 || 'attachment' !== get_post_type( $id ) || ! wp_attachment_is_image( $id ) ) {
			return arcp_error( 'bad_image', sprintf( '%s: Bild-ID %d existiert nicht in der Mediathek oder ist kein Bild. Nutze medien-suchen oder bild-hochladen.', $label, $id ) );
		}
		$clean[] = $id;
	}
	return array_values( array_unique( $clean ) );
}

function arcp_media_item( $id ) {
	$id   = (int) $id;
	$file = get_attached_file( $id );
	return array(
		'id'        => $id,
		'titel'     => get_the_title( $id ),
		'dateiname' => $file ? wp_basename( $file ) : '',
		'url'       => (string) wp_get_attachment_image_url( $id, 'large' ),
	);
}

/** Prüft, ob ein Wert in einer erlaubten Liste ist (Groß-/Kleinschreibung egal) und gibt die korrekte Schreibweise zurück. */
function arcp_match_list( $value, $list, $label ) {
	foreach ( (array) $list as $allowed ) {
		if ( mb_strtolower( trim( (string) $value ) ) === mb_strtolower( $allowed ) ) {
			return $allowed;
		}
	}
	return arcp_error( 'bad_value', sprintf( '%s „%s“ ist nicht erlaubt. Erlaubt sind: %s.', $label, $value, implode( ', ', (array) $list ) ) );
}

/** Bereinigt einfachen Text für Felder (keine Tags, keine Shortcode-Klammern). */
function arcp_clean_line( $text ) {
	$text = wp_strip_all_tags( (string) $text );
	$text = str_replace( array( '[', ']' ), array( '(', ')' ), $text );
	return trim( preg_replace( '/\s+/u', ' ', $text ) );
}

/**
 * Liefert das Theme-Optionsfeld mit neuer Galerie (ohne zu speichern).
 * Basis: $current (bestehende Werte), sonst die Vorlage ohne projektspezifische Inhalte.
 */
function arcp_theme_options_with_gallery( $current, $gallery_ids ) {
	if ( ! is_array( $current ) || empty( $current ) ) {
		$template = get_post_meta( (int) arcp_setting( 'vorlage_id' ), ARCP_THEME_META, true );
		$current  = is_array( $template ) ? $template : array( 'style' => 'slider' );
		foreach ( arcp_theme_content_keys() as $key ) {
			if ( isset( $current[ $key ] ) ) {
				$current[ $key ] = '';
			}
		}
	}
	$current['gallery'] = implode( ',', array_map( 'intval', $gallery_ids ) );
	return $current;
}

function arcp_gallery_ids_from_options( $opts ) {
	if ( ! is_array( $opts ) || empty( $opts['gallery'] ) ) {
		return array();
	}
	return array_values( array_filter( array_map( 'intval', explode( ',', $opts['gallery'] ) ) ) );
}

/** Setzt die Taxonomie-Kategorie passend zur PixFields-Kategorie (eine Liste für beides). */
function arcp_set_category( $post_id, $name ) {
	if ( ! taxonomy_exists( ARCP_TAXONOMY ) || '' === trim( (string) $name ) ) {
		return;
	}
	$term = get_term_by( 'name', $name, ARCP_TAXONOMY );
	if ( $term ) {
		$term_id = (int) $term->term_id;
	} else {
		$created = wp_insert_term( $name, ARCP_TAXONOMY );
		if ( is_wp_error( $created ) ) {
			return;
		}
		$term_id = (int) $created['term_id'];
	}
	wp_set_object_terms( $post_id, array( $term_id ), ARCP_TAXONOMY, false );
}

<?php
/**
 * WPBakery-Inhalte lesen und gezielt ändern.
 *
 * Statt den Seitencode von Claude neu schreiben zu lassen, zerlegt das Plugin den Inhalt
 * in „Elemente“ (Texte, Überschriften, Bilder) mit fester ID. Claude ändert nur einzelne
 * Elemente; Layout, Abstände und alle anderen Einstellungen bleiben unangetastet.
 */

defined( 'ABSPATH' ) || exit;

/** Attribute, die sichtbaren Text enthalten. */
function arcp_wpb_text_attrs() {
	return array( 'title', 'subtitle', 'sub_title', 'second_subtitle', 'text', 'description', 'heading', 'label', 'btn_text', 'button_text', 'caption' );
}

/** Attribute mit genau einer Bild-ID. */
function arcp_wpb_image_attrs() {
	return array( 'img', 'image', 'image_background', 'bg_image', 'background_image', 'image_id', 'photo' );
}

/** Attribute mit mehreren Bild-IDs (kommagetrennt). */
function arcp_wpb_gallery_attrs() {
	return array( 'images', 'gallery', 'image_ids' );
}

/** Lesbare Namen der Bausteine. */
function arcp_wpb_label( $tag ) {
	$labels = array(
		'vc_banner_title'  => 'Banner',
		'vc_column_text'   => 'Textblock',
		'vc_headings'      => 'Überschrift',
		'vc_custom_heading'=> 'Überschrift',
		'vc_single_image'  => 'Bild',
		'vc_gallery'       => 'Galerie',
		'vc_counters'      => 'Zähler',
		'vc_btn'           => 'Button',
		'vc_row'           => 'Zeile',
		'vc_row_inner'     => 'Innere Zeile',
	);
	return $labels[ $tag ] ?? $tag;
}

/**
 * Zerlegt Inhalt in Shortcode-Tokens mit Position.
 * @return array<int, array{close:bool,tag:string,attrs:string,start:int,len:int,raw:string}>
 */
function arcp_wpb_tokens( $content ) {
	$tokens = array();
	if ( ! preg_match_all( '/\[(\/)?([a-zA-Z][a-zA-Z0-9_-]*)((?:[^\]"\[]|"[^"]*")*)\]/', $content, $m, PREG_SET_ORDER | PREG_OFFSET_CAPTURE ) ) {
		return $tokens;
	}
	foreach ( $m as $hit ) {
		$tokens[] = array(
			'close' => '' !== $hit[1][0] && -1 !== $hit[1][1],
			'tag'   => $hit[2][0],
			'attrs' => isset( $hit[3] ) ? $hit[3][0] : '',
			'start' => $hit[0][1],
			'len'   => strlen( $hit[0][0] ),
			'raw'   => $hit[0][0],
		);
	}
	return $tokens;
}

/** Text aus HTML mit Absätzen als Leerzeilen. */
function arcp_wpb_html_to_text( $html ) {
	$html = preg_replace( '#<br\s*/?>#i', "\n", $html );
	$html = preg_replace( '#</p>\s*#i', "\n\n", $html );
	$text = html_entity_decode( wp_strip_all_tags( $html, false ), ENT_QUOTES, 'UTF-8' );
	$text = preg_replace( "/[ \t]+\n/", "\n", $text );
	return trim( preg_replace( "/\n{3,}/", "\n\n", $text ) );
}

/** Klartext → einfache Absätze (HTML). */
function arcp_wpb_text_to_html( $text ) {
	$text  = preg_replace( '#<[^>]*>#', '', str_replace( "\r", '', (string) $text ) ); // Klartext: Tags entfernen, Zeilen behalten.
	$paras = preg_split( "/\n\s*\n/", trim( $text ) );
	$out   = array();
	foreach ( $paras as $p ) {
		$p = trim( $p );
		if ( '' !== $p ) {
			$out[] = '<p>' . nl2br( esc_html( $p ) ) . '</p>';
		}
	}
	return str_replace( array( '[', ']' ), array( '&#91;', '&#93;' ), implode( "\n", $out ) );
}

/** HTML von Claude → sicheres HTML ohne Shortcodes. */
function arcp_wpb_clean_html( $html ) {
	return str_replace( array( '[', ']' ), array( '&#91;', '&#93;' ), wp_kses_post( (string) $html ) );
}

/** Wert für ein Shortcode-Attribut (WPBakery schreibt Anführungszeichen als ``). */
function arcp_wpb_attr_value( $text ) {
	$text = wp_strip_all_tags( (string) $text );
	$text = str_replace( array( '"', '[', ']' ), array( '``', '(', ')' ), $text );
	return trim( preg_replace( '/\s+/u', ' ', $text ) );
}

function arcp_wpb_attr_display( $value ) {
	return str_replace( '``', '"', (string) $value );
}

/**
 * Liste aller änderbaren Elemente.
 * Element-ID: "<Nummer des Bausteins>.<Feld>", z. B. "3.title" oder "5.inhalt".
 */
function arcp_wpb_elements( $content ) {
	$tokens   = arcp_wpb_tokens( $content );
	$elements = array();
	$index    = 0;
	$kontext  = '';
	$count    = count( $tokens );

	for ( $t = 0; $t < $count; $t++ ) {
		$tok = $tokens[ $t ];
		if ( $tok['close'] ) {
			continue;
		}
		$index++;
		$atts  = shortcode_parse_atts( trim( $tok['attrs'] ) );
		$atts  = is_array( $atts ) ? $atts : array();
		$label = arcp_wpb_label( $tok['tag'] );

		foreach ( $atts as $name => $value ) {
			if ( ! is_string( $name ) ) {
				continue;
			}
			if ( in_array( $name, arcp_wpb_text_attrs(), true ) && '' !== trim( $value ) && ! is_numeric( $value ) ) {
				$elements[] = array(
					'element_id' => $index . '.' . $name,
					'typ'        => 'text',
					'baustein'   => $label,
					'feld'       => $name,
					'kontext'    => $kontext,
					'wert'       => arcp_wpb_attr_display( $value ),
				);
				if ( in_array( $name, array( 'title', 'heading' ), true ) ) {
					$kontext = arcp_wpb_attr_display( $value );
				}
			} elseif ( in_array( $name, arcp_wpb_image_attrs(), true ) && ctype_digit( (string) $value ) ) {
				$elements[] = array(
					'element_id' => $index . '.' . $name,
					'typ'        => 'bild',
					'baustein'   => $label,
					'feld'       => $name,
					'kontext'    => $kontext,
					'bild_id'    => (int) $value,
				);
			} elseif ( in_array( $name, arcp_wpb_gallery_attrs(), true ) && preg_match( '/^\d+(,\s*\d+)*$/', (string) $value ) ) {
				$elements[] = array(
					'element_id' => $index . '.' . $name,
					'typ'        => 'bilder',
					'baustein'   => $label,
					'feld'       => $name,
					'kontext'    => $kontext,
					'bild_ids'   => array_map( 'intval', explode( ',', $value ) ),
				);
			}
		}

		// Umschlossener Text ohne verschachtelte Bausteine, z. B. [vc_column_text]…[/vc_column_text].
		$next = $tokens[ $t + 1 ] ?? null;
		if ( $next && $next['close'] && $next['tag'] === $tok['tag'] ) {
			$inner = substr( $content, $tok['start'] + $tok['len'], $next['start'] - ( $tok['start'] + $tok['len'] ) );
			$text  = arcp_wpb_html_to_text( $inner );
			if ( '' !== $text ) {
				$elements[] = array(
					'element_id'       => $index . '.inhalt',
					'typ'              => 'textblock',
					'baustein'         => $label,
					'feld'             => 'inhalt',
					'kontext'          => $kontext,
					'wert'             => $text,
					'hat_formatierung' => (bool) preg_match( '#<(a|strong|b|em|i|ul|ol|h[1-6]|span)\b#i', $inner ),
				);
			}
		}
	}
	return $elements;
}

/**
 * Wendet Änderungen auf Elemente an.
 * @param array $changes Liste von ['element_id' => '3.title', 'text' => '…'] bzw. 'html', 'bild_id', 'bild_ids'.
 * @return string|WP_Error Neuer Inhalt.
 */
function arcp_wpb_apply( $content, $changes ) {
	$tokens   = arcp_wpb_tokens( $content );
	$elements = array();
	foreach ( arcp_wpb_elements( $content ) as $el ) {
		$elements[ $el['element_id'] ] = $el;
	}

	// Öffnende Tokens nach Nummer.
	$open  = array();
	$index = 0;
	foreach ( $tokens as $pos => $tok ) {
		if ( ! $tok['close'] ) {
			$open[ ++$index ] = $pos;
		}
	}

	$tag_edits   = array(); // token-pos => [attr => value]
	$inner_edits = array(); // token-pos => html

	foreach ( (array) $changes as $change ) {
		$id = (string) ( $change['element_id'] ?? '' );
		if ( ! isset( $elements[ $id ] ) ) {
			return arcp_error( 'bad_element', sprintf( 'Element „%s“ gibt es nicht (mehr). Lies den Inhalt mit inhalt-lesen neu ein.', $id ) );
		}
		$el  = $elements[ $id ];
		$pos = $open[ (int) strtok( $id, '.' ) ];

		switch ( $el['typ'] ) {
			case 'text':
				if ( ! isset( $change['text'] ) || '' === trim( (string) $change['text'] ) ) {
					return arcp_error( 'missing', sprintf( 'Für %s fehlt „text“.', $id ) );
				}
				$tag_edits[ $pos ][ $el['feld'] ] = arcp_wpb_attr_value( $change['text'] );
				break;

			case 'bild':
				$ids = arcp_check_image_ids( array( $change['bild_id'] ?? 0 ), $id );
				if ( is_wp_error( $ids ) ) {
					return $ids;
				}
				$tag_edits[ $pos ][ $el['feld'] ] = (string) $ids[0];
				break;

			case 'bilder':
				$ids = arcp_check_image_ids( $change['bild_ids'] ?? array(), $id );
				if ( is_wp_error( $ids ) ) {
					return $ids;
				}
				if ( empty( $ids ) ) {
					return arcp_error( 'missing', sprintf( 'Für %s fehlt „bild_ids“.', $id ) );
				}
				$tag_edits[ $pos ][ $el['feld'] ] = implode( ',', $ids );
				break;

			case 'textblock':
				if ( isset( $change['html'] ) && '' !== trim( (string) $change['html'] ) ) {
					$inner_edits[ $pos ] = "\n" . arcp_wpb_clean_html( $change['html'] ) . "\n";
				} elseif ( isset( $change['text'] ) && '' !== trim( (string) $change['text'] ) ) {
					$inner_edits[ $pos ] = "\n" . arcp_wpb_text_to_html( $change['text'] ) . "\n";
				} else {
					return arcp_error( 'missing', sprintf( 'Für %s fehlt „text“ oder „html“.', $id ) );
				}
				break;
		}
	}

	// Ersetzungen von hinten nach vorne, damit Positionen gültig bleiben.
	$replacements = array();
	foreach ( $tag_edits as $pos => $attrs ) {
		$raw = $tokens[ $pos ]['raw'];
		foreach ( $attrs as $name => $value ) {
			$raw = preg_replace_callback(
				'/(\s' . preg_quote( $name, '/' ) . '=")[^"]*(")/',
				function ( $mm ) use ( $value ) {
					return $mm[1] . $value . $mm[2];
				},
				$raw,
				1
			);
		}
		$replacements[] = array( $tokens[ $pos ]['start'], $tokens[ $pos ]['len'], $raw );
	}
	foreach ( $inner_edits as $pos => $html ) {
		$start          = $tokens[ $pos ]['start'] + $tokens[ $pos ]['len'];
		$end            = $tokens[ $pos + 1 ]['start'];
		$replacements[] = array( $start, $end - $start, $html );
	}
	usort(
		$replacements,
		function ( $a, $b ) {
			return $b[0] <=> $a[0];
		}
	);
	foreach ( $replacements as $r ) {
		$content = substr_replace( $content, $r[2], $r[0], $r[1] );
	}
	return $content;
}

/**
 * Baut den Inhalt eines neuen Projekts im selben Aufbau wie die bestehenden Projekte:
 * Beschreibung, dann Abschnitte (z. B. „Diagramm“, „Pläne“) mit je zwei Bildern pro Zeile.
 */
function arcp_wpb_build_project( $beschreibung, $abschnitte ) {
	$out = '[vc_row][vc_column][vc_column_text]' . arcp_wpb_text_to_html( $beschreibung ) . '[/vc_column_text][/vc_column][/vc_row]';

	foreach ( (array) $abschnitte as $abschnitt ) {
		$titel = arcp_wpb_attr_value( $abschnitt['titel'] ?? '' );
		$ids   = array_map( 'intval', (array) ( $abschnitt['bild_ids'] ?? array() ) );
		if ( '' === $titel || empty( $ids ) ) {
			continue;
		}
		$out .= '[vc_row][vc_column][vc_headings title="' . $titel . '" heading_size="h2"][/vc_headings]';
		foreach ( array_chunk( $ids, 2 ) as $pair ) {
			$width = 2 === count( $pair ) ? '1/2' : '1/1';
			$out  .= '[vc_row_inner]';
			foreach ( $pair as $img ) {
				$out .= '[vc_column_inner width="' . $width . '"][vc_single_image image="' . $img . '" img_size="large"][/vc_column_inner]';
			}
			$out .= '[/vc_row_inner]';
		}
		$out .= '[/vc_column][/vc_row]';
	}
	return $out;
}

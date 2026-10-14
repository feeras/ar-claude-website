<?php
/**
 * Einstellungen mit Standardwerten (aus den bestehenden Projekten abgeleitet).
 */

defined( 'ABSPATH' ) || exit;

function arcp_default_settings() {
	return array(
		'claude_user_id' => 0,    // WordPress-Benutzer, mit dem Claude sich anmeldet.
		'vorlage_id'     => 3864, // Tribüne Konstanz – liefert die Theme-Optionen (Slider-Stil usw.) für neue Projekte.
		'kategorien'     => array( 'Einfamilienhaus', 'Zweifamilienhaus', 'Mehrfamilienhaus', 'Wochenendhaus', 'Gewerbe', 'Kultur & Veranstaltungen', 'Sonstiges' ),
		'arten'          => array( 'Neubau', 'Umbau', 'Modernisierung', 'Sanierung', 'Entwurf' ),
		'staende'        => array( 'Realisiert', 'Im Bau', 'In Planung', 'Nicht Realisiert' ),
	);
}

function arcp_settings() {
	$saved = get_option( 'arcp_settings', array() );
	return wp_parse_args( is_array( $saved ) ? $saved : array(), arcp_default_settings() );
}

function arcp_setting( $key ) {
	$s = arcp_settings();
	return $s[ $key ] ?? null;
}

function arcp_is_claude_user( $user_id = null ) {
	$claude = (int) arcp_setting( 'claude_user_id' );
	$user_id = null === $user_id ? get_current_user_id() : (int) $user_id;
	return $claude > 0 && $claude === $user_id;
}

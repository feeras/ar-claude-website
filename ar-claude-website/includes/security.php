<?php
/**
 * Absicherung des Claude-Benutzers.
 *
 * Das Application Password des Claude-Benutzers soll NUR für unseren MCP-Server taugen.
 * Ohne diese Sperre könnte man mit dem Passwort die ganze REST-API mit Redakteursrechten
 * nutzen (z. B. Seiten direkt veröffentlichen) – am Freigabe-Workflow vorbei.
 */

defined( 'ABSPATH' ) || exit;

add_filter(
	'rest_pre_dispatch',
	function ( $result, $server, $request ) {
		if ( ! arcp_is_claude_user() ) {
			return $result;
		}
		$route = (string) $request->get_route();
		if ( 0 === strpos( $route, '/' . ARCP_NS . '/' ) || '/' . ARCP_NS === $route ) {
			return $result;
		}
		return new WP_Error( 'arcp_claude_locked', 'Der Claude-Benutzer darf nur den MCP-Server „' . ARCP_NS . '“ verwenden.', array( 'status' => 403 ) );
	},
	10,
	3
);

/* Kein Zugang zum Backend für den Claude-Benutzer (er braucht nur die Schnittstelle). */
add_action(
	'admin_init',
	function () {
		if ( arcp_is_claude_user() && ! wp_doing_ajax() ) {
			wp_die( 'Der Claude-Benutzer hat keinen Zugang zum Backend.', 403 );
		}
	}
);

/* Freigabe schützt sich selbst: der Claude-Benutzer kann nie veröffentlichen, auch nicht über Umwege. */
add_filter(
	'wp_insert_post_data',
	function ( $data, $postarr ) {
		if ( arcp_is_claude_user() && empty( $GLOBALS['arcp_freigabe_laeuft'] ) && ARCP_CHANGE_TYPE !== $data['post_type'] && in_array( $data['post_status'], array( 'publish', 'future' ), true ) ) {
			$existing = ! empty( $postarr['ID'] ) ? get_post_status( (int) $postarr['ID'] ) : '';
			if ( $existing !== $data['post_status'] ) {
				$data['post_status'] = 'pending';
			}
		}
		return $data;
	},
	99,
	2
);

/** Ist die aktuelle Anfrage an unseren Namespace gerichtet? */
function arcp_request_is_ours() {
	$uri   = (string) ( $_SERVER['REQUEST_URI'] ?? '' );
	$route = (string) ( $_GET['rest_route'] ?? '' );
	return false !== strpos( $uri, '/wp-json/' . ARCP_NS . '/' ) || 0 === strpos( $route, '/' . ARCP_NS . '/' );
}

/* Ohne gültige Anmeldung: sauberes 401 statt leerer Antwort, damit Clients den Grund sehen. */
add_filter(
	'rest_pre_dispatch',
	function ( $result, $server, $request ) {
		$route = (string) $request->get_route();
		if ( null !== $result || 0 !== strpos( $route, '/' . ARCP_NS . '/' ) || 0 === strpos( $route, '/' . ARCP_NS . '/oauth/' ) || is_user_logged_in() ) {
			return $result;
		}
		// Hinweis für OAuth-Clients (Claude), wo die Anmeldung zu finden ist.
		header( 'WWW-Authenticate: Bearer resource_metadata="' . arcp_oauth_resource_metadata_url() . '", scope="website"' );
		$hint = '' !== arcp_bearer()
			? 'Anmeldung ungültig oder abgelaufen.'
			: 'Anmeldung erforderlich (OAuth).';
		return new WP_Error( 'invalid_token', $hint, array( 'status' => 401 ) );
	},
	5,
	3
);

/* Alten API-Schlüssel aus Version 1.2.x entfernen (wird nicht mehr verwendet). */
add_action( 'admin_init', function () {
	if ( false !== get_option( 'arcp_api_key_hash', false ) ) {
		delete_option( 'arcp_api_key_hash' );
	}
} );

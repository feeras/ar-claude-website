<?php
/**
 * Schlanke OAuth-2.1-Anmeldung für den Claude-Connector.
 *
 * Ablauf: Claude ruft den MCP-Endpunkt auf → 401 mit Verweis auf die Metadaten →
 * Claude registriert sich (Dynamic Client Registration) → öffnet die Anmeldeseite →
 * ein Mensch mit WordPress-Login (Redakteur oder höher) klickt „Erlauben“ →
 * Claude tauscht den Code (mit PKCE) gegen ein Zugangs-Token.
 * Mit dem Token arbeitet der Connector immer als eingeschränkter Claude-Benutzer.
 *
 * Gespeichert werden nur Hashes der Tokens.
 */

defined( 'ABSPATH' ) || exit;

define( 'ARCP_OAUTH_ACCESS_TTL', HOUR_IN_SECONDS );
define( 'ARCP_OAUTH_REFRESH_TTL', 90 * DAY_IN_SECONDS );

/* ---------------------------------------------------------------------------
 * Adressen
 * ------------------------------------------------------------------------- */

function arcp_oauth_issuer() {
	return untrailingslashit( home_url() );
}

function arcp_oauth_path( $path ) {
	return arcp_oauth_issuer() . $path;
}

function arcp_oauth_resource_metadata_url() {
	return arcp_oauth_path( '/.well-known/oauth-protected-resource' );
}

function arcp_oauth_resource_metadata() {
	return array(
		'resource'                 => arcp_mcp_endpoint(),
		'authorization_servers'    => array( arcp_oauth_issuer() ),
		'scopes_supported'         => array( 'website' ),
		'bearer_methods_supported' => array( 'header' ),
		'resource_name'            => 'A/R Architekten Website',
	);
}

function arcp_oauth_server_metadata() {
	return array(
		'issuer'                                => arcp_oauth_issuer(),
		'authorization_endpoint'                => arcp_oauth_path( '/arcp-oauth/authorize' ),
		'token_endpoint'                        => rest_url( ARCP_NS . '/oauth/token' ),
		'registration_endpoint'                 => rest_url( ARCP_NS . '/oauth/register' ),
		'response_types_supported'              => array( 'code' ),
		'grant_types_supported'                 => array( 'authorization_code', 'refresh_token' ),
		'code_challenge_methods_supported'      => array( 'S256' ),
		'token_endpoint_auth_methods_supported' => array( 'none' ),
		'scopes_supported'                      => array( 'website' ),
	);
}

/** Erlaubte Rücksprung-Adressen: Claude (Web/Desktop/Handy) und lokale Clients wie Claude Code. */
function arcp_oauth_redirect_allowed( $uri ) {
	if ( in_array( $uri, array( 'https://claude.ai/api/mcp/auth_callback', 'https://claude.com/api/mcp/auth_callback' ), true ) ) {
		return true;
	}
	$p = wp_parse_url( $uri );
	return isset( $p['scheme'], $p['host'] ) && 'http' === $p['scheme'] && in_array( $p['host'], array( 'localhost', '127.0.0.1' ), true );
}

/* ---------------------------------------------------------------------------
 * Speicher (Optionen, nur Hashes)
 * ------------------------------------------------------------------------- */

function arcp_oauth_hash( $value ) {
	return hash_hmac( 'sha256', (string) $value, wp_salt( 'auth' ) );
}

function arcp_oauth_get( $name ) {
	$v = get_option( 'arcp_oauth_' . $name, array() );
	return is_array( $v ) ? $v : array();
}

function arcp_oauth_put( $name, $value ) {
	update_option( 'arcp_oauth_' . $name, $value, false );
}

function arcp_oauth_tokens_pruned() {
	$tokens = arcp_oauth_get( 'tokens' );
	$now    = time();
	foreach ( $tokens as $h => $t ) {
		if ( (int) $t['e'] < $now ) {
			unset( $tokens[ $h ] );
		}
	}
	return $tokens;
}

/** Gibt Access- und Refresh-Token aus. */
function arcp_oauth_issue( $client_id, $approved_by ) {
	$access  = 'arwa_' . wp_generate_password( 48, false, false );
	$refresh = 'arwr_' . wp_generate_password( 48, false, false );
	$tokens  = arcp_oauth_tokens_pruned();
	$tokens[ arcp_oauth_hash( $access ) ]  = array( 't' => 'a', 'c' => $client_id, 'e' => time() + ARCP_OAUTH_ACCESS_TTL, 'u' => (int) $approved_by );
	$tokens[ arcp_oauth_hash( $refresh ) ] = array( 't' => 'r', 'c' => $client_id, 'e' => time() + ARCP_OAUTH_REFRESH_TTL, 'u' => (int) $approved_by );
	arcp_oauth_put( 'tokens', $tokens );
	return array(
		'access_token'  => $access,
		'token_type'    => 'Bearer',
		'expires_in'    => ARCP_OAUTH_ACCESS_TTL,
		'refresh_token' => $refresh,
		'scope'         => 'website',
	);
}

/** Prüft ein Access-Token. */
function arcp_oauth_valid_access( $token ) {
	if ( 0 !== strpos( (string) $token, 'arwa_' ) ) {
		return false;
	}
	$tokens = arcp_oauth_get( 'tokens' );
	$t      = $tokens[ arcp_oauth_hash( $token ) ] ?? null;
	return $t && 'a' === $t['t'] && (int) $t['e'] > time();
}

function arcp_oauth_connection_count() {
	$clients = array();
	foreach ( arcp_oauth_tokens_pruned() as $t ) {
		if ( 'r' === $t['t'] ) {
			$clients[ $t['c'] ] = true;
		}
	}
	return count( $clients );
}

function arcp_oauth_revoke_all() {
	arcp_oauth_put( 'tokens', array() );
}

/* ---------------------------------------------------------------------------
 * Authorization-Header lesen (Strato & Co. reichen ihn teils nur umbenannt weiter)
 * ------------------------------------------------------------------------- */

function arcp_auth_header() {
	foreach ( array( 'HTTP_AUTHORIZATION', 'REDIRECT_HTTP_AUTHORIZATION', 'REDIRECT_REDIRECT_HTTP_AUTHORIZATION' ) as $k ) {
		if ( ! empty( $_SERVER[ $k ] ) ) {
			return (string) $_SERVER[ $k ];
		}
	}
	if ( function_exists( 'getallheaders' ) ) {
		foreach ( (array) getallheaders() as $name => $value ) {
			if ( 'authorization' === strtolower( $name ) ) {
				return (string) $value;
			}
		}
	}
	return '';
}

function arcp_bearer() {
	$h = arcp_auth_header();
	return 0 === stripos( $h, 'Bearer ' ) ? trim( substr( $h, 7 ) ) : '';
}

/* ---------------------------------------------------------------------------
 * Öffentliche Adressen: /.well-known/… und /arcp-oauth/authorize
 * ------------------------------------------------------------------------- */

add_action( 'init', 'arcp_oauth_router', 1 );

function arcp_oauth_router() {
	$path = (string) wp_parse_url( (string) ( $_SERVER['REQUEST_URI'] ?? '' ), PHP_URL_PATH );
	$base = untrailingslashit( (string) wp_parse_url( home_url(), PHP_URL_PATH ) );
	if ( '' !== $base && 0 === strpos( $path, $base ) ) {
		$path = substr( $path, strlen( $base ) );
	}
	$path = untrailingslashit( $path );

	if ( 0 === strpos( $path, '/.well-known/oauth-protected-resource' ) ) {
		arcp_oauth_json( arcp_oauth_resource_metadata() );
	}
	if ( 0 === strpos( $path, '/.well-known/oauth-authorization-server' ) || 0 === strpos( $path, '/.well-known/openid-configuration' ) ) {
		arcp_oauth_json( arcp_oauth_server_metadata() );
	}
	if ( '/arcp-oauth/authorize' === $path ) {
		arcp_oauth_authorize_page();
	}
}

function arcp_oauth_json( $data, $status = 200 ) {
	status_header( $status );
	nocache_headers();
	header( 'Content-Type: application/json; charset=utf-8' );
	header( 'Access-Control-Allow-Origin: *' );
	echo wp_json_encode( $data, JSON_UNESCAPED_SLASHES );
	exit;
}

/* ---------------------------------------------------------------------------
 * Anmeldeseite
 * ------------------------------------------------------------------------- */

function arcp_oauth_authorize_page() {
	$src  = 'POST' === $_SERVER['REQUEST_METHOD'] ? $_POST : $_GET; // phpcs:ignore
	$p    = array();
	foreach ( array( 'response_type', 'client_id', 'redirect_uri', 'state', 'code_challenge', 'code_challenge_method', 'scope', 'resource' ) as $k ) {
		$p[ $k ] = isset( $src[ $k ] ) ? sanitize_text_field( wp_unslash( $src[ $k ] ) ) : '';
	}
	$p['redirect_uri'] = isset( $src['redirect_uri'] ) ? esc_url_raw( wp_unslash( $src['redirect_uri'] ), array( 'http', 'https' ) ) : '';

	$clients = arcp_oauth_get( 'clients' );
	$client  = $clients[ $p['client_id'] ] ?? null;

	// Ohne gültigen Client oder Rücksprung-Adresse niemals weiterleiten (Schutz vor offenen Weiterleitungen).
	if ( ! $client || ! in_array( $p['redirect_uri'], (array) $client['redirect_uris'], true ) ) {
		arcp_oauth_html( 'Ungültige Anfrage', '<p>Unbekannter Client oder ungültige Rücksprung-Adresse. Bitte die Verbindung in Claude neu starten.</p>' );
	}
	$fail = function ( $error, $desc ) use ( $p ) {
		wp_redirect( add_query_arg( array_filter( array( 'error' => $error, 'error_description' => $desc, 'state' => $p['state'] ) ), $p['redirect_uri'] ) );
		exit;
	};
	if ( 'code' !== $p['response_type'] ) {
		$fail( 'unsupported_response_type', 'Nur response_type=code.' );
	}
	if ( '' === $p['code_challenge'] || 'S256' !== $p['code_challenge_method'] ) {
		$fail( 'invalid_request', 'PKCE mit S256 ist erforderlich.' );
	}

	if ( ! is_user_logged_in() ) {
		wp_safe_redirect( wp_login_url( add_query_arg( $p, arcp_oauth_path( '/arcp-oauth/authorize' ) ) ) );
		exit;
	}
	$user = wp_get_current_user();
	if ( arcp_is_claude_user() || ! current_user_can( 'edit_others_posts' ) ) {
		arcp_oauth_html( 'Keine Berechtigung', '<p>Mit diesem WordPress-Benutzer („' . esc_html( $user->display_name ) . '“) kann die Verbindung nicht erlaubt werden. Nötig ist ein Konto mit mindestens der Rolle Redakteur – nicht der Claude-Benutzer.</p><p><a href="' . esc_url( wp_logout_url( add_query_arg( $p, arcp_oauth_path( '/arcp-oauth/authorize' ) ) ) ) . '">Mit anderem Konto anmelden</a></p>' );
	}
	$claude = get_userdata( (int) arcp_setting( 'claude_user_id' ) );
	if ( ! $claude ) {
		arcp_oauth_html( 'Noch nicht eingerichtet', '<p>In WordPress unter Claude → Einstellungen zuerst den Claude-Benutzer auswählen.</p>' );
	}

	if ( 'POST' === $_SERVER['REQUEST_METHOD'] ) {
		check_admin_referer( 'arcp_oauth_authorize' );
		if ( empty( $_POST['erlauben'] ) ) {
			$fail( 'access_denied', 'Verbindung abgelehnt.' );
		}
		$code  = wp_generate_password( 40, false, false );
		$codes = arcp_oauth_get( 'codes' );
		foreach ( $codes as $h => $c ) {
			if ( $c['e'] < time() ) {
				unset( $codes[ $h ] );
			}
		}
		$codes[ arcp_oauth_hash( $code ) ] = array(
			'c' => $p['client_id'],
			'r' => $p['redirect_uri'],
			'x' => $p['code_challenge'],
			'u' => get_current_user_id(),
			'e' => time() + 10 * MINUTE_IN_SECONDS,
		);
		arcp_oauth_put( 'codes', $codes );
		wp_redirect( add_query_arg( array_filter( array( 'code' => $code, 'state' => $p['state'] ) ), $p['redirect_uri'] ) );
		exit;
	}

	$hidden = '';
	foreach ( $p as $k => $v ) {
		$hidden .= '<input type="hidden" name="' . esc_attr( $k ) . '" value="' . esc_attr( $v ) . '">';
	}
	$host = wp_parse_url( $p['redirect_uri'], PHP_URL_HOST );
	arcp_oauth_html(
		'Claude verbinden',
		'<p><strong>' . esc_html( $client['client_name'] ?: 'Claude' ) . '</strong> möchte die Website <strong>' . esc_html( wp_parse_url( home_url(), PHP_URL_HOST ) ) . '</strong> pflegen.</p>
		<ul>
			<li>Claude arbeitet als Benutzer <strong>„' . esc_html( $claude->display_name ) . '“</strong> (Rolle Redakteur, ohne Veröffentlichungsrecht).</li>
			<li>Änderungen werden nur als Vorschlag mit Vorschau gespeichert. Live geht etwas erst nach ausdrücklicher Freigabe im Chat.</li>
			<li>Die Verbindung kann jederzeit in WordPress unter „Claude“ getrennt werden.</li>
		</ul>
		<p style="color:#666;font-size:13px">Rücksprung zu: ' . esc_html( $host ) . ' · Angemeldet als ' . esc_html( $user->display_name ) . '</p>
		<form method="post">' . wp_nonce_field( 'arcp_oauth_authorize', '_wpnonce', true, false ) . $hidden . '
			<button name="erlauben" value="1" style="background:#2271b1;color:#fff;border:0;padding:10px 18px;border-radius:5px;font-size:15px;cursor:pointer">Erlauben</button>
			<button name="ablehnen" value="1" style="background:#fff;color:#333;border:1px solid #ccc;padding:10px 18px;border-radius:5px;font-size:15px;cursor:pointer;margin-left:8px">Ablehnen</button>
		</form>'
	);
}

function arcp_oauth_html( $title, $body ) {
	status_header( 200 );
	nocache_headers();
	header( 'Content-Type: text/html; charset=utf-8' );
	header( 'X-Frame-Options: DENY' );
	echo '<!doctype html><html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex"><title>' . esc_html( $title ) . '</title></head>'
		. '<body style="margin:0;background:#f0f0f1;font:15px/1.5 -apple-system,BlinkMacSystemFont,Segoe UI,Roboto,sans-serif;color:#1d2327">'
		. '<div style="max-width:520px;margin:8vh auto;background:#fff;padding:28px 32px;border-radius:10px;box-shadow:0 2px 12px rgba(0,0,0,.08)">'
		. '<h1 style="font-size:22px;margin:0 0 14px">' . esc_html( $title ) . '</h1>' . $body . '</div></body></html>';
	exit;
}

/* ---------------------------------------------------------------------------
 * REST: Registrierung und Token
 * ------------------------------------------------------------------------- */

add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			ARCP_NS,
			'/oauth/register',
			array(
				'methods'             => 'POST',
				'callback'            => 'arcp_oauth_register',
				'permission_callback' => '__return_true',
			)
		);
		register_rest_route(
			ARCP_NS,
			'/oauth/token',
			array(
				'methods'             => 'POST',
				'callback'            => 'arcp_oauth_token',
				'permission_callback' => '__return_true',
			)
		);
		register_rest_route(
			ARCP_NS,
			'/oauth/diag',
			array(
				'methods'             => 'GET',
				'callback'            => function () {
					$h = arcp_auth_header();
					return array( 'authorization_header_received' => '' !== $h, 'scheme' => '' !== $h ? strtok( $h, ' ' ) : null );
				},
				'permission_callback' => '__return_true',
			)
		);
	}
);

function arcp_oauth_error( $error, $desc, $status = 400 ) {
	return new WP_REST_Response( array( 'error' => $error, 'error_description' => $desc ), $status );
}

function arcp_oauth_register( WP_REST_Request $req ) {
	$body = $req->get_json_params();
	$body = is_array( $body ) ? $body : $req->get_body_params();
	$uris = array_values( array_filter( (array) ( $body['redirect_uris'] ?? array() ), 'is_string' ) );
	if ( ! $uris ) {
		return arcp_oauth_error( 'invalid_redirect_uri', 'redirect_uris fehlt.' );
	}
	foreach ( $uris as $u ) {
		if ( ! arcp_oauth_redirect_allowed( $u ) ) {
			return arcp_oauth_error( 'invalid_redirect_uri', 'Rücksprung-Adresse nicht erlaubt: ' . $u );
		}
	}
	$clients = arcp_oauth_get( 'clients' );
	if ( count( $clients ) >= 100 ) {
		uasort( $clients, function ( $a, $b ) { return $a['t'] <=> $b['t']; } );
		$clients = array_slice( $clients, -80, null, true );
	}
	$id             = 'arc_' . wp_generate_password( 24, false, false );
	$name           = sanitize_text_field( (string) ( $body['client_name'] ?? 'Claude' ) );
	$clients[ $id ] = array( 'redirect_uris' => $uris, 'client_name' => mb_substr( $name, 0, 80 ), 't' => time() );
	arcp_oauth_put( 'clients', $clients );

	return new WP_REST_Response(
		array(
			'client_id'                  => $id,
			'client_id_issued_at'        => time(),
			'client_name'                => $clients[ $id ]['client_name'],
			'redirect_uris'              => $uris,
			'grant_types'                => array( 'authorization_code', 'refresh_token' ),
			'response_types'             => array( 'code' ),
			'token_endpoint_auth_method' => 'none',
		),
		201
	);
}

function arcp_oauth_b64url( $bin ) {
	return rtrim( strtr( base64_encode( $bin ), '+/', '-_' ), '=' );
}

function arcp_oauth_token( WP_REST_Request $req ) {
	$grant  = (string) $req->get_param( 'grant_type' );
	$client = (string) $req->get_param( 'client_id' );

	if ( 'authorization_code' === $grant ) {
		$code  = (string) $req->get_param( 'code' );
		$codes = arcp_oauth_get( 'codes' );
		$h     = arcp_oauth_hash( $code );
		$c     = $codes[ $h ] ?? null;
		unset( $codes[ $h ] ); // Einmalig verwendbar.
		arcp_oauth_put( 'codes', $codes );

		if ( ! $c || $c['e'] < time() ) {
			return arcp_oauth_error( 'invalid_grant', 'Code ungültig oder abgelaufen.' );
		}
		if ( '' !== $client && $client !== $c['c'] ) {
			return arcp_oauth_error( 'invalid_grant', 'Client passt nicht zum Code.' );
		}
		if ( (string) $req->get_param( 'redirect_uri' ) !== $c['r'] ) {
			return arcp_oauth_error( 'invalid_grant', 'redirect_uri passt nicht.' );
		}
		$verifier = (string) $req->get_param( 'code_verifier' );
		if ( '' === $verifier || ! hash_equals( $c['x'], arcp_oauth_b64url( hash( 'sha256', $verifier, true ) ) ) ) {
			return arcp_oauth_error( 'invalid_grant', 'PKCE-Prüfung fehlgeschlagen.' );
		}
		return new WP_REST_Response( arcp_oauth_issue( $c['c'], $c['u'] ), 200, array( 'Cache-Control' => 'no-store' ) );
	}

	if ( 'refresh_token' === $grant ) {
		$refresh = (string) $req->get_param( 'refresh_token' );
		$tokens  = arcp_oauth_tokens_pruned();
		$h       = arcp_oauth_hash( $refresh );
		$t       = $tokens[ $h ] ?? null;
		if ( ! $t || 'r' !== $t['t'] || ( '' !== $client && $client !== $t['c'] ) ) {
			return arcp_oauth_error( 'invalid_grant', 'Refresh-Token ungültig oder abgelaufen.' );
		}
		unset( $tokens[ $h ] ); // Rotation: altes Refresh-Token wird ungültig.
		arcp_oauth_put( 'tokens', $tokens );
		return new WP_REST_Response( arcp_oauth_issue( $t['c'], $t['u'] ), 200, array( 'Cache-Control' => 'no-store' ) );
	}

	return arcp_oauth_error( 'unsupported_grant_type', 'Nur authorization_code und refresh_token.' );
}

/* ---------------------------------------------------------------------------
 * Anmeldung am MCP-Endpunkt per Bearer-Token
 * ------------------------------------------------------------------------- */

add_filter(
	'determine_current_user',
	function ( $user_id ) {
		if ( $user_id || ! arcp_request_is_ours() ) {
			return $user_id;
		}
		$claude = (int) arcp_setting( 'claude_user_id' );
		if ( $claude && arcp_oauth_valid_access( arcp_bearer() ) ) {
			return $claude;
		}
		return $user_id;
	},
	25
);

/* ---------------------------------------------------------------------------
 * .htaccess: Authorization-Header an PHP durchreichen (Strato filtert ihn sonst)
 * ------------------------------------------------------------------------- */

function arcp_htaccess_lines() {
	return array(
		'<IfModule mod_setenvif.c>',
		'SetEnvIf Authorization "(.+)" HTTP_AUTHORIZATION=$1',
		'</IfModule>',
	);
}

function arcp_htaccess_install() {
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/misc.php';
	$file = get_home_path() . '.htaccess';
	if ( ! file_exists( $file ) || ! is_writable( $file ) ) {
		return false;
	}
	return insert_with_markers( $file, 'AR Claude Authorization', arcp_htaccess_lines() );
}

function arcp_htaccess_remove() {
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/misc.php';
	$file = get_home_path() . '.htaccess';
	if ( file_exists( $file ) && is_writable( $file ) ) {
		insert_with_markers( $file, 'AR Claude Authorization', array() );
	}
}

<?php
/**
 * Eigener MCP-Server mit genau unseren Werkzeugen.
 * Endpunkt: https://<domain>/wp-json/ar-website/mcp
 *
 * Vorteil gegenüber dem Standard-Server des MCP Adapters: Claude sieht nur diese
 * Werkzeuge direkt in der Werkzeugliste – nichts anderes aus WordPress.
 */

defined( 'ABSPATH' ) || exit;

function arcp_mcp_endpoint() {
	return rest_url( ARCP_NS . '/mcp' );
}

function arcp_mcp_available() {
	return class_exists( 'WP\MCP\Core\McpAdapter' );
}

/**
 * Adapter selbst starten. Nötig, wenn der MCP Adapter nur als Bibliothek vorhanden ist
 * (z. B. von WooCommerce mitgebracht) und nicht als eigenes, aktives Plugin.
 * instance() ist ein Singleton – ein doppelter Aufruf schadet nicht.
 */
add_action(
	'plugins_loaded',
	function () {
		if ( arcp_mcp_available() ) {
			\WP\MCP\Core\McpAdapter::instance();
		}
	},
	20
);

/** Prüft, ob unser MCP-Endpunkt tatsächlich als REST-Route registriert ist. */
function arcp_mcp_route_ok() {
	$routes = rest_get_server()->get_routes( ARCP_NS );
	return ! empty( $routes['/' . ARCP_NS . '/mcp'] );
}

add_action(
	'mcp_adapter_init',
	function ( $adapter ) {
		if ( ! is_object( $adapter ) || ! method_exists( $adapter, 'create_server' ) ) {
			return;
		}
		$transport = 'WP\MCP\Transport\HttpTransport';
		$errors    = 'WP\MCP\Infrastructure\ErrorHandling\ErrorLogMcpErrorHandler';
		$observe   = 'WP\MCP\Infrastructure\Observability\NullMcpObservabilityHandler';
		if ( ! class_exists( $transport ) || ! class_exists( $errors ) || ! class_exists( $observe ) ) {
			return;
		}

		$adapter->create_server(
			'ar-website',
			ARCP_NS,
			'mcp',
			'A/R Architekten Website',
			'Projekte und Seiten der Website ararchitekten.de pflegen. Änderungen an veröffentlichten Inhalten werden als Vorschlag mit Vorschau gespeichert und erst nach menschlicher Freigabe live geschaltet.',
			ARCP_VERSION,
			array( $transport ),
			$errors,
			$observe,
			arcp_ability_names(),
			array(),
			array()
		);
	}
);

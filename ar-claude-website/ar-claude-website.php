<?php
/**
 * Plugin Name:       A/R Website für Claude
 * Description:       Stellt Claude (per MCP) klar begrenzte Werkzeuge bereit: Projekte anlegen und ändern, Texte und Bilder auf Seiten ändern. Änderungen an veröffentlichten Inhalten gehen nie direkt live, sondern landen als Vorschlag mit Vorschau-Link und werden erst nach Freigabe im Chat live. Benötigt das Plugin „MCP Adapter“.
 * Version:           1.4.0
 * Requires at least: 6.9
 * Requires PHP:      7.4
 * Author:            A/R Architekten
 * License:           GPL-2.0-or-later
 * Text Domain:       ar-claude-website
 */

defined( 'ABSPATH' ) || exit;

define( 'ARCP_VERSION', '1.4.0' );
define( 'ARCP_DIR', plugin_dir_path( __FILE__ ) );

/** Struktur der Seite (Theme „Prague“ + PixFields + WPBakery). */
define( 'ARCP_POST_TYPE', 'projects' ); // Interner Name; in der URL heißt es /projects-item/
define( 'ARCP_TAXONOMY', 'projects-category' );
define( 'ARCP_THEME_META', 'prague_post_options' );
define( 'ARCP_NS', 'ar-website' ); // Namespace der Abilities und des MCP-Servers.

require_once ARCP_DIR . 'includes/settings.php';
require_once ARCP_DIR . 'includes/helpers.php';
require_once ARCP_DIR . 'includes/wpbakery.php';
require_once ARCP_DIR . 'includes/changes.php';
require_once ARCP_DIR . 'includes/abilities.php';
require_once ARCP_DIR . 'includes/mcp-server.php';
require_once ARCP_DIR . 'includes/oauth.php';
require_once ARCP_DIR . 'includes/uploads.php';
require_once ARCP_DIR . 'includes/security.php';

if ( is_admin() ) {
	require_once ARCP_DIR . 'includes/admin.php';
}

register_deactivation_hook( __FILE__, function () {
	if ( function_exists( 'arcp_htaccess_remove' ) ) {
		arcp_htaccess_remove();
	}
} );

<?php
/**
 * Backend: Einstellungen (Menüpunkt „Claude“).
 */

defined( 'ABSPATH' ) || exit;

add_action( 'admin_menu', 'arcp_admin_menu' );

function arcp_admin_menu() {
	add_menu_page( 'Claude – Einstellungen', 'Claude', 'manage_options', 'arcp-einstellungen', 'arcp_page_settings', 'dashicons-admin-generic', 3 );
}

function arcp_notice() {
	$msgs = array(
						'gespeichert' => array( 'success', 'Einstellungen gespeichert.' ),
		'abgeglichen' => array( 'success', 'Kategorien wurden abgeglichen.' ),
		'htaccess'    => array( 'success', '.htaccess ergänzt – der Authorization-Header wird jetzt an WordPress weitergereicht.' ),
		'getrennt'    => array( 'success', 'Alle Claude-Verbindungen wurden getrennt.' ),
	);
	if ( isset( $_GET['arcp_msg'], $msgs[ $_GET['arcp_msg'] ] ) ) {
		list( $type, $text ) = $msgs[ $_GET['arcp_msg'] ];
		echo '<div class="notice notice-' . esc_attr( $type ) . ' is-dismissible"><p>' . esc_html( $text ) . '</p></div>';
	}
	if ( isset( $_GET['arcp_err'] ) ) {
		echo '<div class="notice notice-error"><p>' . esc_html( wp_unslash( $_GET['arcp_err'] ) ) . '</p></div>';
	}
}

/* ===========================================================================
 * Einstellungen, Systemcheck, Kategorien-Abgleich
 * ========================================================================= */

function arcp_checks() {
	$s      = arcp_settings();
	$fields = get_option( 'pixfields_list' );
	$keys   = array();
	if ( is_array( $fields ) && isset( $fields[ ARCP_POST_TYPE ] ) ) {
		foreach ( (array) $fields[ ARCP_POST_TYPE ] as $f ) {
			if ( isset( $f['meta_key'] ) ) {
				$keys[] = 'pixfield_' . $f['meta_key'];
			}
		}
	}
	$missing = array_diff( array_values( arcp_field_map() ), $keys );
	$claude  = get_userdata( (int) $s['claude_user_id'] );

	return array(
		array( 'Abilities API (WordPress 6.9+)', function_exists( 'wp_register_ability' ), 'WordPress aktualisieren.' ),
		array( 'MCP Adapter vorhanden', arcp_mcp_available(), 'MCP Adapter von github.com/WordPress/mcp-adapter/releases installieren und aktivieren.' ),
		array( 'MCP-Endpunkt erreichbar (' . arcp_mcp_endpoint() . ')', arcp_mcp_available() && arcp_mcp_route_ok(), 'Der Adapter startet nicht oder ist zu alt. Offizielles Plugin „MCP Adapter“ installieren und aktivieren.' ),
		array( 'Projekt-Typ „' . ARCP_POST_TYPE . '“', post_type_exists( ARCP_POST_TYPE ), '„Prague Plugins“ aktivieren.' ),
		array( 'Projekt-Kategorien „' . ARCP_TAXONOMY . '“', taxonomy_exists( ARCP_TAXONOMY ), '„Prague Plugins“ aktivieren.' ),
		array( 'PixFields-Felder vorhanden', empty( $missing ), 'Fehlend: ' . implode( ', ', $missing ) ),
		array( 'Vorlage-Projekt hat Theme-Optionen', is_array( get_post_meta( (int) $s['vorlage_id'], ARCP_THEME_META, true ) ), 'Andere Vorlage-ID wählen (ein Projekt mit Slider).' ),
		array( 'Claude-Benutzer gewählt', (bool) $claude, 'Unten den WordPress-Benutzer „Claude“ auswählen.' ),
		array( 'Claude-Benutzer ist kein Administrator', $claude && ! user_can( $claude, 'manage_options' ), 'Dem Claude-Benutzer die Rolle „Redakteur“ geben.' ),
	);
}

function arcp_category_plan() {
	$s    = arcp_settings();
	$plan = array();
	$ids  = get_posts( array( 'post_type' => ARCP_POST_TYPE, 'post_status' => array( 'publish', 'pending', 'draft', 'private' ), 'posts_per_page' => -1, 'fields' => 'ids' ) );
	foreach ( $ids as $id ) {
		$raw     = trim( (string) get_post_meta( $id, 'pixfield_category', true ) );
		$match   = '' === $raw ? null : arcp_match_list( $raw, $s['kategorien'], 'Kategorie' );
		$current = wp_get_object_terms( $id, ARCP_TAXONOMY, array( 'fields' => 'names' ) );
		$plan[]  = array(
			'id'      => $id,
			'titel'   => get_the_title( $id ),
			'feld'    => $raw,
			'aktuell' => is_wp_error( $current ) ? array() : $current,
			'neu'     => ( $match && ! is_wp_error( $match ) ) ? $match : null,
		);
	}
	return $plan;
}

function arcp_page_settings() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$s    = arcp_settings();
	$plan = taxonomy_exists( ARCP_TAXONOMY ) ? arcp_category_plan() : array();
	$user = get_userdata( (int) $s['claude_user_id'] );
	?>
	<div class="wrap">
		<h1>Claude – Einstellungen</h1>
		<?php arcp_notice(); ?>

		<h2>Systemcheck</h2>
		<table class="widefat striped" style="max-width:900px">
			<?php foreach ( arcp_checks() as $c ) : ?>
				<tr>
					<td style="width:30px"><?php echo $c[1] ? '✅' : '❌'; ?></td>
					<td><?php echo esc_html( $c[0] ); ?></td>
					<td><?php echo $c[1] ? '' : esc_html( $c[2] ); ?></td>
				</tr>
			<?php endforeach; ?>
		</table>

		<h2>Verbindung</h2>
		<p>MCP-Endpunkt: <code><?php echo esc_html( arcp_mcp_endpoint() ); ?></code></p>

		<h3>Connector in Claude (Web, Desktop, Handy)</h3>
		<p>In Claude → Anpassen → Konnektoren → Hinzufügen → „Benutzerdefinierten Connector hinzufügen“:<br>
			Name: <code>ar-website</code> · URL: <code><?php echo esc_html( arcp_mcp_endpoint() ); ?></code><br>
			Beim Verbinden öffnet sich eine Anmeldeseite dieser Website: mit WordPress-Konto (mind. Redakteur) anmelden und „Erlauben“ klicken.</p>
		<?php $auth_ok = arcp_check_auth_header(); ?>
		<p>Aktive Verbindungen: <strong><?php echo (int) arcp_oauth_connection_count(); ?></strong></p>
		<p>Authorization-Header vom Server: <?php echo '1' === $auth_ok ? '✅ kommt an' : ( '0' === $auth_ok ? '❌ wird vom Hosting herausgefiltert – unten aktivieren' : 'noch nicht geprüft' ); ?></p>
		<div style="display:flex;gap:8px;flex-wrap:wrap">
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'arcp_auth_fix' ); ?>
				<input type="hidden" name="action" value="arcp_auth_fix">
				<?php submit_button( 'Authorization-Header aktivieren (.htaccess)', 'secondary', 'submit', false ); ?>
			</form>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('Alle Claude-Verbindungen trennen? Claude muss sich danach neu anmelden.');">
				<?php wp_nonce_field( 'arcp_revoke' ); ?>
				<input type="hidden" name="action" value="arcp_revoke">
				<?php submit_button( 'Alle Verbindungen trennen', 'secondary', 'submit', false ); ?>
			</form>
		</div>


		<h2>Einstellungen</h2>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'arcp_settings' ); ?>
			<input type="hidden" name="action" value="arcp_save_settings">
			<table class="form-table">
				<tr>
					<th>Claude-Benutzer</th>
					<td>
						<?php wp_dropdown_users( array( 'name' => 'claude_user_id', 'selected' => (int) $s['claude_user_id'], 'show_option_none' => '— bitte wählen —', 'option_none_value' => 0 ) ); ?>
						<p class="description">Dieser Benutzer darf nur den MCP-Server nutzen, nie veröffentlichen und kommt nicht ins Backend.</p>
					</td>
				</tr>
				<tr>
					<th>Vorlage-Projekt (ID)</th>
					<td><input type="number" name="vorlage_id" value="<?php echo (int) $s['vorlage_id']; ?>">
						<p class="description">Von diesem Projekt übernehmen neue Projekte die Darstellungs-Einstellungen (Slider-Stil usw.).</p></td>
				</tr>
				<?php foreach ( array( 'kategorien' => 'Kategorien', 'arten' => 'Art des Projekts', 'staende' => 'Aktueller Stand' ) as $key => $label ) : ?>
					<tr>
						<th><?php echo esc_html( $label ); ?></th>
						<td><textarea name="<?php echo esc_attr( $key ); ?>" rows="6" cols="40"><?php echo esc_textarea( implode( "\n", $s[ $key ] ) ); ?></textarea>
							<p class="description">Ein Wert pro Zeile. Claude kann nur diese Werte verwenden.</p></td>
					</tr>
				<?php endforeach; ?>
			</table>
			<?php submit_button( 'Speichern' ); ?>
		</form>

		<?php if ( $plan ) : ?>
			<h2>Kategorien abgleichen</h2>
			<p>Setzt bei allen Projekten die Projekt-Kategorie (für Filter/Listen) auf den Wert aus dem Feld „Kategorie“, sofern er in der Liste oben steht. Fehlende deutsche Kategorien werden angelegt. Alte englische Kategorien werden nicht gelöscht – das kannst du danach unter Projects → Categories selbst tun.</p>
			<table class="widefat striped" style="max-width:1000px">
				<thead><tr><th>Projekt</th><th>Feld „Kategorie“</th><th>Kategorie jetzt</th><th>Kategorie neu</th></tr></thead>
				<?php foreach ( $plan as $row ) : ?>
					<tr>
						<td><?php echo esc_html( $row['titel'] ); ?></td>
						<td><?php echo esc_html( $row['feld'] ?: '–' ); ?></td>
						<td><?php echo esc_html( $row['aktuell'] ? implode( ', ', $row['aktuell'] ) : '–' ); ?></td>
						<td><?php echo $row['neu'] ? '<strong>' . esc_html( $row['neu'] ) . '</strong>' : '<span style="color:#b32d2e">kein passender Wert – manuell prüfen</span>'; ?></td>
					</tr>
				<?php endforeach; ?>
			</table>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:12px" onsubmit="return confirm('Kategorien jetzt wie in der Tabelle setzen?');">
				<?php wp_nonce_field( 'arcp_sync' ); ?>
				<input type="hidden" name="action" value="arcp_sync_categories">
				<?php submit_button( 'Kategorien abgleichen', 'secondary', 'submit', false ); ?>
			</form>
		<?php endif; ?>
	</div>
	<?php
}

add_action(
	'admin_post_arcp_save_settings',
	function () {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Keine Berechtigung.' );
		}
		check_admin_referer( 'arcp_settings' );
		$lines = function ( $key ) {
			$raw = (string) wp_unslash( $_POST[ $key ] ?? '' );
			return array_values( array_unique( array_filter( array_map( 'arcp_clean_line', explode( "\n", $raw ) ) ) ) );
		};
		$new = array(
			'claude_user_id' => absint( $_POST['claude_user_id'] ?? 0 ),
			'vorlage_id'     => absint( $_POST['vorlage_id'] ?? 0 ),
			'kategorien'     => $lines( 'kategorien' ),
			'arten'          => $lines( 'arten' ),
			'staende'        => $lines( 'staende' ),
		);
		$defaults = arcp_default_settings();
		foreach ( array( 'kategorien', 'arten', 'staende' ) as $k ) {
			if ( ! $new[ $k ] ) {
				$new[ $k ] = $defaults[ $k ];
			}
		}
		update_option( 'arcp_settings', $new );
		wp_safe_redirect( admin_url( 'admin.php?page=arcp-einstellungen&arcp_msg=gespeichert' ) );
		exit;
	}
);

add_action(
	'admin_post_arcp_sync_categories',
	function () {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Keine Berechtigung.' );
		}
		check_admin_referer( 'arcp_sync' );
		foreach ( arcp_category_plan() as $row ) {
			if ( $row['neu'] ) {
				arcp_set_category( $row['id'], $row['neu'] );
				// Feld-Schreibweise vereinheitlichen.
				update_post_meta( $row['id'], 'pixfield_category', wp_slash( $row['neu'] ) );
			}
		}
		wp_safe_redirect( admin_url( 'admin.php?page=arcp-einstellungen&arcp_msg=abgeglichen' ) );
		exit;
	}
);

add_action(
	'admin_post_arcp_auth_fix',
	function () {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Keine Berechtigung.' );
		}
		check_admin_referer( 'arcp_auth_fix' );
		$ok = arcp_htaccess_install();
		delete_transient( 'arcp_auth_header_ok' );
		wp_safe_redirect( admin_url( 'admin.php?page=arcp-einstellungen&' . ( $ok ? 'arcp_msg=htaccess' : 'arcp_err=' . rawurlencode( '.htaccess konnte nicht geschrieben werden.' ) ) ) );
		exit;
	}
);

add_action(
	'admin_post_arcp_revoke',
	function () {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Keine Berechtigung.' );
		}
		check_admin_referer( 'arcp_revoke' );
		arcp_oauth_revoke_all();
		wp_safe_redirect( admin_url( 'admin.php?page=arcp-einstellungen&arcp_msg=getrennt' ) );
		exit;
	}
);

/** Prüft per Selbstaufruf, ob ein Authorization-Header bei PHP ankommt. Ergebnis 10 Minuten gespeichert. */
function arcp_check_auth_header( $force = false ) {
	$cached = get_transient( 'arcp_auth_header_ok' );
	if ( false !== $cached && ! $force ) {
		return $cached;
	}
	$res = wp_remote_get( rest_url( ARCP_NS . '/oauth/diag' ), array( 'timeout' => 8, 'headers' => array( 'Authorization' => 'Bearer pruefung' ), 'sslverify' => false ) );
	if ( is_wp_error( $res ) ) {
		return '';
	}
	$data = json_decode( wp_remote_retrieve_body( $res ), true );
	$ok   = ! empty( $data['authorization_header_received'] ) ? '1' : '0';
	set_transient( 'arcp_auth_header_ok', $ok, 10 * MINUTE_IN_SECONDS );
	return $ok;
}

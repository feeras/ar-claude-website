<?php
/**
 * Bilder-Upload per Einmal-Link.
 *
 * Claude kann im Chat angehängte Dateien nicht an Connectors weitergeben. Deshalb erzeugt
 * Claude einen Upload-Link (gültig 2 Stunden, ohne Login, auch am Handy). Der Nutzer wählt
 * dort die Fotos aus, sie landen direkt in der Mediathek, und Claude fragt den Stand ab.
 */

defined( 'ABSPATH' ) || exit;

define( 'ARCP_UPLOAD_TTL', 2 * HOUR_IN_SECONDS );
define( 'ARCP_UPLOAD_MAX_FILES', 40 );
define( 'ARCP_UPLOAD_MAX_MB', 30 );

/* ---------------------------------------------------------------------------
 * Speicher
 * ------------------------------------------------------------------------- */

function arcp_uploads_all() {
	$all = get_option( 'arcp_uploads', array() );
	$all = is_array( $all ) ? $all : array();
	foreach ( $all as $id => $b ) {
		if ( $b['e'] < time() - DAY_IN_SECONDS ) { // abgelaufene nach einem Tag vergessen
			unset( $all[ $id ] );
		}
	}
	return $all;
}

function arcp_uploads_save( $all ) {
	update_option( 'arcp_uploads', $all, false );
}

function arcp_upload_create( $hinweis ) {
	$token = wp_generate_password( 32, false, false );
	$id    = 'up' . wp_rand( 100000, 999999 );
	$all   = arcp_uploads_all();
	$all[ $id ] = array(
		'h'   => hash_hmac( 'sha256', $token, wp_salt( 'auth' ) ),
		'e'   => time() + ARCP_UPLOAD_TTL,
		'u'   => get_current_user_id(),
		'txt' => mb_substr( arcp_clean_line( $hinweis ), 0, 80 ),
		'ids' => array(),
	);
	arcp_uploads_save( $all );
	return array( $id, $token );
}

function arcp_upload_find( $token ) {
	$h = hash_hmac( 'sha256', (string) $token, wp_salt( 'auth' ) );
	foreach ( arcp_uploads_all() as $id => $b ) {
		if ( hash_equals( $b['h'], $h ) ) {
			return array( $id, $b );
		}
	}
	return array( null, null );
}

function arcp_upload_url( $token ) {
	return untrailingslashit( home_url() ) . '/arcp-upload/' . rawurlencode( $token );
}

/* ---------------------------------------------------------------------------
 * Upload-Seite: /arcp-upload/<token>
 * ------------------------------------------------------------------------- */

add_action( 'init', 'arcp_upload_router', 2 );

function arcp_upload_router() {
	$path = (string) wp_parse_url( (string) ( $_SERVER['REQUEST_URI'] ?? '' ), PHP_URL_PATH );
	$base = untrailingslashit( (string) wp_parse_url( home_url(), PHP_URL_PATH ) );
	if ( '' !== $base && 0 === strpos( $path, $base ) ) {
		$path = substr( $path, strlen( $base ) );
	}
	if ( ! preg_match( '#^/arcp-upload/([A-Za-z0-9]{20,64})/?$#', $path, $m ) ) {
		return;
	}
	list( $id, $batch ) = arcp_upload_find( $m[1] );

	if ( 'POST' === $_SERVER['REQUEST_METHOD'] ) {
		arcp_upload_receive( $id, $batch );
	}

	if ( ! $batch || $batch['e'] < time() ) {
		arcp_oauth_html( 'Link abgelaufen', '<p>Dieser Upload-Link ist nicht mehr gültig. Bitte Claude im Chat um einen neuen Link bitten.</p>' );
	}
	arcp_upload_page( $batch );
}

function arcp_upload_json( $data, $status = 200 ) {
	status_header( $status );
	nocache_headers();
	header( 'Content-Type: application/json; charset=utf-8' );
	echo wp_json_encode( $data );
	exit;
}

function arcp_upload_receive( $id, $batch ) {
	if ( ! $batch || $batch['e'] < time() ) {
		arcp_upload_json( array( 'ok' => false, 'fehler' => 'Link abgelaufen.' ), 403 );
	}
	if ( count( $batch['ids'] ) >= ARCP_UPLOAD_MAX_FILES ) {
		arcp_upload_json( array( 'ok' => false, 'fehler' => 'Maximal ' . ARCP_UPLOAD_MAX_FILES . ' Bilder pro Link.' ), 400 );
	}
	if ( empty( $_FILES['bild'] ) || UPLOAD_ERR_OK !== $_FILES['bild']['error'] ) {
		$code = $_FILES['bild']['error'] ?? -1;
		arcp_upload_json( array( 'ok' => false, 'fehler' => UPLOAD_ERR_INI_SIZE === $code || UPLOAD_ERR_FORM_SIZE === $code ? 'Datei zu groß für den Server.' : 'Upload fehlgeschlagen.' ), 400 );
	}
	$file = $_FILES['bild'];
	if ( $file['size'] > ARCP_UPLOAD_MAX_MB * MB_IN_BYTES ) {
		arcp_upload_json( array( 'ok' => false, 'fehler' => 'Datei größer als ' . ARCP_UPLOAD_MAX_MB . ' MB.' ), 400 );
	}

	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';

	// Nur echte Bilder (Inhalt geprüft, nicht nur die Endung).
	$allowed = array( 'jpg|jpeg|jpe' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp' );
	$check   = wp_check_filetype_and_ext( $file['tmp_name'], $file['name'], $allowed );
	if ( empty( $check['type'] ) || false === @getimagesize( $file['tmp_name'] ) ) { // phpcs:ignore
		arcp_upload_json( array( 'ok' => false, 'fehler' => 'Nur JPG, PNG oder WebP.' ), 400 );
	}

	$moved = wp_handle_upload( $file, array( 'test_form' => false, 'mimes' => $allowed ) );
	if ( isset( $moved['error'] ) ) {
		arcp_upload_json( array( 'ok' => false, 'fehler' => $moved['error'] ), 400 );
	}

	$title  = preg_replace( '/\.[^.]+$/', '', wp_basename( $file['name'] ) );
	$title  = trim( preg_replace( '/[_-]+/', ' ', $title ) );
	$att_id = wp_insert_attachment(
		array(
			'post_mime_type' => $moved['type'],
			'post_title'     => sanitize_text_field( $title ),
			'post_status'    => 'inherit',
			'post_author'    => (int) $batch['u'],
		),
		$moved['file'],
		0,
		true
	);
	if ( is_wp_error( $att_id ) ) {
		arcp_upload_json( array( 'ok' => false, 'fehler' => $att_id->get_error_message() ), 500 );
	}
	wp_update_attachment_metadata( $att_id, wp_generate_attachment_metadata( $att_id, $moved['file'] ) );
	update_post_meta( $att_id, '_arcp_upload', $id );

	$all              = arcp_uploads_all();
	$all[ $id ]['ids'][] = $att_id;
	arcp_uploads_save( $all );

	arcp_upload_json(
		array(
			'ok'    => true,
			'id'    => $att_id,
			'name'  => wp_basename( $moved['file'] ),
			'thumb' => (string) wp_get_attachment_image_url( $att_id, 'thumbnail' ),
		)
	);
}

function arcp_upload_page( $batch ) {
	status_header( 200 );
	nocache_headers();
	header( 'Content-Type: text/html; charset=utf-8' );
	header( 'X-Robots-Tag: noindex, nofollow' );
	$minutes = max( 1, (int) round( ( $batch['e'] - time() ) / 60 ) );
	?>
<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>Bilder hochladen – A/R Architekten</title>
<style>
	:root { --bg:#f3f2ef; --card:#fff; --ink:#1d1d1b; --mute:#6b6b66; --line:#dcdad4; --ok:#2e7d4f; --err:#b3261e; --accent:#1d1d1b; }
	@media (prefers-color-scheme: dark) { :root { --bg:#151514; --card:#1f1f1d; --ink:#f0efea; --mute:#a3a29c; --line:#3a3a37; --accent:#f0efea; } }
	* { box-sizing:border-box; }
	body { margin:0; background:var(--bg); color:var(--ink); font:16px/1.5 -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif; }
	main { max-width:640px; margin:0 auto; padding:32px 16px 48px; }
	.brand { font-size:13px; letter-spacing:.14em; text-transform:uppercase; color:var(--mute); margin-bottom:6px; }
	h1 { font-size:26px; margin:0 0 4px; font-weight:600; }
	.sub { color:var(--mute); margin:0 0 24px; }
	label.drop { display:block; background:var(--card); border:2px dashed var(--line); border-radius:12px; padding:36px 16px; text-align:center; cursor:pointer; transition:border-color .15s; }
	label.drop.over { border-color:var(--accent); }
	label.drop strong { display:block; font-size:18px; margin-bottom:4px; }
	label.drop span { color:var(--mute); font-size:14px; }
	input[type=file] { position:absolute; width:1px; height:1px; opacity:0; }
	ul { list-style:none; padding:0; margin:20px 0 0; display:grid; grid-template-columns:repeat(auto-fill,minmax(130px,1fr)); gap:12px; }
	li { background:var(--card); border:1px solid var(--line); border-radius:10px; overflow:hidden; font-size:12px; }
	li .img { aspect-ratio:4/3; background:var(--line) center/cover no-repeat; }
	li .meta { padding:6px 8px; display:flex; justify-content:space-between; gap:6px; }
	li .name { overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
	.st-ok { color:var(--ok); } .st-err { color:var(--err); } .st-run { color:var(--mute); }
	.done { margin-top:24px; padding:16px; background:var(--card); border:1px solid var(--line); border-radius:10px; display:none; }
	.done.show { display:block; }
</style>
</head>
<body>
<main>
	<div class="brand">A/R Architekten · Website</div>
	<h1>Bilder hochladen</h1>
	<p class="sub"><?php echo $batch['txt'] ? esc_html( $batch['txt'] ) . ' · ' : ''; ?>Link gültig noch ca. <?php echo (int) $minutes; ?> Min.</p>

	<label class="drop" id="drop">
		<input type="file" id="files" accept="image/jpeg,image/png,image/webp" multiple>
		<strong>Fotos auswählen</strong>
		<span>oder hierher ziehen · JPG, PNG, WebP · bis <?php echo (int) ARCP_UPLOAD_MAX_MB; ?> MB pro Bild</span>
	</label>

	<ul id="list"></ul>

	<div class="done" id="done"><strong>Fertig.</strong> Zurück zu Claude und z.&nbsp;B. „hochgeladen“ schreiben – Claude findet die Bilder dann automatisch.</div>
</main>
<script>
(function () {
	var input = document.getElementById('files'), drop = document.getElementById('drop'), list = document.getElementById('list'), done = document.getElementById('done');
	var queue = [], running = false, okCount = 0;

	function item(file) {
		var li = document.createElement('li');
		li.innerHTML = '<div class="img"></div><div class="meta"><span class="name"></span><span class="st st-run">…</span></div>';
		li.querySelector('.name').textContent = file.name;
		try { li.querySelector('.img').style.backgroundImage = 'url(' + URL.createObjectURL(file) + ')'; } catch (e) {}
		list.appendChild(li);
		return li;
	}
	function add(files) {
		for (var i = 0; i < files.length; i++) { queue.push({ file: files[i], li: item(files[i]) }); }
		next();
	}
	function next() {
		if (running) { return; }
		var job = queue.shift();
		if (!job) { if (okCount) { done.classList.add('show'); } return; }
		running = true;
		var st = job.li.querySelector('.st');
		st.textContent = 'lädt…';
		var fd = new FormData(); fd.append('bild', job.file);
		fetch(location.href, { method: 'POST', body: fd, credentials: 'omit' })
			.then(function (r) { return r.json(); })
			.then(function (d) {
				if (d.ok) { okCount++; st.textContent = '✓'; st.className = 'st st-ok'; }
				else { st.textContent = d.fehler || 'Fehler'; st.className = 'st st-err'; }
			})
			.catch(function () { st.textContent = 'Fehler'; st.className = 'st st-err'; })
			.then(function () { running = false; next(); });
	}
	input.addEventListener('change', function () { add(input.files); input.value = ''; });
	['dragenter', 'dragover'].forEach(function (ev) { drop.addEventListener(ev, function (e) { e.preventDefault(); drop.classList.add('over'); }); });
	['dragleave', 'drop'].forEach(function (ev) { drop.addEventListener(ev, function (e) { e.preventDefault(); drop.classList.remove('over'); }); });
	drop.addEventListener('drop', function (e) { if (e.dataTransfer && e.dataTransfer.files) { add(e.dataTransfer.files); } });
})();
</script>
</body>
</html>
	<?php
	exit;
}

/* ---------------------------------------------------------------------------
 * Werkzeuge für Claude
 * ------------------------------------------------------------------------- */

add_action(
	'wp_abilities_api_init',
	function () {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}
		wp_register_ability(
			ARCP_NS . '/upload-link',
			array(
				'category'            => ARCP_NS,
				'permission_callback' => 'arcp_can_use',
				'label'               => 'Upload-Link für Bilder erzeugen',
				'description'         => 'Erzeugt einen Link, über den der Nutzer Fotos direkt in die Mediathek hochlädt (ohne Login, auch am Handy, 2 Stunden gültig). IMMER nutzen, wenn der Nutzer Bilder hochladen will oder Bilder hier im Chat anhängt – angehängte Chat-Bilder kannst du selbst nicht übertragen. Den Link als klickbaren Markdown-Link geben und sagen: „Fotos dort auswählen, dann hier kurz Bescheid geben.“ Danach upload-status aufrufen.',
				'input_schema'        => array(
					'type'                 => 'object',
					'additionalProperties' => false,
					'properties'           => array(
						'hinweis' => array( 'type' => 'string', 'description' => 'Kurzer Zweck, der auf der Upload-Seite steht, z. B. „Projekt Haus am Hang“.' ),
					),
				),
				'execute_callback'    => function ( $input = array() ) {
					$input = (array) $input;
					list( $id, $token ) = arcp_upload_create( $input['hinweis'] ?? '' );
					return array(
						'upload_id'         => $id,
						'link'              => arcp_upload_url( $token ),
						'gueltig_minuten'   => (int) ( ARCP_UPLOAD_TTL / 60 ),
						'naechster_schritt' => 'Link als [Bilder hochladen](…) geben. Wenn der Nutzer fertig ist, upload-status mit dieser upload_id aufrufen.',
					);
				},
				'meta'                => array( 'annotations' => array( 'readonly' => false, 'destructive' => false, 'idempotent' => false ) ),
			)
		);

		wp_register_ability(
			ARCP_NS . '/upload-status',
			array(
				'category'            => ARCP_NS,
				'permission_callback' => 'arcp_can_use',
				'label'               => 'Hochgeladene Bilder abrufen',
				'description'         => 'Zeigt die Bilder, die über einen Upload-Link hochgeladen wurden, mit Mediathek-ID und Dateiname. Aufrufen, wenn der Nutzer sagt, dass er fertig ist. Die Bilder dann als nummerierte Liste zeigen und fragen, welches wofür (Titelbild, Slider, Pläne) verwendet wird.',
				'input_schema'        => array(
					'type'                 => 'object',
					'required'             => array( 'upload_id' ),
					'additionalProperties' => false,
					'properties'           => array(
						'upload_id' => array( 'type' => 'string' ),
					),
				),
				'execute_callback'    => function ( $input ) {
					$all = arcp_uploads_all();
					$b   = $all[ (string) $input['upload_id'] ] ?? null;
					if ( ! $b ) {
						return arcp_error( 'not_found', 'Unbekannte upload_id.' );
					}
					return array(
						'anzahl'  => count( $b['ids'] ),
						'bilder'  => array_map( 'arcp_media_item', $b['ids'] ),
						'link_noch_gueltig' => $b['e'] > time(),
						'hinweis' => $b['ids'] ? '' : 'Noch keine Bilder angekommen. Den Nutzer bitten, auf der Upload-Seite zu warten, bis alle ein ✓ zeigen.',
					);
				},
				'meta'                => array( 'annotations' => array( 'readonly' => true, 'destructive' => false, 'idempotent' => true ) ),
			)
		);
	}
);

add_filter(
	'arcp_ability_names',
	function ( $names ) {
		$names[] = ARCP_NS . '/upload-link';
		$names[] = ARCP_NS . '/upload-status';
		return $names;
	}
);

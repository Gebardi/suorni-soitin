<?php
/* Suorni Soitin: päivitys GitHubista (Julkaisut / Releases). Asetukset: WP-ylläpito > Asetukset > Suorni päivitys. */
if ( ! defined( 'ABSPATH' ) ) { exit; }

function suorni_upd_cfg() {
	$c = get_option( 'suorni_upd', array() );
	return array(
		'repo'  => isset( $c['repo'] ) ? trim( (string) $c['repo'] ) : '',
		'token' => isset( $c['token'] ) ? trim( (string) $c['token'] ) : '',
		'auto'  => ! isset( $c['auto'] ) || ! empty( $c['auto'] ),
	);
}
function suorni_upd_slug() { return plugin_basename( dirname( __FILE__ ) . '/suorni-soitin.php' ); }

/* Uusin julkaisu GitHubista, välimuistissa 6 h (nollataan painikkeella) */
function suorni_upd_latest( $force = false ) {
	$c = suorni_upd_cfg();
	if ( ! preg_match( '#^[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+$#', $c['repo'] ) ) { return null; }
	$key = 'suorni_upd_rel';
	if ( ! $force ) {
		$t = get_transient( $key );
		if ( is_array( $t ) ) { return isset( $t['none'] ) ? null : $t; }
	}
	$h = array( 'Accept' => 'application/vnd.github+json', 'User-Agent' => 'Suorni-Updater' );
	if ( '' !== $c['token'] ) { $h['Authorization'] = 'Bearer ' . $c['token']; }
	$r = wp_remote_get( 'https://api.github.com/repos/' . $c['repo'] . '/releases/latest', array( 'timeout' => 10, 'headers' => $h ) );
	if ( is_wp_error( $r ) || 200 !== (int) wp_remote_retrieve_response_code( $r ) ) {
		set_transient( $key, array( 'none' => 1, 'err' => is_wp_error( $r ) ? $r->get_error_message() : 'HTTP ' . wp_remote_retrieve_response_code( $r ) ), 1800 );
		return null;
	}
	$j = json_decode( wp_remote_retrieve_body( $r ), true );
	if ( ! is_array( $j ) || empty( $j['tag_name'] ) ) { return null; }
	$pkg = '';
	if ( ! empty( $j['assets'] ) ) {
		foreach ( $j['assets'] as $a ) {
			if ( isset( $a['name'] ) && preg_match( '#\.zip$#i', $a['name'] ) ) { $pkg = ( '' !== $c['token'] && ! empty( $a['url'] ) ) ? $a['url'] : $a['browser_download_url']; break; }
		}
	}
	if ( '' === $pkg && ! empty( $j['zipball_url'] ) ) { $pkg = $j['zipball_url']; }
	$out = array( 'ver' => ltrim( (string) $j['tag_name'], 'vV' ), 'pkg' => $pkg, 'url' => isset( $j['html_url'] ) ? $j['html_url'] : '', 'notes' => isset( $j['body'] ) ? (string) $j['body'] : '' );
	set_transient( $key, $out, 6 * HOUR_IN_SECONDS );
	return $out;
}

add_filter( 'pre_set_site_transient_update_plugins', function ( $tr ) {
	if ( ! is_object( $tr ) ) { return $tr; }
	$rel = suorni_upd_latest();
	$slug = suorni_upd_slug();
	if ( $rel && '' !== $rel['pkg'] && version_compare( $rel['ver'], SUORNI_VER, '>' ) ) {
		$tr->response[ $slug ] = (object) array( 'slug' => 'suorni-soitin', 'plugin' => $slug, 'new_version' => $rel['ver'], 'url' => $rel['url'], 'package' => $rel['pkg'] );
	} elseif ( isset( $tr->response[ $slug ] ) ) {
		unset( $tr->response[ $slug ] );
	}
	return $tr;
} );

add_filter( 'plugins_api', function ( $res, $action, $args ) {
	if ( 'plugin_information' !== $action || empty( $args->slug ) || 'suorni-soitin' !== $args->slug ) { return $res; }
	$rel = suorni_upd_latest();
	if ( ! $rel ) { return $res; }
	return (object) array( 'name' => 'Suorni Soitin', 'slug' => 'suorni-soitin', 'version' => $rel['ver'], 'download_link' => $rel['pkg'], 'sections' => array( 'changelog' => nl2br( esc_html( $rel['notes'] ) ) ) );
}, 10, 3 );

/* GitHubin zip-paketin kansio nimetään uudelleen oikeaksi (suorni-soitin) */
add_filter( 'upgrader_source_selection', function ( $src, $remote, $upgrader, $hook ) {
	if ( empty( $hook['plugin'] ) || suorni_upd_slug() !== $hook['plugin'] ) { return $src; }
	global $wp_filesystem;
	$dir = trailingslashit( $src );
	if ( ! file_exists( $dir . 'suorni-soitin.php' ) ) {
		$sub = glob( $dir . '*/suorni-soitin.php' );
		if ( $sub ) { $dir = trailingslashit( dirname( $sub[0] ) ); } else { return new WP_Error( 'suorni_upd', 'Päivityspaketista ei löytynyt suorni-soitin.php-tiedostoa.' ); }
	}
	$to = trailingslashit( dirname( untrailingslashit( $src ) ) ) . 'suorni-soitin/';
	if ( untrailingslashit( $dir ) === untrailingslashit( $to ) ) { return $dir; }
	if ( $wp_filesystem && $wp_filesystem->move( untrailingslashit( $dir ), untrailingslashit( $to ), true ) ) { return $to; }
	return $src;
}, 10, 4 );

/* Yksityisen repon tiedosto vaatii tunnisteen */
add_filter( 'http_request_args', function ( $a, $url ) {
	$c = suorni_upd_cfg();
	if ( '' !== $c['token'] && 0 === strpos( $url, 'https://api.github.com/repos/' . $c['repo'] . '/' ) ) {
		$a['headers']['Authorization'] = 'Bearer ' . $c['token'];
		if ( false !== strpos( $url, '/releases/assets/' ) ) { $a['headers']['Accept'] = 'application/octet-stream'; }
	}
	return $a;
}, 10, 2 );

/* Automaattiset päivitykset */
add_filter( 'auto_update_plugin', function ( $up, $item ) {
	if ( isset( $item->plugin ) && suorni_upd_slug() === $item->plugin ) { $c = suorni_upd_cfg(); return $c['auto']; }
	return $up;
}, 10, 2 );

/* Asetussivu */
add_action( 'admin_menu', function () {
	add_options_page( 'Suorni päivitys', 'Suorni päivitys', 'manage_options', 'suorni-paivitys', 'suorni_upd_page' );
} );
function suorni_upd_page() {
	if ( ! current_user_can( 'manage_options' ) ) { return; }
	$msg = '';
	if ( isset( $_POST['suorni_upd_save'] ) && check_admin_referer( 'suorni_upd' ) ) {
		$old = suorni_upd_cfg();
		$tok = isset( $_POST['token'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['token'] ) ) ) : '';
		update_option( 'suorni_upd', array(
			'repo'  => isset( $_POST['repo'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['repo'] ) ) ) : '',
			'token' => '' !== $tok ? $tok : ( ! empty( $_POST['cleartok'] ) ? '' : $old['token'] ),
			'auto'  => ! empty( $_POST['auto'] ) ? 1 : 0,
		), false );
		delete_transient( 'suorni_upd_rel' );
		delete_site_transient( 'update_plugins' );
		$msg = 'Tallennettu.';
	}
	if ( isset( $_POST['suorni_upd_check'] ) && check_admin_referer( 'suorni_upd' ) ) {
		delete_site_transient( 'update_plugins' );
		$rel = suorni_upd_latest( true );
		wp_update_plugins();
		$msg = $rel ? 'Uusin julkaisu GitHubissa: ' . $rel['ver'] . ' (asennettu ' . SUORNI_VER . ').' : 'Julkaisua ei löytynyt. Tarkista repon nimi ja että siellä on vähintään yksi julkaisu (Release).';
	}
	$c = suorni_upd_cfg();
	$t = get_transient( 'suorni_upd_rel' );
	echo '<div class="wrap"><h1>Suorni päivitys</h1>';
	if ( $msg ) { echo '<div class="notice notice-info"><p>' . esc_html( $msg ) . '</p></div>'; }
	if ( is_array( $t ) && ! empty( $t['err'] ) ) { echo '<div class="notice notice-warning"><p>GitHub-virhe: ' . esc_html( $t['err'] ) . '</p></div>'; }
	echo '<p>Asennettu versio: <b>' . esc_html( SUORNI_VER ) . '</b>. Päivitys haetaan GitHub-repon uusimmasta julkaisusta (Release). Julkaisun tunnisteen (esim. v9.22) täytyy olla uudempi kuin asennettu versio.</p>';
	echo '<form method="post">'; wp_nonce_field( 'suorni_upd' );
	echo '<table class="form-table"><tr><th>GitHub-repo</th><td><input name="repo" class="regular-text" placeholder="käyttäjä/repo" value="' . esc_attr( $c['repo'] ) . '"></td></tr>';
	echo '<tr><th>Tunniste (token)</th><td><input name="token" type="password" class="regular-text" autocomplete="off" placeholder="' . ( '' !== $c['token'] ? 'tallennettu – jätä tyhjäksi säilyttääksesi' : 'vain yksityiselle repolle' ) . '"> ' . ( '' !== $c['token'] ? '<label><input type="checkbox" name="cleartok" value="1"> poista tallennettu</label>' : '' ) . '<p class="description">Julkiselle repolle ei tarvita. Yksityiselle: GitHub-tunniste, jolla on vain lukuoikeus repoon (Contents: read).</p></td></tr>';
	echo '<tr><th>Automaattinen asennus</th><td><label><input type="checkbox" name="auto" value="1"' . ( $c['auto'] ? ' checked' : '' ) . '> Asenna uudet versiot itsestään (WordPress tarkistaa kahdesti päivässä)</label></td></tr></table>';
	echo '<p><button class="button button-primary" name="suorni_upd_save" value="1">Tallenna</button> <button class="button" name="suorni_upd_check" value="1">Tarkista päivitykset nyt</button></p></form></div>';
}

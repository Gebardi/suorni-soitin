<?php
/* Massalisäys (tiedostot ohjaamosta) ja päivittäinen tiedostojen terveystarkistus. */
if ( ! defined( 'ABSPATH' ) ) { exit; }

function suorni_up_split( $name ) {
	$n = preg_replace( '#\.[A-Za-z0-9]{2,5}$#', '', (string) $name );
	$n = trim( preg_replace( '#[_]+#', ' ', $n ) );
	$n = trim( preg_replace( '#^\d{1,3}[\s.\-)]+(?=\D)#u', '', $n ) );
	if ( preg_match( '#^(.+?)\s+[-–—]\s+(.+)$#u', $n, $m ) ) { return array( trim( $m[1] ), trim( $m[2] ) ); }
	return array( '', $n );
}
function suorni_up_post( $req ) {
	if ( empty( $_FILES['file'] ) || ! is_array( $_FILES['file'] ) ) { return new WP_Error( 'suorni_up', 'Tiedosto puuttuu.', array( 'status' => 400 ) ); }
	$name = isset( $_FILES['file']['name'] ) ? sanitize_file_name( wp_unslash( $_FILES['file']['name'] ) ) : '';
	if ( ! preg_match( '#\.(mp3|m4a|wav|ogg|aac|flac)$#i', $name ) ) { return new WP_Error( 'suorni_up', 'Sallitut tiedostot: mp3, m4a, wav, ogg, aac, flac.', array( 'status' => 400 ) ); }
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';
	$id = media_handle_upload( 'file', 0 );
	if ( is_wp_error( $id ) ) { return new WP_Error( 'suorni_up', $id->get_error_message(), array( 'status' => 400 ) ); }
	$p    = get_post( $id );
	$meta = wp_get_attachment_metadata( $id );
	$fn   = suorni_up_split( $name );
	$orig = suorni_up_split( isset( $_FILES['file']['name'] ) ? wp_unslash( $_FILES['file']['name'] ) : $name );
	if ( '' !== $orig[1] ) { $fn = $orig; }
	$tag_artist = ( is_array( $meta ) && ! empty( $meta['artist'] ) ) ? (string) $meta['artist'] : '';
	$tag_title  = ( is_array( $meta ) && ! empty( $meta['title'] ) ) ? (string) $meta['title'] : '';
	$artist = '' !== $tag_artist ? $tag_artist : ( '' !== $fn[0] ? $fn[0] : 'Suorni' );
	$title  = '' !== $tag_title ? $tag_title : ( '' !== $fn[1] ? $fn[1] : ( $p ? $p->post_title : 'Nimetön kappale' ) );
	wp_update_post( array( 'ID' => $id, 'post_title' => sanitize_text_field( $title ), 'post_excerpt' => sanitize_text_field( $artist ) ) );
	$dur = ( is_array( $meta ) && ! empty( $meta['length'] ) ) ? (int) $meta['length'] : 0;
	suorni_clear_tracks();
	return suorni_json( array( 'ok' => true, 'id' => $id, 'title' => $title, 'artist' => $artist, 'dur' => $dur, 'warn' => $dur > 0 ? '' : 'Kestoa ei saatu luettua – kappale ei soi radiossa ennen kuin kesto on tiedossa.' ) );
}

/* ---------- Terveystarkistus ---------- */
function suorni_health_get() {
	$h = get_option( 'suorni_health', array() );
	return is_array( $h ) ? array_merge( array( 'at' => 0, 'n' => 0, 'bad' => array(), 'ext' => array(), 'nodur' => 0 ), $h ) : array( 'at' => 0, 'n' => 0, 'bad' => array(), 'ext' => array(), 'nodur' => 0 );
}
function suorni_health_run( $ext_max = 10 ) {
	$u      = wp_upload_dir();
	$bu     = trailingslashit( $u['baseurl'] );
	$bd     = trailingslashit( $u['basedir'] );
	$excl   = suorni_radio_excl();
	$tracks = suorni_view( suorni_tracks(), '' );
	$bad    = array(); $ext = array(); $n = 0; $nodur = 0;
	$ptr    = (int) get_option( 'suorni_health_ptr', 0 );
	$extl   = array();
	foreach ( $tracks as $t ) {
		if ( in_array( (int) $t['id'], $excl, true ) ) { continue; }
		if ( $t['dur'] <= 0 ) { $nodur++; continue; }
		$n++;
		if ( 0 === strpos( $t['url'], $bu ) ) {
			$f = $bd . substr( $t['url'], strlen( $bu ) );
			if ( ! file_exists( $f ) || filesize( $f ) < 1000 ) { $bad[] = array( 'id' => (int) $t['id'], 't' => $t['title'], 'why' => 'tiedosto puuttuu palvelimelta' ); $excl[] = (int) $t['id']; if ( function_exists( 'suorni_hidden_note' ) ) { suorni_hidden_note( (int) $t['id'], 'tiedosto puuttuu palvelimelta' ); } }
		} else { $extl[] = $t; }
	}
	if ( count( $bad ) ) { update_option( 'suorni_radio_excl', array_values( array_unique( $excl ) ), false ); }
	$cnt = count( $extl );
	if ( $cnt ) {
		for ( $i = 0; $i < min( $ext_max, $cnt ); $i++ ) {
			$t = $extl[ ( $ptr + $i ) % $cnt ];
			$r = wp_safe_remote_get( $t['url'], array( 'timeout' => 6, 'headers' => array( 'Range' => 'bytes=0-1023' ), 'redirection' => 3 ) );
			$c = is_wp_error( $r ) ? 0 : (int) wp_remote_retrieve_response_code( $r );
			if ( $c < 200 || $c >= 400 ) { $ext[] = array( 'id' => (int) $t['id'], 't' => $t['title'], 'why' => is_wp_error( $r ) ? $r->get_error_message() : 'HTTP ' . $c ); }
		}
		update_option( 'suorni_health_ptr', ( $ptr + $ext_max ) % $cnt, false );
	}
	$old = suorni_health_get();
	/* ulkoisen lähteen ongelmat säilyvät, kunnes uusi tarkistus osuu samaan kappaleeseen */
	$keep = array();
	foreach ( (array) $old['ext'] as $o ) { $keep[ $o['id'] ] = $o; }
	foreach ( $ext as $e ) { $e['cnt'] = ( isset( $keep[ $e['id'] ]['cnt'] ) ? (int) $keep[ $e['id'] ]['cnt'] : 0 ) + 1; $keep[ $e['id'] ] = $e; }
	$checked = array();
	for ( $i = 0; $i < min( $ext_max, $cnt ); $i++ ) { $checked[] = (int) $extl[ ( $ptr + $i ) % $cnt ]['id']; }
	foreach ( $checked as $cid ) { $isbad = false; foreach ( $ext as $e ) { if ( $e['id'] === $cid ) { $isbad = true; } } if ( ! $isbad ) { unset( $keep[ $cid ] ); } }
	/* Kappale, jonka linkki antaa kahdella peräkkäisellä tarkistuksella 403/404/410, piilotetaan radiosta automaattisesti */
	$auto = array();
	foreach ( $keep as $kid => $kx ) {
		if ( ! empty( $kx['cnt'] ) && (int) $kx['cnt'] >= 2 && preg_match( '/HTTP (403|404|410)/', (string) $kx['why'] ) ) { $auto[] = (int) $kid; unset( $keep[ $kid ] ); }
	}
	if ( $auto ) {
		$ex2 = suorni_radio_excl();
		update_option( 'suorni_radio_excl', array_values( array_unique( array_merge( $ex2, $auto ) ) ), false );
		update_option( 'suorni_health_auto', (int) get_option( 'suorni_health_auto', 0 ) + count( $auto ), false );
		foreach ( $auto as $aid ) { suorni_hidden_note( $aid, 'linkki ei aukea toistuvasti' ); }
	}
	$h = array( 'at' => time(), 'n' => $n, 'bad' => $bad, 'ext' => array_values( $keep ), 'nodur' => $nodur, 'auto' => count( $auto ) );
	update_option( 'suorni_health', $h, false );
	return $h;
}
function suorni_health_post( $req ) { return suorni_json( suorni_health_run( 6 ) ); }
add_action( 'suorni_health_job', function () { suorni_health_run( 15 ); } );
add_action( 'init', function () {
	if ( ! wp_next_scheduled( 'suorni_health_job' ) ) { wp_schedule_event( time() + 600, 'daily', 'suorni_health_job' ); }
} );
add_action( 'rest_api_init', function () {
	register_rest_route( 'suorni/v1', '/radio/upload', array( 'methods' => 'POST', 'callback' => 'suorni_up_post', 'permission_callback' => 'suorni_can' ) );
	register_rest_route( 'suorni/v1', '/radio/health', array( 'methods' => 'POST', 'callback' => 'suorni_health_post', 'permission_callback' => 'suorni_can' ) );
} );
/* Kansikuvan lataus ohjaamosta */
function suorni_cover_up_post( $req ) {
	if ( empty( $_FILES['file'] ) || ! is_array( $_FILES['file'] ) ) { return new WP_Error( 'suorni_up', 'Tiedosto puuttuu.', array( 'status' => 400 ) ); }
	$name = isset( $_FILES['file']['name'] ) ? sanitize_file_name( wp_unslash( $_FILES['file']['name'] ) ) : '';
	if ( ! preg_match( '#\.(jpe?g|png|webp|gif)$#i', $name ) ) { return new WP_Error( 'suorni_up', 'Sallitut kuvat: jpg, png, webp, gif.', array( 'status' => 400 ) ); }
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';
	$id = media_handle_upload( 'file', 0 );
	if ( is_wp_error( $id ) ) { return new WP_Error( 'suorni_up', $id->get_error_message(), array( 'status' => 400 ) ); }
	$url = wp_get_attachment_url( $id );
	if ( ! $url ) { return new WP_Error( 'suorni_up', 'Kuvan osoitetta ei saatu.', array( 'status' => 500 ) ); }
	return suorni_json( array( 'ok' => true, 'url' => esc_url_raw( $url ) ) );
}
add_action( 'rest_api_init', function () {
	register_rest_route( 'suorni/v1', '/radio/coverup', array( 'methods' => 'POST', 'callback' => 'suorni_cover_up_post', 'permission_callback' => 'suorni_can' ) );
} );

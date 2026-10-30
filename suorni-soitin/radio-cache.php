<?php
/* Suno-kappaleet tallennetaan omalle palvelimelle: toisto on luotettava (hyppääminen kesken kappaleen toimii), linkki ei katoa eikä Suno-osoite näy kuuntelijalle. */
if ( ! defined( 'ABSPATH' ) ) { exit; }

function suorni_suno_re() { return '#^https://cdn1\.suno\.ai/([0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12})\.mp[34]$#i'; }
function suorni_suno_paths( $uuid ) {
	$u = wp_upload_dir();
	return array( trailingslashit( $u['basedir'] ) . 'suorni-suno/' . strtolower( $uuid ) . '.mp4', trailingslashit( $u['baseurl'] ) . 'suorni-suno/' . strtolower( $uuid ) . '.mp4' );
}
/* Palauttaa oman kopion osoitteen, jos se on jo tallennettu */
function suorni_suno_local_url( $url ) {
	if ( ! preg_match( suorni_suno_re(), (string) $url, $m ) ) { return $url; }
	$p = suorni_suno_paths( $m[1] );
	return ( file_exists( $p[0] ) && filesize( $p[0] ) > 20000 ) ? $p[1] : $url;
}
function suorni_suno_fetch( $url ) {
	if ( ! preg_match( suorni_suno_re(), (string) $url, $m ) ) { return 'Ei Suno-osoite'; }
	$p = suorni_suno_paths( $m[1] );
	if ( file_exists( $p[0] ) && filesize( $p[0] ) > 20000 ) { return ''; }
	$dir = dirname( $p[0] );
	if ( ! wp_mkdir_p( $dir ) ) { return 'Kansiota ei voi luoda'; }
	if ( ! file_exists( $dir . '/index.html' ) ) { @file_put_contents( $dir . '/index.html', '' ); }
	$tmp = $p[0] . '.part';
	$r   = wp_safe_remote_get( 'https://cdn1.suno.ai/' . strtolower( $m[1] ) . '.mp4', array( 'timeout' => 120, 'stream' => true, 'filename' => $tmp, 'user-agent' => 'Mozilla/5.0 (compatible; Suorni/1.0)' ) );
	if ( is_wp_error( $r ) ) { @unlink( $tmp ); return $r->get_error_message(); }
	$code = (int) wp_remote_retrieve_response_code( $r );
	if ( 200 !== $code || ! file_exists( $tmp ) || filesize( $tmp ) < 20000 ) { @unlink( $tmp ); return 'Lataus epäonnistui (HTTP ' . $code . ')'; }
	rename( $tmp, $p[0] );
	return '';
}
/* Lataa enintään $max puuttuvaa kappaletta. Palauttaa array( ladattu, jäljellä, virhe ) */
function suorni_suno_run( $max = 2 ) {
	$done = 0; $err = '';
	foreach ( suorni_tracks() as $t ) {
		if ( $done >= $max ) { break; }
		if ( ! preg_match( suorni_suno_re(), $t['url'] ) ) { continue; }
		$e = suorni_suno_fetch( $t['url'] );
		if ( '' === $e ) { $done++; } else { $err = $e; }
	}
	if ( $done ) { suorni_clear_tracks(); }
	return array( $done, suorni_suno_left(), $err );
}
function suorni_suno_left() {
	$n = 0;
	foreach ( suorni_tracks() as $t ) { if ( preg_match( suorni_suno_re(), $t['url'] ) ) { $n++; } }
	return $n;
}
function suorni_suno_cache_post( $req ) {
	$r = suorni_suno_run( 2 );
	return suorni_json( array( 'done' => $r[0], 'left' => $r[1], 'err' => $r[2] ) );
}
add_action( 'rest_api_init', function () {
	register_rest_route( 'suorni/v1', '/radio/sunocache', array( 'methods' => 'POST', 'callback' => 'suorni_suno_cache_post', 'permission_callback' => 'suorni_can' ) );
} );
/* Taustaajo: tunnin välein, ja heti uuden kappaleen lisäyksen jälkeen */
add_action( 'suorni_suno_job', function () {
	$r = suorni_suno_run( 3 );
	if ( $r[1] > 0 && $r[0] > 0 ) { wp_schedule_single_event( time() + 30, 'suorni_suno_job' ); }
} );
add_action( 'init', function () {
	if ( ! wp_next_scheduled( 'suorni_suno_job' ) ) { wp_schedule_event( time() + 120, 'hourly', 'suorni_suno_job' ); }
} );
add_action( 'add_attachment', function () {
	if ( ! wp_next_scheduled( 'suorni_suno_job' ) ) { wp_schedule_single_event( time() + 10, 'suorni_suno_job' ); }
} );

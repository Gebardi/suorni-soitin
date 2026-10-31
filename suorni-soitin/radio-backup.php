<?php
/**
 * Suorni Radio: asetusten varmuuskopio. Vienti ja tuonti JSON-tiedostona, automaattiset viikkokopiot ja palautus.
 * Salaisuuksia (API-avaimet, ntfy-osoite) ei viedä, ja tuonti säilyttää nykyiset avaimet.
 * Kappaleiden äänitiedostot ovat WordPressin mediakirjastossa, eikä niitä kopioida tähän.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

function suorni_bk_opts() {
	return array( 'suorni_radio', 'suorni_dj', 'suorni_channels', 'suorni_chmain', 'suorni_lists', 'suorni_ads', 'suorni_scripts', 'suorni_tags', 'suorni_reqcfg', 'suorni_radio_excl', 'suorni_ann', 'suorni_order', 'suorni_social', 'suorni_watch_mail', 'suorni_radio_ai', 'suorni_tts', 'suorni_push' );
}
/* Kentät, joita ei viedä eikä korvata tuonnissa */
function suorni_bk_secrets() {
	return array( 'suorni_radio_ai' => array( 'key' ), 'suorni_tts' => array( 'key' ), 'suorni_push' => array( 'topic' ) );
}
function suorni_bk_meta_keys() {
	return array( '_suorni_progs', '_suorni_style', '_suorni_info', '_suorni_boost', '_suorni_tagged' );
}
function suorni_bk_collect() {
	$sec  = suorni_bk_secrets();
	$opts = array();
	foreach ( suorni_bk_opts() as $n ) {
		$v = get_option( $n, null );
		if ( null === $v ) { continue; }
		if ( isset( $sec[ $n ] ) && is_array( $v ) ) { foreach ( $sec[ $n ] as $k ) { unset( $v[ $k ] ); } }
		$opts[ $n ] = $v;
	}
	$meta = array();
	foreach ( suorni_tracks() as $t ) {
		$m = array();
		foreach ( suorni_bk_meta_keys() as $k ) {
			$x = get_post_meta( (int) $t['id'], $k, true );
			if ( '' !== $x && array() !== $x && null !== $x && false !== $x ) { $m[ $k ] = $x; }
		}
		if ( $m ) { $meta[ (int) $t['id'] ] = $m; }
	}
	return array( 'suorni_backup' => 1, 'at' => time(), 'plugin' => defined( 'SUORNI_VER' ) ? SUORNI_VER : '', 'site' => home_url(), 'opts' => $opts, 'meta' => $meta );
}
function suorni_bk_list() {
	$b = get_option( 'suorni_backups', array() );
	return is_array( $b ) ? $b : array();
}
function suorni_bk_snapshot( $why ) {
	$b   = suorni_bk_list();
	$d   = suorni_bk_collect();
	$b[] = array( 'at' => time(), 'why' => mb_substr( (string) $why, 0, 40 ), 'data' => $d );
	$b   = array_slice( $b, -6 );
	update_option( 'suorni_backups', $b, false );
	update_option( 'suorni_bk_last', time(), false );
	return $d;
}
function suorni_bk_apply( $d ) {
	if ( ! is_array( $d ) || empty( $d['suorni_backup'] ) || empty( $d['opts'] ) || ! is_array( $d['opts'] ) ) { return new WP_Error( 'suorni_bad', 'Tämä ei ole Suorni-varmuuskopio.', array( 'status' => 400 ) ); }
	suorni_bk_snapshot( 'ennen palautusta' );
	$sec = suorni_bk_secrets();
	$n   = 0;
	foreach ( suorni_bk_opts() as $name ) {
		if ( ! array_key_exists( $name, $d['opts'] ) ) { continue; }
		$v = $d['opts'][ $name ];
		if ( isset( $sec[ $name ] ) && is_array( $v ) ) {
			$cur = get_option( $name, array() );
			foreach ( $sec[ $name ] as $k ) { if ( is_array( $cur ) && isset( $cur[ $k ] ) ) { $v[ $k ] = $cur[ $k ]; } }
		}
		if ( is_array( $v ) || is_scalar( $v ) ) { update_option( $name, $v, false ); $n++; }
	}
	$m = 0;
	if ( ! empty( $d['meta'] ) && is_array( $d['meta'] ) ) {
		$ok = array();
		foreach ( suorni_tracks() as $t ) { $ok[ (int) $t['id'] ] = 1; }
		foreach ( $d['meta'] as $id => $mm ) {
			$id = (int) $id;
			if ( ! isset( $ok[ $id ] ) || ! is_array( $mm ) ) { continue; }
			foreach ( suorni_bk_meta_keys() as $k ) {
				if ( isset( $mm[ $k ] ) ) { update_post_meta( $id, $k, $mm[ $k ] ); } else { delete_post_meta( $id, $k ); }
			}
			$m++;
		}
	}
	suorni_clear_tracks();
	delete_transient( 'suorni_ch_pub' );
	return array( 'opts' => $n, 'tracks' => $m );
}
function suorni_bk_state() {
	$out = array();
	foreach ( array_reverse( suorni_bk_list() ) as $b ) {
		$out[] = array( 'at' => (int) $b['at'], 'why' => (string) $b['why'], 'opts' => isset( $b['data']['opts'] ) ? count( $b['data']['opts'] ) : 0, 'tracks' => isset( $b['data']['meta'] ) ? count( $b['data']['meta'] ) : 0, 'when' => wp_date( 'j.n.Y H.i', (int) $b['at'] ) );
	}
	return array( 'list' => $out, 'last' => (int) get_option( 'suorni_bk_last', 0 ) );
}
function suorni_bk_export() { return suorni_json( suorni_bk_collect() ); }
function suorni_bk_make() { suorni_bk_snapshot( 'käsin' ); return suorni_json( array( 'bk' => suorni_bk_state() ) ); }
function suorni_bk_import( $req ) {
	$d = $req->get_param( 'data' );
	$r = suorni_bk_apply( $d );
	if ( is_wp_error( $r ) ) { return $r; }
	return suorni_json( array( 'bk' => suorni_bk_state(), 'applied' => $r ) );
}
function suorni_bk_restore( $req ) {
	$at = (int) $req->get_param( 'at' );
	foreach ( suorni_bk_list() as $b ) {
		if ( (int) $b['at'] === $at ) {
			$r = suorni_bk_apply( $b['data'] );
			if ( is_wp_error( $r ) ) { return $r; }
			return suorni_json( array( 'bk' => suorni_bk_state(), 'applied' => $r ) );
		}
	}
	return new WP_Error( 'suorni_bad', 'Varmuuskopiota ei löytynyt.', array( 'status' => 404 ) );
}
function suorni_bk_del( $req ) {
	$at = (int) $req->get_param( 'at' );
	$b  = array_values( array_filter( suorni_bk_list(), function ( $x ) use ( $at ) { return (int) $x['at'] !== $at; } ) );
	update_option( 'suorni_backups', $b, false );
	return suorni_json( array( 'bk' => suorni_bk_state() ) );
}
add_action( 'rest_api_init', function () {
	register_rest_route( 'suorni/v1', '/radio/export', array( 'methods' => 'GET', 'callback' => 'suorni_bk_export', 'permission_callback' => 'suorni_can' ) );
	register_rest_route( 'suorni/v1', '/radio/bkmake', array( 'methods' => 'POST', 'callback' => 'suorni_bk_make', 'permission_callback' => 'suorni_can' ) );
	register_rest_route( 'suorni/v1', '/radio/import', array( 'methods' => 'POST', 'callback' => 'suorni_bk_import', 'permission_callback' => 'suorni_can' ) );
	register_rest_route( 'suorni/v1', '/radio/bkrestore', array( 'methods' => 'POST', 'callback' => 'suorni_bk_restore', 'permission_callback' => 'suorni_can' ) );
	register_rest_route( 'suorni/v1', '/radio/bkdel', array( 'methods' => 'POST', 'callback' => 'suorni_bk_del', 'permission_callback' => 'suorni_can' ) );
} );
/* Automaattinen viikkokopio (ajetaan valvontatyön yhteydessä) */
add_action( 'suorni_watch_job', function () {
	if ( time() - (int) get_option( 'suorni_bk_last', 0 ) > 7 * DAY_IN_SECONDS ) { suorni_bk_snapshot( 'viikoittain' ); }
}, 30 );

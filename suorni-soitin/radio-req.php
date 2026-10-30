<?php
/* Toivekappaleet: kuuntelija toivoo kappaleen kirjastosta, ohjaamo hyväksyy (tai automaattinen), radio soittaa sen seuraavien joukossa. */
if ( ! defined( 'ABSPATH' ) ) { exit; }

function suorni_reqcfg() {
	$c = get_option( 'suorni_reqcfg', array() );
	return array( 'on' => ! isset( $c['on'] ) || ! empty( $c['on'] ), 'auto' => ! isset( $c['auto'] ) || ! empty( $c['auto'] ) );
}
function suorni_reqs() { $r = get_option( 'suorni_reqs', array() ); return is_array( $r ) ? $r : array(); }
function suorni_reqs_save( $r ) { update_option( 'suorni_reqs', array_slice( array_values( $r ), -60 ), false ); }
function suorni_req_tracks() {
	$excl = suorni_radio_excl();
	$out  = array();
	foreach ( suorni_view( suorni_tracks(), '' ) as $t ) {
		if ( $t['dur'] > 0 && empty( $t['ad'] ) && empty( $t['ann'] ) && ! in_array( (int) $t['id'], $excl, true ) ) { $out[] = array( 'id' => (int) $t['id'], 't' => suorni_nosuno( $t['title'] ), 'a' => suorni_nosuno( $t['artist'] ) ); }
	}
	usort( $out, function ( $x, $y ) { return strcasecmp( $x['a'] . ' ' . $x['t'], $y['a'] . ' ' . $y['t'] ); } );
	return $out;
}
function suorni_reqlist_get( $req ) {
	$c = suorni_reqcfg();
	if ( ! $c['on'] || ! suorni_radio_on() ) { return suorni_json( array( 'on' => false ) ); }
	$l = get_transient( 'suorni_reqlist' );
	if ( ! is_array( $l ) ) { $l = suorni_req_tracks(); set_transient( 'suorni_reqlist', $l, 5 * MINUTE_IN_SECONDS ); }
	$act = 0;
	foreach ( suorni_reqs() as $r ) { if ( in_array( $r['st'], array( 'pending', 'ok' ), true ) ) { $act++; } }
	return suorni_json( array( 'on' => true, 'tracks' => $l, 'waiting' => $act ) );
}
function suorni_req_post( $req ) {
	$c = suorni_reqcfg();
	if ( ! $c['on'] || ! suorni_radio_on() ) { return new WP_Error( 'suorni_off', 'Toiveet eivät ole käytössä.', array( 'status' => 403 ) ); }
	if ( '' !== (string) $req->get_param( 'hp' ) ) { return suorni_json( array( 'ok' => true, 'message' => 'Kiitos!' ) ); }
	$h = suorni_comm_vhash();
	if ( get_transient( 'suorni_rq_' . $h ) ) { return new WP_Error( 'suorni_rate', 'Voit toivoa yhden kappaleen kymmenessä minuutissa.', array( 'status' => 429 ) ); }
	$tid = absint( $req->get_param( 'id' ) );
	$ok  = false; $title = '';
	foreach ( suorni_req_tracks() as $t ) { if ( $t['id'] === $tid ) { $ok = true; $title = $t['t']; break; } }
	if ( ! $ok ) { return new WP_Error( 'suorni_nf', 'Kappaletta ei löydy.', array( 'status' => 404 ) ); }
	$r = suorni_reqs();
	$act = 0;
	foreach ( $r as $x ) {
		if ( in_array( $x['st'], array( 'pending', 'ok' ), true ) ) { $act++; if ( (int) $x['tid'] === $tid ) { return new WP_Error( 'suorni_dup', 'Tämä kappale on jo toivottu ja odottaa vuoroaan.', array( 'status' => 409 ) ); } }
	}
	if ( $act >= 8 ) { return new WP_Error( 'suorni_full', 'Toivejono on täynnä, yritä hetken kuluttua.', array( 'status' => 429 ) ); }
	$name = function_exists( 'suorni_comm_clean' ) ? suorni_comm_clean( (string) $req->get_param( 'name' ), 20 ) : '';
	if ( '' === $name ) { $name = 'Kuuntelija'; }
	$n = 1;
	foreach ( $r as $x ) { $n = max( $n, (int) $x['n'] + 1 ); }
	$r[] = array( 'n' => $n, 'tid' => $tid, 'name' => $name, 'at' => time(), 'st' => $c['auto'] ? 'ok' : 'pending', 'ann' => 0, 'pat' => 0 );
	suorni_reqs_save( $r );
	set_transient( 'suorni_rq_' . $h, 1, 10 * MINUTE_IN_SECONDS );
	return suorni_json( array( 'ok' => true, 'message' => $c['auto'] ? 'Toive otettu vastaan! Kappale soi pian: ' . $title : 'Toive lähetetty! Se soi, kun se on hyväksytty: ' . $title ) );
}
/* Soittovalinta: vanhin hyväksytty toive, jonka kappale kelpaa nykyiseen ohjelmaan */
function suorni_req_pick( $by ) {
	$r = suorni_reqs();
	foreach ( $r as $x ) {
		if ( 'ok' === $x['st'] && isset( $by[ $x['tid'] ] ) && time() - (int) $x['at'] < 6 * HOUR_IN_SECONDS ) { return $by[ $x['tid'] ]; }
	}
	return null;
}
function suorni_req_mark_played( $tid ) {
	$r = suorni_reqs(); $ch = false;
	foreach ( $r as $i => $x ) {
		if ( 'ok' === $x['st'] && (int) $x['tid'] === (int) $tid ) { $r[ $i ]['st'] = 'played'; $r[ $i ]['pat'] = time(); $ch = true; break; }
	}
	if ( $ch ) { suorni_reqs_save( $r ); }
}
/* Juontajalle maininta toiveesta (kerran per toive) */
function suorni_req_facts() {
	$r = suorni_reqs(); $out = array(); $ch = false;
	$names = array();
	foreach ( suorni_req_tracks() as $t ) { $names[ $t['id'] ] = $t; }
	foreach ( $r as $i => $x ) {
		if ( 'ok' !== $x['st'] || ! empty( $x['ann'] ) || ! isset( $names[ $x['tid'] ] ) || count( $out ) >= 2 ) { continue; }
		$t = $names[ $x['tid'] ];
		$r[ $i ]['ann'] = 1; $ch = true;
		$out[] = array( 'fact' => 'Kuuntelija "' . $x['name'] . '" toivoi kappaleen "' . $t['t'] . '" (esittäjä ' . $t['a'] . '). Mainitse lyhyesti ja lämpimästi, että toive soi pian, ja kerro toivojan nimimerkki.', 'plain' => 'Kuuntelija ' . $x['name'] . ' toivoi kappaleen ' . $t['t'] . ', esittäjä ' . $t['a'] . '. Toive soi pian.' );
	}
	if ( $ch ) { suorni_reqs_save( $r ); }
	return $out;
}
/* ---------- Ohjaamo ---------- */
function suorni_req_admin_list() {
	$names = array();
	foreach ( suorni_tracks() as $t ) { $names[ (int) $t['id'] ] = suorni_nosuno( $t['title'] ) . ' – ' . suorni_nosuno( $t['artist'] ); }
	$out = array();
	foreach ( array_reverse( suorni_reqs() ) as $x ) { $out[] = array( 'n' => (int) $x['n'], 'name' => $x['name'], 'song' => isset( $names[ (int) $x['tid'] ] ) ? $names[ (int) $x['tid'] ] : '(poistettu)', 'st' => $x['st'], 'at' => (int) $x['at'] ); if ( count( $out ) >= 30 ) { break; } }
	return $out;
}
function suorni_reqact_post( $req ) {
	$n = absint( $req->get_param( 'n' ) ); $act = sanitize_key( (string) $req->get_param( 'act' ) );
	$r = suorni_reqs();
	foreach ( $r as $i => $x ) {
		if ( (int) $x['n'] !== $n ) { continue; }
		if ( 'ok' === $act && 'pending' === $x['st'] ) { $r[ $i ]['st'] = 'ok'; }
		elseif ( 'no' === $act && in_array( $x['st'], array( 'pending', 'ok' ), true ) ) { $r[ $i ]['st'] = 'no'; }
		elseif ( 'del' === $act ) { unset( $r[ $i ] ); }
		suorni_reqs_save( $r );
		return suorni_json( array( 'ok' => true, 'list' => suorni_req_admin_list() ) );
	}
	return new WP_Error( 'suorni_nf', 'Toivetta ei löydy.', array( 'status' => 404 ) );
}
function suorni_reqcfg_post( $req ) {
	update_option( 'suorni_reqcfg', array( 'on' => $req->get_param( 'on' ) ? 1 : 0, 'auto' => $req->get_param( 'auto' ) ? 1 : 0 ), false );
	return suorni_json( array( 'ok' => true ) );
}
add_action( 'rest_api_init', function () {
	register_rest_route( 'suorni/v1', '/radio/reqlist', array( 'methods' => 'GET', 'callback' => 'suorni_reqlist_get', 'permission_callback' => '__return_true' ) );
	register_rest_route( 'suorni/v1', '/radio/req', array( 'methods' => 'POST', 'callback' => 'suorni_req_post', 'permission_callback' => '__return_true' ) );
	register_rest_route( 'suorni/v1', '/radio/reqact', array( 'methods' => 'POST', 'callback' => 'suorni_reqact_post', 'permission_callback' => 'suorni_can' ) );
	register_rest_route( 'suorni/v1', '/radio/reqcfg', array( 'methods' => 'POST', 'callback' => 'suorni_reqcfg_post', 'permission_callback' => 'suorni_can' ) );
} );

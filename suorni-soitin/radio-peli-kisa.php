<?php
/* Taajuus-pelin viikkokisa: pelaajat kilpailevat samoista kaupungeista. Viikko alkaa maanantaina; viikon vaihtuessa kaupunkien hallinta nollataan (tasainen uusi alku).
   Rajapinnat (julkisia, pelaajan ilmoittamat tiedot, ei todennettu – kuten Top 10):
   GET  peli/map?pid=            kaupunkien tila, oma tila, hintakertoimet, viikon sarjataulukko, edellisen viikon voittajat
   POST peli/claim {pid,city,power}   ottaa/päivittää aseman kaupungissa (power = aseman voima)
   POST peli/unclaim {pid,city}       luopuu asemasta
   Tasaisuus: kertoimet palvelimelta, joita peli soveltaa hintoihin:
     cm (kaupunki) = 1 + 0,25 × kilpailijoiden määrä kaupungissa (max 3,0)
     om (oma)      = 1 + 0,35 × hallussa olevien kaupunkien määrä (kuka hallitsee enemmän, maksaa uusista enemmän)
   Hallinta: kaupungin omistaa suurin power (tasatilanteessa aiemmin tullut). Enintään SUORNI_KISA_CAP hallittua kaupunkia per pelaaja. */
if ( ! defined( 'ABSPATH' ) ) { exit; }
define( 'SUORNI_KISA_CAP', 4 );

function suorni_kisa_week() { return wp_date( 'o-\WW' ); }
function suorni_kisa_ends() {
	try { $d = new DateTime( 'monday next week 00:00:00', wp_timezone() ); return $d->getTimestamp(); } catch ( Exception $e ) { return time() + 86400; }
}
function suorni_kisa_pid( $v ) { return substr( preg_replace( '/[^a-z0-9]/', '', strtolower( (string) $v ) ), 0, 24 ); }
function suorni_kisa_city( $v ) { return substr( preg_replace( '/[^a-z0-9_]/', '', strtolower( (string) $v ) ), 0, 24 ); }
/* Pelaajan hyväksytty nimi (muuten "Nimetön asema") */
function suorni_kisa_nick( $pid ) {
	$l = suorni_peli_top_list();
	return ( isset( $l[ $pid ] ) && ! empty( $l[ $pid ]['ok'] ) && empty( $l[ $pid ]['x'] ) ) ? (string) $l[ $pid ]['n'] : 'Nimetön asema';
}
function suorni_kisa_load() {
	$d = get_option( 'suorni_peli_kisa', array() );
	if ( ! is_array( $d ) ) { $d = array(); }
	$w = suorni_kisa_week();
	if ( ! isset( $d['week'] ) || $d['week'] !== $w ) {
		$prev = isset( $d['week'] ) ? suorni_kisa_standings( isset( $d['c'] ) ? $d['c'] : array() ) : ( isset( $d['prev'] ) ? $d['prev'] : array() );
		$d = array( 'week' => $w, 'c' => array(), 'prev' => array_slice( $prev, 0, 3 ), 'prevweek' => isset( $d['week'] ) ? $d['week'] : '' );
		update_option( 'suorni_peli_kisa', $d, false );
	}
	if ( ! isset( $d['c'] ) || ! is_array( $d['c'] ) ) { $d['c'] = array(); }
	return $d;
}
/* Kaupungin omistaja: suurin power, tasatilanteessa aiemmin tullut */
function suorni_kisa_owner( $holders ) {
	$best = null; $bp = -1; $bt = PHP_INT_MAX;
	foreach ( (array) $holders as $pid => $h ) {
		$p = (float) $h['p']; $t = (int) $h['t'];
		if ( $p > $bp || ( $p === $bp && $t < $bt ) ) { $best = $pid; $bp = $p; $bt = $t; }
	}
	return $best;
}
function suorni_kisa_owned_by( $c, $pid ) {
	$n = 0;
	foreach ( $c as $holders ) { if ( suorni_kisa_owner( $holders ) === $pid ) { $n++; } }
	return $n;
}
function suorni_kisa_standings( $c ) {
	$s = array();
	foreach ( $c as $holders ) {
		$o = suorni_kisa_owner( $holders );
		if ( null === $o ) { continue; }
		if ( ! isset( $s[ $o ] ) ) { $s[ $o ] = array( 'pid' => $o, 'n' => suorni_kisa_nick( $o ), 'cities' => 0, 'power' => 0 ); }
		$s[ $o ]['cities']++;
		$s[ $o ]['power'] += (float) $holders[ $o ]['p'];
	}
	$s = array_values( $s );
	usort( $s, function ( $a, $b ) { return $b['cities'] <=> $a['cities'] ?: $b['power'] <=> $a['power']; } );
	return $s;
}
function suorni_kisa_view( $d, $pid ) {
	$cities = array();
	foreach ( $d['c'] as $city => $holders ) {
		$o = suorni_kisa_owner( $holders );
		$n = count( $holders );
		$mine = ( $pid && isset( $holders[ $pid ] ) );
		$riv = $n - ( $mine ? 1 : 0 );
		$cities[ $city ] = array(
			'n' => $n, 'owner' => null === $o ? '' : suorni_kisa_nick( $o ), 'op' => null === $o ? 0 : (float) $holders[ $o ]['p'],
			'mine' => $mine, 'mp' => $mine ? (float) $holders[ $pid ]['p'] : 0, 'own' => ( $mine && $o === $pid ),
			'cm' => min( 3.0, 1 + 0.25 * max( 0, $riv ) ),
		);
	}
	$owned = $pid ? suorni_kisa_owned_by( $d['c'], $pid ) : 0;
	$st = array();
	foreach ( array_slice( suorni_kisa_standings( $d['c'] ), 0, 10 ) as $r ) { $st[] = array( 'n' => $r['n'], 'cities' => $r['cities'], 'power' => $r['power'], 'me' => ( $pid && $r['pid'] === $pid ) ); }
	$prev = array();
	foreach ( (array) ( isset( $d['prev'] ) ? $d['prev'] : array() ) as $r ) { $prev[] = array( 'n' => $r['n'], 'cities' => $r['cities'] ); }
	return array( 'week' => $d['week'], 'ends' => suorni_kisa_ends(), 'cap' => SUORNI_KISA_CAP, 'cities' => $cities, 'me' => array( 'owned' => $owned, 'om' => 1 + 0.35 * $owned ), 'top' => $st, 'prev' => $prev );
}
function suorni_kisa_map( $req ) {
	return suorni_json( suorni_kisa_view( suorni_kisa_load(), suorni_kisa_pid( $req->get_param( 'pid' ) ) ) );
}
function suorni_kisa_rl( $pid ) {
	$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	$k = 'suorni_pk_' . md5( $ip ); $n = (int) get_transient( $k );
	if ( $n >= 60 ) { return false; }
	set_transient( $k, $n + 1, 600 );
	$k2 = 'suorni_pkp_' . md5( $pid );
	if ( get_transient( $k2 ) ) { return false; }
	set_transient( $k2, 1, 3 );
	return true;
}
function suorni_kisa_claim( $req ) {
	$pid = suorni_kisa_pid( $req->get_param( 'pid' ) );
	$city = suorni_kisa_city( $req->get_param( 'city' ) );
	$pw = (float) $req->get_param( 'power' );
	if ( strlen( $pid ) < 8 || strlen( $city ) < 2 || $pw < 0 || $pw > 1e12 ) { return new WP_Error( 'suorni_bad', 'Virheellinen', array( 'status' => 400 ) ); }
	if ( ! suorni_kisa_rl( $pid ) ) { return suorni_json( array( 'ok' => true, 'wait' => true ) ); }
	$d = suorni_kisa_load();
	if ( ! isset( $d['c'][ $city ] ) && count( $d['c'] ) >= 100 ) { return new WP_Error( 'suorni_bad', 'Virheellinen', array( 'status' => 400 ) ); }
	$holders = isset( $d['c'][ $city ] ) ? $d['c'][ $city ] : array();
	$was = ( suorni_kisa_owner( $holders ) === $pid );
	$old = isset( $holders[ $pid ] ) ? $holders[ $pid ] : null;
	$holders[ $pid ] = array( 'p' => $pw, 't' => $old ? (int) $old['t'] : time() );
	$willown = ( suorni_kisa_owner( $holders ) === $pid );
	/* katto: yksi pelaaja ei voi hallita koko maata */
	if ( $willown && ! $was && suorni_kisa_owned_by( $d['c'], $pid ) >= SUORNI_KISA_CAP ) {
		return suorni_json( array_merge( array( 'ok' => false, 'cap' => true ), suorni_kisa_view( $d, $pid ) ) );
	}
	if ( count( $holders ) > 300 ) { return new WP_Error( 'suorni_bad', 'Kaupunki on täynnä', array( 'status' => 400 ) ); }
	$d['c'][ $city ] = $holders;
	update_option( 'suorni_peli_kisa', $d, false );
	return suorni_json( array_merge( array( 'ok' => true ), suorni_kisa_view( $d, $pid ) ) );
}
function suorni_kisa_unclaim( $req ) {
	$pid = suorni_kisa_pid( $req->get_param( 'pid' ) );
	$city = suorni_kisa_city( $req->get_param( 'city' ) );
	if ( strlen( $pid ) < 8 || strlen( $city ) < 2 ) { return new WP_Error( 'suorni_bad', 'Virheellinen', array( 'status' => 400 ) ); }
	if ( ! suorni_kisa_rl( $pid ) ) { return suorni_json( array( 'ok' => true, 'wait' => true ) ); }
	$d = suorni_kisa_load();
	if ( isset( $d['c'][ $city ][ $pid ] ) ) {
		unset( $d['c'][ $city ][ $pid ] );
		if ( ! $d['c'][ $city ] ) { unset( $d['c'][ $city ] ); }
		update_option( 'suorni_peli_kisa', $d, false );
	}
	return suorni_json( array_merge( array( 'ok' => true ), suorni_kisa_view( $d, $pid ) ) );
}
add_action( 'rest_api_init', function () {
	register_rest_route( 'suorni/v1', '/peli/map', array( 'methods' => 'GET', 'callback' => 'suorni_kisa_map', 'permission_callback' => '__return_true' ) );
	register_rest_route( 'suorni/v1', '/peli/claim', array( 'methods' => 'POST', 'callback' => 'suorni_kisa_claim', 'permission_callback' => '__return_true' ) );
	register_rest_route( 'suorni/v1', '/peli/unclaim', array( 'methods' => 'POST', 'callback' => 'suorni_kisa_unclaim', 'permission_callback' => '__return_true' ) );
} );

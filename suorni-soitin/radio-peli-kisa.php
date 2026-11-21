<?php
/* Taajuus-pelin viikkokisa: pelaajat kilpailevat kaupungeista omassa liigassaan. Viikko alkaa maanantaina; viikon vaihtuessa hallinta nollataan.
   Rajapinnat (julkisia, pelaajan ilmoittamat tiedot, ei todennettu – kuten Top 10):
   GET  peli/map?pid=                 oman liigan kaupunkien tila, oma tila, hintakertoimet, sarjataulukko, edellisen viikon voittajat
   POST peli/claim {pid,city,power}   ottaa/päivittää aseman kaupungissa
   POST peli/unclaim {pid,city}       luopuu asemasta
   Tasaisuus:
   - LIIGAT: kisa käydään liigoittain (aloittelija / amatööri / ammattilainen / mestari), liiga määräytyy pelaajan Top 10 -pisteistä (SUORNI_KISA_LIGAT). Aloittelija ei kilpaile veteraania vastaan.
   - EI kovaa kattoa: asemien määrä kasvaa aktiivisella pelillä. Lupa-raja = 3 + 2 × (tällä viikolla aktiivisten päivien määrä), ja hinta kasvaa hallussa olevien kaupunkien mukana: om = 1,2^hallitut.
   - Kilpailu: cm = 1 + 0,25 × kilpailijoiden määrä kaupungissa (max 3,0).
   - Aloittelijan alennus: ensimmäisen 7 päivän pelaaja maksaa 50 % ensimmäisistä 3 omistetusta kaupungista (nb = 0,5).
   - Hystereesi: omistaja vaihtuu vasta kun haastaja ylittää omistajan voiman yli 10 %. */
if ( ! defined( 'ABSPATH' ) ) { exit; }
/* liigarajat Top 10 -pisteinä: [alaraja] => liiga. Peli-istunto voi säätää taloutensa mukaan. */
if ( ! defined( 'SUORNI_KISA_LIGAT' ) ) { define( 'SUORNI_KISA_LIGAT', '0:aloittelija,10000:amatööri,1000000:ammattilainen,100000000:mestari' ); }

function suorni_kisa_week() { return wp_date( 'o-\WW' ); }
function suorni_kisa_ends() {
	try { $d = new DateTime( 'monday next week 00:00:00', wp_timezone() ); return $d->getTimestamp(); } catch ( Exception $e ) { return time() + 86400; }
}
function suorni_kisa_pid( $v ) { return substr( preg_replace( '/[^a-z0-9]/', '', strtolower( (string) $v ) ), 0, 24 ); }
function suorni_kisa_city( $v ) { return substr( preg_replace( '/[^a-z0-9_]/', '', strtolower( (string) $v ) ), 0, 24 ); }
function suorni_kisa_ligat() {
	$out = array();
	foreach ( explode( ',', SUORNI_KISA_LIGAT ) as $i => $x ) { $p = explode( ':', $x ); $out[] = array( 'min' => (float) $p[0], 'n' => $p[1], 'i' => $i ); }
	return $out;
}
/* Pelaajan liiga (indeksi) Top 10 -pisteiden mukaan; tuntematon pelaaja = 0 */
function suorni_kisa_liiga( $pid ) {
	$l = suorni_peli_top_list();
	$s = isset( $l[ $pid ] ) ? (float) $l[ $pid ]['s'] : 0;
	$r = 0;
	foreach ( suorni_kisa_ligat() as $g ) { if ( $s >= $g['min'] ) { $r = $g['i']; } }
	return $r;
}
function suorni_kisa_liiga_name( $i ) { $g = suorni_kisa_ligat(); return isset( $g[ $i ] ) ? $g[ $i ]['n'] : ''; }
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
		$prev = array();
		if ( isset( $d['week'] ) && isset( $d['c'] ) && is_array( $d['c'] ) ) {
			foreach ( $d['c'] as $lg => $cs ) { $prev[ $lg ] = array_slice( suorni_kisa_standings( $cs ), 0, 3 ); }
		} elseif ( isset( $d['prev'] ) ) { $prev = $d['prev']; }
		$d = array( 'week' => $w, 'c' => array(), 'act' => array(), 'prev' => $prev );
		update_option( 'suorni_peli_kisa', $d, false );
	}
	foreach ( array( 'c', 'act', 'prev' ) as $k ) { if ( ! isset( $d[ $k ] ) || ! is_array( $d[ $k ] ) ) { $d[ $k ] = array(); } }
	return $d;
}
/* Aktiivisuus: päivä merkitään kun pelaaja käy kisanäkymässä; ensimmäinen havainto talteen aloittelijan alennusta varten */
function suorni_kisa_touch( &$d, $pid ) {
	$day = wp_date( 'Ymd' );
	if ( ! isset( $d['act'][ $pid ] ) ) { $d['act'][ $pid ] = array(); }
	if ( ! in_array( $day, $d['act'][ $pid ], true ) ) { $d['act'][ $pid ][] = $day; }
	$f = get_option( 'suorni_peli_first', array() );
	if ( ! is_array( $f ) ) { $f = array(); }
	if ( ! isset( $f[ $pid ] ) ) {
		$f[ $pid ] = time();
		if ( count( $f ) > 5000 ) { asort( $f ); $f = array_slice( $f, -4000, null, true ); }
		update_option( 'suorni_peli_first', $f, false );
	}
}
function suorni_kisa_first( $pid ) { $f = get_option( 'suorni_peli_first', array() ); return ( is_array( $f ) && isset( $f[ $pid ] ) ) ? (int) $f[ $pid ] : time(); }
function suorni_kisa_cap( $d, $pid ) { return 3 + 2 * min( 7, isset( $d['act'][ $pid ] ) ? count( $d['act'][ $pid ] ) : 0 ); }
/* Kaupunki: ['o' => omistaja-pid, 'h' => [pid => [p, t]]] */
function suorni_kisa_pickowner( $city ) {
	$h = isset( $city['h'] ) ? $city['h'] : array();
	$best = null; $bp = -1; $bt = PHP_INT_MAX;
	foreach ( $h as $pid => $x ) {
		$p = (float) $x['p']; $t = (int) $x['t'];
		if ( $p > $bp || ( $p === $bp && $t < $bt ) ) { $best = $pid; $bp = $p; $bt = $t; }
	}
	$cur = isset( $city['o'] ) && isset( $h[ $city['o'] ] ) ? $city['o'] : null;
	if ( null !== $cur && $best !== $cur && $bp <= 1.1 * (float) $h[ $cur ]['p'] ) { return $cur; }
	return $best;
}
function suorni_kisa_owned_by( $cs, $pid ) {
	$n = 0;
	foreach ( $cs as $city ) { if ( isset( $city['o'] ) && $city['o'] === $pid ) { $n++; } }
	return $n;
}
function suorni_kisa_standings( $cs ) {
	$s = array();
	foreach ( $cs as $city ) {
		$o = isset( $city['o'] ) ? $city['o'] : null;
		if ( null === $o || ! isset( $city['h'][ $o ] ) ) { continue; }
		if ( ! isset( $s[ $o ] ) ) { $s[ $o ] = array( 'pid' => $o, 'n' => suorni_kisa_nick( $o ), 'cities' => 0, 'power' => 0 ); }
		$s[ $o ]['cities']++;
		$s[ $o ]['power'] += (float) $city['h'][ $o ]['p'];
	}
	$s = array_values( $s );
	usort( $s, function ( $a, $b ) { return $b['cities'] <=> $a['cities'] ?: $b['power'] <=> $a['power']; } );
	return $s;
}
function suorni_kisa_view( $d, $pid ) {
	$lg = suorni_kisa_liiga( $pid );
	$cs = isset( $d['c'][ $lg ] ) ? $d['c'][ $lg ] : array();
	$cities = array();
	foreach ( $cs as $name => $city ) {
		$o = isset( $city['o'] ) ? $city['o'] : null;
		$h = $city['h'];
		$n = count( $h );
		$mine = ( $pid && isset( $h[ $pid ] ) );
		$riv = $n - ( $mine ? 1 : 0 );
		$cities[ $name ] = array(
			'n' => $n, 'owner' => null === $o ? '' : suorni_kisa_nick( $o ), 'op' => null === $o ? 0 : (float) $h[ $o ]['p'],
			'mine' => $mine, 'mp' => $mine ? (float) $h[ $pid ]['p'] : 0, 'own' => ( $mine && $o === $pid ),
			'cm' => min( 3.0, 1 + 0.25 * max( 0, $riv ) ),
		);
	}
	$owned = $pid ? suorni_kisa_owned_by( $cs, $pid ) : 0;
	$newb = $pid && ( time() - suorni_kisa_first( $pid ) ) < 7 * 86400;
	$st = array();
	foreach ( array_slice( suorni_kisa_standings( $cs ), 0, 10 ) as $r ) { $st[] = array( 'n' => $r['n'], 'cities' => $r['cities'], 'power' => $r['power'], 'me' => ( $pid && $r['pid'] === $pid ) ); }
	$prev = array();
	foreach ( (array) ( isset( $d['prev'][ $lg ] ) ? $d['prev'][ $lg ] : array() ) as $r ) { $prev[] = array( 'n' => $r['n'], 'cities' => $r['cities'] ); }
	return array(
		'week' => $d['week'], 'ends' => suorni_kisa_ends(), 'league' => $lg, 'leagueName' => suorni_kisa_liiga_name( $lg ),
		'cities' => $cities,
		'me' => array( 'owned' => $owned, 'cap' => suorni_kisa_cap( $d, $pid ), 'om' => pow( 1.2, $owned ), 'nb' => ( $newb && $owned < 3 ) ? 0.5 : 1.0, 'days' => isset( $d['act'][ $pid ] ) ? count( $d['act'][ $pid ] ) : 0 ),
		'top' => $st, 'prev' => $prev,
	);
}
function suorni_kisa_map( $req ) {
	$pid = suorni_kisa_pid( $req->get_param( 'pid' ) );
	$d = suorni_kisa_load();
	if ( strlen( $pid ) >= 8 && ! get_transient( 'suorni_pkm_' . md5( $pid ) ) ) {
		set_transient( 'suorni_pkm_' . md5( $pid ), 1, 60 );
		suorni_kisa_touch( $d, $pid );
		update_option( 'suorni_peli_kisa', $d, false );
	}
	return suorni_json( suorni_kisa_view( $d, $pid ) );
}
function suorni_kisa_rl( $pid ) {
	$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	$k = 'suorni_pk_' . md5( $ip ); $n = (int) get_transient( $k );
	if ( $n >= 240 ) { return false; }
	set_transient( $k, $n + 1, 600 );
	$k2 = 'suorni_pkp_' . md5( $pid );
	if ( get_transient( $k2 ) ) { return false; }
	set_transient( $k2, 1, 3 );
	return true;
}
function suorni_kisa_claim( $req ) {
	$pid = suorni_kisa_pid( $req->get_param( 'pid' ) );
	$name = suorni_kisa_city( $req->get_param( 'city' ) );
	$pw = (float) $req->get_param( 'power' );
	if ( strlen( $pid ) < 8 || strlen( $name ) < 2 || $pw < 0 || $pw > 1e12 ) { return new WP_Error( 'suorni_bad', 'Virheellinen', array( 'status' => 400 ) ); }
	if ( ! suorni_kisa_rl( $pid ) ) { return suorni_json( array( 'ok' => true, 'wait' => true ) ); }
	$d = suorni_kisa_load();
	$lg = suorni_kisa_liiga( $pid );
	if ( ! isset( $d['c'][ $lg ] ) ) { $d['c'][ $lg ] = array(); }
	$cs = &$d['c'][ $lg ];
	if ( ! isset( $cs[ $name ] ) && count( $cs ) >= 100 ) { return new WP_Error( 'suorni_bad', 'Virheellinen', array( 'status' => 400 ) ); }
	$city = isset( $cs[ $name ] ) ? $cs[ $name ] : array( 'o' => null, 'h' => array() );
	if ( count( $city['h'] ) > 300 && ! isset( $city['h'][ $pid ] ) ) { return new WP_Error( 'suorni_bad', 'Kaupunki on täynnä', array( 'status' => 400 ) ); }
	$old = isset( $city['h'][ $pid ] ) ? $city['h'][ $pid ] : null;
	$city['h'][ $pid ] = array( 'p' => $pw, 't' => $old ? (int) $old['t'] : time() );
	$was = ( isset( $city['o'] ) && $city['o'] === $pid );
	$new = suorni_kisa_pickowner( $city );
	/* lupa-raja: uuden kaupungin omistaminen vaatii aktiivisia päiviä */
	if ( $new === $pid && ! $was && suorni_kisa_owned_by( $cs, $pid ) >= suorni_kisa_cap( $d, $pid ) ) {
		unset( $cs );
		return suorni_json( array_merge( array( 'ok' => false, 'cap' => true ), suorni_kisa_view( $d, $pid ) ) );
	}
	$city['o'] = $new;
	$cs[ $name ] = $city;
	unset( $cs );
	suorni_kisa_touch( $d, $pid );
	update_option( 'suorni_peli_kisa', $d, false );
	return suorni_json( array_merge( array( 'ok' => true ), suorni_kisa_view( $d, $pid ) ) );
}
function suorni_kisa_unclaim( $req ) {
	$pid = suorni_kisa_pid( $req->get_param( 'pid' ) );
	$name = suorni_kisa_city( $req->get_param( 'city' ) );
	if ( strlen( $pid ) < 8 || strlen( $name ) < 2 ) { return new WP_Error( 'suorni_bad', 'Virheellinen', array( 'status' => 400 ) ); }
	if ( ! suorni_kisa_rl( $pid ) ) { return suorni_json( array( 'ok' => true, 'wait' => true ) ); }
	$d = suorni_kisa_load();
	$lg = suorni_kisa_liiga( $pid );
	if ( isset( $d['c'][ $lg ][ $name ]['h'][ $pid ] ) ) {
		unset( $d['c'][ $lg ][ $name ]['h'][ $pid ] );
		if ( ! $d['c'][ $lg ][ $name ]['h'] ) { unset( $d['c'][ $lg ][ $name ] ); }
		else { $d['c'][ $lg ][ $name ]['o'] = suorni_kisa_pickowner( array( 'o' => null, 'h' => $d['c'][ $lg ][ $name ]['h'] ) ); }
		update_option( 'suorni_peli_kisa', $d, false );
	}
	return suorni_json( array_merge( array( 'ok' => true ), suorni_kisa_view( $d, $pid ) ) );
}
add_action( 'rest_api_init', function () {
	register_rest_route( 'suorni/v1', '/peli/map', array( 'methods' => 'GET', 'callback' => 'suorni_kisa_map', 'permission_callback' => '__return_true' ) );
	register_rest_route( 'suorni/v1', '/peli/claim', array( 'methods' => 'POST', 'callback' => 'suorni_kisa_claim', 'permission_callback' => '__return_true' ) );
	register_rest_route( 'suorni/v1', '/peli/unclaim', array( 'methods' => 'POST', 'callback' => 'suorni_kisa_unclaim', 'permission_callback' => '__return_true' ) );
} );

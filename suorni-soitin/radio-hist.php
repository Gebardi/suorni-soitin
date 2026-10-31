<?php
/* Soittohistoria, viikon top 10 ja kappaleen jakaminen. */
if ( ! defined( 'ABSPATH' ) ) { exit; }

function suorni_hist_get() { $h = get_option( 'suorni_hist' . suorni_ch_sfx(), array() ); return is_array( $h ) ? $h : array(); }
/* Kirjataan soiva kappale historiaan (kutsutaan /radio/now -pyynnöstä) */
function suorni_hist_note( $items, $now ) {
	foreach ( $items as $it ) {
		if ( 'track' !== $it['type'] || $it['start'] > $now || $it['start'] + $it['dur'] <= $now ) { continue; }
		$k = (string) (int) $it['start'];
		$h = suorni_hist_get();
		foreach ( array_slice( $h, -3 ) as $x ) { if ( isset( $x['k'] ) && $x['k'] === $k ) { return; } }
		$h[] = array( 'k' => $k, 'id' => $it['id'], 'sub' => ! empty( $it['sub'] ) ? 1 : 0, 't' => (string) $it['title'], 'a' => (string) $it['artist'], 'at' => (int) $it['start'] );
		update_option( 'suorni_hist' . suorni_ch_sfx(), array_slice( $h, -40 ), false );
		return;
	}
}
function suorni_wk_key() { return wp_date( 'o-W' ); }
function suorni_wk_add( $key, $delta ) {
	if ( ! $delta ) { return; }
	$w = get_option( 'suorni_wk', array() );
	if ( ! is_array( $w ) || ! isset( $w['w'] ) || suorni_wk_key() !== $w['w'] ) { $w = array( 'w' => suorni_wk_key(), 'm' => array() ); }
	$w['m'][ $key ] = ( isset( $w['m'][ $key ] ) ? (int) $w['m'][ $key ] : 0 ) + (int) $delta;
	update_option( 'suorni_wk', $w, false );
	foreach ( suorni_ch_ids() as $c ) { delete_transient( 'suorni_hist_out' . ( '' === $c ? '' : '_' . $c ) ); }
}
function suorni_hist_row( $key, $tracks, $subs ) {
	if ( 0 === strpos( $key, 't' ) ) {
		$id = (int) substr( $key, 1 );
		foreach ( $tracks as $t ) { if ( (int) $t['id'] === $id ) { return array( 'id' => $id, 't' => $t['title'], 'a' => $t['artist'] ); } }
	} elseif ( 0 === strpos( $key, 's' ) ) {
		$id = (int) substr( $key, 1 );
		foreach ( $subs as $s ) { if ( (int) $s['id'] === $id && in_array( $s['status'], array( 'ok', 'dropped' ), true ) ) { return array( 'id' => 'c' . $id, 't' => $s['title'], 'a' => $s['artist'] ); } }
	}
	return null;
}
function suorni_hist_build() {
	$c = get_transient( 'suorni_hist_out' . suorni_ch_sfx() );
	if ( is_array( $c ) ) { return $c; }
	$tracks = suorni_tracks();
	$subs   = function_exists( 'suorni_subs' ) ? suorni_subs() : array();
	$recent = array();
	foreach ( array_reverse( array_slice( suorni_hist_get(), -15 ) ) as $x ) { $recent[] = array( 'id' => $x['id'], 't' => suorni_nosuno( $x['t'] ), 'a' => suorni_nosuno( $x['a'] ), 'at' => (int) $x['at'] ); }
	$w   = get_option( 'suorni_wk', array() );
	$m   = ( is_array( $w ) && isset( $w['w'] ) && suorni_wk_key() === $w['w'] && isset( $w['m'] ) ) ? $w['m'] : array();
	$lab = 'Viikon top 10';
	if ( count( array_filter( $m, function ( $v ) { return $v > 0; } ) ) < 3 ) {
		$m = array();
		$tv = get_option( 'suorni_tvotes', array() );
		if ( is_array( $tv ) ) { foreach ( $tv as $id => $x ) { $m[ 't' . (int) $id ] = (int) $x['u'] - (int) $x['d']; } }
		foreach ( $subs as $s ) { $m[ 's' . (int) $s['id'] ] = (int) ( isset( $s['up'] ) ? $s['up'] : 0 ) - (int) ( isset( $s['down'] ) ? $s['down'] : 0 ); }
		$lab = 'Suosituimmat';
	}
	arsort( $m );
	$top = array();
	foreach ( $m as $k => $net ) {
		if ( $net <= 0 || count( $top ) >= 10 ) { continue; }
		$r = suorni_hist_row( (string) $k, $tracks, $subs );
		if ( $r ) { $r['n'] = $net; $r['t'] = suorni_nosuno( $r['t'] ); $r['a'] = suorni_nosuno( $r['a'] ); $top[] = $r; }
	}
	$out = array( 'recent' => $recent, 'top' => $top, 'toplabel' => $lab );
	set_transient( 'suorni_hist_out' . suorni_ch_sfx(), $out, 60 );
	return $out;
}
function suorni_hist_get_rest( $req ) {
	if ( ! suorni_radio_on() ) { return suorni_json( array( 'off' => true ) ); }
	suorni_ch_set( suorni_ch_clean( $req->get_param( 'ch' ) ) );
	$out = suorni_hist_build();
	$one = (string) $req->get_param( 'id' );
	if ( '' !== $one && preg_match( '#^c?\d{1,9}$#', $one ) ) {
		$r = null;
		if ( 'c' === $one[0] ) { $r = suorni_hist_row( 's' . substr( $one, 1 ), array(), suorni_subs() ); }
		else { $r = suorni_hist_row( 't' . $one, suorni_tracks(), array() ); }
		if ( $r ) { $out['one'] = array( 'id' => $one, 't' => suorni_nosuno( $r['t'] ), 'a' => suorni_nosuno( $r['a'] ) ); }
	}
	return suorni_json( $out );
}
add_action( 'rest_api_init', function () {
	register_rest_route( 'suorni/v1', '/radio/hist', array( 'methods' => 'GET', 'callback' => 'suorni_hist_get_rest', 'permission_callback' => '__return_true' ) );
} );

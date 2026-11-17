<?php
/* Taajuus-pelin yhteinen viikkotavoite: kaikki pelaajat kerryttävät yhteistä pistemäärää, tavoite kasvaa edellisen viikon tuloksen mukaan.
   Rajapinnat (julkisia, pelaajan ilmoittamat tiedot, ei todennettu):
   GET  peli/comm?pid=            { week, ends, goal, total, mine, contributors, min, prev }
   POST peli/contrib {pid,n}      lisää pelaajan panoksen (n = kuuntelupisteitä, max 40 / kutsu), palauttaa saman näkymän
   Suojaukset: kutsuväli 15 s / pelaaja, päiväkatto 800 p / pelaaja, IP-raja 90 kutsua / 10 min.
   Tavoite = max( 1500, 1,15 × edellisen viikon kokonaispisteet ). Viikko vaihtuu maanantaina (sama kuin viikkokisa). */
if ( ! defined( 'ABSPATH' ) ) { exit; }
function suorni_comm_load() {
	$d = get_option( 'suorni_peli_comm', array() );
	if ( ! is_array( $d ) ) { $d = array(); }
	$w = suorni_kisa_week();
	if ( ! isset( $d['week'] ) || $d['week'] !== $w ) {
		$prev = isset( $d['tot'] ) ? (int) $d['tot'] : 0;
		$d = array( 'week' => $w, 'tot' => 0, 'c' => array(), 'day' => array(), 'prev' => $prev );
		update_option( 'suorni_peli_comm', $d, false );
	}
	foreach ( array( 'c', 'day' ) as $k ) { if ( ! isset( $d[ $k ] ) || ! is_array( $d[ $k ] ) ) { $d[ $k ] = array(); } }
	return $d;
}
function suorni_comm_view( $d, $pid ) {
	$h = md5( $pid );
	$prev = isset( $d['prev'] ) ? (int) $d['prev'] : 0;
	return array(
		'week' => $d['week'], 'ends' => suorni_kisa_ends(),
		'goal' => max( 1500, (int) round( $prev * 1.15 ) ),
		'total' => (int) $d['tot'], 'mine' => isset( $d['c'][ $h ] ) ? (int) $d['c'][ $h ] : 0,
		'contributors' => count( $d['c'] ), 'min' => 40, 'prev' => $prev,
	);
}
function suorni_comm_get( $req ) {
	$pid = suorni_kisa_pid( $req->get_param( 'pid' ) );
	return suorni_json( suorni_comm_view( suorni_comm_load(), $pid ) );
}
function suorni_comm_post( $req ) {
	$pid = suorni_kisa_pid( $req->get_param( 'pid' ) );
	$n = max( 0, min( 40, (int) $req->get_param( 'n' ) ) );
	if ( strlen( $pid ) < 8 ) { return new WP_Error( 'suorni_bad', 'Virheellinen', array( 'status' => 400 ) ); }
	$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	$k = 'suorni_pc_' . md5( $ip ); $c = (int) get_transient( $k );
	$k2 = 'suorni_pcp_' . md5( $pid );
	$d = suorni_comm_load();
	if ( $c >= 90 || get_transient( $k2 ) || $n < 1 ) { return suorni_json( array_merge( array( 'ok' => true, 'wait' => true ), suorni_comm_view( $d, $pid ) ) ); }
	set_transient( $k, $c + 1, 600 );
	set_transient( $k2, 1, 15 );
	$h = md5( $pid ); $today = wp_date( 'Ymd' );
	$dk = $h . $today;
	$used = isset( $d['day'][ $dk ] ) ? (int) $d['day'][ $dk ] : 0;
	$add = max( 0, min( $n, 800 - $used ) );
	if ( $add > 0 ) {
		$d['day'] = array_filter( $d['day'], function ( $k3 ) use ( $today ) { return substr( $k3, -8 ) === $today; }, ARRAY_FILTER_USE_KEY );
		$d['day'][ $dk ] = $used + $add;
		$d['c'][ $h ] = ( isset( $d['c'][ $h ] ) ? (int) $d['c'][ $h ] : 0 ) + $add;
		$d['tot'] = (int) $d['tot'] + $add;
		update_option( 'suorni_peli_comm', $d, false );
	}
	return suorni_json( array_merge( array( 'ok' => true ), suorni_comm_view( $d, $pid ) ) );
}
add_action( 'rest_api_init', function () {
	register_rest_route( 'suorni/v1', '/peli/comm', array( 'methods' => 'GET', 'callback' => 'suorni_comm_get', 'permission_callback' => '__return_true' ) );
	register_rest_route( 'suorni/v1', '/peli/contrib', array( 'methods' => 'POST', 'callback' => 'suorni_comm_post', 'permission_callback' => '__return_true' ) );
} );

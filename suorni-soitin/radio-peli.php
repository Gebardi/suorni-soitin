<?php
/* Taajuus-peli (peli/): staattinen PWA lisäosan kansiossa. Sivusto käyttää "tavallisia" kestolinkkejä, joten /peli/-polulle ei voi tehdä uudelleenkirjoitussääntöä;
   lyhyt osoite https://suorni.fi/?peli=1 ohjaa pelin omaan osoitteeseen (service workerin scope on silloin vain peli-kansio, ei koko sivusto). */
if ( ! defined( 'ABSPATH' ) ) { exit; }

function suorni_peli_url() { return plugins_url( 'peli/index.html', dirname( __FILE__ ) . '/suorni-soitin.php' ); }
add_action( 'template_redirect', function () {
	if ( ! isset( $_GET['peli'] ) ) { return; }
	$q = isset( $_GET['src'] ) ? sanitize_key( wp_unslash( $_GET['src'] ) ) : '';
	wp_redirect( suorni_peli_url() . ( '' !== $q ? '?src=' . $q : '' ), 302 );
	exit;
}, 1 );

/* Etusivun pelilinkki: Taajuus-peli, jonka voi lisätä kotinäkymään kuvakkeeksi */
function suorni_peli_html() {
	$u = esc_url( add_query_arg( array( 'peli' => '1', 'src' => 'sivu' ), home_url( '/' ) ) );
	return '<section class="rpeli"><div class="rpi"><img src="' . esc_url( plugins_url( 'peli/icon-192.png', dirname( __FILE__ ) . '/suorni-soitin.php' ) ) . '" alt="" width="56" height="56"></div>'
		. '<div class="rpt"><b>🎮 Taajuus – pelaa omaa radioasemaa</b><span>Rento peli: rakenna autotallilähettimestä valtakunnan radio. Voit lisätä pelin puhelimen tai tietokoneen kotinäkymään omaksi sovelluksekseen (selaimen valikosta: &quot;Lisää kotinäyttöön&quot; tai &quot;Asenna&quot;).</span>'
		. '<a class="rpb" href="' . $u . '">Pelaa ja asenna →</a></div></section>'
		. '<style>.sr .rpeli{margin:26px 0 0;display:flex;gap:14px;align-items:center;padding:14px;border:1px solid rgba(255,255,255,.14);border-radius:12px;background:rgba(255,255,255,.04)}.sr .rpi img{display:block;border-radius:12px}.sr .rpt{display:flex;flex-direction:column;gap:6px;min-width:0}.sr .rpt b{font-size:15px}.sr .rpt span{font-size:13px;line-height:1.5;opacity:.85}.sr .rpb{align-self:flex-start;margin-top:4px;padding:8px 14px;border-radius:8px;background:var(--a,#4da3ff);color:#06101f;font-weight:700;text-decoration:none;font-size:14px}@media(max-width:480px){.sr .rpeli{align-items:flex-start}}</style>';
}

/* ---------- Taajuus-pelin rajapinnat ----------
   /peli/now: viimeksi oikeasti kuunneltu kappale (soitin ilmoittaa sen /play-kutsulla, kun kappaletta on kuunneltu 30 s tai puolet).
   /peli/score + /peli/top: pelaajien Top 10 (nimimerkki + kokonaistuotto). Tiedot ovat pelaajan itse ilmoittamia, eivät todennettuja. */
function suorni_peli_name( $n ) {
	$n = sanitize_text_field( (string) $n );
	$n = preg_replace( '/[^\p{L}\p{N} _.\-]/u', '', $n );
	$n = trim( preg_replace( '/\s+/u', ' ', $n ) );
	return function_exists( 'mb_substr' ) ? mb_substr( $n, 0, 16 ) : substr( $n, 0, 16 );
}
function suorni_peli_now() {
	$d = get_transient( 'suorni_now' );
	if ( ! is_array( $d ) || empty( $d['id'] ) ) { return suorni_json( array( 'id' => 0 ) ); }
	return suorni_json( array( 'id' => (int) $d['id'], 'age' => max( 0, time() - (int) $d['t'] ) ) );
}
function suorni_peli_top_list() {
	$l = get_option( 'suorni_peli_top', array() );
	return is_array( $l ) ? $l : array();
}
function suorni_peli_score( $req ) {
	$pid = preg_replace( '/[^a-z0-9]/', '', strtolower( (string) $req->get_param( 'pid' ) ) );
	$pid = substr( $pid, 0, 24 );
	$nm  = suorni_peli_name( $req->get_param( 'name' ) );
	$sc  = (float) $req->get_param( 'score' );
	if ( strlen( $pid ) < 8 || mb_strlen( $nm ) < 2 || $sc < 0 || $sc > 1e18 ) { return new WP_Error( 'suorni_bad', 'Virheellinen', array( 'status' => 400 ) ); }
	$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	$k  = 'suorni_ps_' . md5( $ip . $pid );
	if ( get_transient( $k ) ) { return suorni_json( array( 'ok' => true, 'wait' => true ) ); }
	set_transient( $k, 1, 20 );
	$l = suorni_peli_top_list();
	$old = isset( $l[ $pid ] ) ? $l[ $pid ] : null;
	if ( $old && ! empty( $old['x'] ) ) { return suorni_json( array( 'ok' => true ) ); } /* piilotettu: ei päivitetä */
	if ( $old && $sc < (float) $old['s'] ) { $sc = (float) $old['s']; }
	$l[ $pid ] = array( 'n' => $nm, 's' => $sc, 't' => time(), 'x' => 0 );
	uasort( $l, function ( $a, $b ) { return $b['s'] <=> $a['s']; } );
	$l = array_slice( $l, 0, 200, true );
	update_option( 'suorni_peli_top', $l, false );
	return suorni_json( array( 'ok' => true ) );
}
function suorni_peli_top( $req ) {
	$pid = preg_replace( '/[^a-z0-9]/', '', strtolower( (string) $req->get_param( 'pid' ) ) );
	$l = array_filter( suorni_peli_top_list(), function ( $e ) { return empty( $e['x'] ); } );
	uasort( $l, function ( $a, $b ) { return $b['s'] <=> $a['s']; } );
	$top = array(); $me = null; $i = 0;
	foreach ( $l as $k => $e ) {
		$i++;
		if ( $i <= 10 ) { $top[] = array( 'n' => $e['n'], 's' => $e['s'], 'me' => ( $pid && $k === $pid ) ); }
		if ( $pid && $k === $pid ) { $me = array( 'rank' => $i, 's' => $e['s'] ); }
	}
	return suorni_json( array( 'top' => $top, 'me' => $me, 'n' => count( $l ) ) );
}
function suorni_peli_hide( $req ) {
	$nm = suorni_peli_name( $req->get_param( 'name' ) );
	$l  = suorni_peli_top_list(); $n = 0;
	foreach ( $l as $k => $e ) { if ( 0 === strcasecmp( $e['n'], $nm ) ) { $l[ $k ]['x'] = 1; $n++; } }
	update_option( 'suorni_peli_top', $l, false );
	return suorni_json( array( 'ok' => true, 'hidden' => $n ) );
}
add_action( 'rest_api_init', function () {
	register_rest_route( 'suorni/v1', '/peli/now', array( 'methods' => 'GET', 'callback' => 'suorni_peli_now', 'permission_callback' => '__return_true' ) );
	register_rest_route( 'suorni/v1', '/peli/score', array( 'methods' => 'POST', 'callback' => 'suorni_peli_score', 'permission_callback' => '__return_true' ) );
	register_rest_route( 'suorni/v1', '/peli/top', array( 'methods' => 'GET', 'callback' => 'suorni_peli_top', 'permission_callback' => '__return_true' ) );
	register_rest_route( 'suorni/v1', '/peli/hide', array( 'methods' => 'POST', 'callback' => 'suorni_peli_hide', 'permission_callback' => 'suorni_can' ) );
} );

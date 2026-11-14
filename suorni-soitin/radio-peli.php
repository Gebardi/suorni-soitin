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
		. '<style>.sr .rpeli{margin:18px 0 6px;display:flex;gap:14px;align-items:center;padding:14px;border:1px solid rgba(255,255,255,.14);border-radius:12px;background:rgba(255,255,255,.04)}.sr .rpi img{display:block;border-radius:12px}.sr .rpt{display:flex;flex-direction:column;gap:6px;min-width:0}.sr .rpt b{font-size:15px}.sr .rpt span{font-size:13px;line-height:1.5;opacity:.85}.sr .rpb{align-self:flex-start;margin-top:4px;padding:8px 14px;border-radius:8px;background:var(--a,#4da3ff);color:#06101f;font-weight:700;text-decoration:none;font-size:14px}@media(max-width:480px){.sr .rpeli{align-items:flex-start}}</style>';
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
	if ( $old && $sc < (float) $old['s'] ) { $sc = (float) $old['s']; }
	$same = $old && 0 === strcmp( $old['n'], $nm );
	/* uusi tai vaihdettu nimimerkki odottaa ylläpidon hyväksyntää */
	$l[ $pid ] = array( 'n' => $nm, 's' => $sc, 't' => time(), 'ok' => ( $same && ! empty( $old['ok'] ) ) ? 1 : 0, 'x' => ( $same && ! empty( $old['x'] ) ) ? 1 : 0 );
	uasort( $l, function ( $a, $b ) { return $b['s'] <=> $a['s']; } );
	$l = array_slice( $l, 0, 200, true );
	update_option( 'suorni_peli_top', $l, false );
	return suorni_json( array( 'ok' => true ) );
}
function suorni_peli_top( $req ) {
	$pid = preg_replace( '/[^a-z0-9]/', '', strtolower( (string) $req->get_param( 'pid' ) ) );
	$all = suorni_peli_top_list();
	$l = array_filter( $all, function ( $e ) { return ! empty( $e['ok'] ) && empty( $e['x'] ); } );
	uasort( $l, function ( $a, $b ) { return $b['s'] <=> $a['s']; } );
	$top = array(); $me = null; $i = 0;
	foreach ( $l as $k => $e ) {
		$i++;
		if ( $i <= 10 ) { $top[] = array( 'n' => $e['n'], 's' => $e['s'], 'me' => ( $pid && $k === $pid ) ); }
		if ( $pid && $k === $pid ) { $me = array( 'rank' => $i, 's' => $e['s'] ); }
	}
	if ( $pid && ! $me && isset( $all[ $pid ] ) ) {
		$e  = $all[ $pid ];
		$me = array( 'rank' => 0, 's' => $e['s'], 'state' => ! empty( $e['x'] ) ? 'hylatty' : 'odottaa' );
	}
	return suorni_json( array( 'top' => $top, 'me' => $me, 'n' => count( $l ) ) );
}
/* Peukutus: pelaajat arvioivat oikeita kappaleita pelissä. option suorni_peli_votes = [ kappale-id => [ pelaaja-id => 1|-1 ] ] */
function suorni_peli_vote( $req ) {
	$pid = substr( preg_replace( '/[^a-z0-9]/', '', strtolower( (string) $req->get_param( 'pid' ) ) ), 0, 24 );
	$tid = absint( $req->get_param( 'tid' ) );
	$v   = (int) $req->get_param( 'v' );
	if ( strlen( $pid ) < 8 || ! $tid || ! in_array( $v, array( -1, 0, 1 ), true ) || 0 !== strpos( (string) get_post_mime_type( $tid ), 'audio' ) ) {
		return new WP_Error( 'suorni_bad', 'Virheellinen', array( 'status' => 400 ) );
	}
	$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	$rk = 'suorni_pv_' . md5( $ip );
	$n  = (int) get_transient( $rk );
	if ( $n >= 60 ) { return suorni_json( array( 'ok' => true, 'wait' => true ) ); }
	set_transient( $rk, $n + 1, 600 );
	$all = get_option( 'suorni_peli_votes', array() );
	if ( ! is_array( $all ) ) { $all = array(); }
	$t = ( isset( $all[ $tid ] ) && is_array( $all[ $tid ] ) ) ? $all[ $tid ] : array();
	if ( 0 === $v ) { unset( $t[ $pid ] ); }
	elseif ( isset( $t[ $pid ] ) || count( $t ) < 5000 ) { $t[ $pid ] = $v; }
	$all[ $tid ] = $t;
	update_option( 'suorni_peli_votes', $all, false );
	return suorni_json( array( 'ok' => true ) );
}
function suorni_peli_votes_get() {
	$all = get_option( 'suorni_peli_votes', array() ); $out = array();
	if ( is_array( $all ) ) {
		foreach ( $all as $tid => $t ) {
			$u = 0; $d = 0;
			foreach ( (array) $t as $x ) { if ( $x > 0 ) { $u++; } elseif ( $x < 0 ) { $d++; } }
			if ( $u || $d ) { $out[] = array( 'id' => (int) $tid, 'up' => $u, 'down' => $d ); }
		}
	}
	return suorni_json( array( 'votes' => $out ) );
}
add_action( 'rest_api_init', function () {
	register_rest_route( 'suorni/v1', '/peli/vote', array( 'methods' => 'POST', 'callback' => 'suorni_peli_vote', 'permission_callback' => '__return_true' ) );
	register_rest_route( 'suorni/v1', '/peli/votes', array( 'methods' => 'GET', 'callback' => 'suorni_peli_votes_get', 'permission_callback' => 'suorni_can' ) );
} );
add_action( 'rest_api_init', function () {
	register_rest_route( 'suorni/v1', '/peli/now', array( 'methods' => 'GET', 'callback' => 'suorni_peli_now', 'permission_callback' => '__return_true' ) );
	register_rest_route( 'suorni/v1', '/peli/score', array( 'methods' => 'POST', 'callback' => 'suorni_peli_score', 'permission_callback' => '__return_true' ) );
	register_rest_route( 'suorni/v1', '/peli/top', array( 'methods' => 'GET', 'callback' => 'suorni_peli_top', 'permission_callback' => '__return_true' ) );
} );

/* Arvaa tyyli -minipeli: pelaajien tyyliarviot kappaleille (yksi arvio per pelaaja ja kappale) */
function suorni_peli_guess( $req ) {
	$pid = substr( preg_replace( '/[^a-z0-9]/', '', strtolower( (string) $req->get_param( 'pid' ) ) ), 0, 24 );
	$tid = absint( $req->get_param( 'tid' ) );
	$g   = substr( preg_replace( '/[^a-z0-9]/', '', strtolower( (string) $req->get_param( 'g' ) ) ), 0, 16 );
	if ( strlen( $pid ) < 8 || ! $tid || '' === $g || 0 !== strpos( (string) get_post_mime_type( $tid ), 'audio' ) ) {
		return new WP_Error( 'suorni_bad', 'Virheellinen', array( 'status' => 400 ) );
	}
	$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	$rk = 'suorni_pg_' . md5( $ip );
	$n  = (int) get_transient( $rk );
	if ( $n >= 80 ) { return suorni_json( array( 'ok' => true, 'wait' => true ) ); }
	set_transient( $rk, $n + 1, 600 );
	$all = get_option( 'suorni_peli_guess', array() );
	if ( ! is_array( $all ) ) { $all = array(); }
	$t = ( isset( $all[ $tid ] ) && is_array( $all[ $tid ] ) ) ? $all[ $tid ] : array();
	if ( isset( $t[ $pid ] ) || count( $t ) < 2000 ) { $t[ $pid ] = $g; }
	$all[ $tid ] = $t;
	update_option( 'suorni_peli_guess', $all, false );
	return suorni_json( array( 'ok' => true ) );
}
add_action( 'rest_api_init', function () {
	register_rest_route( 'suorni/v1', '/peli/guess', array( 'methods' => 'POST', 'callback' => 'suorni_peli_guess', 'permission_callback' => '__return_true' ) );
} );

/* Ohjaamon 🎮 Peli -välilehti: nimimerkkien moderointi ja kappaleiden peukutus */
function suorni_peli_admin() {
	$l = suorni_peli_top_list();
	uasort( $l, function ( $a, $b ) { return $b['s'] <=> $a['s']; } );
	$g = array( 'pend' => array(), 'ok' => array(), 'no' => array() );
	foreach ( $l as $k => $e ) {
		$row = array( 'pid' => $k, 'n' => $e['n'], 's' => round( (float) $e['s'] ) );
		if ( ! empty( $e['x'] ) ) { $g['no'][] = $row; }
		elseif ( ! empty( $e['ok'] ) ) { $g['ok'][] = $row; }
		else { $g['pend'][] = $row; }
	}
	$all = get_option( 'suorni_peli_votes', array() );
	$tr  = array();
	if ( function_exists( 'suorni_tracks' ) ) { foreach ( (array) suorni_tracks() as $t ) { if ( isset( $t['id'] ) ) { $tr[ (int) $t['id'] ] = $t; } } }
	$rows = array();
	if ( is_array( $all ) ) {
		foreach ( $all as $tid => $t ) {
			$u = 0; $d = 0;
			foreach ( (array) $t as $x ) { if ( $x > 0 ) { $u++; } elseif ( $x < 0 ) { $d++; } }
			if ( ! ( $u + $d ) ) { continue; }
			$tt = isset( $tr[ (int) $tid ] ) ? $tr[ (int) $tid ] : null;
			$nm = $tt ? ( $tt['title'] . ( ! empty( $tt['artist'] ) ? ' – ' . $tt['artist'] : '' ) ) : ( get_the_title( (int) $tid ) ?: ( '#' . (int) $tid ) );
			$rows[] = array( 'id' => (int) $tid, 'n' => $nm, 'u' => $u, 'd' => $d );
		}
	}
	usort( $rows, function ( $a, $b ) { $x = ( $b['u'] - $b['d'] ) <=> ( $a['u'] - $a['d'] ); return $x ?: $b['u'] <=> $a['u']; } );
	$g['votes'] = $rows;
	return $g;
}
function suorni_peliact_post( $req ) {
	$pid = preg_replace( '/[^a-z0-9]/', '', strtolower( (string) $req->get_param( 'pid' ) ) );
	$do  = sanitize_key( (string) $req->get_param( 'do' ) );
	$l   = suorni_peli_top_list();
	if ( $pid && isset( $l[ $pid ] ) ) {
		if ( 'ok' === $do ) { $l[ $pid ]['ok'] = 1; $l[ $pid ]['x'] = 0; }
		elseif ( 'no' === $do ) { $l[ $pid ]['ok'] = 0; $l[ $pid ]['x'] = 1; }
		elseif ( 'del' === $do ) { unset( $l[ $pid ] ); }
		update_option( 'suorni_peli_top', $l, false );
	}
	return suorni_json( array( 'peli' => suorni_peli_admin() ) );
}
add_action( 'rest_api_init', function () {
	register_rest_route( 'suorni/v1', '/radio/peliact', array( 'methods' => 'POST', 'callback' => 'suorni_peliact_post', 'permission_callback' => 'suorni_can' ) );
} );

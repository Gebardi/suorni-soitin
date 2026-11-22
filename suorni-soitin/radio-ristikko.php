<?php
/* Kuvaristikko (pop-ristikko): yhteistyö + kilpailu. Sivu: https://suorni.fi/?ristikko=1  (huone: ?ristikko=KOODI)
   Vastaukset ovat vain palvelimella (ristikko-puzzles.php). Ruutuun tulee kirjain vain, jos se on oikein.
   Pisteet: oikea kirjain +10, sanan viimeistelijälle +30, väärä kirjain -5 (ei alle nollan).
   Rajapinnat (julkisia, pelaajan ilmoittama tunniste ei ole todennettu):
   POST ristikko/new   {pid,name,pz?}          luo huoneen, palauttaa {room}
   POST ristikko/state {room,pid,name,v}       huoneen tila (v = viimeksi nähty versio; sama versio -> {same:1}); liittää pelaajan
   POST ristikko/put   {room,pid,i,ch}         arvaus ruutuun i (rivi*sarakkeet+sarake); palauttaa tilan + {right:bool}
   Suojaukset: huonekohtainen lukko (GET_LOCK), 40 arvausta / 10 s / pelaaja, IP-raja 1500 kutsua / 10 min, 20 huonetta / tunti / IP,
   enintään 8 pelaajaa huoneessa, huoneet poistuvat 2 vrk jälkeen. */
if ( ! defined( 'ABSPATH' ) ) { exit; }

function suorni_rk_puzzles() { static $p = null; if ( null === $p ) { $p = include dirname( __FILE__ ) . '/ristikko-puzzles.php'; } return is_array( $p ) ? $p : array(); }
function suorni_rk_code( $v ) { return substr( preg_replace( '/[^A-Z0-9]/', '', strtoupper( (string) $v ) ), 0, 5 ); }
function suorni_rk_opt( $code ) { return 'suorni_rk_' . $code; }
function suorni_rk_ip_ok( $max = 1500 ) {
	$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	$k = 'suorni_rkip_' . md5( $ip ); $c = (int) get_transient( $k );
	if ( $c >= $max ) { return false; }
	set_transient( $k, $c + 1, 600 );
	return true;
}
function suorni_rk_lock( $code ) { global $wpdb; return (bool) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 3)', 'suorni_rk_' . $code ) ); }
function suorni_rk_unlock( $code ) { global $wpdb; $wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', 'suorni_rk_' . $code ) ); }
function suorni_rk_load( $code ) {
	$d = get_option( suorni_rk_opt( $code ), null );
	if ( ! is_array( $d ) || empty( $d['pz'] ) ) { return null; }
	if ( time() - (int) $d['t0'] > 2 * DAY_IN_SECONDS ) { return null; }
	return $d;
}
function suorni_rk_save( $code, $d ) { update_option( suorni_rk_opt( $code ), $d, false ); }
function suorni_rk_puzzle( $id ) { foreach ( suorni_rk_puzzles() as $p ) { if ( (int) $p['id'] === (int) $id ) { return $p; } } return null; }

/* ruutujen sanalistat: jokaiselle sanalle sen ruutuindeksit */
function suorni_rk_cells_of( $e, $C ) {
	$dr = 'r' === $e['d'] ? 0 : 1; $dc = 'r' === $e['d'] ? 1 : 0; $out = array();
	for ( $j = 0, $n = strlen( $e['w'] ) ? mb_strlen( $e['w'], 'UTF-8' ) : 0; $j < $n; $j++ ) { $out[] = ( $e['r'] + $dr * $j ) * $C + $e['c'] + $dc * $j; }
	return $out;
}
function suorni_rk_solution( $p ) {
	$sol = array();
	foreach ( $p['ents'] as $e ) {
		$cells = suorni_rk_cells_of( $e, $p['c'] );
		$chars = preg_split( '//u', $e['w'], -1, PREG_SPLIT_NO_EMPTY );
		foreach ( $cells as $j => $i ) { $sol[ $i ] = $chars[ $j ]; }
	}
	return $sol;
}
function suorni_rk_layout( $p ) {
	$ents = array();
	foreach ( $p['ents'] as $k => $e ) { $ents[] = array( 'id' => $k, 'e' => $e['e'], 'h' => $e['h'], 'r' => $e['r'], 'c' => $e['c'], 'd' => $e['d'], 'len' => mb_strlen( $e['w'], 'UTF-8' ) ); }
	return array( 'id' => (int) $p['id'], 'r' => (int) $p['r'], 'c' => (int) $p['c'], 'ents' => $ents );
}
function suorni_rk_view( $d, $pid, $full ) {
	$h = md5( $pid ); $me = -1; $pl = array();
	foreach ( $d['pl'] as $i => $x ) { if ( $x['h'] === $h ) { $me = $i; } $pl[] = array( 'n' => $x['n'], 's' => (int) $x['s'], 'ok' => (int) $x['ok'], 'bad' => (int) $x['bad'] ); }
	$v = array( 'v' => (int) $d['v'], 'cells' => (object) $d['cells'], 'pl' => $pl, 'me' => $me, 'done' => (int) $d['done'], 't0' => (int) $d['t0'], 'now' => time(), 'wd' => (object) $d['wd'] );
	if ( $full ) { $p = suorni_rk_puzzle( $d['pz'] ); if ( $p ) { $v['pz'] = suorni_rk_layout( $p ); } }
	return $v;
}
function suorni_rk_join( &$d, $pid, $name ) {
	$h = md5( $pid );
	foreach ( $d['pl'] as $i => &$x ) { if ( $x['h'] === $h ) { if ( '' !== $name && $x['n'] !== $name ) { $x['n'] = $name; $d['v']++; return true; } return false; } }
	unset( $x );
	if ( count( $d['pl'] ) >= 8 ) { return false; }
	$d['pl'][] = array( 'h' => $h, 'n' => '' !== $name ? $name : 'Pelaaja ' . ( count( $d['pl'] ) + 1 ), 's' => 0, 'ok' => 0, 'bad' => 0 );
	$d['v']++;
	return true;
}
function suorni_rk_new( $req ) {
	$pid = suorni_kisa_pid( $req->get_param( 'pid' ) );
	if ( strlen( $pid ) < 8 ) { return new WP_Error( 'suorni_bad', 'Virheellinen', array( 'status' => 400 ) ); }
	$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	$k = 'suorni_rknew_' . md5( $ip ); $c = (int) get_transient( $k );
	if ( $c >= 20 ) { return new WP_Error( 'suorni_limit', 'Liian monta huonetta, yritä myöhemmin', array( 'status' => 429 ) ); }
	set_transient( $k, $c + 1, HOUR_IN_SECONDS );
	$all = suorni_rk_puzzles();
	if ( ! $all ) { return new WP_Error( 'suorni_none', 'Ei ristikoita', array( 'status' => 500 ) ); }
	$want = (int) $req->get_param( 'pz' ); $p = $want ? suorni_rk_puzzle( $want ) : null;
	if ( ! $p ) { $p = $all[ array_rand( $all ) ]; }
	/* vanhojen huoneiden siivous */
	$idx = get_option( 'suorni_rk_idx', array() ); if ( ! is_array( $idx ) ) { $idx = array(); }
	foreach ( $idx as $cd => $t ) { if ( time() - (int) $t > 2 * DAY_IN_SECONDS + 3600 ) { delete_option( suorni_rk_opt( $cd ) ); unset( $idx[ $cd ] ); } }
	$al = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; $code = '';
	for ( $try = 0; $try < 10; $try++ ) {
		$code = ''; for ( $i = 0; $i < 5; $i++ ) { $code .= $al[ wp_rand( 0, strlen( $al ) - 1 ) ]; }
		if ( ! isset( $idx[ $code ] ) ) { break; }
	}
	$d = array( 'pz' => (int) $p['id'], 't0' => time(), 'v' => 1, 'cells' => array(), 'pl' => array(), 'done' => 0, 'wd' => array() );
	suorni_rk_join( $d, $pid, suorni_peli_name( $req->get_param( 'name' ) ) );
	suorni_rk_save( $code, $d );
	$idx[ $code ] = time(); update_option( 'suorni_rk_idx', $idx, false );
	return suorni_json( array( 'room' => $code ) );
}
function suorni_rk_state( $req ) {
	if ( ! suorni_rk_ip_ok() ) { return new WP_Error( 'suorni_limit', 'Liikaa kutsuja', array( 'status' => 429 ) ); }
	$code = suorni_rk_code( $req->get_param( 'room' ) ); $pid = suorni_kisa_pid( $req->get_param( 'pid' ) );
	if ( strlen( $code ) < 5 || strlen( $pid ) < 8 ) { return new WP_Error( 'suorni_bad', 'Virheellinen', array( 'status' => 400 ) ); }
	$d = suorni_rk_load( $code );
	if ( ! $d ) { return new WP_Error( 'suorni_gone', 'Huonetta ei löydy tai se on vanhentunut', array( 'status' => 404 ) ); }
	$name = suorni_peli_name( $req->get_param( 'name' ) ); $h = md5( $pid ); $known = false;
	foreach ( $d['pl'] as $x ) { if ( $x['h'] === $h && ( '' === $name || $x['n'] === $name ) ) { $known = true; } }
	if ( ! $known ) {
		if ( ! suorni_rk_lock( $code ) ) { return new WP_Error( 'suorni_busy', 'Kokeile uudelleen', array( 'status' => 503 ) ); }
		$d = suorni_rk_load( $code );
		if ( $d && suorni_rk_join( $d, $pid, $name ) ) { suorni_rk_save( $code, $d ); }
		suorni_rk_unlock( $code );
		if ( ! $d ) { return new WP_Error( 'suorni_gone', 'Huonetta ei löydy', array( 'status' => 404 ) ); }
	}
	$v = (int) $req->get_param( 'v' );
	if ( $v > 0 && $v === (int) $d['v'] ) { return suorni_json( array( 'same' => 1, 'v' => $v, 'now' => time() ) ); }
	return suorni_json( suorni_rk_view( $d, $pid, 0 === $v ) );
}
function suorni_rk_put( $req ) {
	if ( ! suorni_rk_ip_ok() ) { return new WP_Error( 'suorni_limit', 'Liikaa kutsuja', array( 'status' => 429 ) ); }
	$code = suorni_rk_code( $req->get_param( 'room' ) ); $pid = suorni_kisa_pid( $req->get_param( 'pid' ) );
	$i = (int) $req->get_param( 'i' ); $ch = mb_strtoupper( mb_substr( (string) $req->get_param( 'ch' ), 0, 1, 'UTF-8' ), 'UTF-8' );
	if ( strlen( $code ) < 5 || strlen( $pid ) < 8 || '' === $ch || ! preg_match( '/^\p{L}$/u', $ch ) ) { return new WP_Error( 'suorni_bad', 'Virheellinen', array( 'status' => 400 ) ); }
	$k = 'suorni_rkp_' . md5( $pid ); $c = (int) get_transient( $k );
	if ( $c >= 40 ) { return new WP_Error( 'suorni_limit', 'Hidasta vähän', array( 'status' => 429 ) ); }
	set_transient( $k, $c + 1, 10 );
	if ( ! suorni_rk_lock( $code ) ) { return new WP_Error( 'suorni_busy', 'Kokeile uudelleen', array( 'status' => 503 ) ); }
	$d = suorni_rk_load( $code );
	if ( ! $d ) { suorni_rk_unlock( $code ); return new WP_Error( 'suorni_gone', 'Huonetta ei löydy', array( 'status' => 404 ) ); }
	$p = suorni_rk_puzzle( $d['pz'] ); $sol = suorni_rk_solution( $p ); $h = md5( $pid ); $me = -1;
	foreach ( $d['pl'] as $n => $x ) { if ( $x['h'] === $h ) { $me = $n; } }
	$right = null;
	if ( $me >= 0 && ! $d['done'] && isset( $sol[ $i ] ) && ! isset( $d['cells'][ $i ] ) ) {
		if ( $sol[ $i ] === $ch ) {
			$right = true;
			$d['cells'][ $i ] = array( $ch, $me );
			$d['pl'][ $me ]['s'] += 10; $d['pl'][ $me ]['ok']++;
			foreach ( $p['ents'] as $k2 => $e ) {
				if ( isset( $d['wd'][ $k2 ] ) ) { continue; }
				$all = true; $has = false;
				foreach ( suorni_rk_cells_of( $e, $p['c'] ) as $ci ) { if ( ! isset( $d['cells'][ $ci ] ) ) { $all = false; break; } if ( $ci === $i ) { $has = true; } }
				if ( $all && $has ) { $d['wd'][ $k2 ] = $me; $d['pl'][ $me ]['s'] += 30; }
			}
			if ( count( $d['cells'] ) >= count( $sol ) ) { $d['done'] = time(); }
		} else {
			$right = false;
			$d['pl'][ $me ]['s'] = max( 0, $d['pl'][ $me ]['s'] - 5 ); $d['pl'][ $me ]['bad']++;
		}
		$d['v']++;
		suorni_rk_save( $code, $d );
	}
	suorni_rk_unlock( $code );
	return suorni_json( array_merge( suorni_rk_view( $d, $pid, false ), array( 'right' => $right ) ) );
}
add_action( 'rest_api_init', function () {
	foreach ( array( 'new' => 'suorni_rk_new', 'state' => 'suorni_rk_state', 'put' => 'suorni_rk_put' ) as $r => $cb ) {
		register_rest_route( 'suorni/v1', '/ristikko/' . $r, array( 'methods' => 'POST', 'callback' => $cb, 'permission_callback' => '__return_true' ) );
	}
} );

/* Sivu: ?ristikko=1 (aloitus) tai ?ristikko=KOODI (huone) */
add_action( 'template_redirect', function () {
	if ( ! isset( $_GET['ristikko'] ) ) { return; }
	$room = suorni_rk_code( wp_unslash( $_GET['ristikko'] ) );
	if ( strlen( $room ) < 5 ) { $room = ''; }
	$u = plugin_dir_url( __FILE__ ); $ver = rawurlencode( SUORNI_VER );
	$cfg = wp_json_encode( array( 'api' => rest_url( 'suorni/v1/ristikko/' ), 'room' => $room, 'home' => home_url( '/' ), 'n' => count( suorni_rk_puzzles() ) ) );
	nocache_headers();
	header( 'Content-Type: text/html; charset=utf-8' );
	echo '<!doctype html><html lang="fi"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><meta name="robots" content="noindex"><meta name="theme-color" content="#0b1020"><title>Kuvaristikko – Suorni</title>'
		. '<link rel="stylesheet" href="' . esc_url( $u . 'ristikko.css?ver=' . $ver ) . '"></head><body><div id="app"></div>'
		. '<script>window.RK=' . $cfg . ';</script><script src="' . esc_url( $u . 'ristikko.js?ver=' . $ver ) . '"></script></body></html>';
	exit;
}, 1 );

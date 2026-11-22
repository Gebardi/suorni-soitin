<?php
/* Kuvaristikko (pop-ristikko): yhteistyö + kilpailu. Sivu: https://suorni.fi/?ristikko=1  (huone: ?ristikko=KOODI)
   Vastaukset ovat vain palvelimella (ristikko-puzzles.php). Ruutuun tulee kirjain vain, jos se on oikein.
   Pisteet: oikea kirjain +10, sanan viimeistelijälle +30, väärä kirjain -5 (ei alle nollan).
   Rajapinnat (julkisia, pelaajan ilmoittama tunniste ei ole todennettu):
   POST ristikko/new   {pid,name,pz?}          luo huoneen, palauttaa {room}
   POST ristikko/state {room,pid,name,v}       huoneen tila (v = viimeksi nähty versio; sama versio -> {same:1}); liittää pelaajan
   POST ristikko/start {room,pid}              käynnistää lähtölaskennan (go = nyt + 6 s); arvaukset hyväksytään vasta go:n jälkeen
   POST ristikko/hint  {room,pid,e,kind}       vihje: kuvan lisäksi teksti (w, -10 p) tai yksi kirjain (l, -25 p)
   POST ristikko/next  {room,pid}              valmiista huoneesta seuraavaan (sama huone kaikille)
   POST ristikko/lb    {pid}                   pistepörssi (viikko / kaikki ajat; nimi näkyy vasta hyväksynnän jälkeen)
   POST ristikko/put   {room,pid,i,ch}         arvaus ruutuun i (rivi*sarakkeet+sarake); palauttaa tilan + {right:bool}
   Suojaukset: huonekohtainen lukko (GET_LOCK), 40 arvausta / 10 s / pelaaja, IP-raja 1500 kutsua / 10 min, 20 huonetta / tunti / IP,
   enintään 8 pelaajaa huoneessa, huoneet poistuvat 2 vrk jälkeen. */
if ( ! defined( 'ABSPATH' ) ) { exit; }

function suorni_rk_puzzles() {
	$p = include dirname( __FILE__ ) . '/ristikko-puzzles.php'; if ( ! is_array( $p ) ) { $p = array(); }
	$c = get_option( 'suorni_rk_custom', array() );
	if ( is_array( $c ) ) { foreach ( $c as $x ) { if ( is_array( $x ) && ! empty( $x['ents'] ) ) { $p[] = $x; } } }
	return $p;
}
function suorni_rk_latest_custom() { $c = get_option( 'suorni_rk_custom', array() ); return ( is_array( $c ) && $c ) ? end( $c ) : null; }
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
	if ( ! suorni_rk_puzzle( $d['pz'] ) ) { return null; }
	if ( ! isset( $d['go'] ) ) { $d['go'] = (int) $d['t0']; }
	if ( ! isset( $d['next'] ) ) { $d['next'] = ''; }
	if ( ! isset( $d['hh'] ) ) { $d['hh'] = array(); }
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
	$u = function_exists( 'suorni_rkg_dir' ) ? suorni_rkg_dir() : array( 'url' => '' );
	foreach ( $p['ents'] as $k => $e ) { $ents[] = array( 'id' => $k, 'e' => $e['e'], 'img' => ! empty( $e['f'] ) ? $u['url'] . $e['f'] : '', 'r' => $e['r'], 'c' => $e['c'], 'd' => $e['d'], 'len' => mb_strlen( $e['w'], 'UTF-8' ) ); }
	return array( 'id' => (int) $p['id'], 'title' => isset( $p['title'] ) ? $p['title'] : '', 'r' => (int) $p['r'], 'c' => (int) $p['c'], 'ents' => $ents );
}
/* paljastetut vihjetekstit: sanan indeksi => teksti */
function suorni_rk_hh( $d ) {
	$o = array(); if ( empty( $d['hh'] ) ) { return $o; }
	$p = suorni_rk_puzzle( $d['pz'] ); if ( ! $p ) { return $o; }
	foreach ( $d['hh'] as $k => $v ) { if ( isset( $p['ents'][ $k ] ) ) { $o[ $k ] = (string) $p['ents'][ $k ]['h']; } }
	return $o;
}
function suorni_rk_view( $d, $pid, $full ) {
	$h = md5( $pid ); $me = -1; $pl = array();
	foreach ( $d['pl'] as $i => $x ) { if ( $x['h'] === $h ) { $me = $i; } $pl[] = array( 'n' => $x['n'], 's' => (int) $x['s'], 'ok' => (int) $x['ok'], 'bad' => (int) $x['bad'], 'hn' => isset( $x['hn'] ) ? (int) $x['hn'] : 0 ); }
	$v = array( 'v' => (int) $d['v'], 'cells' => (object) $d['cells'], 'pl' => $pl, 'me' => $me, 'done' => (int) $d['done'], 't0' => (int) $d['t0'], 'go' => (int) $d['go'], 'next' => (string) $d['next'], 'now' => time(), 'wd' => (object) $d['wd'], 'hh' => (object) suorni_rk_hh( $d ) );
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
function suorni_rk_make( $pid, $name, $want, $avoid = 0 ) {
	$all = suorni_rk_puzzles();
	if ( ! $all ) { return new WP_Error( 'suorni_none', 'Ei ristikoita', array( 'status' => 500 ) ); }
	$p = $want ? suorni_rk_puzzle( $want ) : null;
	if ( ! $p ) {
		$pool = array(); foreach ( $all as $x ) { if ( (int) $x['id'] !== (int) $avoid ) { $pool[] = $x; } }
		if ( ! $pool ) { $pool = $all; }
		$p = $pool[ array_rand( $pool ) ];
	}
	/* vanhojen huoneiden siivous */
	$idx = get_option( 'suorni_rk_idx', array() ); if ( ! is_array( $idx ) ) { $idx = array(); }
	foreach ( $idx as $cd => $t ) { if ( time() - (int) $t > 2 * DAY_IN_SECONDS + 3600 ) { delete_option( suorni_rk_opt( $cd ) ); unset( $idx[ $cd ] ); } }
	$al = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; $code = '';
	for ( $try = 0; $try < 10; $try++ ) {
		$code = ''; for ( $i = 0; $i < 5; $i++ ) { $code .= $al[ wp_rand( 0, strlen( $al ) - 1 ) ]; }
		if ( ! isset( $idx[ $code ] ) ) { break; }
	}
	$d = array( 'pz' => (int) $p['id'], 't0' => time(), 'go' => 0, 'next' => '', 'lb' => 0, 'hh' => array(), 'v' => 1, 'cells' => array(), 'pl' => array(), 'done' => 0, 'wd' => array() );
	suorni_rk_join( $d, $pid, $name );
	suorni_rk_save( $code, $d );
	$idx[ $code ] = time(); update_option( 'suorni_rk_idx', $idx, false );
	return $code;
}
function suorni_rk_newlimit() {
	$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	$k = 'suorni_rknew_' . md5( $ip ); $c = (int) get_transient( $k );
	if ( $c >= 20 ) { return false; }
	set_transient( $k, $c + 1, HOUR_IN_SECONDS );
	return true;
}
function suorni_rk_new( $req ) {
	$pid = suorni_kisa_pid( $req->get_param( 'pid' ) );
	if ( strlen( $pid ) < 8 ) { return new WP_Error( 'suorni_bad', 'Virheellinen', array( 'status' => 400 ) ); }
	if ( ! suorni_rk_newlimit() ) { return new WP_Error( 'suorni_limit', 'Liian monta huonetta, yritä myöhemmin', array( 'status' => 429 ) ); }
	$code = suorni_rk_make( $pid, suorni_peli_name( $req->get_param( 'name' ) ), (int) $req->get_param( 'pz' ) );
	if ( is_wp_error( $code ) ) { return $code; }
	return suorni_json( array( 'room' => $code ) );
}
/* Vihje: kuvan lisäksi sanan vihjeteksti (-10 p) tai yksi kirjain (-25 p). Pisteet voivat mennä miinukselle huoneessa; pörssiin lasketaan vain positiiviset. */
function suorni_rk_hint( $req ) {
	if ( ! suorni_rk_ip_ok() ) { return new WP_Error( 'suorni_limit', 'Liikaa kutsuja', array( 'status' => 429 ) ); }
	$code = suorni_rk_code( $req->get_param( 'room' ) ); $pid = suorni_kisa_pid( $req->get_param( 'pid' ) );
	$en = (int) $req->get_param( 'e' ); $kind = 'l' === $req->get_param( 'kind' ) ? 'l' : 'w';
	if ( strlen( $code ) < 5 || strlen( $pid ) < 8 ) { return new WP_Error( 'suorni_bad', 'Virheellinen', array( 'status' => 400 ) ); }
	$k = 'suorni_rkh_' . md5( $pid ); $c = (int) get_transient( $k );
	if ( $c >= 20 ) { return new WP_Error( 'suorni_limit', 'Hidasta vähän', array( 'status' => 429 ) ); }
	set_transient( $k, $c + 1, 10 );
	if ( ! suorni_rk_lock( $code ) ) { return new WP_Error( 'suorni_busy', 'Kokeile uudelleen', array( 'status' => 503 ) ); }
	$d = suorni_rk_load( $code );
	if ( ! $d ) { suorni_rk_unlock( $code ); return new WP_Error( 'suorni_gone', 'Huonetta ei löydy', array( 'status' => 404 ) ); }
	$p = suorni_rk_puzzle( $d['pz'] ); $h = md5( $pid ); $me = -1;
	foreach ( $d['pl'] as $n => $x ) { if ( $x['h'] === $h ) { $me = $n; } }
	if ( $me >= 0 && $p && isset( $p['ents'][ $en ] ) && ! $d['done'] && $d['go'] && time() >= (int) $d['go'] ) {
		$e = $p['ents'][ $en ]; $sol = suorni_rk_solution( $p ); $changed = false;
		if ( 'w' === $kind ) {
			if ( empty( $d['hh'][ $en ] ) ) { $d['hh'][ $en ] = 1; $changed = true; $cost = 10; }
		} else {
			foreach ( suorni_rk_cells_of( $e, $p['c'] ) as $ci ) {
				if ( ! isset( $d['cells'][ $ci ] ) ) { $d['cells'][ $ci ] = array( $sol[ $ci ], -1 ); $changed = true; $cost = 25; break; }
			}
		}
		if ( $changed ) {
			$d['pl'][ $me ]['s'] -= $cost; $d['pl'][ $me ]['hn'] = ( isset( $d['pl'][ $me ]['hn'] ) ? (int) $d['pl'][ $me ]['hn'] : 0 ) + 1;
			foreach ( $p['ents'] as $k2 => $e2 ) {
				if ( isset( $d['wd'][ $k2 ] ) ) { continue; }
				$all = true; foreach ( suorni_rk_cells_of( $e2, $p['c'] ) as $ci ) { if ( ! isset( $d['cells'][ $ci ] ) ) { $all = false; break; } }
				if ( $all ) { $d['wd'][ $k2 ] = -1; }
			}
			if ( count( $d['cells'] ) >= count( $sol ) ) { $d['done'] = time(); if ( empty( $d['lb'] ) ) { $d['lb'] = 1; suorni_rk_lb_add( $d ); } }
			$d['v']++; suorni_rk_save( $code, $d );
		}
	}
	suorni_rk_unlock( $code );
	return suorni_json( suorni_rk_view( $d, $pid, false ) );
}
/* Aloitus: kuka tahansa huoneen pelaaja käynnistää lähtölaskennan kaikille (go = aika, jolloin peli alkaa) */
function suorni_rk_start( $req ) {
	if ( ! suorni_rk_ip_ok() ) { return new WP_Error( 'suorni_limit', 'Liikaa kutsuja', array( 'status' => 429 ) ); }
	$code = suorni_rk_code( $req->get_param( 'room' ) ); $pid = suorni_kisa_pid( $req->get_param( 'pid' ) );
	if ( strlen( $code ) < 5 || strlen( $pid ) < 8 ) { return new WP_Error( 'suorni_bad', 'Virheellinen', array( 'status' => 400 ) ); }
	if ( ! suorni_rk_lock( $code ) ) { return new WP_Error( 'suorni_busy', 'Kokeile uudelleen', array( 'status' => 503 ) ); }
	$d = suorni_rk_load( $code );
	if ( ! $d ) { suorni_rk_unlock( $code ); return new WP_Error( 'suorni_gone', 'Huonetta ei löydy', array( 'status' => 404 ) ); }
	$h = md5( $pid ); $in = false; foreach ( $d['pl'] as $x ) { if ( $x['h'] === $h ) { $in = true; } }
	if ( $in && ! $d['go'] ) { $d['go'] = time() + 6; $d['v']++; suorni_rk_save( $code, $d ); }
	suorni_rk_unlock( $code );
	return suorni_json( suorni_rk_view( $d, $pid, false ) );
}
/* Seuraava ristikko: ensimmäinen painaja luo huoneen, muut liittyvät samaan */
function suorni_rk_next( $req ) {
	if ( ! suorni_rk_ip_ok() ) { return new WP_Error( 'suorni_limit', 'Liikaa kutsuja', array( 'status' => 429 ) ); }
	$code = suorni_rk_code( $req->get_param( 'room' ) ); $pid = suorni_kisa_pid( $req->get_param( 'pid' ) );
	if ( strlen( $code ) < 5 || strlen( $pid ) < 8 ) { return new WP_Error( 'suorni_bad', 'Virheellinen', array( 'status' => 400 ) ); }
	if ( ! suorni_rk_lock( $code ) ) { return new WP_Error( 'suorni_busy', 'Kokeile uudelleen', array( 'status' => 503 ) ); }
	$d = suorni_rk_load( $code );
	if ( ! $d || ! $d['done'] ) { suorni_rk_unlock( $code ); return new WP_Error( 'suorni_gone', 'Ristikko ei ole vielä valmis', array( 'status' => 400 ) ); }
	$nx = (string) $d['next'];
	if ( '' === $nx || ! suorni_rk_load( $nx ) ) {
		if ( ! suorni_rk_newlimit() ) { suorni_rk_unlock( $code ); return new WP_Error( 'suorni_limit', 'Liian monta huonetta, yritä myöhemmin', array( 'status' => 429 ) ); }
		$name = ''; $h = md5( $pid ); foreach ( $d['pl'] as $x ) { if ( $x['h'] === $h ) { $name = $x['n']; } }
		$nx = suorni_rk_make( $pid, $name, 0, $d['pz'] );
		if ( is_wp_error( $nx ) ) { suorni_rk_unlock( $code ); return $nx; }
		$d['next'] = $nx; $d['v']++; suorni_rk_save( $code, $d );
	}
	suorni_rk_unlock( $code );
	return suorni_json( array( 'room' => $nx ) );
}

/* ---------- pistepörssi (viikko / kaikki ajat); pisteet kertyvät valmiista ristikoista, nimet hyväksytään ohjaamossa ---------- */
function suorni_rk_wk() { return wp_date( 'o-\WW' ); }
function suorni_rk_lb_add( $d ) {
	$l = get_option( 'suorni_rk_lb', array() ); if ( ! is_array( $l ) ) { $l = array(); }
	$wk = suorni_rk_wk();
	foreach ( $d['pl'] as $x ) {
		if ( (int) $x['s'] <= 0 ) { continue; }
		$h = $x['h'];
		if ( ! isset( $l[ $h ] ) ) { $l[ $h ] = array( 'n' => $x['n'], 'ok' => 0, 'a' => 0, 'g' => 0, 'w' => array(), 't' => 0 ); }
		if ( $l[ $h ]['n'] !== $x['n'] ) { $l[ $h ]['n'] = $x['n']; $l[ $h ]['ok'] = 0; }
		$l[ $h ]['a'] += (int) $x['s']; $l[ $h ]['g']++; $l[ $h ]['t'] = time();
		$l[ $h ]['w'][ $wk ] = ( isset( $l[ $h ]['w'][ $wk ] ) ? (int) $l[ $h ]['w'][ $wk ] : 0 ) + (int) $x['s'];
		krsort( $l[ $h ]['w'] ); $l[ $h ]['w'] = array_slice( $l[ $h ]['w'], 0, 8, true );
	}
	if ( count( $l ) > 1200 ) { uasort( $l, function ( $a, $b ) { return $b['a'] <=> $a['a']; } ); $l = array_slice( $l, 0, 1000, true ); }
	update_option( 'suorni_rk_lb', $l, false );
}
function suorni_rk_lb_view( $pid ) {
	$l = get_option( 'suorni_rk_lb', array() ); if ( ! is_array( $l ) ) { $l = array(); }
	$me = md5( $pid ); $wk = suorni_rk_wk(); $out = array();
	foreach ( array( 'w', 'a' ) as $key ) {
		$rows = array();
		foreach ( $l as $h => $x ) {
			$s = 'w' === $key ? ( isset( $x['w'][ $wk ] ) ? (int) $x['w'][ $wk ] : 0 ) : (int) $x['a'];
			if ( $s <= 0 ) { continue; }
			$mine = ( $h === $me );
			$nm = 1 === (int) $x['ok'] ? $x['n'] : ( $mine ? $x['n'] . ' (odottaa hyväksyntää)' : 'Nimetön pelaaja' );
			$rows[] = array( 'n' => $nm, 's' => $s, 'me' => $mine );
		}
		usort( $rows, function ( $a, $b ) { return $b['s'] <=> $a['s']; } );
		$out[ $key ] = array_slice( $rows, 0, 10 );
	}
	return array( 'w' => $out['w'], 'a' => $out['a'], 'wk' => $wk );
}
function suorni_rk_lb( $req ) {
	if ( ! suorni_rk_ip_ok() ) { return new WP_Error( 'suorni_limit', 'Liikaa kutsuja', array( 'status' => 429 ) ); }
	return suorni_json( suorni_rk_lb_view( suorni_kisa_pid( $req->get_param( 'pid' ) ) ) );
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
	if ( $me >= 0 && ! $d['done'] && $d['go'] && time() >= (int) $d['go'] && isset( $sol[ $i ] ) && ! isset( $d['cells'][ $i ] ) ) {
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
			if ( count( $d['cells'] ) >= count( $sol ) ) { $d['done'] = time(); if ( empty( $d['lb'] ) ) { $d['lb'] = 1; suorni_rk_lb_add( $d ); } }
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
	foreach ( array( 'new' => 'suorni_rk_new', 'state' => 'suorni_rk_state', 'put' => 'suorni_rk_put', 'start' => 'suorni_rk_start', 'hint' => 'suorni_rk_hint', 'next' => 'suorni_rk_next', 'lb' => 'suorni_rk_lb' ) as $r => $cb ) {
		register_rest_route( 'suorni/v1', '/ristikko/' . $r, array( 'methods' => 'POST', 'callback' => $cb, 'permission_callback' => '__return_true' ) );
	}
} );

/* Sivu: ?ristikko=1 (aloitus) tai ?ristikko=KOODI (huone) */
add_action( 'template_redirect', function () {
	if ( ! isset( $_GET['ristikko'] ) ) { return; }
	$room = suorni_rk_code( wp_unslash( $_GET['ristikko'] ) );
	if ( strlen( $room ) < 5 ) { $room = ''; }
	$u = plugin_dir_url( __FILE__ ); $ver = rawurlencode( SUORNI_VER );
	$feat = null; $want = isset( $_GET['pz'] ) ? (int) $_GET['pz'] : 0;
	if ( $want ) { $fp = suorni_rk_puzzle( $want ); if ( $fp ) { $feat = array( 'id' => (int) $fp['id'], 'title' => isset( $fp['title'] ) ? (string) $fp['title'] : '' ); } }
	if ( ! $feat ) { $lc = suorni_rk_latest_custom(); if ( $lc ) { $feat = array( 'id' => (int) $lc['id'], 'title' => (string) $lc['title'] ); } }
	$cfg = wp_json_encode( array( 'api' => rest_url( 'suorni/v1/ristikko/' ), 'room' => $room, 'home' => home_url( '/' ), 'n' => count( suorni_rk_puzzles() ), 'feat' => $feat ) );
	nocache_headers();
	header( 'Content-Type: text/html; charset=utf-8' );
	echo '<!doctype html><html lang="fi"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><meta name="robots" content="noindex"><meta name="theme-color" content="#0b1020"><title>Kuvaristikko – Suorni</title>'
		. '<link rel="stylesheet" href="' . esc_url( $u . 'ristikko.css?ver=' . $ver ) . '"></head><body><div id="app"></div>'
		. '<script>window.RK=' . $cfg . ';</script><script src="' . esc_url( $u . 'ristikko.js?ver=' . $ver ) . '"></script></body></html>';
	exit;
}, 1 );

require_once dirname( __FILE__ ) . '/radio-ristikko-ai.php';

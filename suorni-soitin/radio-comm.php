<?php
/**
 * Suorni Radio: yhteisön kappaleet (uudet artistit lähettävät, kuuntelijat peukuttavat).
 * Ladataan radio.php:sta.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

function suorni_comm_on() {
	$c = suorni_radio_cfg();
	return $c['on'] && $c['comm'];
}
function suorni_subs() {
	$s = get_option( 'suorni_subs', array() );
	return is_array( $s ) ? $s : array();
}
function suorni_subs_save( $s ) {
	update_option( 'suorni_subs', array_values( $s ), false );
}
function suorni_comm_dir() {
	$up = wp_upload_dir();
	return array( trailingslashit( $up['basedir'] ) . 'suorni-subs/', trailingslashit( $up['baseurl'] ) . 'suorni-subs/' );
}
function suorni_comm_score( $s ) {
	return ( $s['up'] + 1 ) / ( $s['up'] + $s['down'] + 2 );
}
/* Hyväksytyt yhteisön kappaleet samassa muodossa kuin tavalliset kappaleet */
function suorni_comm_tracks() {
	$out = array();
	foreach ( suorni_subs() as $s ) {
		if ( 'ok' !== $s['status'] || $s['dur'] < 1 ) { continue; }
		$out[] = array(
			'id'     => 'c' . $s['id'],
			'sub'    => $s['id'],
			'url'    => $s['url'],
			'dur'    => (int) $s['dur'],
			'title'  => $s['title'],
			'artist' => $s['artist'],
			'info'   => 'Esittäjä ' . $s['artist'] . '. Uusi kappale, jonka on lähettänyt kuuntelija. Siitä saa keskustella chatissa ja antaa sille peukun.',
			'ad'     => 0,
			'ann'    => 0,
			'_at'    => (int) $s['apprat'],
			'_pl'    => (int) $s['plays'],
			'_sc'    => suorni_comm_score( $s ),
		);
	}
	return $out;
}
/* Järjestys: uudet (ei vielä soitetut) ensin, sitten pisteiden mukaan */
function suorni_comm_ranked( $exclude = array(), $played = array() ) {
	$fresh = array();
	$rest  = array();
	foreach ( suorni_comm_tracks() as $t ) {
		if ( in_array( $t['id'], $exclude, true ) ) { continue; }
		if ( 0 === $t['_pl'] && ! isset( $played[ $t['sub'] ] ) ) { $fresh[] = $t; } else { $rest[] = $t; }
	}
	usort( $fresh, function ( $a, $b ) { return $a['_at'] <=> $b['_at']; } );
	usort( $rest, function ( $a, $b ) { return $b['_sc'] <=> $a['_sc']; } );
	return array( $fresh, $rest );
}
/* Valitsee seuraavan yhteisön kappaleen tai null (jolloin soitetaan oma musiikki) */
function suorni_comm_pick( &$st ) {
	$cfg = suorni_radio_cfg();
	if ( ! $cfg['comm'] ) { return null; }
	$played = ( isset( $st['cplayed'] ) && is_array( $st['cplayed'] ) ) ? $st['cplayed'] : array();
	$recent = array_slice( $st['recent'], -6 );
	list( $fresh, $rest ) = suorni_comm_ranked( $recent, $played );
	if ( $fresh ) { return $fresh[0]; }
	if ( ! $rest || wp_rand( 1, 100 ) > $cfg['comm_share'] ) { return null; }
	$sum = 0;
	foreach ( $rest as $t ) { $sum += $t['_sc']; }
	$r = wp_rand( 0, 1000 ) / 1000 * $sum;
	foreach ( $rest as $t ) {
		$r -= $t['_sc'];
		if ( $r <= 0 ) { return $t; }
	}
	return $rest[0];
}
function suorni_comm_played( $sub ) {
	$s = suorni_subs();
	foreach ( $s as $i => $x ) {
		if ( (int) $x['id'] === (int) $sub ) { $s[ $i ]['plays'] = (int) $x['plays'] + 1; suorni_subs_save( $s ); return; }
	}
}
/* Tiedot soittimelle: jono (max 5) ja äänimäärät */
function suorni_comm_public( $items ) {
	$cfg = suorni_radio_cfg();
	if ( ! $cfg['comm'] ) { return array( 'on' => false ); }
	$log = suorni_radio_log();
	$pl  = ( isset( $log['st']['cplayed'] ) && is_array( $log['st']['cplayed'] ) ) ? $log['st']['cplayed'] : array();
	list( $fresh, $rest ) = suorni_comm_ranked( array(), $pl );
	$q = array();
	foreach ( array_slice( array_merge( $fresh, $rest ), 0, (int) $cfg['comm_queue'] ) as $t ) { $q[] = (int) $t['sub']; }
	$ids = $q;
	foreach ( $items as $it ) { if ( ! empty( $it['sub'] ) ) { $ids[] = (int) $it['sub']; } }
	$out = array( 'on' => true, 'q' => array(), 'v' => new stdClass() );
	$v   = array();
	foreach ( suorni_subs() as $s ) {
		if ( ! in_array( (int) $s['id'], $ids, true ) || ! in_array( $s['status'], array( 'ok', 'dropped' ), true ) ) { continue; }
		$v[ (string) $s['id'] ] = array( 'u' => (int) $s['up'], 'd' => (int) $s['down'] );
		if ( in_array( (int) $s['id'], $q, true ) ) { $out['q'][ array_search( (int) $s['id'], $q, true ) ] = array( 'id' => (int) $s['id'], 't' => $s['title'], 'a' => $s['artist'], 'u' => (int) $s['up'], 'd' => (int) $s['down'] ); }
	}
	ksort( $out['q'] );
	$out['q'] = array_values( $out['q'] );
	if ( $v ) { $out['v'] = $v; }
	return $out;
}
function suorni_comm_vhash() {
	$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	$ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
	return substr( md5( $ip . '|' . $ua . '|' . wp_salt() . 'vote' ), 0, 10 );
}
/* Omien kappaleiden peukut (ei pudota kappaleita, vain tilasto) */
function suorni_tvote_get( $id ) {
	$m = get_option( 'suorni_tvotes', array() );
	return ( is_array( $m ) && isset( $m[ (int) $id ] ) ) ? array( 'u' => (int) $m[ (int) $id ]['u'], 'd' => (int) $m[ (int) $id ]['d'] ) : array( 'u' => 0, 'd' => 0 );
}
function suorni_tvotes_public( $items ) {
	$out = array();
	foreach ( $items as $it ) { if ( ! empty( $it['tid'] ) ) { $out[ (string) $it['tid'] ] = suorni_tvote_get( $it['tid'] ); } }
	return $out ? $out : new stdClass();
}
function suorni_tvote( $req, $id, $v ) {
	if ( ! suorni_radio_on() ) { return new WP_Error( 'suorni_off', 'Radio ei ole käytössä.', array( 'status' => 403 ) ); }
	if ( ! $v ) { return new WP_Error( 'suorni_bad', 'Virheellinen ääni.', array( 'status' => 400 ) ); }
	if ( ! $id || 'attachment' !== get_post_type( $id ) ) { return new WP_Error( 'suorni_nf', 'Kappaletta ei löydy.', array( 'status' => 404 ) ); }
	$h = suorni_comm_vhash();
	if ( get_transient( 'suorni_vrate_' . $h ) > 60 ) { return new WP_Error( 'suorni_rate', 'Liian monta ääntä. Odota hetki.', array( 'status' => 429 ) ); }
	set_transient( 'suorni_vrate_' . $h, (int) get_transient( 'suorni_vrate_' . $h ) + 1, 10 * MINUTE_IN_SECONDS );
	$m = get_option( 'suorni_tvotes', array() );
	if ( ! is_array( $m ) ) { $m = array(); }
	$x  = ( isset( $m[ $id ] ) && is_array( $m[ $id ] ) ) ? $m[ $id ] : array( 'u' => 0, 'd' => 0, 'v' => array() );
	$vv = ( isset( $x['v'] ) && is_array( $x['v'] ) ) ? $x['v'] : array();
	$old = isset( $vv[ $h ] ) ? (int) $vv[ $h ] : 0;
	if ( isset( $vv[ $h ] ) && (int) $vv[ $h ] === $v ) { unset( $vv[ $h ] ); } else { $vv[ $h ] = $v; }
	if ( function_exists( 'suorni_wk_add' ) ) { suorni_wk_add( 't' . $id, ( isset( $vv[ $h ] ) ? (int) $vv[ $h ] : 0 ) - $old ); }
	$up = 0; $dn = 0;
	foreach ( $vv as $z ) { if ( $z > 0 ) { $up++; } else { $dn++; } }
	$m[ $id ] = array( 'u' => $up, 'd' => $dn, 'v' => $vv );
	update_option( 'suorni_tvotes', $m, false );
	return suorni_json( array( 'ok' => true, 'u' => $up, 'd' => $dn, 'mine' => isset( $vv[ $h ] ) ? (int) $vv[ $h ] : 0 ) );
}
function suorni_comm_vote( $req ) {
	$kk = (string) $req->get_param( 'k' );
	if ( 't' === $kk ) {
		$v = (int) $req->get_param( 'v' );
		return suorni_tvote( $req, absint( $req->get_param( 'id' ) ), $v > 0 ? 1 : ( $v < 0 ? -1 : 0 ) );
	}
	if ( ! suorni_comm_on() ) { return new WP_Error( 'suorni_off', 'Yhteisön kappaleet eivät ole käytössä.', array( 'status' => 403 ) ); }
	$id = absint( $req->get_param( 'id' ) );
	$v  = (int) $req->get_param( 'v' );
	$v  = $v > 0 ? 1 : ( $v < 0 ? -1 : 0 );
	if ( ! $id || ! $v ) { return new WP_Error( 'suorni_bad', 'Virheellinen ääni.', array( 'status' => 400 ) ); }
	$h = suorni_comm_vhash();
	if ( get_transient( 'suorni_vrate_' . $h ) > 60 ) { return new WP_Error( 'suorni_rate', 'Liian monta ääntä. Odota hetki.', array( 'status' => 429 ) ); }
	set_transient( 'suorni_vrate_' . $h, (int) get_transient( 'suorni_vrate_' . $h ) + 1, 10 * MINUTE_IN_SECONDS );
	$cfg = suorni_radio_cfg();
	$s   = suorni_subs();
	foreach ( $s as $i => $x ) {
		if ( (int) $x['id'] !== $id ) { continue; }
		if ( ! in_array( $x['status'], array( 'ok', 'dropped' ), true ) ) { break; }
		$vv = ( isset( $x['v'] ) && is_array( $x['v'] ) ) ? $x['v'] : array();
		$old = isset( $vv[ $h ] ) ? (int) $vv[ $h ] : 0;
		if ( isset( $vv[ $h ] ) && (int) $vv[ $h ] === $v ) { unset( $vv[ $h ] ); } else { $vv[ $h ] = $v; }
		if ( function_exists( 'suorni_wk_add' ) ) { suorni_wk_add( 's' . $id, ( isset( $vv[ $h ] ) ? (int) $vv[ $h ] : 0 ) - $old ); }
		$up = 0; $dn = 0;
		foreach ( $vv as $z ) { if ( $z > 0 ) { $up++; } else { $dn++; } }
		$s[ $i ]['v'] = $vv; $s[ $i ]['up'] = $up; $s[ $i ]['down'] = $dn;
		$n = $up + $dn;
		if ( 'ok' === $x['status'] && $n >= $cfg['comm_min_votes'] && ( $up / $n * 100 ) < $cfg['comm_min_pct'] ) { $s[ $i ]['status'] = 'dropped'; }
		suorni_subs_save( $s );
		return suorni_json( array( 'ok' => true, 'u' => $up, 'd' => $dn, 'mine' => isset( $vv[ $h ] ) ? (int) $vv[ $h ] : 0, 'dropped' => ( 'dropped' === $s[ $i ]['status'] ) ) );
	}
	return new WP_Error( 'suorni_nf', 'Kappaletta ei löydy.', array( 'status' => 404 ) );
}
function suorni_comm_clean( $t, $max ) {
	$t = sanitize_text_field( (string) $t );
	$t = preg_replace( '/[\x00-\x1F\x7F<>«»#]/u', '', $t );
	return mb_substr( trim( $t ), 0, $max );
}
function suorni_comm_submit( $req ) {
	if ( ! suorni_comm_on() ) { return new WP_Error( 'suorni_off', 'Kappaleiden lähetys ei ole käytössä.', array( 'status' => 403 ) ); }
	if ( '' !== trim( (string) $req->get_param( 'hp' ) ) ) { return suorni_json( array( 'ok' => true ) ); }
	$cfg    = suorni_radio_cfg();
	$artist = suorni_comm_clean( $req->get_param( 'artist' ), 40 );
	$title  = suorni_comm_clean( $req->get_param( 'title' ), 60 );
	$email  = sanitize_email( (string) $req->get_param( 'email' ) );
	if ( '' === $artist || '' === $title ) { return new WP_Error( 'suorni_bad', 'Kirjoita artistin nimi ja kappaleen nimi.', array( 'status' => 400 ) ); }
	if ( ! is_email( $email ) ) { return new WP_Error( 'suorni_bad', 'Kirjoita toimiva sähköpostiosoite.', array( 'status' => 400 ) ); }
	if ( ! $req->get_param( 'rights' ) ) { return new WP_Error( 'suorni_bad', 'Vahvista oikeudet kappaleeseen.', array( 'status' => 400 ) ); }
	$h = suorni_client_hash();
	$k = 'suorni_sub_' . $h;
	if ( (int) get_transient( $k ) >= 3 ) { return new WP_Error( 'suorni_rate', 'Voit lähettää enintään 3 kappaletta päivässä.', array( 'status' => 429 ) ); }
	$subs = suorni_subs();
	$pend = 0;
	foreach ( $subs as $x ) { if ( 'pending' === $x['status'] ) { $pend++; } }
	if ( $pend >= 40 ) { return new WP_Error( 'suorni_full', 'Hyväksyntäjono on tällä hetkellä täynnä. Yritä myöhemmin uudelleen.', array( 'status' => 429 ) ); }
	$files = $req->get_file_params();
	$f     = isset( $files['file'] ) ? $files['file'] : null;
	if ( ! $f || ! isset( $f['error'] ) || UPLOAD_ERR_OK !== (int) $f['error'] ) {
		$e = $f && isset( $f['error'] ) ? (int) $f['error'] : 4;
		return new WP_Error( 'suorni_file', ( UPLOAD_ERR_INI_SIZE === $e || UPLOAD_ERR_FORM_SIZE === $e ) ? 'Tiedosto on liian iso palvelimelle.' : 'Valitse mp3-tiedosto.', array( 'status' => 400 ) );
	}
	if ( $f['size'] > $cfg['comm_max_mb'] * 1048576 ) { return new WP_Error( 'suorni_file', 'Tiedosto on liian iso (enintään ' . $cfg['comm_max_mb'] . ' Mt).', array( 'status' => 400 ) ); }
	if ( ! preg_match( '/\.mp3$/i', (string) $f['name'] ) || ! is_uploaded_file( $f['tmp_name'] ) ) { return new WP_Error( 'suorni_file', 'Lähetä tiedosto mp3-muodossa.', array( 'status' => 400 ) ); }
	$fh  = fopen( $f['tmp_name'], 'rb' );
	$mag = $fh ? fread( $fh, 3 ) : '';
	if ( $fh ) { fclose( $fh ); }
	$okmagic = ( 'ID3' === $mag ) || ( strlen( $mag ) >= 2 && 0xFF === ord( $mag[0] ) && 0xE0 === ( ord( $mag[1] ) & 0xE0 ) );
	if ( ! $okmagic ) { return new WP_Error( 'suorni_file', 'Tiedosto ei ole kelvollinen mp3.', array( 'status' => 400 ) ); }
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';
	$meta = wp_read_audio_metadata( $f['tmp_name'] );
	$dur  = ( is_array( $meta ) && ! empty( $meta['length'] ) ) ? (int) round( $meta['length'] ) : 0;
	if ( $dur < 20 || $dur > 600 ) { return new WP_Error( 'suorni_file', 'Kappaleen pitää olla 20 sekunnin ja 10 minuutin väliltä.', array( 'status' => 400 ) ); }
	list( $dir, $base ) = suorni_comm_dir();
	if ( ! wp_mkdir_p( $dir ) ) { return new WP_Error( 'suorni_file', 'Tallennus ei onnistunut.', array( 'status' => 500 ) ); }
	$name = strtolower( wp_generate_password( 24, false, false ) ) . '.mp3';
	if ( ! move_uploaded_file( $f['tmp_name'], $dir . $name ) ) { return new WP_Error( 'suorni_file', 'Tallennus ei onnistunut.', array( 'status' => 500 ) ); }
	$next = 1;
	foreach ( $subs as $x ) { $next = max( $next, (int) $x['id'] + 1 ); }
	$subs[] = array( 'id' => $next, 'artist' => $artist, 'title' => $title, 'email' => $email, 'file' => $name, 'url' => $base . $name, 'dur' => $dur, 'at' => time(), 'status' => 'pending', 'up' => 0, 'down' => 0, 'v' => array(), 'plays' => 0, 'apprat' => 0 );
	suorni_subs_save( $subs );
	set_transient( $k, (int) get_transient( $k ) + 1, DAY_IN_SECONDS );
	return suorni_json( array( 'ok' => true ) );
}
/* ---------- Ohjaamo ---------- */
function suorni_comm_admin_list() {
	$out = array();
	foreach ( array_reverse( suorni_subs() ) as $s ) {
		$out[] = array( 'id' => (int) $s['id'], 'artist' => $s['artist'], 'title' => $s['title'], 'email' => $s['email'], 'url' => $s['url'], 'dur' => (int) $s['dur'], 'at' => (int) $s['at'], 'status' => $s['status'], 'up' => (int) $s['up'], 'down' => (int) $s['down'], 'plays' => (int) $s['plays'] );
	}
	return $out;
}
function suorni_comm_list_get() {
	return suorni_json( array( 'subs' => suorni_comm_admin_list() ) );
}
function suorni_comm_act( $req ) {
	$id  = absint( $req->get_param( 'id' ) );
	$act = sanitize_key( (string) $req->get_param( 'act' ) );
	$s   = suorni_subs();
	list( $dir ) = suorni_comm_dir();
	foreach ( $s as $i => $x ) {
		if ( (int) $x['id'] !== $id ) { continue; }
		if ( 'ok' === $act || 'restore' === $act ) {
			$s[ $i ]['status'] = 'ok';
			$s[ $i ]['apprat'] = time();
			if ( 'restore' === $act ) { $s[ $i ]['v'] = array(); $s[ $i ]['up'] = 0; $s[ $i ]['down'] = 0; }
		} elseif ( 'no' === $act || 'del' === $act ) {
			if ( ! empty( $x['file'] ) && preg_match( '/^[a-z0-9]+\.mp3$/', $x['file'] ) && file_exists( $dir . $x['file'] ) ) { @unlink( $dir . $x['file'] ); }
			unset( $s[ $i ] );
		}
		break;
	}
	suorni_subs_save( $s );
	return suorni_json( array( 'subs' => suorni_comm_admin_list() ) );
}
add_action( 'rest_api_init', function () {
	register_rest_route( 'suorni/v1', '/radio/submit', array( 'methods' => 'POST', 'callback' => 'suorni_comm_submit', 'permission_callback' => '__return_true' ) );
	register_rest_route( 'suorni/v1', '/radio/vote', array( 'methods' => 'POST', 'callback' => 'suorni_comm_vote', 'permission_callback' => '__return_true' ) );
	register_rest_route( 'suorni/v1', '/radio/commlist', array( 'methods' => 'GET', 'callback' => 'suorni_comm_list_get', 'permission_callback' => 'suorni_can' ) );
	register_rest_route( 'suorni/v1', '/radio/commact', array( 'methods' => 'POST', 'callback' => 'suorni_comm_act', 'permission_callback' => 'suorni_can' ) );
} );
function suorni_comm_nowdesc( $it ) {
	return $it['title'] . ( '' !== $it['artist'] ? ' (esittäjä ' . $it['artist'] . ')' : '' ) . ( ! empty( $it['sub'] ) ? ', kuuntelijan lähettämä uusi kappale, josta voi antaa peukun ja keskustella' : '' );
}

<?php
/* Kanavat: kuuntelija valitsee musiikkikanavan (esim. Iskelmä, Party, Metalli). Jokaisella kanavalla on oma aikajana ja oma soittolista;
   juontajat, ohjelmat, sää, uutiset ja mainokset ovat yhteiset. Pääkanava ("Kaikki") toimii kuten ennen. */
if ( ! defined( 'ABSPATH' ) ) { exit; }

/* Nykyinen kanava tämän pyynnön ajan ('' = pääkanava) */
function suorni_ch_cur() { return isset( $GLOBALS['suorni_ch'] ) ? (string) $GLOBALS['suorni_ch'] : ''; }
function suorni_ch_set( $c ) { $GLOBALS['suorni_ch'] = (string) $c; }
function suorni_ch_sfx() { $c = suorni_ch_cur(); return '' === $c ? '' : '_' . $c; }

function suorni_ch_main() {
	$m = get_option( 'suorni_chmain', array() );
	return array(
		'name' => ( is_array( $m ) && ! empty( $m['name'] ) ) ? (string) $m['name'] : 'Kaikki',
		'icon' => ( is_array( $m ) && ! empty( $m['icon'] ) ) ? (string) $m['icon'] : '📻',
	);
}
function suorni_ch_all() {
	$c = get_option( 'suorni_channels', array() );
	if ( ! is_array( $c ) ) { return array(); }
	$out = array();
	foreach ( $c as $x ) {
		if ( is_array( $x ) && ! empty( $x['id'] ) && ! empty( $x['name'] ) ) {
			$out[] = array( 'id' => (string) $x['id'], 'name' => (string) $x['name'], 'icon' => isset( $x['icon'] ) ? (string) $x['icon'] : '', 'list' => isset( $x['list'] ) ? (string) $x['list'] : '', 'on' => ! isset( $x['on'] ) || ! empty( $x['on'] ) );
		}
	}
	return $out;
}
function suorni_ch_find( $id ) {
	foreach ( suorni_ch_all() as $c ) { if ( $c['id'] === $id ) { return $c; } }
	return null;
}
/* Kanavan kappaleet: soittolistan kappaleet, joilla on kesto eikä ole piilotettu */
function suorni_ch_tracks( $ch ) {
	$def = suorni_ch_find( $ch );
	if ( ! $def || '' === $def['list'] ) { return array(); }
	$excl = suorni_radio_excl();
	$out  = array();
	foreach ( suorni_view( suorni_tracks(), $def['list'] ) as $t ) {
		if ( $t['dur'] > 0 && ! in_array( (int) $t['id'], $excl, true ) ) { $out[] = $t; }
	}
	return $out;
}
/* Soittojoukko kanavalle ohjelman $pid mukaan (ohjelmakohtainen rajaus kelpaa, jos jää kappaleita) */
function suorni_ch_pool( $ch, $pid = '' ) {
	$all = suorni_ch_tracks( $ch );
	if ( '' === $pid ) { return $all; }
	$ok = array_values( array_filter( $all, function ( $t ) use ( $pid ) { return suorni_track_allowed( $t, $pid ); } ) );
	return $ok ? $ok : $all;
}
/* Julkinen lista: pääkanava + käytössä olevat kanavat, joilla on vähintään 3 soitettavaa kappaletta */
function suorni_ch_public() {
	$c = get_transient( 'suorni_ch_pub' );
	if ( is_array( $c ) ) { return $c; }
	$m   = suorni_ch_main();
	$out = array( array( 'id' => '', 'name' => $m['name'], 'icon' => $m['icon'] ) );
	foreach ( suorni_ch_all() as $x ) {
		if ( ! $x['on'] || count( suorni_ch_tracks( $x['id'] ) ) < 3 ) { continue; }
		$out[] = array( 'id' => $x['id'], 'name' => $x['name'], 'icon' => $x['icon'] );
	}
	set_transient( 'suorni_ch_pub', $out, 120 );
	return $out;
}
/* Pyynnön kanava-arvo -> kelvollinen kanava-id tai '' */
function suorni_ch_clean( $raw ) {
	$id = sanitize_key( (string) $raw );
	if ( '' === $id ) { return ''; }
	foreach ( suorni_ch_public() as $c ) { if ( $c['id'] === $id ) { return $id; } }
	return '';
}
/* Käy läpi pääkanava ja kaikki kanavat (asetusten tallennus, piilotetut kappaleet) */
function suorni_ch_ids() {
	$o = array( '' );
	foreach ( suorni_ch_all() as $c ) { $o[] = $c['id']; }
	return $o;
}

/* ---------- Ohjaamo ---------- */
function suorni_ch_admin() {
	$out = array();
	foreach ( suorni_ch_all() as $c ) { $c['n'] = count( suorni_ch_tracks( $c['id'] ) ); $out[] = $c; }
	return array( 'main' => suorni_ch_main(), 'list' => $out );
}
function suorni_ch_post( $req ) {
	$in = $req->get_param( 'channels' );
	if ( ! is_array( $in ) ) { return new WP_Error( 'suorni_bad', 'Virheelliset kanavat', array( 'status' => 400 ) ); }
	$lists = suorni_lists();
	$lids  = array();
	foreach ( $lists as $l ) { $lids[] = $l['id']; }
	$out  = array();
	$used = array( 'kaikki', 'main' );
	$lch  = false;
	foreach ( array_slice( $in, 0, 12 ) as $x ) {
		if ( ! is_array( $x ) ) { continue; }
		$name = mb_substr( trim( sanitize_text_field( isset( $x['name'] ) ? $x['name'] : '' ) ), 0, 24 );
		if ( '' === $name ) { continue; }
		$icon = mb_substr( trim( sanitize_text_field( isset( $x['icon'] ) ? $x['icon'] : '' ) ), 0, 8 );
		$id   = sanitize_key( isset( $x['id'] ) ? $x['id'] : '' );
		if ( '' === $id ) { $id = sanitize_title( $name ); }
		if ( '' === $id ) { $id = 'kanava'; }
		$id   = substr( $id, 0, 24 );
		$base = $id; $n = 2;
		while ( in_array( $id, $used, true ) ) { $id = $base . '-' . $n; $n++; }
		$used[] = $id;
		$lid = isset( $x['list'] ) ? sanitize_title( $x['list'] ) : '';
		if ( '' === $lid || ! in_array( $lid, $lids, true ) ) {
			/* Ei soittolistaa: luodaan kanavan nimellä uusi lista */
			$lid = sanitize_title( $name );
			if ( '' === $lid ) { $lid = 'kanava'; }
			$b2 = $lid; $k = 2;
			while ( in_array( $lid, $lids, true ) ) { $lid = $b2 . '-' . $k; $k++; }
			$lids[]  = $lid;
			$lists[] = array( 'id' => $lid, 'name' => $name, 'ids' => array() );
			$lch     = true;
		}
		$out[] = array( 'id' => $id, 'name' => $name, 'icon' => $icon, 'list' => $lid, 'on' => ! isset( $x['on'] ) || ! empty( $x['on'] ) );
	}
	if ( $lch ) { update_option( 'suorni_lists', $lists, false ); }
	update_option( 'suorni_channels', $out, false );
	$m = $req->get_param( 'main' );
	if ( is_array( $m ) ) {
		$mn = mb_substr( trim( sanitize_text_field( isset( $m['name'] ) ? $m['name'] : '' ) ), 0, 24 );
		update_option( 'suorni_chmain', array( 'name' => '' !== $mn ? $mn : 'Kaikki', 'icon' => mb_substr( trim( sanitize_text_field( isset( $m['icon'] ) ? $m['icon'] : '' ) ), 0, 8 ) ), false );
	}
	delete_transient( 'suorni_ch_pub' );
	return suorni_json( array( 'ch' => suorni_ch_admin(), 'lists' => suorni_lists() ) );
}
/* Luokittelu: ops = [ { list: soittolistan id, add: [kappale-id], remove: [kappale-id] } ] */
function suorni_ch_classify_post( $req ) {
	$ops = $req->get_param( 'ops' );
	if ( ! is_array( $ops ) ) { return new WP_Error( 'suorni_bad', 'Virheelliset muutokset', array( 'status' => 400 ) ); }
	$lists = suorni_lists();
	foreach ( array_slice( $ops, 0, 40 ) as $op ) {
		if ( ! is_array( $op ) || empty( $op['list'] ) ) { continue; }
		foreach ( $lists as $i => $l ) {
			if ( $l['id'] !== $op['list'] ) { continue; }
			$ids = array_map( 'intval', (array) $l['ids'] );
			if ( ! empty( $op['add'] ) && is_array( $op['add'] ) ) { $ids = array_merge( $ids, array_map( 'absint', $op['add'] ) ); }
			if ( ! empty( $op['remove'] ) && is_array( $op['remove'] ) ) { $ids = array_diff( $ids, array_map( 'absint', $op['remove'] ) ); }
			$lists[ $i ]['ids'] = array_values( array_unique( array_filter( $ids ) ) );
		}
	}
	update_option( 'suorni_lists', $lists, false );
	delete_transient( 'suorni_ch_pub' );
	return suorni_json( array( 'lists' => $lists, 'ch' => suorni_ch_admin() ) );
}
add_action( 'rest_api_init', function () {
	register_rest_route( 'suorni/v1', '/radio/channels', array( 'methods' => 'POST', 'callback' => 'suorni_ch_post', 'permission_callback' => 'suorni_can' ) );
	register_rest_route( 'suorni/v1', '/radio/classify', array( 'methods' => 'POST', 'callback' => 'suorni_ch_classify_post', 'permission_callback' => 'suorni_can' ) );
} );

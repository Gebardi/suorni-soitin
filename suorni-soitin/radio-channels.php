<?php
/* Kanavat: kuuntelija valitsee musiikkikanavan (esim. Iskelmä, Party, Metalli). Jokaisella kanavalla on oma aikajana ja oma soittolista;
   juontajat, ohjelmat, sää, uutiset ja mainokset ovat yhteiset. Pääkanava ("Kaikki") toimii kuten ennen. */
if ( ! defined( 'ABSPATH' ) ) { exit; }

/* Nykyinen kanava tämän pyynnön ajan ('' = pääkanava) */
function suorni_ch_cur() { return isset( $GLOBALS['suorni_ch'] ) ? (string) $GLOBALS['suorni_ch'] : ''; }
function suorni_ch_set( $c ) { $GLOBALS['suorni_ch'] = (string) $c; }
function suorni_ch_sfx() { $c = suorni_ch_cur(); return '' === $c ? '' : '_' . $c; }


/* ---------- Ulkoasu: valmiit tyylit + omat värit + taustakuva ---------- */
function suorni_ch_styles() {
	return array(
		array( 'id' => 'classic', 'name' => 'Retro-oranssi (oletus)', 'a' => '#ffb347', 'b' => '#ff8a1f', 'bg' => '#0f141d' ),
		array( 'id' => 'metal', 'name' => 'Metalli', 'a' => '#ff3b3b', 'b' => '#a3121b', 'bg' => '#140808' ),
		array( 'id' => 'party', 'name' => 'Party', 'a' => '#ff4fd8', 'b' => '#7c4dff', 'bg' => '#170a26' ),
		array( 'id' => 'iskelma', 'name' => 'Iskelmä', 'a' => '#ffd27a', 'b' => '#e0707a', 'bg' => '#1f1316' ),
		array( 'id' => 'teras', 'name' => 'Teräs (miesten ilta)', 'a' => '#6fb1ff', 'b' => '#2f6fd0', 'bg' => '#0a1320' ),
		array( 'id' => 'rauhallinen', 'name' => 'Rauhallinen', 'a' => '#7fd6c2', 'b' => '#3aa08c', 'bg' => '#0a1a1c' ),
	);
}
function suorni_ch_style_find( $id ) {
	foreach ( suorni_ch_styles() as $x ) { if ( $x['id'] === $id ) { return $x; } }
	return null;
}
/* Arvaa tyyli kanavan nimestä (uusi kanava, jolle ei ole valittu tyyliä) */
function suorni_ch_style_guess( $name ) {
	$n   = mb_strtolower( $name );
	$map = array(
		'metal' => array( 'metal', 'rock', 'raskas', 'hevi' ),
		'party' => array( 'party', 'juhl', 'bile', 'dance', 'tanss', 'disco' ),
		'iskelma' => array( 'iskelm', 'suomi', 'tango', 'schlager' ),
		'teras' => array( 'mies', 'sauna', 'garage', 'autotalli' ),
		'rauhallinen' => array( 'rauhall', 'chill', 'lounge', 'rento', 'yö', 'uni' ),
	);
	foreach ( $map as $st => $words ) {
		foreach ( $words as $w ) { if ( false !== mb_strpos( $n, $w ) ) { return $st; } }
	}
	return '';
}
/* Tallennettava ulkoasu: st (tyyli), a/b/bg (omat värit, tyhjä = tyylin väri), img (taustakuvan osoite) */
function suorni_ch_th_norm( $raw ) {
	$raw = is_array( $raw ) ? $raw : array();
	$st  = isset( $raw['st'] ) ? sanitize_key( $raw['st'] ) : '';
	if ( '' !== $st && ! suorni_ch_style_find( $st ) ) { $st = ''; }
	$out = array( 'st' => $st );
	foreach ( array( 'a', 'b', 'bg' ) as $k ) {
		$v = isset( $raw[ $k ] ) ? sanitize_hex_color( trim( (string) $raw[ $k ] ) ) : '';
		$out[ $k ] = $v ? strtolower( $v ) : '';
	}
	$out['img'] = isset( $raw['img'] ) ? esc_url_raw( trim( (string) $raw['img'] ) ) : '';
	return $out;
}
/* Kuuntelijalle näytettävä ulkoasu: {a,b,bg,img} tai null, jos oletusilme */
function suorni_ch_th_out( $th ) {
	$th = suorni_ch_th_norm( $th );
	if ( '' === $th['st'] && '' === $th['a'] && '' === $th['b'] && '' === $th['bg'] && '' === $th['img'] ) { return null; }
	if ( 'classic' === $th['st'] && '' === $th['a'] && '' === $th['b'] && '' === $th['bg'] && '' === $th['img'] ) { return null; }
	$base = suorni_ch_style_find( '' !== $th['st'] ? $th['st'] : 'classic' );
	return array(
		'a'   => '' !== $th['a'] ? $th['a'] : $base['a'],
		'b'   => '' !== $th['b'] ? $th['b'] : $base['b'],
		'bg'  => '' !== $th['bg'] ? $th['bg'] : $base['bg'],
		'img' => $th['img'],
	);
}

function suorni_ch_main() {
	$m = get_option( 'suorni_chmain', array() );
	return array(
		'name' => ( is_array( $m ) && ! empty( $m['name'] ) ) ? (string) $m['name'] : 'Kaikki',
		'icon' => ( is_array( $m ) && ! empty( $m['icon'] ) ) ? (string) $m['icon'] : '📻',
		'th'   => suorni_ch_th_norm( ( is_array( $m ) && isset( $m['th'] ) ) ? $m['th'] : array() ),
	);
}
function suorni_ch_all() {
	$c = get_option( 'suorni_channels', array() );
	if ( ! is_array( $c ) ) { return array(); }
	$out = array();
	foreach ( $c as $x ) {
		if ( is_array( $x ) && ! empty( $x['id'] ) && ! empty( $x['name'] ) ) {
			$out[] = array( 'id' => (string) $x['id'], 'name' => (string) $x['name'], 'icon' => isset( $x['icon'] ) ? (string) $x['icon'] : '', 'list' => isset( $x['list'] ) ? (string) $x['list'] : '', 'on' => ! isset( $x['on'] ) || ! empty( $x['on'] ), 'th' => suorni_ch_th_norm( isset( $x['th'] ) ? $x['th'] : array() ) );
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
	$mt  = suorni_ch_th_out( $m['th'] );
	$out = array( array_merge( array( 'id' => '', 'name' => $m['name'], 'icon' => $m['icon'] ), $mt ? array( 'th' => $mt ) : array() ) );
	foreach ( suorni_ch_all() as $x ) {
		if ( ! $x['on'] || count( suorni_ch_tracks( $x['id'] ) ) < 3 ) { continue; }
		$xt    = suorni_ch_th_out( $x['th'] );
		$out[] = array_merge( array( 'id' => $x['id'], 'name' => $x['name'], 'icon' => $x['icon'] ), $xt ? array( 'th' => $xt ) : array() );
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
	return array( 'main' => suorni_ch_main(), 'list' => $out, 'styles' => suorni_ch_styles() );
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
		$is_new = '' === $id;
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
		$th = suorni_ch_th_norm( isset( $x['th'] ) ? $x['th'] : array() );
		if ( $is_new && '' === $th['st'] && '' === $th['a'] && '' === $th['b'] && '' === $th['bg'] && '' === $th['img'] ) { $th['st'] = suorni_ch_style_guess( $name ); }
		$out[] = array( 'id' => $id, 'name' => $name, 'icon' => $icon, 'list' => $lid, 'on' => ! isset( $x['on'] ) || ! empty( $x['on'] ), 'th' => $th );
	}
	if ( $lch ) { update_option( 'suorni_lists', $lists, false ); }
	update_option( 'suorni_channels', $out, false );
	$m = $req->get_param( 'main' );
	if ( is_array( $m ) ) {
		$mn = mb_substr( trim( sanitize_text_field( isset( $m['name'] ) ? $m['name'] : '' ) ), 0, 24 );
		update_option( 'suorni_chmain', array( 'name' => '' !== $mn ? $mn : 'Kaikki', 'icon' => mb_substr( trim( sanitize_text_field( isset( $m['icon'] ) ? $m['icon'] : '' ) ), 0, 8 ), 'th' => suorni_ch_th_norm( isset( $m['th'] ) ? $m['th'] : array() ) ), false );
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

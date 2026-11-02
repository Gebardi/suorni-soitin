<?php
/**
 * Suorni Radio: Radio-tagit. Sanoitusapuri antaa jokaisesta kappaleesta yhden rivin:
 *   Kappaleen nimi | kanavat: Iskelmä, Miesten ilta | ohjelmat: Ilta, Yö | tyyli: lämmin iskelmä, haitari | kuvaus: lyhyt tarina
 * Rivit liitetään ohjaamoon kerran. Kun samanniminen kappale ilmestyy mediakirjastoon (tai on jo siellä),
 * kappale lisätään heti oikeille kanaville ja ohjelmiin ja sille tallennetaan tyyli ja kuvaus.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

/* Vertailuavain: pienet kirjaimet, ääkköset pois, sulkeet ja versiomerkinnät pois, vain a-z0-9 */
function suorni_tags_n( $s ) {
	$s = mb_strtolower( (string) $s );
	$s = strtr( $s, array( 'ä' => 'a', 'ö' => 'o', 'å' => 'a', 'é' => 'e', 'ü' => 'u' ) );
	$s = preg_replace( '/\(.*?\)|\[.*?\]/u', ' ', $s );
	$s = preg_replace( '/\b(?:v|ver|version|versio)\s*\d+\b/u', ' ', $s );
	return preg_replace( '/[^a-z0-9]+/', '', $s );
}
function suorni_tags_all() {
	$e = get_option( 'suorni_tags', array() );
	return is_array( $e ) ? $e : array();
}
function suorni_tags_split( $v ) {
	$out = array();
	foreach ( preg_split( '/[,;&]+|\s+ja\s+/u', (string) $v ) as $x ) {
		$x = trim( $x, " \t\"'“”" );
		if ( '' !== $x ) { $out[] = mb_substr( $x, 0, 40 ); }
	}
	return array_slice( $out, 0, 8 );
}
/* Jäsentää liitetyn tekstin riveiksi. Palauttaa taulukon avain => merkintä. */
function suorni_tags_parse( $text ) {
	$out = array();
	foreach ( preg_split( '/\r\n|\r|\n/', (string) $text ) as $line ) {
		$line = trim( $line );
		if ( '' === $line || '#' === $line[0] || false === strpos( $line, '|' ) ) { continue; }
		$line  = preg_replace( '/^\s*(?:[-*•]|\d+[.)])\s*/u', '', $line );
		$line  = preg_replace( '/^(?:radio[- ]?tagi|radio)\s*:\s*/iu', '', $line );
		$parts = explode( '|', $line );
		$title = trim( sanitize_text_field( (string) array_shift( $parts ) ), " \t\"'“”«»*_" );
		$key   = suorni_tags_n( $title );
		if ( '' === $title || '' === $key ) { continue; }
		$e = array( 't' => mb_substr( $title, 0, 80 ), 'ch' => array(), 'pr' => array(), 'st' => '', 'info' => '', 'at' => time(), 'n' => 0 );
		foreach ( $parts as $p ) {
			if ( ! preg_match( '/^\s*([^:=]{2,14})\s*[:=]\s*(.*)$/su', $p, $m ) ) { continue; }
			$k = suorni_tags_n( $m[1] );
			$v = trim( sanitize_text_field( $m[2] ) );
			if ( in_array( $k, array( 'kanavat', 'kanava', 'kanavalle', 'channels', 'channel' ), true ) ) { $e['ch'] = suorni_tags_split( $v ); }
			elseif ( in_array( $k, array( 'ohjelmat', 'ohjelma', 'vuoro', 'vuorot', 'programs', 'program' ), true ) ) { $e['pr'] = suorni_tags_split( $v ); }
			elseif ( in_array( $k, array( 'tyyli', 'style', 'genre', 'tunnelma' ), true ) ) { $e['st'] = mb_substr( '' === $e['st'] ? $v : $e['st'] . ', ' . $v, 0, 400 ); }
			elseif ( in_array( $k, array( 'kuvaus', 'info', 'teksti', 'tarina' ), true ) ) { $e['info'] = mb_substr( $v, 0, 600 ); }
		}
		if ( $e['ch'] || $e['pr'] || '' !== $e['st'] || '' !== $e['info'] ) { $out[ $key ] = $e; }
	}
	return $out;
}
function suorni_tags_name_match( $a, $b, $min ) {
	$a = suorni_tags_n( $a );
	$b = suorni_tags_n( $b );
	if ( '' === $a || '' === $b ) { return false; }
	if ( $a === $b ) { return true; }
	return ( strlen( $a ) >= $min && false !== strpos( $b, $a ) ) || ( strlen( $b ) >= $min && false !== strpos( $a, $b ) );
}
/* Merkinnän nimet -> nykyisen asetuksen kanavat ja ohjelmat */
function suorni_tags_resolve( $e ) {
	$r = array( 'ch' => array(), 'pr_ids' => array(), 'pr_names' => array(), 'pr_all' => false, 'bad' => array(), 'bad_ch' => array() );
	foreach ( (array) $e['ch'] as $n ) {
		if ( in_array( suorni_tags_n( $n ), array( 'kaikki', 'paakanava', 'main' ), true ) ) { continue; }
		$hit = false;
		foreach ( suorni_ch_all() as $c ) {
			if ( suorni_tags_name_match( $n, $c['name'], 4 ) || suorni_tags_n( $n ) === suorni_tags_n( $c['id'] ) ) { $r['ch'][ $c['id'] ] = $c; $hit = true; }
		}
		if ( ! $hit ) { $r['bad'][] = 'kanava ' . $n; $r['bad_ch'][] = $n; }
	}
	foreach ( (array) $e['pr'] as $n ) {
		if ( in_array( suorni_tags_n( $n ), array( 'kaikki', 'kaikkiohjelmat', 'all' ), true ) ) { $r['pr_all'] = true; continue; }
		$hit = false;
		foreach ( suorni_radio_cfg()['programs'] as $p ) {
			if ( suorni_tags_name_match( $n, $p['name'], 2 ) || suorni_tags_n( $n ) === suorni_tags_n( $p['id'] ) ) {
				if ( ! in_array( $p['id'], $r['pr_ids'], true ) ) { $r['pr_ids'][] = $p['id']; $r['pr_names'][] = $p['name']; }
				$hit = true;
			}
		}
		if ( ! $hit ) { $r['bad'][] = 'ohjelma ' . $n; }
	}
	return $r;
}
/* Käyttää merkinnän yhteen kappaleeseen. $lists on viittaus soittolistoihin (tallennetaan kerralla). */
function suorni_tags_apply_track( $e, $t, &$lists, $reset ) {
	$r  = suorni_tags_resolve( $e );
	$id = (int) $t['id'];
	$chl = array();
	foreach ( suorni_ch_all() as $c ) { $chl[] = $c['list']; }
	foreach ( $lists as $i => $l ) {
		$ids = array_map( 'intval', (array) $l['ids'] );
		if ( $reset && in_array( $l['id'], $chl, true ) ) { $ids = array_diff( $ids, array( $id ) ); }
		foreach ( $r['ch'] as $c ) { if ( $c['list'] === $l['id'] && ! in_array( $id, $ids, true ) ) { $ids[] = $id; } }
		$lists[ $i ]['ids'] = array_values( array_unique( $ids ) );
	}
	if ( $e['pr'] ) { suorni_track_set_progs( $id, $r['pr_all'] ? array() : $r['pr_ids'] ); }
	if ( '' !== $e['st'] ) { update_post_meta( $id, '_suorni_style', $e['st'] ); }
	if ( '' !== $e['info'] ) { update_post_meta( $id, '_suorni_info', $e['info'] ); }
	update_post_meta( $id, '_suorni_tagged', time() );
}
/* Automaattiset kanavat (9.69): kun tagien mainitsemalle, vielä olemattomalle kanavalle löytyy kirjastosta vähintään
 * SUORNI_TAGS_AUTOCH_MIN soitettavaa kappaletta, kanava ja sen soittolista luodaan ja kappaleet lisätään.
 * Julkisessa valinnassa kanava näkyy heti (vähintään 3 soitettavaa kappaletta). Käyttäjän poistamia kanavia ei luoda uudelleen. */
if ( ! defined( 'SUORNI_TAGS_AUTOCH_MIN' ) ) { define( 'SUORNI_TAGS_AUTOCH_MIN', 3 ); }
$GLOBALS['suorni_tags_newch'] = array();
function suorni_tags_autochannels( $E ) {
	$GLOBALS['suorni_tags_newch'] = array();
	$all = suorni_ch_all();
	if ( count( $all ) >= 12 ) { return; }
	$skip = get_option( 'suorni_ch_skip', array() );
	if ( ! is_array( $skip ) ) { $skip = array(); }
	$excl = suorni_radio_excl();
	$byk  = array();
	foreach ( suorni_view( suorni_tracks(), '' ) as $t ) {
		if ( $t['dur'] > 0 && ! in_array( (int) $t['id'], $excl, true ) ) { $byk[ suorni_tags_n( $t['title'] ) ][] = (int) $t['id']; }
	}
	$need = array();
	foreach ( $E as $k => $e ) {
		if ( empty( $byk[ $k ] ) || empty( $e['ch'] ) ) { continue; }
		$r = suorni_tags_resolve( $e );
		foreach ( $r['bad_ch'] as $n ) {
			$nk = suorni_tags_n( $n );
			if ( strlen( $nk ) < 3 || in_array( $nk, $skip, true ) ) { continue; }
			if ( ! isset( $need[ $nk ] ) ) { $need[ $nk ] = array( 'name' => $n, 'ids' => array() ); }
			$need[ $nk ]['ids'] = array_merge( $need[ $nk ]['ids'], $byk[ $k ] );
		}
	}
	if ( ! $need ) { return; }
	$lists = suorni_lists();
	$chs   = get_option( 'suorni_channels', array() );
	if ( ! is_array( $chs ) ) { $chs = array(); }
	$used  = array( 'kaikki', 'main' );
	foreach ( $chs as $c ) { if ( is_array( $c ) && isset( $c['id'] ) ) { $used[] = $c['id']; } }
	$lids = array();
	foreach ( $lists as $l ) { $lids[] = $l['id']; }
	$icons = array( 'metal' => '🤘', 'party' => '🎉', 'iskelma' => '🎤', 'teras' => '🍺', 'rauhallinen' => '🌙' );
	$made  = array();
	foreach ( $need as $nk => $x ) {
		$ids = array_values( array_unique( $x['ids'] ) );
		if ( count( $ids ) < SUORNI_TAGS_AUTOCH_MIN || count( $chs ) >= 12 ) { continue; }
		$name = mb_substr( mb_strtoupper( mb_substr( $x['name'], 0, 1 ) ) . mb_substr( $x['name'], 1 ), 0, 24 );
		$id   = substr( sanitize_title( $name ), 0, 24 );
		if ( '' === $id ) { continue; }
		$b = $id; $i = 2;
		while ( in_array( $id, $used, true ) ) { $id = $b . '-' . $i; $i++; }
		$used[] = $id;
		$lid = $id; $i = 2;
		while ( in_array( $lid, $lids, true ) ) { $lid = $id . '-' . $i; $i++; }
		$lids[]  = $lid;
		$lists[] = array( 'id' => $lid, 'name' => $name, 'ids' => $ids );
		$st      = suorni_ch_style_guess( $name );
		$chs[]   = array( 'id' => $id, 'name' => $name, 'icon' => isset( $icons[ $st ] ) ? $icons[ $st ] : '🎵', 'list' => $lid, 'on' => true, 'th' => suorni_ch_th_norm( array( 'st' => $st ) ) );
		$made[]  = $name;
	}
	if ( ! $made ) { return; }
	update_option( 'suorni_lists', $lists, false );
	update_option( 'suorni_channels', $chs, false );
	delete_transient( 'suorni_ch_pub' );
	suorni_clear_tracks();
	$GLOBALS['suorni_tags_newch'] = $made;
}
/* Käy läpi kappaleet ja käyttää tagit niille, joille niitä ei ole vielä käytetty. Palauttaa käytettyjen määrän. */
function suorni_tags_apply_all( $reset_keys = array() ) {
	$E = suorni_tags_all();
	if ( ! $E ) { return 0; }
	suorni_tags_autochannels( $E );
	$lists = suorni_lists();
	$n     = 0;
	foreach ( suorni_view( suorni_tracks(), '' ) as $t ) {
		$k = suorni_tags_n( $t['title'] );
		if ( '' === $k || ! isset( $E[ $k ] ) ) { continue; }
		$re = in_array( $k, $reset_keys, true );
		if ( ! $re && get_post_meta( (int) $t['id'], '_suorni_tagged', true ) ) { continue; }
		suorni_tags_apply_track( $E[ $k ], $t, $lists, $re );
		$E[ $k ]['n'] = (int) $E[ $k ]['n'] + 1;
		$n++;
	}
	if ( $n ) {
		update_option( 'suorni_lists', $lists, false );
		update_option( 'suorni_tags', $E, false );
		delete_transient( 'suorni_ch_pub' );
		suorni_clear_tracks();
	}
	return $n;
}
function suorni_tags_state() {
	suorni_tags_apply_all();
	$out = array();
	foreach ( array_reverse( suorni_tags_all(), true ) as $k => $e ) {
		$r     = suorni_tags_resolve( $e );
		$out[] = array(
			'key' => $k, 't' => $e['t'], 'ch' => array_values( array_map( function ( $c ) { return $c['name']; }, $r['ch'] ) ),
			'pr' => $r['pr_all'] ? array( 'kaikki' ) : $r['pr_names'], 'st' => $e['st'], 'info' => $e['info'], 'n' => (int) $e['n'], 'bad' => $r['bad'],
		);
	}
	$chn = array();
	foreach ( suorni_ch_all() as $c ) { $chn[] = $c['name']; }
	$prn = array();
	foreach ( suorni_radio_cfg()['programs'] as $p ) { $prn[] = $p['name']; }
	return array( 'list' => $out, 'chn' => $chn, 'prn' => $prn );
}
function suorni_tags_post( $req ) {
	$text = mb_substr( (string) $req->get_param( 'text' ), 0, 30000 );
	$new  = suorni_tags_parse( $text );
	if ( ! $new ) { return new WP_Error( 'suorni_bad', 'Rivejä ei löytynyt. Rivin muoto: Kappaleen nimi | kanavat: Iskelmä | ohjelmat: Ilta | tyyli: ...', array( 'status' => 400 ) ); }
	$E     = suorni_tags_all();
	$reset = array();
	$added = 0;
	$repl  = 0;
	foreach ( $new as $k => $e ) {
		if ( isset( $E[ $k ] ) ) { $repl++; $reset[] = $k; unset( $E[ $k ] ); } else { $added++; }
		$E[ $k ] = $e;
	}
	$E = array_slice( $E, -300, null, true );
	update_option( 'suorni_tags', $E, false );
	$applied = suorni_tags_apply_all( $reset );
	return suorni_json( array( 'tags' => suorni_tags_state(), 'added' => $added, 'replaced' => $repl, 'applied' => $applied, 'newch' => $GLOBALS['suorni_tags_newch'] ) );
}
function suorni_tags_del( $req ) {
	$E = suorni_tags_all();
	$k = sanitize_key( (string) $req->get_param( 'key' ) );
	if ( isset( $E[ $k ] ) ) { unset( $E[ $k ] ); update_option( 'suorni_tags', $E, false ); }
	return suorni_json( array( 'tags' => suorni_tags_state() ) );
}
add_action( 'rest_api_init', function () {
	register_rest_route( 'suorni/v1', '/radio/tags', array( 'methods' => 'POST', 'callback' => 'suorni_tags_post', 'permission_callback' => 'suorni_can' ) );
	register_rest_route( 'suorni/v1', '/radio/tagdel', array( 'methods' => 'POST', 'callback' => 'suorni_tags_del', 'permission_callback' => 'suorni_can' ) );
} );
/* Uusi ääni mediakirjastoon: käytä tagit heti (jälkeen kuin uuden kappaleen oletusohjelma on asetettu) */
add_action( 'add_attachment', function ( $id ) {
	if ( 0 !== strpos( (string) get_post_mime_type( $id ), 'audio' ) ) { return; }
	suorni_clear_tracks();
	suorni_tags_apply_all();
}, 30 );

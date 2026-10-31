<?php
/**
 * Plugin Name: Suorni Soitin
 * Description: Soitin mediakirjaston äänitiedostoista. Lyhytkoodit: [suorni_soitin] (valinnaisesti lista="nimi") ja [suorni_kauko] (kauko-ohjain kirjautuneelle).
 * Version: 9.52
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

define( 'SUORNI_VER', '9.52' );
require_once plugin_dir_path( __FILE__ ) . 'radio.php';
require_once plugin_dir_path( __FILE__ ) . 'update.php';
require_once plugin_dir_path( __FILE__ ) . 'radio-cache.php';

/* ---------- Kappaleet ---------- */
/* Suno-nimeä ei näytetä sivuilla: poistetaan se kappaleen nimestä, esittäjästä ja esittelystä. */
function suorni_nosuno( $s ) {
	$s = (string) $s;
	if ( false === stripos( $s, 'suno' ) && false === stripos( $s, 'losgebardos' ) ) { return $s; }
	$s = preg_replace( '#\s*[|\-–—(\[]\s*(made with |created with |by )?suno(\.com|\.ai)?\s*[)\]]?\s*$#iu', '', $s );
	$s = preg_replace( '#\b(made with |created with |generated (with|by) |by )?suno\w*(\.com|\.ai)?\b[\s,:;-]*#iu', '', $s );
	$s = preg_replace( '#\b(by\s+)?losgebardos\b#iu', '', $s );
	$s = preg_replace( '#^[\s\-–—|:,;]+|[\s\-–—|:,;]+$#u', '', $s );
	return trim( preg_replace( '#\s{2,}#', ' ', $s ) );
}
/* Suno sulki julkisen mp3-latauksen (cdn1.suno.ai/<id>.mp3 antaa 403), mutta saman kappaleen mp4 on avoin: korvataan vanhat osoitteet lennossa. */
function suorni_fix_url( $url ) {
	$u = preg_replace( '#^(https://cdn1\.suno\.ai/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12})\.mp3$#i', '$1.mp4', (string) $url );
	return function_exists( 'suorni_suno_local_url' ) ? suorni_suno_local_url( $u ) : $u;
}
function suorni_build_tracks() {
	$posts  = get_posts( array(
		'post_type'      => 'attachment',
		'post_mime_type' => 'audio',
		'post_status'    => 'inherit',
		'posts_per_page' => -1,
		'orderby'        => 'date',
		'order'          => 'DESC',
	) );
	$tracks = array();
	foreach ( $posts as $p ) {
		$m = wp_get_attachment_metadata( $p->ID );
		if ( ! is_array( $m ) ) { $m = array(); }
		if ( '' !== trim( $p->post_excerpt ) ) {
			$artist = $p->post_excerpt;
		} elseif ( ! empty( $m['artist'] ) ) {
			$artist = $m['artist'];
		} else {
			$artist = 'Suorni';
		}
		$tracks[] = array(
			'id'     => (int) $p->ID,
			'title'  => ( '' !== trim( suorni_nosuno( $p->post_title ) ) ? suorni_nosuno( $p->post_title ) : 'Nimetön kappale' ),
			'artist' => suorni_nosuno( $artist ),
			'genre'  => isset( $m['genre'] ) ? $m['genre'] : '',
			'url'    => suorni_fix_url( wp_get_attachment_url( $p->ID ) ),
			'dur'    => isset( $m['length'] ) ? (int) $m['length'] : 0,
			'ad'     => in_array( (int) $p->ID, suorni_ads()['ids'], true ),
			'ann'    => in_array( (int) $p->ID, suorni_ann_tracks(), true ),
			'info'   => suorni_nosuno( (string) get_post_meta( $p->ID, '_suorni_info', true ) ),
			'at'     => (int) strtotime( $p->post_date_gmt . ' UTC' ),
			'boost'  => (string) get_post_meta( $p->ID, '_suorni_boost', true ),
			'style'  => (string) get_post_meta( $p->ID, '_suorni_style', true ),
			'sugg'   => function_exists( 'suorni_sugg_get' ) ? suorni_sugg_get( $p->ID ) : null,
			'progs'  => function_exists( 'suorni_track_progs' ) ? suorni_track_progs( $p->ID ) : array(),
		);
	}
	/* Oma järjestys: uudet kappaleet ensin, sitten tallennettu järjestys */
	$order = get_option( 'suorni_order' );
	if ( is_array( $order ) && $order ) {
		$by  = array();
		$new = array();
		foreach ( $tracks as $t ) {
			$by[ $t['id'] ] = $t;
			if ( ! in_array( $t['id'], $order, true ) ) { $new[] = $t; }
		}
		$sorted = array();
		foreach ( $order as $id ) {
			if ( isset( $by[ $id ] ) ) { $sorted[] = $by[ $id ]; }
		}
		$tracks = array_merge( $new, $sorted );
	}
	return $tracks;
}
function suorni_tracks() {
	$t = get_transient( 'suorni_tracks' );
	if ( ! is_array( $t ) ) {
		$t = suorni_build_tracks();
		set_transient( 'suorni_tracks', $t, 5 * MINUTE_IN_SECONDS );
	}
	return $t;
}
function suorni_clear_tracks() { delete_transient( 'suorni_tracks' ); }
foreach ( array( 'add_attachment', 'edit_attachment', 'delete_attachment', 'attachment_updated' ) as $suorni_h ) {
	add_action( $suorni_h, 'suorni_clear_tracks' );
}
add_filter( 'wp_update_attachment_metadata', function ( $data ) { suorni_clear_tracks(); return $data; } );

/* ---------- Mainokset ---------- */
/* Kohde: 's' = myymälä, 'r' = julkinen radio, 'b' = molemmat. Ennen eriyttämistä kaikki mainokset olivat myymälän mainoksia ('s'). */
function suorni_ads() {
	$a = get_option( 'suorni_ads', array() );
	if ( ! is_array( $a ) ) { $a = array(); }
	$min  = isset( $a['min'] ) ? max( 1, min( 20, (int) $a['min'] ) ) : 3;
	$max  = isset( $a['max'] ) ? max( $min, min( 20, (int) $a['max'] ) ) : $min;
	$rmin = isset( $a['rmin'] ) ? max( 1, min( 40, (int) $a['rmin'] ) ) : 4;
	$rmax = isset( $a['rmax'] ) ? max( $rmin, min( 40, (int) $a['rmax'] ) ) : max( $rmin, 6 );
	$tg   = array();
	if ( isset( $a['tg'] ) && is_array( $a['tg'] ) ) {
		foreach ( $a['tg'] as $id => $v ) { if ( in_array( $v, array( 's', 'r', 'b' ), true ) ) { $tg[ (int) $id ] = $v; } }
	}
	return array(
		'on'   => ! empty( $a['on'] ),
		'min'  => $min,
		'max'  => $max,
		'rmin' => $rmin,
		'rmax' => $rmax,
		'tg'   => $tg,
		'ids'  => ( isset( $a['ids'] ) && is_array( $a['ids'] ) ) ? array_values( array_map( 'intval', $a['ids'] ) ) : array(),
	);
}
function suorni_ads_public() {
	$a = suorni_ads();
	unset( $a['ids'], $a['tg'], $a['rmin'], $a['rmax'] );
	return $a;
}
/* Mainoskappaleet kohteelle: 's' (myymälä) tai 'r' (julkinen radio) */
function suorni_ads_for( $w ) {
	$a   = suorni_ads();
	$out = array();
	foreach ( suorni_tracks() as $t ) {
		if ( empty( $t['ad'] ) || ! empty( $t['ann'] ) || $t['dur'] <= 0 ) { continue; }
		$g = isset( $a['tg'][ (int) $t['id'] ] ) ? $a['tg'][ (int) $t['id'] ] : 's';
		if ( $g === $w || 'b' === $g ) { $out[] = $t; }
	}
	return $out;
}
function suorni_ads_post( $req ) {
	$old = suorni_ads();
	$min = max( 1, min( 20, absint( $req->get_param( 'min' ) ) ) );
	$max = max( $min, min( 20, absint( $req->get_param( 'max' ) ) ) );
	$ids = $req->get_param( 'ids' );
	$ids = is_array( $ids ) ? array_values( array_unique( array_map( 'absint', $ids ) ) ) : array();
	$tg  = $old['tg'];
	if ( is_array( $req->get_param( 'tg' ) ) ) {
		$tg = array();
		foreach ( $req->get_param( 'tg' ) as $id => $v ) { if ( in_array( $v, array( 's', 'r', 'b' ), true ) ) { $tg[ absint( $id ) ] = $v; } }
	}
	$rmin = null !== $req->get_param( 'rmin' ) ? max( 1, min( 40, absint( $req->get_param( 'rmin' ) ) ) ) : $old['rmin'];
	$rmax = null !== $req->get_param( 'rmax' ) ? max( $rmin, min( 40, absint( $req->get_param( 'rmax' ) ) ) ) : max( $rmin, $old['rmax'] );
	update_option( 'suorni_ads', array( 'on' => (bool) $req->get_param( 'on' ), 'min' => $min, 'max' => $max, 'rmin' => $rmin, 'rmax' => $rmax, 'tg' => $tg, 'ids' => $ids ), false );
	if ( null !== $req->get_param( 'radads' ) ) {
		$o = get_option( 'suorni_radio', array() );
		if ( ! is_array( $o ) ) { $o = array(); }
		$o['radads'] = (bool) $req->get_param( 'radads' );
		update_option( 'suorni_radio', $o, false );
	}
	suorni_clear_tracks();
	return suorni_json( array( 'ok' => true ) );
}

/* ---------- Kuulutukset (ajastetut ilmoitukset) ---------- */
function suorni_anns() {
	$a = get_option( 'suorni_ann', array() );
	if ( ! is_array( $a ) ) { return array(); }
	$out = array();
	foreach ( $a as $x ) {
		if ( ! is_array( $x ) || empty( $x['id'] ) ) { continue; }
		$out[] = array(
			'id'      => (string) $x['id'],
			'label'   => isset( $x['label'] ) ? (string) $x['label'] : '',
			'track'   => isset( $x['track'] ) ? (int) $x['track'] : 0,
			'time'    => ( isset( $x['time'] ) && preg_match( '/^([01]?\d|2[0-3]):[0-5]\d$/', $x['time'] ) ) ? $x['time'] : '12:00',
			'days'    => ( isset( $x['days'] ) && is_array( $x['days'] ) ) ? array_values( array_unique( array_map( 'intval', $x['days'] ) ) ) : array( 1, 2, 3, 4, 5, 6, 7 ),
			'on'      => ! empty( $x['on'] ),
			'targets' => ( isset( $x['targets'] ) && is_array( $x['targets'] ) ) ? array_values( array_map( 'sanitize_key', $x['targets'] ) ) : array(),
		);
	}
	return $out;
}
function suorni_ann_tracks() {
	static $cache = null;
	if ( null !== $cache ) { return $cache; }
	$cache = array();
	foreach ( suorni_anns() as $x ) { if ( $x['track'] ) { $cache[] = $x['track']; } }
	return $cache;
}
function suorni_ann_post( $req ) {
	$in = $req->get_param( 'anns' );
	if ( ! is_array( $in ) ) { return new WP_Error( 'suorni_bad', 'Virheelliset kuulutukset', array( 'status' => 400 ) ); }
	$out = array(); $used = array();
	foreach ( $in as $x ) {
		if ( ! is_array( $x ) ) { continue; }
		$id = isset( $x['id'] ) ? sanitize_key( $x['id'] ) : '';
		if ( '' === $id || isset( $used[ $id ] ) ) { $id = 'k' . strtolower( wp_generate_password( 6, false, false ) ); }
		$used[ $id ] = 1;
		$time = isset( $x['time'] ) ? sanitize_text_field( $x['time'] ) : '12:00';
		$out[] = array(
			'id'      => $id,
			'label'   => sanitize_text_field( isset( $x['label'] ) ? $x['label'] : '' ),
			'track'   => isset( $x['track'] ) ? absint( $x['track'] ) : 0,
			'time'    => preg_match( '/^([01]?\d|2[0-3]):[0-5]\d$/', $time ) ? $time : '12:00',
			'days'    => ( isset( $x['days'] ) && is_array( $x['days'] ) ) ? array_values( array_filter( array_unique( array_map( 'absint', $x['days'] ) ), function ( $d ) { return $d >= 1 && $d <= 7; } ) ) : array(),
			'on'      => ! empty( $x['on'] ),
			'targets' => ( isset( $x['targets'] ) && is_array( $x['targets'] ) ) ? array_values( array_map( 'sanitize_key', $x['targets'] ) ) : array(),
		);
	}
	update_option( 'suorni_ann', $out, false );
	suorni_clear_tracks();
	return suorni_json( array( 'anns' => suorni_anns() ) );
}
/* Kuulutukset, joiden aika on tullut (2 minuutin ikkuna). Puhelin muistaa jo soitetut avaimen perusteella. */
function suorni_ann_due( $tid ) {
	$due = array();
	$n   = (int) wp_date( 'N' );
	$m   = (int) wp_date( 'G' ) * 60 + (int) wp_date( 'i' );
	foreach ( suorni_anns() as $x ) {
		if ( ! $x['on'] || ! $x['track'] || ! in_array( $n, $x['days'], true ) ) { continue; }
		if ( $x['targets'] && ! in_array( $tid, $x['targets'], true ) ) { continue; }
		$t = suorni_hm( $x['time'] );
		if ( $m >= $t && $m < $t + 2 ) {
			$due[] = array( 'k' => wp_date( 'Ymd' ) . '-' . $x['id'] . '-' . $x['time'], 'id' => $x['track'] );
		}
	}
	return $due;
}

/* ---------- Juontaja (puhuva radiojuontaja) ---------- */
function suorni_dj() {
	$d   = get_option( 'suorni_dj', array() );
	if ( ! is_array( $d ) ) { $d = array(); }
	$min = isset( $d['min'] ) ? max( 1, min( 20, (int) $d['min'] ) ) : 3;
	$max = isset( $d['max'] ) ? max( $min, min( 20, (int) $d['max'] ) ) : max( $min, 5 );
	return array(
		'on'      => ! empty( $d['on'] ),
		'min'     => $min,
		'max'     => $max,
		'time'    => ! isset( $d['time'] ) || ! empty( $d['time'] ),
		'weather' => ! isset( $d['weather'] ) || ! empty( $d['weather'] ),
		'next'    => isset( $d['next'] ) ? max( 0, min( 3, (int) $d['next'] ) ) : 1,
		'where'   => ( isset( $d['where'] ) && '' !== $d['where'] ) ? (string) $d['where'] : 'Lohjalla',
		'lat'     => isset( $d['lat'] ) ? (float) $d['lat'] : 60.2514,
		'lon'     => isset( $d['lon'] ) ? (float) $d['lon'] : 24.0667,
		'rate'    => isset( $d['rate'] ) ? max( 0.6, min( 1.4, (float) $d['rate'] ) ) : 1.0,
		'pitch'   => isset( $d['pitch'] ) ? max( 0.6, min( 1.6, (float) $d['pitch'] ) ) : 1.15,
		'voice'   => isset( $d['voice'] ) ? (string) $d['voice'] : '',
		'tod'     => ! isset( $d['tod'] ) || ! empty( $d['tod'] ),
		'station' => ( isset( $d['station'] ) && '' !== trim( $d['station'] ) ) ? (string) $d['station'] : 'Suorni Radio',
		'phrases' => isset( $d['phrases'] ) ? (string) $d['phrases'] : '',
		'cloud'   => ! empty( $d['cloud'] ) && '' !== suorni_tts_cfg()['key'],
		'every'   => ( isset( $d['every'] ) && in_array( $d['every'], array( 'songs', 'hour', 'half' ), true ) ) ? $d['every'] : 'songs',
		'titles'  => ( isset( $d['titles'] ) && in_array( $d['titles'], array( 'auto', 'fi', 'off' ), true ) ) ? $d['titles'] : 'auto',
	);
}
function suorni_clean_phrases( $txt ) {
	$lines = preg_split( '/\r\n|\r|\n/', (string) $txt );
	$out   = array();
	foreach ( $lines as $l ) {
		$l = mb_substr( trim( sanitize_text_field( $l ) ), 0, 220 );
		if ( '' !== $l ) { $out[] = $l; }
		if ( count( $out ) >= 20 ) { break; }
	}
	return implode( "\n", $out );
}
function suorni_dj_post( $req ) {
	$min = max( 1, min( 20, absint( $req->get_param( 'min' ) ) ) );
	$max = max( $min, min( 20, absint( $req->get_param( 'max' ) ) ) );
	update_option( 'suorni_dj', array(
		'on'      => (bool) $req->get_param( 'on' ),
		'min'     => $min,
		'max'     => $max,
		'time'    => (bool) $req->get_param( 'time' ),
		'weather' => (bool) $req->get_param( 'weather' ),
		'next'    => max( 0, min( 3, absint( $req->get_param( 'next' ) ) ) ),
		'where'   => sanitize_text_field( (string) $req->get_param( 'where' ) ),
		'lat'     => max( -90, min( 90, (float) $req->get_param( 'lat' ) ) ),
		'lon'     => max( -180, min( 180, (float) $req->get_param( 'lon' ) ) ),
		'rate'    => max( 0.6, min( 1.4, (float) $req->get_param( 'rate' ) ) ),
		'pitch'   => max( 0.6, min( 1.6, (float) $req->get_param( 'pitch' ) ) ),
		'voice'   => sanitize_text_field( (string) $req->get_param( 'voice' ) ),
		'tod'     => (bool) $req->get_param( 'tod' ),
		'station' => mb_substr( sanitize_text_field( (string) $req->get_param( 'station' ) ), 0, 60 ),
		'phrases' => suorni_clean_phrases( (string) $req->get_param( 'phrases' ) ),
		'cloud'   => (bool) $req->get_param( 'cloud' ),
		'every'   => in_array( $req->get_param( 'every' ), array( 'songs', 'hour', 'half' ), true ) ? $req->get_param( 'every' ) : 'songs',
		'titles'  => in_array( $req->get_param( 'titles' ), array( 'auto', 'fi', 'off' ), true ) ? $req->get_param( 'titles' ) : 'auto',
	), false );
	return suorni_json( suorni_dj() );
}

/* ---------- Pilviääni (Azure Speech) juontajalle ---------- */
function suorni_tts_cfg() {
	$c = get_option( 'suorni_tts', array() );
	if ( ! is_array( $c ) ) { $c = array(); }
	return array(
		'key'    => isset( $c['key'] ) ? (string) $c['key'] : '',
		'region' => ( isset( $c['region'] ) && preg_match( '/^[a-z0-9]{3,30}$/', $c['region'] ) ) ? $c['region'] : 'westeurope',
		'vfi'    => ( isset( $c['vfi'] ) && preg_match( '/^[A-Za-z]{2,3}-[A-Za-z]{2,4}-[A-Za-z0-9]+$/', $c['vfi'] ) ) ? $c['vfi'] : 'fi-FI-NooraNeural',
		'ven'    => ( isset( $c['ven'] ) && preg_match( '/^[A-Za-z]{2,3}-[A-Za-z]{2,4}-[A-Za-z0-9]+$/', $c['ven'] ) ) ? $c['ven'] : 'en-US-JennyNeural',
	);
}
function suorni_tts_public() {
	$c = suorni_tts_cfg();
	return array( 'haskey' => '' !== $c['key'], 'region' => $c['region'], 'vfi' => $c['vfi'], 'ven' => $c['ven'] );
}
function suorni_tts_cfg_post( $req ) {
	$c   = suorni_tts_cfg();
	$key = trim( (string) $req->get_param( 'key' ) );
	if ( '' !== $key ) { $c['key'] = sanitize_text_field( $key ); }
	if ( $req->get_param( 'clearkey' ) ) { $c['key'] = ''; }
	foreach ( array( 'region', 'vfi', 'ven' ) as $f ) {
		$v = trim( (string) $req->get_param( $f ) );
		if ( '' !== $v ) { $c[ $f ] = sanitize_text_field( $v ); }
	}
	update_option( 'suorni_tts', $c, false );
	return suorni_json( suorni_tts_public() );
}
/* segs: [ {t: teksti, l: 'fi'|'en'} ] -> mp3-tiedoston osoite (välimuistissa) */
function suorni_tts_synth( $segs, $rate, $voices = array() ) {
	$c = suorni_tts_cfg();
	foreach ( array( 'fi' => 'vfi', 'en' => 'ven' ) as $lk => $ck ) {
		if ( ! empty( $voices[ $lk ] ) && preg_match( '/^[A-Za-z]{2,3}-[A-Za-z]{2,4}-[A-Za-z0-9]+$/', $voices[ $lk ] ) ) { $c[ $ck ] = $voices[ $lk ]; }
	}
	if ( '' === $c['key'] ) { return new WP_Error( 'suorni_tts', 'Azure-avainta ei ole tallennettu.', array( 'status' => 400 ) ); }
	if ( ! is_array( $segs ) || ! $segs ) { return new WP_Error( 'suorni_tts', 'Ei tekstiä.', array( 'status' => 400 ) ); }
	$pct   = (int) round( ( max( 0.6, min( 1.4, (float) $rate ) ) - 1 ) * 100 );
	$body  = '';
	$total = 0;
	$count = 0;
	foreach ( $segs as $g ) {
		if ( ! is_array( $g ) || empty( $g['t'] ) ) { continue; }
		$t = mb_substr( trim( wp_strip_all_tags( (string) $g['t'] ) ), 0, 1400 );
		if ( '' === $t ) { continue; }
		$total += mb_strlen( $t );
		$count++;
		if ( $count > 30 || $total > 3200 ) { break; }
		$voice = ( isset( $g['l'] ) && 'en' === $g['l'] ) ? $c['ven'] : $c['vfi'];
		if ( ! empty( $g['v'] ) && preg_match( '/^[A-Za-z]{2,3}-[A-Za-z]{2,4}-[A-Za-z0-9]+$/', $g['v'] ) ) { $voice = $g['v']; }
		$sp = isset( $g['r'] ) ? (int) round( ( max( 0.5, min( 1.5, (float) $g['r'] ) ) - 1 ) * 100 ) : $pct;
		$pi = isset( $g['p'] ) ? ' pitch=\'' . ( (int) $g['p'] >= 0 ? '+' : '' ) . max( -50, min( 50, (int) $g['p'] ) ) . "%'" : '';
		$body .= "<voice name='" . esc_attr( $voice ) . "'><prosody rate='" . ( $sp >= 0 ? '+' : '' ) . $sp . "%'" . $pi . ">" . htmlspecialchars( $t, ENT_XML1 | ENT_QUOTES, 'UTF-8' ) . "</prosody><break time='250ms'/></voice>";
	}
	if ( '' === $body ) { return new WP_Error( 'suorni_tts', 'Ei tekstiä.', array( 'status' => 400 ) ); }
	$ssml = "<speak version='1.0' xml:lang='fi-FI' xmlns='http://www.w3.org/2001/10/synthesis'>" . $body . '</speak>';
	$up   = wp_upload_dir();
	$dir  = trailingslashit( $up['basedir'] ) . 'suorni-tts';
	$hash = md5( $ssml );
	$file = $dir . '/' . $hash . '.mp3';
	$url  = trailingslashit( $up['baseurl'] ) . 'suorni-tts/' . $hash . '.mp3';
	if ( file_exists( $file ) && filesize( $file ) > 200 ) { return array( 'url' => $url, 'cached' => true, 'bytes' => filesize( $file ) ); }
	/* Suoja liialliselta kulutukselta: enintään 250 uutta puhetta tunnissa (muutettavissa: asetus suorni_tts_max) */
	$n = (int) get_transient( 'suorni_tts_n' );
	if ( $n >= (int) get_option( 'suorni_tts_max', 250 ) ) { return new WP_Error( 'suorni_tts', 'Liian monta uutta puhetta tunnissa.', array( 'status' => 429 ) ); }
	$r = wp_remote_post( 'https://' . $c['region'] . '.tts.speech.microsoft.com/cognitiveservices/v1', array(
		'timeout' => 20,
		'headers' => array(
			'Ocp-Apim-Subscription-Key' => $c['key'],
			'Content-Type'              => 'application/ssml+xml',
			'X-Microsoft-OutputFormat'  => 'audio-24khz-96kbitrate-mono-mp3',
			'User-Agent'                => 'SuorniSoitin',
		),
		'body'    => $ssml,
	) );
	if ( is_wp_error( $r ) ) { return new WP_Error( 'suorni_tts', 'Yhteys Azureen epäonnistui: ' . $r->get_error_message(), array( 'status' => 502 ) ); }
	$code = (int) wp_remote_retrieve_response_code( $r );
	$data = wp_remote_retrieve_body( $r );
	if ( 200 !== $code || strlen( $data ) < 200 ) {
		$hint = ( 401 === $code || 403 === $code ) ? ' (tarkista avain ja alue)' : '';
		return new WP_Error( 'suorni_tts', 'Azure vastasi virheellä ' . $code . $hint . '.', array( 'status' => 502 ) );
	}
	if ( ! wp_mkdir_p( $dir ) ) { return new WP_Error( 'suorni_tts', 'Tallennuskansiota ei voitu luoda.', array( 'status' => 500 ) ); }
	if ( false === file_put_contents( $file, $data ) ) { return new WP_Error( 'suorni_tts', 'Tiedoston tallennus epäonnistui.', array( 'status' => 500 ) ); }
	set_transient( 'suorni_tts_n', $n + 1, HOUR_IN_SECONDS );
	if ( function_exists( 'suorni_usage_add' ) ) { suorni_usage_add( 'tts', 1, $total ); }
	if ( 1 === wp_rand( 1, 20 ) ) {
		foreach ( (array) glob( $dir . '/*.mp3' ) as $f ) { if ( filemtime( $f ) < time() - 14 * DAY_IN_SECONDS ) { @unlink( $f ); } }
	}
	return array( 'url' => $url, 'cached' => false, 'bytes' => strlen( $data ) );
}
function suorni_tts_post( $req ) {
	$d = suorni_dj();
	$r = suorni_tts_synth( $req->get_param( 'segs' ), $d['rate'] );
	if ( is_wp_error( $r ) ) { return $r; }
	return suorni_json( $r );
}
function suorni_tts_test( $req ) {
	$r = suorni_tts_synth( array( array( 't' => 'Hei, tässä Suorni Radio. Äänitesti onnistui.', 'l' => 'fi' ), array( 't' => 'Midnight Drive', 'l' => 'en' ) ), 1 );
	if ( is_wp_error( $r ) ) { return suorni_json( array( 'ok' => false, 'message' => $r->get_error_message() ) ); }
	return suorni_json( array( 'ok' => true, 'url' => $r['url'] ) );
}

/* ---------- Soittolistat ---------- */
function suorni_lists() {
	$l = get_option( 'suorni_lists', array() );
	return is_array( $l ) ? array_values( $l ) : array();
}
function suorni_view( $tracks, $only ) {
	$tracks = array_values( array_filter( $tracks, function ( $t ) { return empty( $t['ad'] ) && empty( $t['ann'] ); } ) );
	if ( '' === $only ) { return $tracks; }
	foreach ( suorni_lists() as $l ) {
		if ( $l['id'] === $only ) {
			$ids = $l['ids'];
			return array_values( array_filter( $tracks, function ( $t ) use ( $ids ) { return in_array( $t['id'], $ids, true ); } ) );
		}
	}
	return array();
}

/* ---------- Apufunktiot ---------- */
function suorni_fmt( $s ) {
	$s = (int) $s;
	return $s > 0 ? floor( $s / 60 ) . ':' . sprintf( '%02d', $s % 60 ) : '';
}
function suorni_summary( $tracks ) {
	$n   = count( $tracks );
	$sum = 0;
	$miss = 0;
	foreach ( $tracks as $t ) {
		if ( $t['dur'] > 0 ) { $sum += $t['dur']; } else { $miss++; }
	}
	$txt = $n . ( 1 === $n ? ' kappale' : ' kappaletta' );
	if ( $n && $sum > 0 ) {
		$h    = (int) floor( $sum / 3600 );
		$m    = (int) floor( ( $sum % 3600 ) / 60 );
		$txt .= ' · ' . ( $miss ? 'vähintään ' : '' ) . ( $h ? $h . ' h ' . $m . ' min' : $m . ' min ' . ( $sum % 60 ) . ' s' ) . ( $miss ? ' (' . $miss . ' ilman kestoa)' : '' );
	}
	return $txt;
}
function suorni_json( $data ) {
	$r = new WP_REST_Response( $data );
	$r->header( 'Cache-Control', 'no-store' );
	return $r;
}
function suorni_ep( $name ) { return esc_url( rest_url( 'suorni/v1/' . $name ) ); }
function suorni_can() { return current_user_can( 'upload_files' ); }

/* ---------- Kohteet (soittimet) ja yhdistämiskoodit ---------- */
function suorni_targets() {
	$t = get_option( 'suorni_targets' );
	if ( is_array( $t ) && $t ) { return $t; }
	/* Siirto vanhasta yhden soittimen mallista */
	$tok = get_option( 'suorni_token' );
	if ( ! is_string( $tok ) || strlen( $tok ) < 10 ) { $tok = wp_generate_password( 12, false, false ); }
	$t = array( array( 'id' => 'oletus', 'name' => 'Päälaite', 'token' => $tok ) );
	update_option( 'suorni_targets', $t, false );
	update_option( 'suorni_t_oletus', array(
		'cmd'   => get_option( 'suorni_cmd', array( 'seq' => 0, 'a' => '', 'id' => 0 ) ),
		'vol'   => (int) get_option( 'suorni_vol', 100 ),
		'shuf'  => (int) get_option( 'suorni_shuf', 0 ),
		'state' => get_option( 'suorni_state', array() ),
	), false );
	return $t;
}
function suorni_target( $id ) {
	$all = suorni_targets();
	foreach ( $all as $t ) { if ( $t['id'] === $id ) { return $t; } }
	return $all[0];
}
function suorni_target_by_token( $tok ) {
	$tok = (string) $tok;
	if ( strlen( $tok ) < 10 ) { return null; }
	foreach ( suorni_targets() as $t ) { if ( hash_equals( $t['token'], $tok ) ) { return $t; } }
	return null;
}
function suorni_tdata( $id ) {
	$d = get_option( 'suorni_t_' . $id, array() );
	if ( ! is_array( $d ) ) { $d = array(); }
	return array_merge( array( 'cmd' => array( 'seq' => 0, 'a' => '', 'id' => 0 ), 'vol' => 100, 'shuf' => 0, 'list' => '', 'state' => array() ), $d );
}
function suorni_tsave( $id, $d ) { update_option( 'suorni_t_' . $id, $d, false ); }
function suorni_token() { $t = suorni_targets(); return $t[0]['token']; }
/* Puhelimen kohde: koodi parametrista k tai evästeestä */
function suorni_req_phone( $req ) {
	$t = suorni_target_by_token( $req->get_param( 'k' ) );
	if ( ! $t && isset( $_COOKIE['suorni_dev'] ) ) { $t = suorni_target_by_token( wp_unslash( $_COOKIE['suorni_dev'] ) ); }
	return $t;
}
function suorni_token_ok( $req ) { return (bool) suorni_req_phone( $req ); }
/* Omistajan valitsema kohde (parametri t) */
function suorni_req_owner_target( $req ) { return suorni_target( sanitize_key( (string) $req->get_param( 't' ) ) ); }
/* Pitkäikäinen pariutus: yhdistämislinkki asettaa palvelimelta evästeen (säilyy selaimessa vuoden) */
add_action( 'init', function () {
	if ( empty( $_GET['suorni_pair'] ) || PHP_VERSION_ID < 70300 || headers_sent() ) { return; }
	$given = sanitize_text_field( wp_unslash( $_GET['suorni_pair'] ) );
	if ( ! suorni_target_by_token( $given ) ) { return; }
	$opts = array( 'expires' => time() + YEAR_IN_SECONDS, 'path' => '/', 'secure' => is_ssl(), 'samesite' => 'Lax' );
	setcookie( 'suorni_dev', $given, array_merge( $opts, array( 'httponly' => true ) ) );
	setcookie( 'suorni_p', '1', array_merge( $opts, array( 'httponly' => false ) ) );
} );
function suorni_reset_token( $req ) {
	$id  = sanitize_key( (string) $req->get_param( 't' ) );
	$all = suorni_targets();
	foreach ( $all as $i => $t ) { if ( $t['id'] === $id ) { $all[ $i ]['token'] = wp_generate_password( 12, false, false ); } }
	update_option( 'suorni_targets', $all, false );
	return suorni_json( array( 'ok' => true ) );
}
function suorni_targets_public() {
	$out = array();
	foreach ( suorni_targets() as $t ) { $d = suorni_tdata( $t['id'] ); $out[] = array( 'id' => $t['id'], 'name' => $t['name'], 'token' => $t['token'], 'vol' => (int) $d['vol'], 'shuf' => (int) $d['shuf'], 'list' => (string) $d['list'] ); }
	return $out;
}
function suorni_targets_post( $req ) {
	$in  = $req->get_param( 'targets' );
	if ( ! is_array( $in ) || ! $in ) { return new WP_Error( 'suorni_bad', 'Virheelliset kohteet', array( 'status' => 400 ) ); }
	$old = array();
	foreach ( suorni_targets() as $t ) { $old[ $t['id'] ] = $t; }
	$out = array(); $used = array();
	foreach ( $in as $x ) {
		if ( ! is_array( $x ) ) { continue; }
		$name = sanitize_text_field( isset( $x['name'] ) ? $x['name'] : '' );
		if ( '' === $name ) { continue; }
		$id = isset( $x['id'] ) ? sanitize_key( $x['id'] ) : '';
		if ( '' === $id || isset( $used[ $id ] ) || ! isset( $old[ $id ] ) ) {
			$id = sanitize_key( sanitize_title( $name ) );
			if ( '' === $id ) { $id = 'kohde'; }
			$base = $id; $n = 2;
			while ( isset( $used[ $id ] ) || isset( $old[ $id ] ) ) { $id = $base . $n; $n++; }
			$tok = wp_generate_password( 12, false, false );
		} else { $tok = $old[ $id ]['token']; }
		$used[ $id ] = 1;
		$out[] = array( 'id' => $id, 'name' => $name, 'token' => $tok );
	}
	if ( ! $out ) { return new WP_Error( 'suorni_bad', 'Vähintään yksi kohde tarvitaan', array( 'status' => 400 ) ); }
	update_option( 'suorni_targets', $out, false );
	foreach ( $old as $id => $t ) { if ( ! isset( $used[ $id ] ) ) { delete_option( 'suorni_t_' . $id ); } }
	/* poistetut kohteet pois asiakkailta */
	$cl = suorni_clients();
	foreach ( $cl as $i => $c ) { $cl[ $i ]['targets'] = array_values( array_intersect( $c['targets'], array_keys( $used ) ) ); }
	update_option( 'suorni_clients', $cl, false );
	return suorni_json( array( 'targets' => suorni_targets_public() ) );
}

/* ---------- Asiakkaat (pääsy kaukosäätimeen ilman WordPress-tunnuksia) ---------- */
function suorni_clients() {
	$c = get_option( 'suorni_clients', array() );
	return is_array( $c ) ? $c : array();
}
function suorni_clients_post( $req ) {
	$in  = $req->get_param( 'clients' );
	if ( ! is_array( $in ) ) { return new WP_Error( 'suorni_bad', 'Virheelliset asiakkaat', array( 'status' => 400 ) ); }
	$old = array();
	foreach ( suorni_clients() as $c ) { $old[ $c['id'] ] = $c; }
	$tids = array();
	foreach ( suorni_targets() as $t ) { $tids[] = $t['id']; }
	$out = array(); $used = array();
	foreach ( $in as $x ) {
		if ( ! is_array( $x ) ) { continue; }
		$name = sanitize_text_field( isset( $x['name'] ) ? $x['name'] : '' );
		if ( '' === $name ) { continue; }
		$id = isset( $x['id'] ) ? sanitize_key( $x['id'] ) : '';
		if ( '' === $id || isset( $used[ $id ] ) || ! isset( $old[ $id ] ) ) {
			$id = 'a' . strtolower( wp_generate_password( 6, false, false ) );
			$key = wp_generate_password( 24, false, false );
		} else {
			$key = $old[ $id ]['key'];
			if ( ! empty( $x['newkey'] ) ) { $key = wp_generate_password( 24, false, false ); }
		}
		$used[ $id ] = 1;
		$tg  = ( isset( $x['targets'] ) && is_array( $x['targets'] ) ) ? array_values( array_intersect( array_map( 'sanitize_key', $x['targets'] ), $tids ) ) : array();
		$out[] = array( 'id' => $id, 'name' => $name, 'key' => $key, 'targets' => $tg );
	}
	update_option( 'suorni_clients', $out, false );
	return suorni_json( array( 'clients' => $out ) );
}
function suorni_client_from_req( $req ) {
	$k = (string) $req->get_header( 'X-Suorni-Key' );
	if ( '' === $k ) { $k = (string) $req->get_param( 'key' ); }
	if ( strlen( $k ) < 16 ) { return null; }
	foreach ( suorni_clients() as $c ) { if ( hash_equals( $c['key'], $k ) ) { return $c; } }
	return null;
}
function suorni_client_ok( $req ) { return (bool) suorni_client_from_req( $req ); }
/* Palauttaa kohteen vain jos asiakkaalla on siihen oikeus */
function suorni_client_target( $req ) {
	$c = suorni_client_from_req( $req );
	if ( ! $c || ! $c['targets'] ) { return null; }
	$id = sanitize_key( (string) $req->get_param( 't' ) );
	if ( ! in_array( $id, $c['targets'], true ) ) { $id = $c['targets'][0]; }
	return suorni_target( $id );
}
function suorni_client_info( $req ) {
	$c = suorni_client_from_req( $req );
	$tg = array();
	foreach ( suorni_targets() as $t ) { if ( in_array( $t['id'], $c['targets'], true ) ) { $d = suorni_tdata( $t['id'] ); $tg[] = array( 'id' => $t['id'], 'name' => $t['name'], 'vol' => (int) $d['vol'], 'shuf' => (int) $d['shuf'], 'list' => (string) $d['list'] ); } }
	$tracks = suorni_view( suorni_tracks(), '' );
	$list = array();
	foreach ( $tracks as $t ) { $list[] = array( 'id' => $t['id'], 'title' => $t['title'], 'artist' => $t['artist'], 'dur' => $t['dur'] ); }
	$ls = array();
	foreach ( suorni_lists() as $l ) { $ls[] = array( 'id' => $l['id'], 'name' => $l['name'], 'ids' => $l['ids'] ); }
	return suorni_json( array( 'name' => $c['name'], 'targets' => $tg, 'tracks' => $list, 'lists' => $ls ) );
}
function suorni_client_cmd( $req ) {
	$t = suorni_client_target( $req );
	if ( ! $t ) { return new WP_Error( 'suorni_no', 'Ei oikeutta', array( 'status' => 403 ) ); }
	$a = sanitize_key( (string) $req->get_param( 'a' ) );
	if ( ! in_array( $a, array( 'play', 'pause', 'next', 'prev', 'track', 'vol', 'shuf', 'list' ), true ) ) {
		return new WP_Error( 'suorni_bad', 'Tuntematon komento', array( 'status' => 400 ) );
	}
	return suorni_cmd_apply( $t['id'], $req );
}
function suorni_client_state( $req ) {
	$t = suorni_client_target( $req );
	if ( ! $t ) { return new WP_Error( 'suorni_no', 'Ei oikeutta', array( 'status' => 403 ) ); }
	return suorni_state_out( $t['id'] );
}
/* Ei hakukoneisiin */
add_filter( 'wp_robots', function ( $r ) {
	global $post;
	if ( is_singular() && $post && ( has_shortcode( $post->post_content, 'suorni_asiakas' ) || has_shortcode( $post->post_content, 'suorni_kauko' ) ) ) { $r['noindex'] = true; $r['nofollow'] = true; }
	return $r;
} );

/* ---------- REST: komennot ---------- */
/* ---------- Aikataulu (palvelimen aikavyöhyke = WordPressin asetus) ---------- */
function suorni_sched() {
	$def = array(
		'on' => false,
		'wd' => array( 'on' => true, 'from' => '09:00', 'to' => '18:00' ),
		'sa' => array( 'on' => true, 'from' => '10:00', 'to' => '16:00' ),
		'su' => array( 'on' => false, 'from' => '10:00', 'to' => '16:00' ),
	);
	$s = get_option( 'suorni_sched', array() );
	if ( ! is_array( $s ) ) { $s = array(); }
	$out = array( 'on' => ! empty( $s['on'] ) );
	foreach ( array( 'wd', 'sa', 'su' ) as $k ) {
		$d = $def[ $k ];
		$v = ( isset( $s[ $k ] ) && is_array( $s[ $k ] ) ) ? $s[ $k ] : array();
		$out[ $k ] = array(
			'on'   => isset( $v['on'] ) ? (bool) $v['on'] : $d['on'],
			'from' => ( isset( $v['from'] ) && preg_match( '/^([01]?\d|2[0-3]):[0-5]\d$/', $v['from'] ) ) ? $v['from'] : $d['from'],
			'to'   => ( isset( $v['to'] ) && preg_match( '/^([01]?\d|2[0-3]):[0-5]\d$/', $v['to'] ) ) ? $v['to'] : $d['to'],
		);
	}
	return $out;
}
function suorni_hm( $s ) {
	$p = explode( ':', $s );
	return (int) $p[0] * 60 + (int) $p[1];
}
function suorni_sched_open() {
	$s = suorni_sched();
	if ( ! $s['on'] ) { return true; }
	$n   = (int) wp_date( 'N' );
	$key = 7 === $n ? 'su' : ( 6 === $n ? 'sa' : 'wd' );
	$d   = $s[ $key ];
	if ( ! $d['on'] ) { return false; }
	$m = (int) wp_date( 'G' ) * 60 + (int) wp_date( 'i' );
	return $m >= suorni_hm( $d['from'] ) && $m < suorni_hm( $d['to'] );
}
function suorni_sched_public() {
	$s         = suorni_sched();
	$s['open'] = suorni_sched_open();
	$s['now']  = wp_date( 'H:i' );
	return $s;
}
function suorni_sched_post( $req ) {
	$in  = $req->get_param( 'days' );
	$cur = suorni_sched();
	$new = array( 'on' => (bool) $req->get_param( 'on' ) );
	foreach ( array( 'wd', 'sa', 'su' ) as $k ) {
		$v         = ( is_array( $in ) && isset( $in[ $k ] ) && is_array( $in[ $k ] ) ) ? $in[ $k ] : array();
		$new[ $k ] = array(
			'on'   => isset( $v['on'] ) ? (bool) $v['on'] : $cur[ $k ]['on'],
			'from' => isset( $v['from'] ) ? sanitize_text_field( $v['from'] ) : $cur[ $k ]['from'],
			'to'   => isset( $v['to'] ) ? sanitize_text_field( $v['to'] ) : $cur[ $k ]['to'],
		);
	}
	update_option( 'suorni_sched', $new, false );
	return suorni_json( suorni_sched_public() );
}

function suorni_cmd_out( $tid ) {
	$d      = suorni_tdata( $tid );
	$c      = $d['cmd'];
	$c['v']    = (int) $d['vol'];
	$c['sh']   = (bool) $d['shuf'];
	$c['l']    = (string) $d['list'];
	$c['open'] = suorni_sched_open();
	$c['sch']  = suorni_sched()['on'];
	$c['an']   = suorni_ann_due( $tid );
	$c['lv']   = (int) get_option( 'suorni_lv', 150 );
	$c['dj']   = suorni_dj();
	return suorni_json( $c );
}
function suorni_cmd_get( $req ) {
	$t = suorni_req_phone( $req );
	return suorni_cmd_out( $t ? $t['id'] : suorni_target( '' )['id'] );
}
function suorni_cmd_apply( $tid, $req ) {
	$a = sanitize_key( $req->get_param( 'a' ) );
	$d = suorni_tdata( $tid );
	if ( 'shuf' === $a ) {
		$d['shuf'] = $req->get_param( 'v' ) ? 1 : 0;
		suorni_tsave( $tid, $d );
		return suorni_cmd_out( $tid );
	}
	if ( 'lv' === $a ) {
		if ( ! current_user_can( 'upload_files' ) ) { return new WP_Error( 'suorni_no', 'Ei oikeutta', array( 'status' => 403 ) ); }
		update_option( 'suorni_lv', max( 100, min( 400, absint( $req->get_param( 'v' ) ) ) ), false );
		return suorni_cmd_out( $tid );
	}
	if ( 'list' === $a ) {
		$v = sanitize_title( (string) $req->get_param( 'v' ) );
		$ok = '' === $v;
		foreach ( suorni_lists() as $l ) { if ( $l['id'] === $v ) { $ok = true; } }
		if ( ! $ok ) { return new WP_Error( 'suorni_bad', 'Tuntematon teema', array( 'status' => 400 ) ); }
		$d['list'] = $v;
		suorni_tsave( $tid, $d );
		return suorni_cmd_out( $tid );
	}
	if ( 'vol' === $a ) {
		$d['vol'] = min( 100, absint( $req->get_param( 'v' ) ) );
		suorni_tsave( $tid, $d );
		return suorni_cmd_out( $tid );
	}
	if ( ! in_array( $a, array( 'play', 'pause', 'next', 'prev', 'track', 'ann', 'dj' ), true ) ) {
		return new WP_Error( 'suorni_bad', 'Tuntematon komento', array( 'status' => 400 ) );
	}
	if ( in_array( $a, array( 'ann', 'dj' ), true ) && ! current_user_can( 'upload_files' ) ) { return new WP_Error( 'suorni_no', 'Ei oikeutta', array( 'status' => 403 ) ); }
	$d['cmd'] = array( 'seq' => (int) $d['cmd']['seq'] + 1, 'a' => $a, 'id' => absint( $req->get_param( 'id' ) ) );
	suorni_tsave( $tid, $d );
	return suorni_cmd_out( $tid );
}
function suorni_cmd_post( $req ) {
	$t = suorni_req_owner_target( $req );
	return suorni_cmd_apply( $t['id'], $req );
}

/* ---------- REST: lista, järjestys, soittolistat ---------- */
function suorni_tracks_get() {
	return suorni_json( array( 'tracks' => suorni_tracks(), 'lists' => suorni_lists(), 'ads' => suorni_ads_public(), 'sched' => suorni_sched_public(), 'ver' => SUORNI_VER ) );
}
function suorni_order_post( $req ) {
	$ids = $req->get_param( 'ids' );
	if ( ! is_array( $ids ) ) {
		return new WP_Error( 'suorni_bad', 'Virheellinen järjestys', array( 'status' => 400 ) );
	}
	update_option( 'suorni_order', array_values( array_unique( array_map( 'absint', $ids ) ) ), false );
	suorni_clear_tracks();
	return suorni_json( array( 'ok' => true ) );
}
function suorni_lists_post( $req ) {
	$in = $req->get_param( 'lists' );
	if ( ! is_array( $in ) ) {
		return new WP_Error( 'suorni_bad', 'Virheelliset soittolistat', array( 'status' => 400 ) );
	}
	$out  = array();
	$used = array();
	foreach ( $in as $l ) {
		if ( ! is_array( $l ) ) { continue; }
		$name = sanitize_text_field( isset( $l['name'] ) ? $l['name'] : '' );
		if ( '' === $name ) { continue; }
		$id = sanitize_title( ( isset( $l['id'] ) && $l['id'] ) ? $l['id'] : $name );
		if ( '' === $id ) { $id = 'lista'; }
		$base = $id;
		$n    = 2;
		while ( in_array( $id, $used, true ) ) { $id = $base . '-' . $n; $n++; }
		$used[] = $id;
		$ids    = ( isset( $l['ids'] ) && is_array( $l['ids'] ) ) ? array_values( array_unique( array_map( 'absint', $l['ids'] ) ) ) : array();
		$out[]  = array( 'id' => $id, 'name' => $name, 'ids' => $ids );
	}
	update_option( 'suorni_lists', $out, false );
	return suorni_json( array( 'lists' => $out ) );
}

/* ---------- REST: soittimen tila ja kuuntelukerrat ---------- */
function suorni_state_post( $req ) {
	$t = suorni_req_phone( $req );
	if ( ! $t ) { return new WP_Error( 'suorni_no', 'Ei oikeutta', array( 'status' => 403 ) ); }
	$st = array(
		'id'      => absint( $req->get_param( 'id' ) ),
		'playing' => (bool) $req->get_param( 'playing' ),
		'pos'     => max( 0, (int) $req->get_param( 'pos' ) ),
		'dur'     => max( 0, (int) $req->get_param( 'dur' ) ),
		'ver'     => sanitize_text_field( (string) $req->get_param( 'ver' ) ),
		't'       => time(),
	);
	update_option( 'suorni_ts_' . $t['id'], $st, false );
	return suorni_json( array( 'ok' => true ) );
}
function suorni_state_out( $tid ) {
	$s = get_option( 'suorni_ts_' . $tid, array() );
	if ( ! is_array( $s ) || ! $s ) { $d = suorni_tdata( $tid ); $s = is_array( $d['state'] ) ? $d['state'] : array(); }
	$s['now'] = time();
	return suorni_json( $s );
}
function suorni_state_get( $req ) {
	$t = suorni_req_owner_target( $req );
	return suorni_state_out( $t['id'] );
}
function suorni_play_post( $req ) {
	$id = absint( $req->get_param( 'id' ) );
	if ( ! $id || 0 !== strpos( (string) get_post_mime_type( $id ), 'audio' ) ) {
		return suorni_json( array( 'ok' => false ) );
	}
	$ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	$key = 'suorni_p_' . md5( $ip . '|' . $id );
	if ( get_transient( $key ) ) { return suorni_json( array( 'ok' => true ) ); }
	set_transient( $key, 1, 60 );
	$plays = get_option( 'suorni_plays', array() );
	if ( ! is_array( $plays ) ) { $plays = array(); }
	$plays[ $id ] = ( isset( $plays[ $id ] ) ? (int) $plays[ $id ] : 0 ) + 1;
	update_option( 'suorni_plays', $plays, false );
	return suorni_json( array( 'ok' => true ) );
}
function suorni_stats_get() {
	$plays = get_option( 'suorni_plays', array() );
	if ( ! is_array( $plays ) ) { $plays = array(); }
	return suorni_json( array( 'plays' => (object) $plays ) );
}

/* ---------- Kappaleiden lisäys linkillä (Suno ja suorat äänilinkit) ---------- */
function suorni_uuid_re() { return '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}'; }
function suorni_find_uuid( $html ) {
	$re   = suorni_uuid_re();
	$pats = array(
		'#<link[^>]+rel=["\']canonical["\'][^>]*href=["\'][^"\']*/song/(' . $re . ')#i',
		'#<link[^>]+href=["\'][^"\']*/song/(' . $re . ')[^"\']*["\'][^>]*rel=["\']canonical#i',
		'#property=["\']og:url["\'][^>]*content=["\'][^"\']*/song/(' . $re . ')#i',
		'#/song/(' . $re . ')#i',
	);
	foreach ( $pats as $p ) {
		if ( preg_match( $p, $html, $m ) ) { return strtolower( $m[1] ); }
	}
	return '';
}
function suorni_parse_title( $html ) {
	$t = '';
	if ( preg_match( '#<title[^>]*>(.*?)</title>#is', $html, $m ) ) {
		$t = $m[1];
	} elseif ( preg_match( '#property=["\']og:title["\'][^>]*content=["\']([^"\']*)#i', $html, $m ) ) {
		$t = $m[1];
	}
	$t = trim( html_entity_decode( wp_strip_all_tags( $t ), ENT_QUOTES, 'UTF-8' ) );
	$t = trim( preg_replace( '#\s*\|\s*Suno\s*$#i', '', $t ) );
	if ( '' === $t || 0 === strcasecmp( $t, 'Suno' ) ) { return array( '', '' ); }
	$pos = strrpos( $t, ' by ' );
	if ( false !== $pos ) {
		return array( sanitize_text_field( substr( $t, 0, $pos ) ), sanitize_text_field( substr( $t, $pos + 4 ) ) );
	}
	return array( sanitize_text_field( $t ), '' );
}
/* Kappaleen kesto Sunon julkiselta sivulta (sekunteina, 0 jos ei löydy) */
function suorni_parse_duration( $html ) {
	if ( preg_match_all( '#\\\\?"duration\\\\?"\\s*:\\s*([0-9]+(?:\\.[0-9]+)?)#', $html, $m ) ) {
		foreach ( $m[1] as $v ) {
			$d = (int) round( (float) $v );
			if ( $d >= 5 && $d <= 3600 ) { return $d; }
		}
	}
	return 0;
}
function suorni_sunodur_post( $req ) {
	$id = absint( $req->get_param( 'id' ) );
	$g  = $id ? (string) get_post_field( 'guid', $id ) : '';
	if ( ! preg_match( '#^https://cdn1\\.suno\\.ai/(' . suorni_uuid_re() . ')\\.mp[34]$#i', $g, $m ) ) {
		return suorni_json( array( 'ok' => false ) );
	}
	if ( get_post_meta( $id, '_suorni_nodur', true ) ) { return suorni_json( array( 'ok' => false ) ); }
	$r   = wp_safe_remote_get( 'https://suno.com/song/' . strtolower( $m[1] ), array( 'timeout' => 10, 'redirection' => 5, 'user-agent' => 'Mozilla/5.0 (compatible; Suorni/1.0)' ) );
	$dur = 0;
	if ( ! is_wp_error( $r ) && 200 === (int) wp_remote_retrieve_response_code( $r ) ) {
		$dur = suorni_parse_duration( wp_remote_retrieve_body( $r ) );
	}
	if ( $dur <= 0 ) {
		update_post_meta( $id, '_suorni_nodur', 1 );
		return suorni_json( array( 'ok' => false ) );
	}
	$meta = wp_get_attachment_metadata( $id );
	if ( ! is_array( $meta ) ) { $meta = array(); }
	$meta['length'] = $dur;
	wp_update_attachment_metadata( $id, $meta );
	suorni_clear_tracks();
	return suorni_json( array( 'ok' => true, 'dur' => $dur ) );
}
/* Kappaleen poisto (linkki tai mediakirjaston äänitiedosto) */
function suorni_delete_post( $req ) {
	$id = absint( $req->get_param( 'id' ) );
	if ( ! $id || 'attachment' !== get_post_type( $id ) || 0 !== strpos( (string) get_post_mime_type( $id ), 'audio' ) ) {
		return suorni_link_error( 'Kappaletta ei löytynyt.' );
	}
	if ( ! wp_delete_attachment( $id, true ) ) {
		return suorni_link_error( 'Poisto epäonnistui.' );
	}
	$lists = suorni_lists();
	foreach ( $lists as $i => $l ) { $lists[ $i ]['ids'] = array_values( array_diff( $l['ids'], array( $id ) ) ); }
	update_option( 'suorni_lists', $lists, false );
	$ads = get_option( 'suorni_ads' );
	if ( is_array( $ads ) && isset( $ads['ids'] ) && is_array( $ads['ids'] ) ) { $ads['ids'] = array_values( array_diff( array_map( 'intval', $ads['ids'] ), array( $id ) ) ); update_option( 'suorni_ads', $ads, false ); }
	$order = get_option( 'suorni_order' );
	if ( is_array( $order ) ) { update_option( 'suorni_order', array_values( array_diff( $order, array( $id ) ) ), false ); }
	$plays = get_option( 'suorni_plays', array() );
	if ( is_array( $plays ) && isset( $plays[ $id ] ) ) { unset( $plays[ $id ] ); update_option( 'suorni_plays', $plays, false ); }
	suorni_clear_tracks();
	return suorni_json( array( 'status' => 'deleted' ) );
}
function suorni_link_error( $msg ) { return suorni_json( array( 'status' => 'error', 'message' => $msg ) ); }
function suorni_add_to_theme( $name, $track_id ) {
	$name = sanitize_text_field( $name );
	if ( '' === $name || ! $track_id ) { return; }
	$lists = suorni_lists();
	$idx   = -1;
	foreach ( $lists as $i => $l ) {
		if ( 0 === strcasecmp( $l['name'], $name ) || $l['id'] === sanitize_title( $name ) ) { $idx = $i; break; }
	}
	if ( $idx < 0 ) {
		$id = sanitize_title( $name );
		if ( '' === $id ) { $id = 'teema'; }
		$used = array();
		foreach ( $lists as $l ) { $used[] = $l['id']; }
		$base = $id; $n = 2;
		while ( in_array( $id, $used, true ) ) { $id = $base . '-' . $n; $n++; }
		$lists[] = array( 'id' => $id, 'name' => $name, 'ids' => array() );
		$idx = count( $lists ) - 1;
	}
	if ( ! in_array( $track_id, $lists[ $idx ]['ids'], true ) ) { $lists[ $idx ]['ids'][] = (int) $track_id; }
	update_option( 'suorni_lists', $lists, false );
}
function suorni_addlink_post( $req ) {
	$url    = trim( (string) $req->get_param( 'url' ) );
	$title  = sanitize_text_field( (string) $req->get_param( 'title' ) );
	$artist = sanitize_text_field( (string) $req->get_param( 'artist' ) );
	$parts  = wp_parse_url( $url );
	if ( empty( $parts['scheme'] ) || 'https' !== strtolower( $parts['scheme'] ) || empty( $parts['host'] ) ) {
		return suorni_link_error( 'Linkin pitää alkaa https://' );
	}
	$host = strtolower( $parts['host'] );
	$path = isset( $parts['path'] ) ? $parts['path'] : '';
	$re   = suorni_uuid_re();
	$mime = 'audio/mpeg';
	$src_title  = '';
	$src_artist = '';
	if ( in_array( $host, array( 'suno.com', 'www.suno.com', 'app.suno.ai', 'suno.ai', 'www.suno.ai' ), true ) ) {
		$uuid = '';
		if ( preg_match( '#/song/(' . $re . ')#i', $path, $m ) ) { $uuid = strtolower( $m[1] ); }
		if ( '' === $uuid || '' === $title ) {
			$r = wp_safe_remote_get( $url, array( 'timeout' => 10, 'redirection' => 5, 'user-agent' => 'Mozilla/5.0 (compatible; Suorni/1.0)' ) );
			if ( ! is_wp_error( $r ) && 200 === (int) wp_remote_retrieve_response_code( $r ) ) {
				$html = wp_remote_retrieve_body( $r );
				if ( '' === $uuid ) { $uuid = suorni_find_uuid( $html ); }
				list( $src_title, $src_artist ) = suorni_parse_title( $html );
			}
		}
		if ( '' === $uuid ) {
			return suorni_link_error( 'Suno-linkistä ei löytynyt kappaletta. Kokeile pitkää linkkiä (suno.com/song/…).' );
		}
		$audio     = 'https://cdn1.suno.ai/' . $uuid . '.mp4';
		$mime      = 'audio/mp4';
		$def_title = 'Nimetön kappale';
	} elseif ( preg_match( '#\.(mp3|m4a|wav|ogg|aac|flac)$#i', $path, $m ) ) {
		$maps      = array( 'mp3' => 'audio/mpeg', 'm4a' => 'audio/mp4', 'wav' => 'audio/wav', 'ogg' => 'audio/ogg', 'aac' => 'audio/aac', 'flac' => 'audio/flac' );
		$mime      = $maps[ strtolower( $m[1] ) ];
		$audio     = esc_url_raw( $url );
		$def_title = urldecode( pathinfo( $path, PATHINFO_FILENAME ) );
	} else {
		return suorni_link_error( 'Tuettuja ovat Suno-linkit ja suorat äänitiedostolinkit (mp3, m4a, wav, ogg).' );
	}
	global $wpdb;
	$alt    = preg_replace( '#\.mp4$#i', '.mp3', $audio );
	$exists = $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'attachment' AND guid IN ( %s, %s ) LIMIT 1", $audio, $alt ) );
	$teema = (string) $req->get_param( 'teema' );
	if ( $exists ) {
		suorni_add_to_theme( $teema, (int) $exists );
		return suorni_json( array( 'status' => 'exists', 'message' => get_the_title( (int) $exists ) . ( '' !== $teema ? ' (lisätty teemaan ' . sanitize_text_field( $teema ) . ')' : '' ), 'lists' => suorni_lists() ) );
	}
	$final_title  = '' !== $title ? $title : ( '' !== $src_title ? $src_title : $def_title );
	$final_artist = '' !== $artist ? $artist : ( '' !== $src_artist ? $src_artist : 'Suorni' );
	$id           = wp_insert_attachment( array(
		'post_title'     => $final_title,
		'post_excerpt'   => $final_artist,
		'post_content'   => 'Lisätty linkillä: ' . esc_url_raw( $url ),
		'post_mime_type' => $mime,
		'post_status'    => 'inherit',
		'guid'           => $audio,
	), false );
	if ( is_wp_error( $id ) || ! $id ) {
		return suorni_link_error( 'Kappaleen tallennus epäonnistui.' );
	}
	suorni_add_to_theme( $teema, (int) $id );
	suorni_clear_tracks();
	/* Heti tarkistus: ellei tiedosto avaudu, kappale ei mene radion soittolistalle */
	$warn = '';
	$pr   = wp_safe_remote_get( $audio, array( 'timeout' => 8, 'headers' => array( 'Range' => 'bytes=0-1023' ), 'redirection' => 3 ) );
	$pc   = is_wp_error( $pr ) ? 0 : (int) wp_remote_retrieve_response_code( $pr );
	if ( $pc < 200 || $pc >= 400 ) {
		$e = get_option( 'suorni_radio_excl', array() );
		$e = is_array( $e ) ? $e : array();
		$e[] = (int) $id;
		update_option( 'suorni_radio_excl', array_values( array_unique( array_map( 'intval', $e ) ) ), false );
		if ( function_exists( 'suorni_hidden_note' ) ) { suorni_hidden_note( (int) $id, 'linkki ei aukea' . ( $pc ? ' (HTTP ' . $pc . ')' : '' ) ); }
		$warn = 'Kappale lisättiin, mutta sen tiedosto ei aukea' . ( $pc ? ' (HTTP ' . $pc . ')' : '' ) . ', joten se on piilotettu radiosta. Lataa kappale mp3-tiedostona mediakirjastoon.';
	}
	return suorni_json( array(
		'lists'  => suorni_lists(),
		'status' => 'added',
		'warn'   => $warn,
		'track'  => array( 'id' => (int) $id, 'title' => $final_title, 'artist' => $final_artist, 'url' => $audio ),
	) );
}
function suorni_dur_post( $req ) {
	$id  = absint( $req->get_param( 'id' ) );
	$dur = absint( $req->get_param( 'dur' ) );
	if ( ! $id || ! $dur || 'attachment' !== get_post_type( $id ) || get_post_meta( $id, '_wp_attached_file', true ) ) {
		return suorni_json( array( 'ok' => false ) );
	}
	$m = wp_get_attachment_metadata( $id );
	if ( ! is_array( $m ) ) { $m = array(); }
	$m['length'] = $dur;
	wp_update_attachment_metadata( $id, $m );
	suorni_clear_tracks();
	return suorni_json( array( 'ok' => true ) );
}

add_action( 'rest_api_init', function () {
	$ns = 'suorni/v1';
	register_rest_route( $ns, '/cmd', array(
		array( 'methods' => 'GET', 'callback' => 'suorni_cmd_get', 'permission_callback' => 'suorni_token_ok' ),
		array( 'methods' => 'POST', 'callback' => 'suorni_cmd_post', 'permission_callback' => 'suorni_can' ),
	) );
	register_rest_route( $ns, '/state', array(
		array( 'methods' => 'GET', 'callback' => 'suorni_state_get', 'permission_callback' => 'suorni_can' ),
		array( 'methods' => 'POST', 'callback' => 'suorni_state_post', 'permission_callback' => 'suorni_token_ok' ),
	) );
	register_rest_route( $ns, '/tracks', array( 'methods' => 'GET', 'callback' => 'suorni_tracks_get', 'permission_callback' => '__return_true' ) );
	register_rest_route( $ns, '/play', array( 'methods' => 'POST', 'callback' => 'suorni_play_post', 'permission_callback' => '__return_true' ) );
	register_rest_route( $ns, '/stats', array( 'methods' => 'GET', 'callback' => 'suorni_stats_get', 'permission_callback' => 'suorni_can' ) );
	register_rest_route( $ns, '/order', array( 'methods' => 'POST', 'callback' => 'suorni_order_post', 'permission_callback' => 'suorni_can' ) );
	register_rest_route( $ns, '/lists', array( 'methods' => 'POST', 'callback' => 'suorni_lists_post', 'permission_callback' => 'suorni_can' ) );
	register_rest_route( $ns, '/addlink', array( 'methods' => 'POST', 'callback' => 'suorni_addlink_post', 'permission_callback' => 'suorni_can' ) );
	register_rest_route( $ns, '/sunodur', array( 'methods' => 'POST', 'callback' => 'suorni_sunodur_post', 'permission_callback' => 'suorni_can' ) );
	register_rest_route( $ns, '/sched', array( 'methods' => 'POST', 'callback' => 'suorni_sched_post', 'permission_callback' => 'suorni_can' ) );
	register_rest_route( $ns, '/ads', array( 'methods' => 'POST', 'callback' => 'suorni_ads_post', 'permission_callback' => 'suorni_can' ) );
	register_rest_route( $ns, '/delete', array( 'methods' => 'POST', 'callback' => 'suorni_delete_post', 'permission_callback' => 'suorni_can' ) );
	register_rest_route( $ns, '/dur', array( 'methods' => 'POST', 'callback' => 'suorni_dur_post', 'permission_callback' => 'suorni_can' ) );
	register_rest_route( $ns, '/tts', array( 'methods' => 'POST', 'callback' => 'suorni_tts_post', 'permission_callback' => 'suorni_token_ok' ) );
	register_rest_route( $ns, '/ttscfg', array( 'methods' => 'POST', 'callback' => 'suorni_tts_cfg_post', 'permission_callback' => 'suorni_can' ) );
	register_rest_route( $ns, '/ttstest', array( 'methods' => 'POST', 'callback' => 'suorni_tts_test', 'permission_callback' => 'suorni_can' ) );
	register_rest_route( $ns, '/dj', array( 'methods' => 'POST', 'callback' => 'suorni_dj_post', 'permission_callback' => 'suorni_can' ) );
	register_rest_route( $ns, '/ann', array( 'methods' => 'POST', 'callback' => 'suorni_ann_post', 'permission_callback' => 'suorni_can' ) );
	register_rest_route( $ns, '/targets', array( 'methods' => 'POST', 'callback' => 'suorni_targets_post', 'permission_callback' => 'suorni_can' ) );
	register_rest_route( $ns, '/clients', array( 'methods' => 'POST', 'callback' => 'suorni_clients_post', 'permission_callback' => 'suorni_can' ) );
	register_rest_route( $ns, '/c/info', array( 'methods' => 'GET', 'callback' => 'suorni_client_info', 'permission_callback' => 'suorni_client_ok' ) );
	register_rest_route( $ns, '/c/cmd', array( 'methods' => 'POST', 'callback' => 'suorni_client_cmd', 'permission_callback' => 'suorni_client_ok' ) );
	register_rest_route( $ns, '/c/state', array( 'methods' => 'GET', 'callback' => 'suorni_client_state', 'permission_callback' => 'suorni_client_ok' ) );
	register_rest_route( $ns, '/reset', array( 'methods' => 'POST', 'callback' => 'suorni_reset_token', 'permission_callback' => 'suorni_can' ) );
} );

/* ---------- Soitin ---------- */
add_shortcode( 'suorni_soitin', function ( $atts ) {
	wp_enqueue_script( 'suorni-player', plugins_url( 'player.js', __FILE__ ), array(), SUORNI_VER, true );
	$atts   = shortcode_atts( array( 'lista' => '' ), $atts );
	$only   = sanitize_title( $atts['lista'] );
	$tracks = suorni_tracks();
	$view   = suorni_view( $tracks, $only );
	ob_start();
	?>
<style>
.sp{background:var(--sp-bg,#0b0b12);color:var(--sp-tx,#ececf4);border-radius:16px;padding:22px;max-width:760px;margin:20px auto;font:16px/1.5 system-ui,-apple-system,"Segoe UI",sans-serif}
.sp *{box-sizing:border-box}
.sp h2{margin:0 0 4px;font-size:28px;color:var(--sp-tx,#ececf4)}
.sp .s{color:var(--sp-mut,#8b8ba3);margin:0 0 14px}
.sp .b{background:linear-gradient(135deg,var(--sp-acc,#8b7bff),var(--sp-acc2,#ff7ab8));color:#fff;border:0;border-radius:999px;padding:10px 20px;font:inherit;font-weight:600;cursor:pointer}
.sp .tb{display:flex;flex-wrap:wrap;gap:8px;align-items:center}
.sp .ic{background:var(--sp-panel,#14141f);color:var(--sp-tx,#ececf4);border:1px solid var(--sp-line,#262636);border-radius:999px;width:42px;height:42px;font-size:18px;cursor:pointer;padding:0}
.sp .ch{display:flex;flex-wrap:wrap;gap:8px;margin-top:14px}
.sp .ch[hidden]{display:none}
.sp .ch button{background:var(--sp-panel,#14141f);color:var(--sp-tx,#ececf4);border:1px solid var(--sp-line,#262636);border-radius:999px;padding:6px 14px;font:inherit;font-size:14px;cursor:pointer}
.sp .ch button.on{background:linear-gradient(135deg,var(--sp-acc,#8b7bff),var(--sp-acc2,#ff7ab8));border-color:transparent;color:#fff}
.sp .q{width:100%;margin-top:14px;padding:10px 14px;background:var(--sp-panel,#14141f);color:var(--sp-tx,#ececf4);border:1px solid var(--sp-line,#262636);border-radius:10px;font:inherit}
.sp .r{display:flex;align-items:center;gap:12px;padding:9px 6px;border-bottom:1px solid var(--sp-line,#262636);cursor:pointer;border-radius:8px}
.sp .r:hover,.sp .r.on{background:var(--sp-panel,#14141f)}
.sp .r.bad{opacity:.55}
.sp [data-warn] a{color:var(--sp-acc,#8b7bff)}
.sp .em{margin-top:12px;background:var(--sp-panel,#14141f);border-radius:12px;padding:10px}
.sp .em[hidden]{display:none}
.sp .em .eh{display:flex;justify-content:space-between;align-items:center;gap:8px;margin-bottom:8px;font-size:14px}
.sp .em .eh button{background:none;border:0;color:var(--sp-tx,#ececf4);font-size:18px;cursor:pointer}
.sp .em iframe{width:100%;height:220px;border:0;border-radius:8px}
.sp .n{width:20px;text-align:center;color:var(--sp-mut,#8b8ba3);font-size:14px}
.sp .c{width:44px;height:44px;border-radius:8px;flex:none}
.sp .m{flex:1;min-width:0}
.sp .m b,.sp .m span{display:block;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.sp .m span{color:var(--sp-mut,#8b8ba3);font-size:14px}
.sp .d{color:var(--sp-mut,#8b8ba3);font-size:14px;font-variant-numeric:tabular-nums}
.sp .l{margin-top:12px;border-top:1px solid var(--sp-line,#262636)}
.sp .bar{display:flex;align-items:center;gap:12px;margin-top:16px;background:var(--sp-panel,#14141f);border-radius:12px;padding:10px 14px}
.sp .bar button{background:none;border:0;color:var(--sp-tx,#ececf4);font-size:20px;cursor:pointer;padding:4px 8px}
.sp .bar .pp{background:linear-gradient(135deg,var(--sp-acc,#8b7bff),var(--sp-acc2,#ff7ab8));border-radius:50%;width:42px;height:42px;padding:0}
.sp .bar .pp{color:#fff}
.sp .nw{flex:1;min-width:0}
.sp .nw b{display:block;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;font-size:14px}
.sp input[type=range]{width:100%;accent-color:var(--sp-acc,#8b7bff)}
.sp .t{color:var(--sp-mut,#8b8ba3);font-size:14px}
</style>
<div class="sp" data-ver="<?php echo esc_attr( SUORNI_VER ); ?>" data-tu="<?php echo suorni_ep( 'tracks' ); ?>" data-cmd="<?php echo suorni_ep( 'cmd' ); ?>" data-state-url="<?php echo suorni_ep( 'state' ); ?>" data-tts-url="<?php echo suorni_ep( 'tts' ); ?>" data-play-url="<?php echo suorni_ep( 'play' ); ?>" data-only="<?php echo esc_attr( $only ); ?>" data-ads="<?php echo esc_attr( wp_json_encode( suorni_ads_public() ) ); ?>" data-lists="<?php echo esc_attr( wp_json_encode( suorni_lists() ) ); ?>" data-tracks="<?php echo esc_attr( wp_json_encode( $tracks ) ); ?>">
	<h2>Suorni Radio</h2>
	<p class="s">Omia kappaleita. Paina play ja anna soida.</p>
	<p class="t" data-tot style="margin:0 0 12px"><?php echo esc_html( suorni_summary( $view ) ); ?></p>
	<div class="tb">
		<button class="b" data-a="all">▶ Soita kaikki</button>
		<button class="ic" data-a="shuffle" aria-label="Satunnaistoisto" style="opacity:.45">🔀</button>
		<button class="ic" data-a="repeat" aria-label="Toisto">🔁</button>
	</div>
	<div class="ch" data-ch hidden></div>
	<div class="em" data-em hidden></div>
	<p class="t" data-warn hidden style="margin:10px 0 0"></p>
	<input class="q" type="search" data-q placeholder="Hae kappaleita…" aria-label="Hae kappaleita">
	<div class="l" data-l>
<?php foreach ( $view as $i => $t ) : ?>
		<div class="r"><div class="n"><?php echo (int) $i + 1; ?></div><div class="c"></div><div class="m"><b><?php echo esc_html( $t['title'] ); ?></b><span><?php echo esc_html( $t['artist'] ); ?></span></div><div class="d"><?php echo esc_html( suorni_fmt( $t['dur'] ) ); ?></div></div>
<?php endforeach; ?>
	</div>
	<div class="bar">
		<button data-a="prev" aria-label="Edellinen">⏮</button>
		<button class="pp" data-a="pp" aria-label="Soita">▶</button>
		<button data-a="next" aria-label="Seuraava">⏭</button>
		<div class="nw"><b data-n>Ei valittua kappaletta</b><input type="range" min="0" max="1000" value="0" data-s aria-label="Kohta"></div>
		<span class="t" data-t>0:00</span>
	</div>
	<audio preload="metadata"></audio>
</div>
	<?php
	return ob_get_clean();
} );

/* ---------- Kauko-ohjain (vain kirjautuneelle) ---------- */
add_shortcode( 'suorni_kauko', function ( $atts ) {
	if ( ! current_user_can( 'upload_files' ) ) {
		return '<p>Kirjaudu ensin WordPressiin nähdäksesi kauko-ohjaimen.</p>';
	}
	wp_enqueue_script( 'suorni-kauko', plugins_url( 'kauko.js', __FILE__ ), array(), SUORNI_VER, true );
	$atts   = shortcode_atts( array( 'soitin' => home_url( '/' ), 'asiakas' => home_url( '/asiakas/' ) ), $atts );
	$tracks = suorni_tracks();
	ob_start();
	?>
<style>
.sk{background:var(--sp-bg,#0b0b12);color:var(--sp-tx,#ececf4);border-radius:16px;padding:22px;max-width:560px;margin:20px auto;font:16px/1.5 system-ui,-apple-system,"Segoe UI",sans-serif}
.sk *{box-sizing:border-box}
.sk h2{margin:0 0 12px;font-size:24px;color:var(--sp-tx,#ececf4)}
.sk button{background:var(--sp-panel,#14141f);color:var(--sp-tx,#ececf4);border:1px solid var(--sp-line,#262636);border-radius:10px;padding:12px 16px;font:inherit;cursor:pointer;flex:1}
.sk button:hover{border-color:var(--sp-acc,#8b7bff)}
.sk .k,.sk .k2{display:flex;gap:10px;margin-bottom:12px}
.sk .np{background:var(--sp-panel,#14141f);border-radius:12px;padding:12px 14px;margin-bottom:12px}
.sk .np b{display:block}
.sk .np span{color:var(--sp-mut,#8b8ba3);font-size:14px}
.sk .pg{height:4px;background:var(--sp-line,#262636);border-radius:2px;margin-top:8px}
.sk .pg i{display:block;height:100%;width:0;background:linear-gradient(90deg,var(--sp-acc,#8b7bff),var(--sp-acc2,#ff7ab8));border-radius:2px}
.sk .vl{display:flex;gap:10px;align-items:center;margin:0 0 12px}
.sk .vl input{flex:1;accent-color:var(--sp-acc,#8b7bff)}
.sk .st{color:var(--sp-mut,#8b8ba3);font-size:14px;min-height:22px;margin:6px 0}
.sk .rw{display:flex;gap:6px;margin-bottom:6px}
.sk .rw .tr{flex:1;width:auto;text-align:left;margin-bottom:0}
.sk .rw.on .tr{border-color:var(--sp-acc,#8b7bff)}
.sk .tr span{display:block;color:var(--sp-mut,#8b8ba3);font-size:14px}
.sk .mv{flex:none;width:42px;padding:0}
.sk .rm{color:#e5484d}
.sk .mv:disabled{opacity:.3;cursor:default}
.sk details{border-top:1px solid var(--sp-line,#262636);margin-top:14px;padding-top:10px}
.sk summary{cursor:pointer;font-weight:600}
.sk .ls{margin:10px 0}
.sk .lh{display:flex;gap:8px;align-items:center}
.sk .lh b{flex:1}
.sk .lh button{flex:none;padding:6px 10px}
.sk .lp{max-height:220px;overflow:auto;padding:8px 4px}
.sk .lp label{display:block;padding:3px 0}
.sk .la{display:flex;gap:8px;margin-top:10px}
.sk .la input,.sk .lk{flex:1;width:100%;padding:10px;border-radius:8px;border:1px solid var(--sp-line,#262636);background:var(--sp-panel,#14141f);color:var(--sp-tx,#ececf4);font:inherit}
.sk .la button{flex:none}
.sk .sr{padding:2px 0}
.sk .sc-r{display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin:10px 0}
.sk .sc-r b{width:44px}
.sk .sc-t{padding:8px;border-radius:8px;border:1px solid var(--sp-line,#262636);background:var(--sp-panel,#14141f);color:var(--sp-tx,#ececf4);font:inherit}
.sk .mk-n{width:70px;flex:none;padding:8px;border-radius:8px;border:1px solid var(--sp-line,#262636);background:var(--sp-panel,#14141f);color:var(--sp-tx,#ececf4);font:inherit}
.sk .mk-r{display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin:8px 0}
.sk textarea.lk{resize:vertical}
</style>
<div class="sk" data-ver="<?php echo esc_attr( SUORNI_VER ); ?>" data-nonce="<?php echo esc_attr( wp_create_nonce( 'wp_rest' ) ); ?>" data-url="<?php echo suorni_ep( 'cmd' ); ?>" data-tu="<?php echo suorni_ep( 'tracks' ); ?>" data-order-url="<?php echo suorni_ep( 'order' ); ?>" data-lists-url="<?php echo suorni_ep( 'lists' ); ?>" data-state-url="<?php echo suorni_ep( 'state' ); ?>" data-stats-url="<?php echo suorni_ep( 'stats' ); ?>" data-reset-url="<?php echo suorni_ep( 'reset' ); ?>" data-tts-cfg-url="<?php echo suorni_ep( 'ttscfg' ); ?>" data-tts-test-url="<?php echo suorni_ep( 'ttstest' ); ?>" data-tts="<?php echo esc_attr( wp_json_encode( suorni_tts_public() ) ); ?>" data-dj-url="<?php echo suorni_ep( 'dj' ); ?>" data-dj="<?php echo esc_attr( wp_json_encode( suorni_dj() ) ); ?>" data-ann-url="<?php echo suorni_ep( 'ann' ); ?>" data-anns="<?php echo esc_attr( wp_json_encode( suorni_anns() ) ); ?>" data-targets-url="<?php echo suorni_ep( 'targets' ); ?>" data-clients-url="<?php echo suorni_ep( 'clients' ); ?>" data-targets="<?php echo esc_attr( wp_json_encode( suorni_targets_public() ) ); ?>" data-clients="<?php echo esc_attr( wp_json_encode( suorni_clients() ) ); ?>" data-player-page="<?php echo esc_url( $atts['soitin'] ); ?>" data-client-page="<?php echo esc_url( $atts['asiakas'] ); ?>" data-addlink-url="<?php echo suorni_ep( 'addlink' ); ?>" data-dur-url="<?php echo suorni_ep( 'dur' ); ?>" data-sunodur-url="<?php echo suorni_ep( 'sunodur' ); ?>" data-delete-url="<?php echo suorni_ep( 'delete' ); ?>" data-ads-url="<?php echo suorni_ep( 'ads' ); ?>" data-sched-url="<?php echo suorni_ep( 'sched' ); ?>" data-sched="<?php echo esc_attr( wp_json_encode( suorni_sched_public() ) ); ?>" data-shuf="<?php $td = suorni_tdata( suorni_targets()[0]['id'] ); echo (int) $td['shuf']; ?>" data-ads="<?php echo esc_attr( wp_json_encode( suorni_ads_public() ) ); ?>" data-lists="<?php echo esc_attr( wp_json_encode( suorni_lists() ) ); ?>" data-tracks="<?php echo esc_attr( wp_json_encode( $tracks ) ); ?>">
	<h2>Kauko-ohjain</h2>
	<div class="k2" data-tsel-wrap hidden><select class="sc-t" data-tsel aria-label="Kohde" style="flex:1"></select></div>
	<div class="np"><b data-npt>Ei tietoa soittimesta</b><span data-nps>Yhdistä puhelin alta, niin näet täällä mitä siellä soi.</span><div class="pg"><i data-pgi></i></div></div>
	<div class="k">
		<button data-a="prev">⏮</button>
		<button data-a="play">▶ Soita</button>
		<button data-a="pause">⏸ Tauko</button>
		<button data-a="next">⏭</button>
	</div>
	<div class="k2"><select class="sc-t" data-theme-sel aria-label="Teema" style="flex:1"></select></div>
	<div class="k2"><button data-shuf>🔀 Satunnaistoisto: pois</button></div>
	<div class="vl"><span>🔈</span><input type="range" min="0" max="100" value="<?php echo (int) $td['vol']; ?>" data-vol aria-label="Äänenvoimakkuus"><span data-vv></span></div>
	<div class="vl"><span>📣</span><input type="range" min="100" max="400" step="10" value="<?php echo (int) get_option( 'suorni_lv', 150 ); ?>" data-lv aria-label="Mainosten ja kuulutusten voimakkuus"><span data-lvv></span></div>
	<p class="st" style="margin:-6px 0 8px">Mainosten ja kuulutusten voimakkuus (100 % = sama kuin musiikki, 400 % = paljon kovempi)</p>
	<div class="st" data-st role="status"></div>
	<div class="st" data-tot></div>
	<div data-l></div>
	<details><summary>Lisää kappale linkillä</summary>
		<p class="st">Liitä Suno-linkki (suno.com/song/… tai suno.com/s/…) tai suora äänitiedoston linkki, yksi per rivi. Halutessasi voit antaa tiedot näin: linkki | Nimi | Esittäjä | Kesto (esim. 3:25) | Teema. Teema valitaan myös yllä olevasta valikosta, ja riville kirjoitettu teema voittaa valikon. Uusi teema luodaan itsestään.</p>
		<div class="la" style="margin:0 0 8px"><select class="sc-t" data-add-theme aria-label="Teema" style="flex:1"></select><input data-add-theme-new placeholder="Uuden teeman nimi" hidden></div>
		<textarea class="lk" rows="4" data-links placeholder="https://suno.com/song/..."></textarea>
		<button data-addlinks style="width:100%;margin-top:8px">Lisää linkit</button>
		<div class="st" data-addlog></div>
	</details>
	<details><summary>Aikataulu</summary><div data-sched-box></div></details>
	<details><summary>Kuulutukset (ajastetut ilmoitukset)</summary><div data-ann-box></div></details>
	<details><summary>Juontaja (kellonaika, sää, seuraava kappale)</summary><div data-dj-box></div></details>
	<details><summary>Mainokset</summary><div data-ads-box></div></details>
	<details><summary>Soittolistat (teemat)</summary><div data-lists-box></div></details>
	<details><summary>Kuuntelukerrat</summary><div data-stats></div></details>
	<p><a href="<?php echo esc_url( suorni_radio_admin_url() ); ?>" style="color:var(--sp-acc,#8b7bff);font-weight:600">📻 Nettiradion ohjaamo →</a></p>
	<details><summary>Yhdistä puhelin</summary><div data-pair-box></div></details>
	<details><summary>Kohteet (soittimet)</summary><div data-targets-box></div></details>
	<details><summary>Asiakkaat</summary><div data-clients-box></div></details>
</div>
	<?php
	return ob_get_clean();
} );

/* ---------- Asiakkaan kauko-ohjain (avainlinkki, ei WordPress-tunnuksia) ---------- */
add_shortcode( 'suorni_asiakas', function () {
	wp_enqueue_script( 'suorni-asiakas', plugins_url( 'asiakas.js', __FILE__ ), array(), SUORNI_VER, true );
	ob_start();
	?>
<style>
.sa{background:var(--sp-bg,#0b0b12);color:var(--sp-tx,#ececf4);border-radius:16px;padding:22px;max-width:560px;margin:20px auto;font:16px/1.5 system-ui,-apple-system,"Segoe UI",sans-serif}
.sa *{box-sizing:border-box}
.sa h2{margin:0 0 12px;font-size:24px;color:var(--sp-tx,#ececf4)}
.sa button{background:var(--sp-panel,#14141f);color:var(--sp-tx,#ececf4);border:1px solid var(--sp-line,#262636);border-radius:10px;padding:12px 16px;font:inherit;cursor:pointer;flex:1}
.sa .k,.sa .k2{display:flex;gap:10px;margin-bottom:12px}
.sa select{width:100%;padding:10px;border-radius:8px;border:1px solid var(--sp-line,#262636);background:var(--sp-panel,#14141f);color:var(--sp-tx,#ececf4);font:inherit;margin-bottom:12px}
.sa .np{background:var(--sp-panel,#14141f);border-radius:12px;padding:12px 14px;margin-bottom:12px}
.sa .np b{display:block}
.sa .np span{color:var(--sp-mut,#8b8ba3);font-size:14px}
.sa .vl{display:flex;gap:10px;align-items:center;margin:0 0 12px}
.sa .vl input{flex:1;accent-color:var(--sp-acc,#8b7bff)}
.sa .st{color:var(--sp-mut,#8b8ba3);font-size:14px;min-height:22px;margin:6px 0}
.sa .rw button{width:100%;text-align:left;margin-bottom:6px}
.sa .rw span{display:block;color:var(--sp-mut,#8b8ba3);font-size:14px}
</style>
<div class="sa" data-info="<?php echo suorni_ep( 'c/info' ); ?>" data-cmd="<?php echo suorni_ep( 'c/cmd' ); ?>" data-state="<?php echo suorni_ep( 'c/state' ); ?>">
	<h2>Soitin</h2>
	<div class="st" data-st role="status">Ladataan…</div>
	<div data-body hidden>
		<select data-tsel aria-label="Soitin" hidden></select>
		<div class="np"><b data-npt>Ei tietoa soittimesta</b><span data-nps></span></div>
		<div class="k"><button data-a="prev">⏮</button><button data-a="play">▶ Soita</button><button data-a="pause">⏸ Tauko</button><button data-a="next">⏭</button></div>
		<select data-theme-sel aria-label="Teema" hidden></select>
		<div class="k2"><button data-shuf>🔀 Satunnaistoisto</button></div>
		<div class="vl"><span>🔈</span><input type="range" min="0" max="100" value="100" data-vol aria-label="Äänenvoimakkuus"><span data-vv></span></div>
		<div data-l></div>
	</div>
</div>
	<?php
	return ob_get_clean();
} );

/* Luo "Asiakas"-sivu automaattisesti (kerran), jotta osoite /asiakas/ toimii heti */
add_action( 'admin_init', function () {
	if ( get_option( 'suorni_asiakas_page' ) ) { return; }
	update_option( 'suorni_asiakas_page', 1, false );
	if ( get_page_by_path( 'asiakas' ) ) { return; }
	wp_insert_post( array(
		'post_title'   => 'Asiakas',
		'post_name'    => 'asiakas',
		'post_status'  => 'publish',
		'post_type'    => 'page',
		'post_content' => '[suorni_asiakas]',
	) );
} );

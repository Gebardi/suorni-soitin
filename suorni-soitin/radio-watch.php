<?php
/**
 * Suorni Radio: valvonta, hälytykset ja käyttö-/kulujen yhteenveto.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

/* ---------- Käyttölaskuri (31 päivää) ---------- */
function suorni_usage_add( $kind, $n = 1, $chars = 0 ) {
	$u = get_option( 'suorni_usage', array() );
	if ( ! is_array( $u ) ) { $u = array(); }
	$d = wp_date( 'Ymd' );
	if ( ! isset( $u[ $d ] ) ) { $u[ $d ] = array( 'ai' => 0, 'tts' => 0, 'ch' => 0 ); }
	$u[ $d ][ $kind ] = ( isset( $u[ $d ][ $kind ] ) ? (int) $u[ $d ][ $kind ] : 0 ) + (int) $n;
	if ( $chars > 0 ) { $u[ $d ]['ch'] = (int) $u[ $d ]['ch'] + (int) $chars; }
	krsort( $u );
	$u = array_slice( $u, 0, 31, true );
	update_option( 'suorni_usage', $u, false );
}
function suorni_usage_sum() {
	$u = get_option( 'suorni_usage', array() );
	if ( ! is_array( $u ) ) { $u = array(); }
	$sum = function ( $days ) use ( $u ) {
		$r = array( 'ai' => 0, 'tts' => 0, 'ch' => 0 );
		for ( $i = 0; $i < $days; $i++ ) {
			$k = wp_date( 'Ymd', time() - $i * DAY_IN_SECONDS );
			if ( isset( $u[ $k ] ) ) { foreach ( $r as $f => $v ) { $r[ $f ] += isset( $u[ $k ][ $f ] ) ? (int) $u[ $k ][ $f ] : 0; } }
		}
		return $r;
	};
	$t = $sum( 1 ); $w = $sum( 7 ); $m = $sum( 30 );
	$est = function ( $x ) { return round( $x['ch'] / 1000000 * 16, 2 ); };
	return array(
		'today' => $t, 'week' => $w, 'month' => $m,
		'tts_usd' => array( 'today' => $est( $t ), 'week' => $est( $w ), 'month' => $est( $m ) ),
		'tts_hour' => (int) get_transient( 'suorni_tts_n' ), 'tts_hour_max' => (int) get_option( 'suorni_tts_max', 250 ),
	);
}

/* ---------- Rikkinäisinä piilotetut kappaleet ---------- */
function suorni_hidden_note( $id, $why ) {
	$l = get_option( 'suorni_health_hidden', array() );
	if ( ! is_array( $l ) ) { $l = array(); }
	$l[ (int) $id ] = array( 'id' => (int) $id, 't' => (string) get_the_title( (int) $id ), 'why' => mb_substr( (string) $why, 0, 120 ), 'at' => time() );
	if ( count( $l ) > 100 ) { $l = array_slice( $l, -100, null, true ); }
	update_option( 'suorni_health_hidden', $l, false );
}
function suorni_hidden_list() {
	$l  = get_option( 'suorni_health_hidden', array() );
	$ex = array_map( 'intval', (array) suorni_radio_excl() );
	$o  = array();
	if ( is_array( $l ) ) { foreach ( $l as $x ) { if ( in_array( (int) $x['id'], $ex, true ) ) { $o[] = $x; } } }
	return array_reverse( $o );
}
function suorni_unhide_post( $req ) {
	$id = absint( $req->get_param( 'id' ) );
	$ex = array_values( array_diff( array_map( 'intval', (array) suorni_radio_excl() ), array( $id ) ) );
	update_option( 'suorni_radio_excl', $ex, false );
	$l = get_option( 'suorni_health_hidden', array() );
	if ( is_array( $l ) ) { unset( $l[ $id ] ); update_option( 'suorni_health_hidden', $l, false ); }
	delete_option( 'suorni_health' );
	return suorni_json( suorni_watch_data() );
}

/* ---------- Tarkistukset ---------- */
function suorni_watch_checks() {
	$out = array();
	$add = function ( $id, $lvl, $title, $detail ) use ( &$out ) { $out[] = array( 'id' => $id, 'lvl' => $lvl, 'title' => $title, 'detail' => $detail ); };
	$cfg = suorni_radio_cfg();
	if ( empty( $cfg['on'] ) ) {
		$add( 'radio', 'warn', 'Radio on pois päältä', 'Radio ei lähetä, koska se on kytketty pois asetuksista.' );
		return $out;
	}
	/* Aikajana */
	$log = suorni_radio_log();
	$now = microtime( true );
	$cur = null; $end = 0;
	foreach ( $log['items'] as $it ) {
		if ( $it['start'] <= $now && $now < $it['start'] + $it['dur'] ) { $cur = $it; }
		$end = max( $end, $it['start'] + $it['dur'] );
	}
	$ahead = (int) max( 0, $end - $now );
	if ( ! $cur ) { $add( 'line', 'bad', 'Radiosta ei kuulu mitään', 'Aikajanalla ei ole nyt soivaa kohtaa. Tarkista, että soittolistalla on kappaleita ja että kappaleet ovat sallittuja ohjelmalle.' ); }
	elseif ( $ahead < 120 ) { $add( 'line', 'bad', 'Aikajana loppumassa', 'Ohjelmaa on suunniteltu vain ' . $ahead . ' s eteenpäin.' ); }
	elseif ( $ahead < 600 ) { $add( 'line', 'warn', 'Aikajana lyhyt', 'Ohjelmaa on suunniteltu ' . round( $ahead / 60 ) . ' min eteenpäin.' ); }
	else { $add( 'line', 'ok', 'Aikajana kunnossa', 'Ohjelmaa suunniteltu ' . round( $ahead / 60 ) . ' min eteenpäin.' ); }
	/* Tekoäly */
	$ai = function_exists( 'suorni_radio_ai_cfg' ) ? suorni_radio_ai_cfg() : array( 'on' => false );
	if ( ! empty( $ai['on'] ) && '' !== $ai['key'] ) {
		$e = (string) get_option( 'suorni_ai_err', '' );
		$d = get_option( 'suorni_ai_day', array() );
		$n = ( is_array( $d ) && isset( $d['d'] ) && wp_date( 'Ymd' ) === $d['d'] ) ? (int) $d['n'] : 0;
		$mx = (int) $ai['max'];
		if ( '' !== $e ) { $add( 'ai', 'bad', 'Tekoäly antaa virheen', $e . ' Juontaja puhuu sillä aikaa valmiilla teksteillä.' ); }
		elseif ( $mx > 0 && $n >= $mx ) { $add( 'ai', 'warn', 'Tekoälyn päiväraja täynnä', $n . '/' . $mx . ' puhetta tänään. Juontaja käyttää valmiita tekstejä päivän loppuun.' ); }
		elseif ( $mx > 0 && $n >= 0.8 * $mx ) { $add( 'ai', 'warn', 'Tekoälyn päiväraja lähestyy', $n . '/' . $mx . ' puhetta tänään.' ); }
		else { $add( 'ai', 'ok', 'Tekoäly toimii', $n . ( $mx > 0 ? '/' . $mx : '' ) . ' puhetta tänään.' ); }
	}
	/* Puhesynteesi */
	$de = (string) get_option( 'suorni_radio_djerr', '' );
	$tn = (int) get_transient( 'suorni_tts_n' ); $tm = (int) get_option( 'suorni_tts_max', 250 );
	if ( '' !== $de ) { $add( 'tts', 'bad', 'Juontajan puhe ei synny', $de ); }
	elseif ( $tm > 0 && $tn >= 0.8 * $tm ) { $add( 'tts', 'warn', 'Puhesynteesin tuntiraja lähestyy', $tn . '/' . $tm . ' uutta puhetta tunnissa.' ); }
	else { $add( 'tts', 'ok', 'Juontajan puhe toimii', $tn . ' uutta puhetta viimeisen tunnin aikana.' ); }
	/* Uutiset, urheilu, NHL */
	$fs = get_option( 'suorni_feed_stat', array() );
	if ( ! is_array( $fs ) ) { $fs = array(); }
	$names = array( 'news' => 'Uutiset', 'sport' => 'Urheilu', 'nhl' => 'NHL-tulokset' );
	foreach ( $names as $k => $nm ) {
		if ( empty( $fs[ $k ] ) ) { continue; }
		$x = $fs[ $k ];
		if ( ! empty( $x['ok'] ) ) { $add( 'feed_' . $k, 'ok', $nm . ' haetaan', $x['n'] . ' juttua, haettu ' . wp_date( 'j.n. H:i', (int) $x['t'] ) . '.' ); }
		else { $add( 'feed_' . $k, 'warn', $nm . ' ei haeta', ( $x['err'] ? $x['err'] . ' ' : '' ) . ( ! empty( $x['lastok'] ) ? 'Viimeksi onnistui ' . wp_date( 'j.n. H:i', (int) $x['lastok'] ) . '.' : 'Ei ole vielä onnistunut.' ) ); }
	}
	/* Kappaleet */
	$h = suorni_health_get();
	$ex = suorni_radio_excl();
	$exi = array_map( 'intval', (array) $ex );
	$bad = 0;
	foreach ( array_merge( (array) $h['bad'], (array) $h['ext'] ) as $b ) { if ( is_array( $b ) && isset( $b['id'] ) && ! in_array( (int) $b['id'], $exi, true ) ) { $bad++; } }
	if ( $bad > 0 ) { $add( 'tracks', 'warn', $bad . ' kappaletta ei toimi', 'Ne ovat vielä radion soittolistalla. Piilota ne Soittolista-välilehden tarkistuksesta.' ); }
	elseif ( ! empty( $h['at'] ) ) { $add( 'tracks', 'ok', 'Kappaleet toimivat', 'Tarkistettu ' . wp_date( 'j.n. H:i', (int) $h['at'] ) . '.' . ( (int) get_option( 'suorni_health_auto', 0 ) > 0 ? ' Rikkinäisiä piilotettu automaattisesti yhteensä ' . (int) get_option( 'suorni_health_auto', 0 ) . '.' : '' ) ); }
	else { $add( 'tracks', 'warn', 'Kappaleita ei ole tarkistettu', 'Paina Soittolista-välilehdellä "Tarkista kappaleet".' ); }
	return $out;
}
function suorni_watch_data() {
	$c = suorni_watch_checks();
	$lvl = 'ok';
	foreach ( $c as $x ) { if ( 'bad' === $x['lvl'] ) { $lvl = 'bad'; break; } if ( 'warn' === $x['lvl'] ) { $lvl = 'warn'; } }
	return array( 'lvl' => $lvl, 'hidden' => suorni_hidden_list(), 'djlog' => array_reverse( (array) get_option( 'suorni_dj_log', array() ) ), 'checks' => $c, 'usage' => suorni_usage_sum(), 'mail' => (bool) get_option( 'suorni_watch_mail', 0 ), 'at' => wp_date( 'H.i' ) );
}
function suorni_watch_get() { return suorni_json( suorni_watch_data() ); }
function suorni_watch_mail_post( $req ) {
	update_option( 'suorni_watch_mail', $req->get_param( 'on' ) ? 1 : 0, false );
	return suorni_json( suorni_watch_data() );
}
add_action( 'rest_api_init', function () {
	register_rest_route( 'suorni/v1', '/radio/watch', array( 'methods' => 'GET', 'callback' => 'suorni_watch_get', 'permission_callback' => 'suorni_can' ) );
	register_rest_route( 'suorni/v1', '/radio/unhide', array( 'methods' => 'POST', 'callback' => 'suorni_unhide_post', 'permission_callback' => 'suorni_can' ) );
	register_rest_route( 'suorni/v1', '/radio/watchmail', array( 'methods' => 'POST', 'callback' => 'suorni_watch_mail_post', 'permission_callback' => 'suorni_can' ) );
} );

/* ---------- Hälytys sähköpostiin (vain jos kytketty päälle) ---------- */
add_filter( 'cron_schedules', function ( $s ) {
	$s['suorni_10min'] = array( 'interval' => 600, 'display' => 'Suorni: 10 min' );
	return $s;
} );
add_action( 'init', function () {
	if ( ! wp_next_scheduled( 'suorni_watch_job' ) ) { wp_schedule_event( time() + 300, 'suorni_10min', 'suorni_watch_job' ); }
} );
add_action( 'suorni_watch_job', function () {
	if ( ! get_option( 'suorni_watch_mail', 0 ) ) { return; }
	$bad = array(); $lines = array();
	foreach ( suorni_watch_checks() as $x ) { if ( 'bad' === $x['lvl'] ) { $bad[] = $x['id']; $lines[] = '- ' . $x['title'] . ': ' . $x['detail']; } }
	$last = get_option( 'suorni_watch_sent', array( 'ids' => '', 't' => 0 ) );
	$key  = implode( ',', $bad );
	if ( ! $bad ) { if ( '' !== $last['ids'] ) { update_option( 'suorni_watch_sent', array( 'ids' => '', 't' => time() ), false ); } return; }
	if ( $last['ids'] === $key && time() - (int) $last['t'] < 6 * HOUR_IN_SECONDS ) { return; }
	wp_mail( get_option( 'admin_email' ), 'Suorni Radio: ' . count( $bad ) . ' ongelmaa', "Radiossa on ongelma:\n\n" . implode( "\n", $lines ) . "\n\nTilanne näkyy ohjaamon Yleiset-välilehdellä.\n" . home_url( '/' ) );
	update_option( 'suorni_watch_sent', array( 'ids' => $key, 't' => time() ), false );
} );

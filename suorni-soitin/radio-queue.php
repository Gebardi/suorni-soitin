<?php
/* Radion ohjaamon lähetyspaneeli: soiva kappale + seuraavat 5 kappaletta/juontoa, ja niiden poisto jonosta. */
if ( ! defined( 'ABSPATH' ) ) { exit; }

function suorni_radio_queue_item( $it ) {
	return array(
		'key'    => sprintf( '%.3f', $it['start'] ),
		'type'   => $it['type'],
		'start'  => $it['start'],
		'dur'    => $it['dur'],
		'title'  => isset( $it['title'] ) ? $it['title'] : '',
		'artist' => isset( $it['artist'] ) ? $it['artist'] : '',
		'text'   => isset( $it['text'] ) ? $it['text'] : '',
		'prog'   => isset( $it['prog'] ) ? $it['prog'] : '',
		'sub'    => ! empty( $it['sub'] ) ? 1 : 0,
		'shout'  => ! empty( $it['shout'] ) ? 1 : 0,
	);
}
function suorni_radio_queue_get( $req ) {
	$cfg = suorni_radio_cfg();
	$out = array( 'on' => (bool) $cfg['on'], 'now' => microtime( true ), 'cur' => null, 'next' => array(), 'live' => function_exists( 'suorni_radio_live_count' ) ? suorni_radio_live_count() : 0 );
	if ( ! $cfg['on'] ) { return suorni_json( $out ); }
	$log = suorni_radio_log();
	$now = microtime( true );
	$ahead = 0;
	foreach ( $log['items'] as $it ) { if ( $it['start'] > $now ) { $ahead++; } }
	/* Jono rakennetaan normaalisti vain ~8 min eteenpäin: laajennetaan, jotta 5 seuraavaa näkyy */
	/* Ilman kuuntelijoita jonoa ei rakenneta itsestään (säästää tekoäly- ja puhesynteesikuluja); ohjaamosta voi rakentaa esikatselun painikkeella */
	$build = ( $out['live'] > 0 ) || (bool) $req->get_param( 'build' );
	if ( $build && $ahead < 5 && suorni_radio_lock() ) {
		wp_cache_delete( 'suorni_radio_log', 'options' );
		$log = suorni_radio_log();
		$now = microtime( true );
		$ahead = 0;
		foreach ( $log['items'] as $it ) { if ( $it['start'] > $now ) { $ahead++; } }
		if ( $ahead < 5 ) { suorni_radio_extend( $log, $now, 1500 ); update_option( 'suorni_radio_log', $log, false ); }
		suorni_radio_unlock();
	}
	$now = microtime( true );
	$out['now'] = $now;
	foreach ( $log['items'] as $it ) {
		if ( $it['start'] <= $now && $it['start'] + $it['dur'] > $now ) { $out['cur'] = suorni_radio_queue_item( $it ); }
		elseif ( $it['start'] > $now && count( $out['next'] ) < 5 ) { $out['next'][] = suorni_radio_queue_item( $it ); }
	}
	return suorni_json( $out );
}
/* Poistaa kohteen jonosta; myöhemmät siirtyvät aikaisemmaksi, ettei jää tyhjää. */
function suorni_radio_queue_cut( $req ) {
	$key = (string) $req->get_param( 'key' );
	if ( ! preg_match( '#^\d+(\.\d+)?$#', $key ) ) { return new WP_Error( 'suorni_bad', 'Virheellinen kohde', array( 'status' => 400 ) ); }
	if ( ! suorni_radio_lock() ) { return new WP_Error( 'suorni_busy', 'Jono on juuri päivittymässä, yritä hetken kuluttua uudelleen.', array( 'status' => 409 ) ); }
	wp_cache_delete( 'suorni_radio_log', 'options' );
	wp_cache_delete( 'alloptions', 'options' );
	$log = suorni_radio_log();
	$now = microtime( true );
	$idx = -1;
	foreach ( $log['items'] as $i => $it ) { if ( abs( $it['start'] - (float) $key ) < 0.002 ) { $idx = $i; break; } }
	if ( $idx < 0 ) { suorni_radio_unlock(); return new WP_Error( 'suorni_gone', 'Kohde on jo ehtinyt soida tai poistua. Päivitä paneeli.', array( 'status' => 404 ) ); }
	$it = $log['items'][ $idx ];
	if ( $it['start'] + $it['dur'] <= $now + 1 ) { suorni_radio_unlock(); return new WP_Error( 'suorni_gone', 'Kohde on jo loppumassa.', array( 'status' => 409 ) ); }
	$shift = ( $it['start'] <= $now ) ? ( $it['start'] + $it['dur'] - $now ) : $it['dur'];
	$items = array();
	foreach ( $log['items'] as $i => $x ) {
		if ( $i === $idx ) { continue; }
		if ( $i > $idx ) { $x['start'] -= $shift; }
		$items[] = $x;
	}
	$log['items'] = $items;
	/* Jos poisto siirsi tasalle (tasatunnin juonto) asetetun kohteen aikaisemmaksi, täytetään aukko kappaleilla, jotta juonto pysyy tasalla */
	$cfg = suorni_radio_cfg();
	for ( $i = max( 0, $idx ); $i < count( $log['items'] ); $i++ ) {
		$x = $log['items'][ $i ];
		if ( ! empty( $x['anc'] ) && $x['start'] > $now && $x['anc'] - $x['start'] > 8 ) { suorni_radio_fill_before( $log, $i, $cfg ); }
	}
	update_option( 'suorni_radio_log', $log, false );
	suorni_radio_unlock();
	return suorni_json( array( 'ok' => true ) );
}
add_action( 'rest_api_init', function () {
	register_rest_route( 'suorni/v1', '/radio/queue', array( 'methods' => 'GET', 'callback' => 'suorni_radio_queue_get', 'permission_callback' => 'suorni_can' ) );
	register_rest_route( 'suorni/v1', '/radio/queuecut', array( 'methods' => 'POST', 'callback' => 'suorni_radio_queue_cut', 'permission_callback' => 'suorni_can' ) );
} );

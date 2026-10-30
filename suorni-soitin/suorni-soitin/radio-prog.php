<?php
/**
 * Suorni Radio: kappaleiden ohjelmarajaus ja sääpaneeli.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

/* ---------- Kappale vain tietyille ohjelmille ---------- */
function suorni_prog_ids() {
	$out = array();
	foreach ( suorni_radio_cfg()['programs'] as $p ) { $out[ $p['id'] ] = $p['name']; }
	return $out;
}
function suorni_track_progs( $id ) {
	$m = get_post_meta( (int) $id, '_suorni_progs', true );
	return is_array( $m ) ? array_values( array_map( 'sanitize_key', $m ) ) : array();
}
function suorni_track_set_progs( $id, $progs ) {
	$valid = array_keys( suorni_prog_ids() );
	$progs = array_values( array_intersect( array_unique( array_map( 'sanitize_key', (array) $progs ) ), $valid ) );
	if ( ! $progs || count( $progs ) >= count( $valid ) ) { delete_post_meta( $id, '_suorni_progs' ); } else { update_post_meta( $id, '_suorni_progs', $progs ); }
	suorni_clear_tracks();
	return $progs;
}
/* Saako kappale soida ohjelmassa? Tyhjä rajaus = kaikissa ohjelmissa. */
function suorni_track_allowed( $t, $pid ) {
	if ( '' === $pid || empty( $t['progs'] ) || ! is_array( $t['progs'] ) ) { return true; }
	return in_array( $pid, $t['progs'], true );
}
function suorni_radio_trackprogs_post( $req ) {
	$id = absint( $req->get_param( 'id' ) );
	if ( ! $id || 'attachment' !== get_post_type( $id ) ) { return new WP_Error( 'suorni_bad', 'Tuntematon kappale', array( 'status' => 400 ) ); }
	return suorni_json( array( 'ok' => true, 'progs' => suorni_track_set_progs( $id, (array) $req->get_param( 'progs' ) ) ) );
}
/* Mediakirjaston kappaleen tietoihin valinta: kaikki ohjelmat tai yksi ohjelma */
add_filter( 'attachment_fields_to_edit', function ( $fields, $post ) {
	if ( 0 !== strpos( (string) $post->post_mime_type, 'audio' ) ) { return $fields; }
	$cur = suorni_track_progs( $post->ID );
	$h   = '<select name="attachments[' . (int) $post->ID . '][suorni_prog]"><option value="">Kaikki ohjelmat</option>';
	foreach ( suorni_prog_ids() as $id => $nm ) { $h .= '<option value="' . esc_attr( $id ) . '"' . ( 1 === count( $cur ) && $cur[0] === $id ? ' selected' : '' ) . '>Vain: ' . esc_html( $nm ) . '</option>'; }
	if ( count( $cur ) > 1 ) { $h .= '<option value="__keep" selected>Useita ohjelmia (muokkaa radion ohjaamossa)</option>'; }
	$h .= '</select>';
	$fields['suorni_prog'] = array( 'label' => 'Radio-ohjelma', 'input' => 'html', 'html' => $h, 'helps' => 'Valitse, missä radio-ohjelmassa kappale soi.' );
	return $fields;
}, 10, 2 );
add_filter( 'attachment_fields_to_save', function ( $post, $att ) {
	if ( isset( $att['suorni_prog'] ) && '__keep' !== $att['suorni_prog'] ) {
		$v = sanitize_key( (string) $att['suorni_prog'] );
		suorni_track_set_progs( (int) $post['ID'], '' === $v ? array() : array( $v ) );
	}
	return $post;
}, 10, 2 );

/* ---------- Sää sivulle ---------- */
function suorni_wx_icon( $c ) {
	if ( 0 === $c || 1 === $c ) { return '☀️'; }
	if ( 2 === $c ) { return '⛅'; }
	if ( 3 === $c ) { return '☁️'; }
	if ( 45 === $c || 48 === $c ) { return '🌫️'; }
	if ( $c >= 71 && $c <= 77 || 85 === $c || 86 === $c ) { return '❄️'; }
	if ( $c >= 95 ) { return '⛈️'; }
	if ( $c >= 51 ) { return '🌧️'; }
	return '🌡️';
}
function suorni_wind_dir( $deg ) {
	$n = array( 'pohjoisesta', 'koillisesta', 'idästä', 'kaakosta', 'etelästä', 'lounaasta', 'lännestä', 'luoteesta' );
	return $n[ (int) round( ( (float) $deg % 360 ) / 45 ) % 8 ];
}
function suorni_wx_full() {
	$d   = suorni_radio_dj();
	$key = 'suorni_wxf_' . md5( $d['lat'] . ',' . $d['lon'] );
	$c   = get_transient( $key );
	if ( is_array( $c ) ) { return $c; }
	if ( get_transient( $key . '_fail' ) ) { return null; }
	$url = add_query_arg( array(
		'latitude'        => $d['lat'],
		'longitude'       => $d['lon'],
		'current'         => 'temperature_2m,apparent_temperature,relative_humidity_2m,precipitation,weather_code,wind_speed_10m,wind_gusts_10m,wind_direction_10m',
		'daily'           => 'temperature_2m_max,temperature_2m_min',
		'wind_speed_unit' => 'ms',
		'timezone'        => 'Europe/Helsinki',
		'forecast_days'   => 1,
	), 'https://api.open-meteo.com/v1/forecast' );
	$r = wp_remote_get( $url, array( 'timeout' => 5 ) );
	$j = ( ! is_wp_error( $r ) && 200 === (int) wp_remote_retrieve_response_code( $r ) ) ? json_decode( wp_remote_retrieve_body( $r ), true ) : null;
	if ( ! is_array( $j ) || ! isset( $j['current']['temperature_2m'] ) ) { set_transient( $key . '_fail', 1, 2 * MINUTE_IN_SECONDS ); return null; }
	$cu  = $j['current'];
	$out = array(
		'place' => $d['where'],
		't'     => round( (float) $cu['temperature_2m'] ),
		'fl'    => isset( $cu['apparent_temperature'] ) ? round( (float) $cu['apparent_temperature'] ) : null,
		'w'     => isset( $cu['wind_speed_10m'] ) ? round( (float) $cu['wind_speed_10m'] ) : null,
		'g'     => isset( $cu['wind_gusts_10m'] ) ? round( (float) $cu['wind_gusts_10m'] ) : null,
		'wd'    => isset( $cu['wind_direction_10m'] ) ? suorni_wind_dir( $cu['wind_direction_10m'] ) : '',
		'h'     => isset( $cu['relative_humidity_2m'] ) ? (int) $cu['relative_humidity_2m'] : null,
		'p'     => isset( $cu['precipitation'] ) ? round( (float) $cu['precipitation'], 1 ) : null,
		'hi'    => isset( $j['daily']['temperature_2m_max'][0] ) ? round( (float) $j['daily']['temperature_2m_max'][0] ) : null,
		'lo'    => isset( $j['daily']['temperature_2m_min'][0] ) ? round( (float) $j['daily']['temperature_2m_min'][0] ) : null,
		'txt'   => suorni_wx_text( isset( $cu['weather_code'] ) ? (int) $cu['weather_code'] : -1 ),
		'ic'    => suorni_wx_icon( isset( $cu['weather_code'] ) ? (int) $cu['weather_code'] : -1 ),
		'at'    => wp_date( 'H.i' ),
	);
	set_transient( $key, $out, 10 * MINUTE_IN_SECONDS );
	return $out;
}
function suorni_radio_wx_get() {
	$c = suorni_radio_cfg();
	if ( ! $c['on'] || ! $c['wx_show'] ) { return suorni_json( array( 'wx' => null ) ); }
	return suorni_json( array( 'wx' => suorni_wx_full() ) );
}
add_action( 'rest_api_init', function () {
	register_rest_route( 'suorni/v1', '/radio/wx', array( 'methods' => 'GET', 'callback' => 'suorni_radio_wx_get', 'permission_callback' => '__return_true' ) );
	register_rest_route( 'suorni/v1', '/radio/trackprogs', array( 'methods' => 'POST', 'callback' => 'suorni_radio_trackprogs_post', 'permission_callback' => 'suorni_can' ) );
} );

/* ---------- Tehosoitto: uudet kappaleet soivat ensin ja muita useammin ---------- */
function suorni_boost_played_map() {
	$m = get_option( 'suorni_bplayed', array() );
	return is_array( $m ) ? $m : array();
}
function suorni_boost_played( $id ) {
	$m = suorni_boost_played_map();
	$m[ (int) $id ] = time();
	if ( count( $m ) > 300 ) { $m = array_slice( $m, -200, null, true ); }
	update_option( 'suorni_bplayed', $m, false );
}
function suorni_track_boosted( $t, $cfg ) {
	if ( ! $cfg['boost_on'] ) { return false; }
	$mode = isset( $t['boost'] ) ? $t['boost'] : '';
	if ( 'off' === $mode ) { return false; }
	if ( 'on' === $mode ) { return true; }
	return ! empty( $t['at'] ) && ( time() - (int) $t['at'] ) < $cfg['boost_days'] * DAY_IN_SECONDS;
}
/* Palauttaa tehosoittokappaleen tai null. Uusi (vielä radiossa soittamaton) kappale soi aina ensin. */
function suorni_boost_pick( $st, $pool ) {
	$cfg = suorni_radio_cfg();
	if ( ! $cfg['boost_on'] ) { return null; }
	$recent = array_slice( $st['recent'], -6 );
	$played = suorni_boost_played_map();
	$fresh  = array();
	$boost  = array();
	foreach ( $pool as $t ) {
		if ( ! suorni_track_boosted( $t, $cfg ) ) { continue; }
		if ( in_array( $t['id'], $recent, true ) ) { continue; }
		$t['boosted'] = 1;
		if ( ! isset( $played[ (int) $t['id'] ] ) ) { $fresh[] = $t; } else { $boost[] = $t; }
	}
	if ( $fresh ) {
		usort( $fresh, function ( $a, $b ) { return (int) $b['at'] <=> (int) $a['at']; } );
		return $fresh[0];
	}
	if ( $boost && wp_rand( 1, 100 ) <= $cfg['boost_share'] ) { return $boost[ wp_rand( 0, count( $boost ) - 1 ) ]; }
	return null;
}
function suorni_radio_trackboost_post( $req ) {
	$id   = absint( $req->get_param( 'id' ) );
	$mode = sanitize_key( (string) $req->get_param( 'mode' ) );
	if ( ! $id || 'attachment' !== get_post_type( $id ) ) { return new WP_Error( 'suorni_bad', 'Tuntematon kappale', array( 'status' => 400 ) ); }
	if ( 'on' === $mode || 'off' === $mode ) { update_post_meta( $id, '_suorni_boost', $mode ); } else { delete_post_meta( $id, '_suorni_boost' ); $mode = 'auto'; }
	suorni_clear_tracks();
	return suorni_json( array( 'ok' => true, 'mode' => $mode ) );
}
add_action( 'rest_api_init', function () {
	register_rest_route( 'suorni/v1', '/radio/trackboost', array( 'methods' => 'POST', 'callback' => 'suorni_radio_trackboost_post', 'permission_callback' => 'suorni_can' ) );
} );

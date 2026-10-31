<?php
/**
 * Suorni Radio: ilmoitus puhelimeen, kun uusi kuuntelija tulee radioon.
 * Käyttää ntfy.sh-palvelua (ilmainen ntfy-sovellus iPhoneen/Androidiin). Viestissä ei ole kuuntelijan tunnistetietoja,
 * vain kuuntelijamäärä, tulolähde ja kanava. Oletuksena pois päältä.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

function suorni_push_cfg() {
	$c = get_option( 'suorni_push', array() );
	if ( ! is_array( $c ) ) { $c = array(); }
	$topic = ( isset( $c['topic'] ) && preg_match( '/^[a-z0-9_-]{12,60}$/', (string) $c['topic'] ) ) ? (string) $c['topic'] : '';
	$int   = isset( $c['int'] ) ? (int) $c['int'] : 0;
	if ( ! in_array( $int, array( 0, 60, 300, 900, 3600 ), true ) ) { $int = 0; }
	return array( 'on' => ! empty( $c['on'] ) && '' !== $topic, 'topic' => $topic, 'int' => $int );
}
function suorni_push_state() {
	$c = suorni_push_cfg();
	return array( 'on' => $c['on'], 'topic' => $c['topic'], 'int' => $c['int'], 'url' => '' !== $c['topic'] ? 'https://ntfy.sh/' . $c['topic'] : '', 'last' => (int) get_option( 'suorni_push_last', 0 ), 'err' => (string) get_option( 'suorni_push_err', '' ) );
}
/* Lähetys ntfy.sh:iin. Ei-estävä kutsu kuuntelijan pingistä, estävä testissä. */
function suorni_push_send( $title, $msg, $blocking = false ) {
	$c = suorni_push_cfg();
	if ( '' === $c['topic'] ) { return new WP_Error( 'suorni_push', 'Ilmoitusosoitetta ei ole luotu.' ); }
	$r = wp_remote_post( 'https://ntfy.sh', array(
		'timeout'  => $blocking ? 10 : 2,
		'blocking' => $blocking,
		'headers'  => array( 'Content-Type' => 'application/json' ),
		'body'     => wp_json_encode( array( 'topic' => $c['topic'], 'title' => $title, 'message' => $msg, 'priority' => 3, 'tags' => array( 'headphones' ) ) ),
	) );
	if ( ! $blocking ) { return true; }
	if ( is_wp_error( $r ) ) { return new WP_Error( 'suorni_push', 'Yhteys ntfy-palveluun epäonnistui: ' . $r->get_error_message() ); }
	$code = (int) wp_remote_retrieve_response_code( $r );
	if ( $code < 200 || $code >= 300 ) { return new WP_Error( 'suorni_push', 'ntfy vastasi ' . $code . '.' ); }
	return true;
}
/* Uusi kuuntelija liittyi (kutsutaan suorni_radio_ping:stä) */
function suorni_push_join( $key, $count, $src, $ch, $shop ) {
	$c = suorni_push_cfg();
	if ( ! $c['on'] ) { return; }
	$dk = 'suorni_pn_' . md5( $key );
	if ( get_transient( $dk ) ) { return; }
	set_transient( $dk, 1, 1800 );
	$last = (int) get_option( 'suorni_push_last', 0 );
	if ( $c['int'] > 0 && time() - $last < $c['int'] ) { return; }
	update_option( 'suorni_push_last', time(), false );
	$bits = array( 'Nyt kuuntelee ' . (int) $count );
	if ( $shop ) { $bits[] = 'myymälätila'; }
	if ( '' !== $src ) { $bits[] = 'lähde: ' . $src; }
	if ( '' !== $ch ) { $f = suorni_ch_find( $ch ); if ( $f ) { $bits[] = 'kanava: ' . $f['name']; } }
	suorni_push_send( '🎧 Uusi kuuntelija', implode( ' · ', $bits ), false );
}
function suorni_push_post( $req ) {
	$c = get_option( 'suorni_push', array() );
	if ( ! is_array( $c ) ) { $c = array(); }
	if ( $req->get_param( 'renew' ) || empty( $c['topic'] ) ) { $c['topic'] = 'suorni-' . strtolower( wp_generate_password( 20, false, false ) ); }
	$c['on']  = (bool) $req->get_param( 'on' );
	$int      = (int) $req->get_param( 'int' );
	$c['int'] = in_array( $int, array( 0, 60, 300, 900, 3600 ), true ) ? $int : 0;
	update_option( 'suorni_push', $c, false );
	$res = array();
	if ( $req->get_param( 'test' ) ) {
		$t = suorni_push_send( '🎧 Testi', 'Radio Suornin ilmoitukset toimivat.', true );
		update_option( 'suorni_push_err', is_wp_error( $t ) ? $t->get_error_message() : '', false );
		$res['test'] = is_wp_error( $t ) ? $t->get_error_message() : 'ok';
	}
	$res['push'] = suorni_push_state();
	return suorni_json( $res );
}
add_action( 'rest_api_init', function () {
	register_rest_route( 'suorni/v1', '/radio/push', array( 'methods' => 'POST', 'callback' => 'suorni_push_post', 'permission_callback' => 'suorni_can' ) );
} );

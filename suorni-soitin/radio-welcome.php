<?php
/**
 * Suorni Radio: lyhyt tervetuloa-tervehdys uudelle kuuntelijalle.
 * Soitin hakee äänen vasta kun kuuntelija on kuunnellut ensimmäisen kappaleen loppuun.
 * Tekstit ovat kiinteitä (ei tekoälykuluja) ja äänitiedostot tallentuvat välimuistiin.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

function suorni_welcome_texts( $name, $host ) {
	if ( '' !== $host ) {
		return array(
			'Hei, täällä ' . $host . '. Kuuntelet kanavaa ' . $name . ', toivottavasti nautit matkasta!',
			'Tervetuloa kuuntelemaan, minä olen ' . $host . '. Kanavana ' . $name . '. Toivottavasti nautit matkasta.',
			'Moi, ' . $host . ' tässä. Kuuntelet kanavaa ' . $name . '. Nauti matkasta!',
		);
	}
	return array(
		'Tervetuloa kuuntelemaan. Kuuntelet kanavaa ' . $name . ', toivottavasti nautit matkasta!',
		'Hei ja tervetuloa! Kanavana ' . $name . '. Toivottavasti nautit matkasta.',
		'Moi, kuuntelet kanavaa ' . $name . '. Nauti matkasta!',
	);
}
function suorni_welcome_get() {
	$cfg = suorni_radio_cfg();
	if ( ! $cfg['on'] || empty( $cfg['welcome'] ) || '' === suorni_tts_cfg()['key'] ) { return suorni_json( array( 'url' => '' ) ); }
	/* Rajoitus: yksi haku per kuuntelija kahdessa minuutissa */
	$h = md5( ( isset( $_SERVER['REMOTE_ADDR'] ) ? (string) $_SERVER['REMOTE_ADDR'] : '' ) . wp_salt() );
	if ( get_transient( 'suorni_wel_' . $h ) ) { return suorni_json( array( 'url' => '' ) ); }
	set_transient( 'suorni_wel_' . $h, 1, 2 * MINUTE_IN_SECONDS );
	$prog = suorni_radio_prog_at( $cfg, time() );
	$tx   = suorni_welcome_texts( $cfg['name'], $prog['host'] );
	$t    = $tx[ wp_rand( 0, count( $tx ) - 1 ) ];
	$r    = suorni_tts_synth( array( array( 't' => $t, 'l' => 'fi' ) ), $prog['rate'] > 0 ? $prog['rate'] : suorni_radio_dj()['rate'], array( 'fi' => $prog['vfi'], 'en' => $prog['ven'] ) );
	if ( is_wp_error( $r ) || empty( $r['url'] ) ) { return suorni_json( array( 'url' => '' ) ); }
	return suorni_json( array( 'url' => $r['url'], 'text' => $t ) );
}
add_action( 'rest_api_init', function () {
	register_rest_route( 'suorni/v1', '/radio/welcome', array( 'methods' => 'GET', 'callback' => 'suorni_welcome_get', 'permission_callback' => '__return_true' ) );
} );

<?php
/**
 * Suorni Radio: myymälätila. Sama radio kuin kuuntelijoilla, mutta aukioloajat,
 * kuulutukset ja mainokset kuten myymäläsoittimessa. Ei chattia eikä lomakkeita.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

function suorni_shop_next_open() {
	$s = suorni_sched();
	if ( ! $s['on'] ) { return ''; }
	$names = array( 1 => 'ma', 2 => 'ti', 3 => 'ke', 4 => 'to', 5 => 'pe', 6 => 'la', 7 => 'su' );
	$m     = (int) wp_date( 'G' ) * 60 + (int) wp_date( 'i' );
	for ( $i = 0; $i < 8; $i++ ) {
		$ts  = time() + $i * DAY_IN_SECONDS;
		$n   = (int) wp_date( 'N', $ts );
		$d   = $s[ 7 === $n ? 'su' : ( 6 === $n ? 'sa' : 'wd' ) ];
		if ( ! $d['on'] ) { continue; }
		if ( 0 === $i && suorni_hm( $d['from'] ) <= $m ) { continue; }
		return ( 0 === $i ? '' : $names[ $n ] . ' ' ) . 'klo ' . str_replace( ':', '.', $d['from'] );
	}
	return '';
}
function suorni_shop_get( $req ) {
	$tid = sanitize_key( (string) $req->get_param( 't' ) );
	if ( '' === $tid ) { $tt = suorni_targets(); $tid = $tt[0]['id']; }
	$ads = suorni_ads();
	$list = array();
	foreach ( suorni_tracks() as $t ) { if ( ! empty( $t['ad'] ) && empty( $t['ann'] ) && $t['dur'] > 0 ) { $list[] = array( 'id' => $t['id'], 'url' => $t['url'], 'title' => $t['title'], 'artist' => $t['artist'] ); } }
	$by = array();
	foreach ( suorni_tracks() as $t ) { $by[ $t['id'] ] = $t; }
	$an = array();
	foreach ( suorni_ann_due( $tid ) as $x ) { if ( isset( $by[ $x['id'] ] ) ) { $an[] = array( 'k' => $x['k'], 'url' => $by[ $x['id'] ]['url'], 'title' => $by[ $x['id'] ]['title'] ); } }
	$sch = suorni_sched();
	return suorni_json( array(
		'open' => suorni_sched_open(),
		'sch'  => $sch['on'],
		'next' => suorni_shop_next_open(),
		'an'   => $an,
		'ads'  => array( 'on' => $ads['on'] && $list && ! suorni_radio_cfg()['radads'], 'min' => $ads['min'], 'max' => $ads['max'], 'list' => $list ),
	) );
}
add_action( 'rest_api_init', function () {
	register_rest_route( 'suorni/v1', '/radio/shop', array( 'methods' => 'GET', 'callback' => 'suorni_shop_get', 'permission_callback' => '__return_true' ) );
} );
/* Sivu: /?suorni_kauppa=1 (valinnaisesti &t=laitteen-tunnus kuulutusten kohdistukseen) */
add_action( 'template_redirect', function () {
	if ( ! isset( $_GET['suorni_kauppa'] ) ) { return; }
	nocache_headers();
	header( 'X-Robots-Tag: noindex, nofollow' );
	header( 'Content-Type: text/html; charset=utf-8' );
	?><!doctype html><html lang="fi"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Myymälän radio</title>
<style>body{margin:0;background:#0f141d;padding:8px}</style>
<?php wp_head(); ?></head><body>
<?php echo do_shortcode( '[suorni_radio mode="kauppa"]' ); ?>
<?php wp_footer(); ?></body></html><?php
	exit;
} );

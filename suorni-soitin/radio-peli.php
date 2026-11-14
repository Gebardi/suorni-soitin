<?php
/* Taajuus-peli (peli/): staattinen PWA lisäosan kansiossa. Sivusto käyttää "tavallisia" kestolinkkejä, joten /peli/-polulle ei voi tehdä uudelleenkirjoitussääntöä;
   lyhyt osoite https://suorni.fi/?peli=1 ohjaa pelin omaan osoitteeseen (service workerin scope on silloin vain peli-kansio, ei koko sivusto). */
if ( ! defined( 'ABSPATH' ) ) { exit; }

function suorni_peli_url() { return plugins_url( 'peli/index.html', dirname( __FILE__ ) . '/suorni-soitin.php' ); }
add_action( 'template_redirect', function () {
	if ( ! isset( $_GET['peli'] ) ) { return; }
	$q = isset( $_GET['src'] ) ? sanitize_key( wp_unslash( $_GET['src'] ) ) : '';
	wp_redirect( suorni_peli_url() . ( '' !== $q ? '?src=' . $q : '' ), 302 );
	exit;
}, 1 );

/* Etusivun pelilinkki: Taajuus-peli, jonka voi lisätä kotinäkymään kuvakkeeksi */
function suorni_peli_html() {
	$u = esc_url( add_query_arg( array( 'peli' => '1', 'src' => 'sivu' ), home_url( '/' ) ) );
	return '<section class="rpeli"><div class="rpi"><img src="' . esc_url( plugins_url( 'peli/icon-192.png', dirname( __FILE__ ) . '/suorni-soitin.php' ) ) . '" alt="" width="56" height="56"></div>'
		. '<div class="rpt"><b>🎮 Taajuus – pelaa omaa radioasemaa</b><span>Rento peli: rakenna autotallilähettimestä valtakunnan radio. Voit lisätä pelin puhelimen tai tietokoneen kotinäkymään omaksi sovelluksekseen (selaimen valikosta: &quot;Lisää kotinäyttöön&quot; tai &quot;Asenna&quot;).</span>'
		. '<a class="rpb" href="' . $u . '">Pelaa ja asenna →</a></div></section>'
		. '<style>.sr .rpeli{margin:26px 0 0;display:flex;gap:14px;align-items:center;padding:14px;border:1px solid rgba(255,255,255,.14);border-radius:12px;background:rgba(255,255,255,.04)}.sr .rpi img{display:block;border-radius:12px}.sr .rpt{display:flex;flex-direction:column;gap:6px;min-width:0}.sr .rpt b{font-size:15px}.sr .rpt span{font-size:13px;line-height:1.5;opacity:.85}.sr .rpb{align-self:flex-start;margin-top:4px;padding:8px 14px;border-radius:8px;background:var(--a,#4da3ff);color:#06101f;font-weight:700;text-decoration:none;font-size:14px}@media(max-width:480px){.sr .rpeli{align-items:flex-start}}</style>';
}

/* ---------- Taajuus-pelin rajapinnat ----------
   /peli/now: viimeksi oikeasti kuunneltu kappale (soitin ilmoittaa sen /play-kutsulla, kun kappaletta on kuunneltu 30 s tai puolet).
   /peli/score + /peli/top: pelaajien Top 10 (nimimerkki + kokonaistuotto). Tiedot ovat pelaajan itse ilmoittamia, eivät todennettuja. */
function suorni_peli_name( $n ) {
	$n = sanitize_text_field( (string) $n );
	$n = preg_replace( '/[^\p{L}\p{N} _.\-]/u', '', $n );
	$n = trim( preg_replace( '/\s+/u', ' ', $n ) );
	return function_exists( 'mb_substr' ) ? mb_substr( $n, 0, 16 ) : substr( $n, 0, 16 );
}
function suorni_peli_now() {
	$d = get_transient( 'suorni_now' );
	if ( ! is_array( $d ) || empty( $d['id'] ) ) { return suorni_json( array( 'id' => 0 ) ); }
	return suorni_json( array( 'id' => (int) $d['id'], 'age' => max( 0, time() - (int) $d['t'] ) ) );
}
function suorni_peli_top_list() {
	$l = get_option( 'suorni_peli_top', array() );
	return is_array( $l ) ? $l : array();
}
function suorni_peli_score( $req ) {
	$pid = preg_replace( '/[^a-z0-9]/', '', strtolower( (string) $req->get_param( 'pid' ) ) );
	$pid = substr( $pid, 0, 24 );
	$nm  = suorni_peli_name( $req->get_param( 'name' ) );
	$sc  = (float) $req->get_param( 'score' );
	if ( strlen( $pid ) < 8 || mb_strlen( $nm ) < 2 || $sc < 0 || $sc > 1e18 ) { return new WP_Error( 'suorni_bad', 'Virheellinen', array( 'status' => 400 ) ); }
	$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	$k  = 'suorni_ps_' . md5( $ip . $pid );
	if ( get_transient( $k ) ) { return suorni_json( array( 'ok' => true, 'wait' => true ) ); }
	set_transient( $k, 1, 20 );
	$l = suorni_peli_top_list();
	$old = isset( $l[ $pid ] ) ? $l[ $pid ] : null;
	if ( $old && $sc < (float) $old['s'] ) { $sc = (float) $old['s']; }
	$same = $old && 0 === strcmp( $old['n'], $nm );
	/* uusi tai vaihdettu nimimerkki odottaa ylläpidon hyväksyntää */
	$l[ $pid ] = array( 'n' => $nm, 's' => $sc, 't' => time(), 'ok' => ( $same && ! empty( $old['ok'] ) ) ? 1 : 0, 'x' => ( $same && ! empty( $old['x'] ) ) ? 1 : 0 );
	uasort( $l, function ( $a, $b ) { return $b['s'] <=> $a['s']; } );
	$l = array_slice( $l, 0, 200, true );
	update_option( 'suorni_peli_top', $l, false );
	return suorni_json( array( 'ok' => true ) );
}
function suorni_peli_top( $req ) {
	$pid = preg_replace( '/[^a-z0-9]/', '', strtolower( (string) $req->get_param( 'pid' ) ) );
	$all = suorni_peli_top_list();
	$l = array_filter( $all, function ( $e ) { return ! empty( $e['ok'] ) && empty( $e['x'] ); } );
	uasort( $l, function ( $a, $b ) { return $b['s'] <=> $a['s']; } );
	$top = array(); $me = null; $i = 0;
	foreach ( $l as $k => $e ) {
		$i++;
		if ( $i <= 10 ) { $top[] = array( 'n' => $e['n'], 's' => $e['s'], 'me' => ( $pid && $k === $pid ) ); }
		if ( $pid && $k === $pid ) { $me = array( 'rank' => $i, 's' => $e['s'] ); }
	}
	if ( $pid && ! $me && isset( $all[ $pid ] ) ) {
		$e  = $all[ $pid ];
		$me = array( 'rank' => 0, 's' => $e['s'], 'state' => ! empty( $e['x'] ) ? 'hylatty' : 'odottaa' );
	}
	return suorni_json( array( 'top' => $top, 'me' => $me, 'n' => count( $l ) ) );
}
function suorni_peli_hide( $req ) {
	$nm = suorni_peli_name( $req->get_param( 'name' ) );
	$l  = suorni_peli_top_list(); $n = 0;
	foreach ( $l as $k => $e ) { if ( 0 === strcasecmp( $e['n'], $nm ) ) { $l[ $k ]['x'] = 1; $n++; } }
	update_option( 'suorni_peli_top', $l, false );
	return suorni_json( array( 'ok' => true, 'hidden' => $n ) );
}
add_action( 'rest_api_init', function () {
	register_rest_route( 'suorni/v1', '/peli/now', array( 'methods' => 'GET', 'callback' => 'suorni_peli_now', 'permission_callback' => '__return_true' ) );
	register_rest_route( 'suorni/v1', '/peli/score', array( 'methods' => 'POST', 'callback' => 'suorni_peli_score', 'permission_callback' => '__return_true' ) );
	register_rest_route( 'suorni/v1', '/peli/top', array( 'methods' => 'GET', 'callback' => 'suorni_peli_top', 'permission_callback' => '__return_true' ) );
	register_rest_route( 'suorni/v1', '/peli/hide', array( 'methods' => 'POST', 'callback' => 'suorni_peli_hide', 'permission_callback' => 'suorni_can' ) );
} );

/* Moderointi: Työkalut > Taajuus-nimimerkit (vaatii upload_files-oikeuden) */
add_action( 'admin_menu', function () {
	add_management_page( 'Taajuus-nimimerkit', 'Taajuus-nimimerkit', 'upload_files', 'suorni-peli-nimet', 'suorni_peli_admin_page' );
} );
add_action( 'admin_post_suorni_peli_mod', function () {
	if ( ! current_user_can( 'upload_files' ) ) { wp_die( 'Ei oikeutta' ); }
	check_admin_referer( 'suorni_peli_mod' );
	$pid = isset( $_POST['pid'] ) ? preg_replace( '/[^a-z0-9]/', '', strtolower( wp_unslash( $_POST['pid'] ) ) ) : '';
	$do  = isset( $_POST['do'] ) ? sanitize_key( wp_unslash( $_POST['do'] ) ) : '';
	$l   = suorni_peli_top_list();
	if ( $pid && isset( $l[ $pid ] ) ) {
		if ( 'ok' === $do ) { $l[ $pid ]['ok'] = 1; $l[ $pid ]['x'] = 0; }
		if ( 'no' === $do ) { $l[ $pid ]['ok'] = 0; $l[ $pid ]['x'] = 1; }
		if ( 'del' === $do ) { unset( $l[ $pid ] ); }
		update_option( 'suorni_peli_top', $l, false );
	}
	wp_safe_redirect( admin_url( 'tools.php?page=suorni-peli-nimet' ) );
	exit;
} );
function suorni_peli_admin_page() {
	if ( ! current_user_can( 'upload_files' ) ) { return; }
	$l = suorni_peli_top_list();
	uasort( $l, function ( $a, $b ) { return $b['s'] <=> $a['s']; } );
	$groups = array( 'Odottaa hyväksyntää' => array(), 'Hyväksytyt' => array(), 'Hylätyt' => array() );
	foreach ( $l as $k => $e ) {
		if ( ! empty( $e['x'] ) ) { $groups['Hylätyt'][ $k ] = $e; }
		elseif ( ! empty( $e['ok'] ) ) { $groups['Hyväksytyt'][ $k ] = $e; }
		else { $groups['Odottaa hyväksyntää'][ $k ] = $e; }
	}
	echo '<div class="wrap"><h1>Taajuus-nimimerkit</h1><p>Uudet nimimerkit näkyvät Top 10 -listalla vasta hyväksynnän jälkeen. Pisteet ovat pelaajan itse ilmoittamia.</p>';
	foreach ( $groups as $title => $rows ) {
		echo '<h2>' . esc_html( $title ) . ' (' . count( $rows ) . ')</h2>';
		if ( ! $rows ) { echo '<p>Ei ketään.</p>'; continue; }
		echo '<table class="widefat striped" style="max-width:720px"><thead><tr><th>Nimimerkki</th><th>Pisteet</th><th></th></tr></thead><tbody>';
		foreach ( $rows as $k => $e ) {
			echo '<tr><td>' . esc_html( $e['n'] ) . '</td><td>' . esc_html( number_format_i18n( $e['s'] ) ) . '</td><td>';
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline">';
			wp_nonce_field( 'suorni_peli_mod' );
			echo '<input type="hidden" name="action" value="suorni_peli_mod"><input type="hidden" name="pid" value="' . esc_attr( $k ) . '">';
			if ( 'Hyväksytyt' !== $title ) { echo '<button class="button button-primary" name="do" value="ok">Hyväksy</button> '; }
			if ( 'Hylätyt' !== $title ) { echo '<button class="button" name="do" value="no">Hylkää</button> '; }
			echo '<button class="button-link-delete" name="do" value="del">Poista</button></form></td></tr>';
		}
		echo '</tbody></table>';
	}
	echo '</div>';
}

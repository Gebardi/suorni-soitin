<?php
/* Radion aikajanan tasasuunnittelu: juonto tasatunnilta (tai puolelta), kappaleet sovitetaan päättymään tasalle ja mainokset ajetaan kappaleen jälkeen. */
if ( ! defined( 'ABSPATH' ) ) { exit; }

/* Seuraava "tasa-aika" (aikaleima) tai 0. Tasa = tunti/puoli tuntia (ohjelman asetus) tai ilmoitusta haluavan ohjelman alkamishetki.
   Jos $end on tasan rajalla, palautetaan sitä seuraava raja (nykyisen rajan juonto tehdään heti). */
function suorni_radio_anchor( $cfg, $end ) {
	$e    = (int) $end;
	$prog = suorni_radio_prog_at( $cfg, $e );
	$best = 0;
	if ( 'hour' === $prog['every'] || 'half' === $prog['every'] ) {
		$step = 'hour' === $prog['every'] ? 3600 : 1800;
		$s    = (int) wp_date( 'G', $e ) * 3600 + (int) wp_date( 'i', $e ) * 60 + (int) wp_date( 's', $e );
		$best = $e - ( $s % $step ) + $step;
	}
	$limit = $best ? $best : $e + 3600;
	for ( $t = $e - ( $e % 60 ) + 60; $t <= $limit; $t += 60 ) {
		$np = suorni_radio_prog_at( $cfg, $t );
		if ( $np['id'] !== $prog['id'] ) {
			if ( ! empty( $np['ann'] ) && ( ! $best || $t < $best ) ) { $best = $t; }
			break;
		}
	}
	/* kellonaikaan sidotut omat juonnot */
	$st_ = function_exists( 'suorni_radio_script_next_time' ) ? suorni_radio_script_next_time( $e ) : 0;
	if ( $st_ && ( ! $best || $st_ < $best ) ) { $best = $st_; }
	return $best;
}
function suorni_radio_min_dur( $pool ) {
	$m = 0;
	foreach ( $pool as $t ) { $d = (int) $t['dur']; if ( $d > 0 && ( ! $m || $d < $m ) ) { $m = $d; } }
	return $m;
}
/* Päättyykö kappaleen jälkeen jäljelle jäävä aika $x hyväksyttävästi? (enintään 6 s ylitys, joka häivytetään, tai enintään 1 s vajaa) */
function suorni_radio_fit_end( $x ) { return $x >= -6 && $x <= 1; }
/* good[r] = jäljellä olevat r sekuntia voidaan täyttää kappaleilla niin, että loppu osuu tasalle */
function suorni_radio_fit_table( $cand, $R ) {
	$ds = array();
	foreach ( $cand as $t ) { $d = (int) $t['dur']; if ( $d > 0 ) { $ds[ $d ] = true; } }
	$ds   = array_keys( $ds );
	$good = array();
	for ( $r = 2; $r <= $R; $r++ ) {
		$ok = false;
		foreach ( $ds as $d ) {
			$x = $r - $d;
			if ( suorni_radio_fit_end( $x ) || ( $x > 1 && ! empty( $good[ $x ] ) ) ) { $ok = true; break; }
		}
		$good[ $r ] = $ok;
	}
	return $good;
}
/* Valitsee seuraavan kappaleen niin, että jäljelle jäävä aika on täytettävissä. Palauttaa kappaleen tai null (ei sovitusta). */
function suorni_radio_fit_choose( $cand, $R ) {
	$Ri = (int) round( $R );
	if ( $Ri < 2 || ! $cand ) { return null; }
	$good = suorni_radio_fit_table( $cand, $Ri );
	$ok   = array();
	foreach ( $cand as $t ) {
		$d = (int) $t['dur'];
		if ( $d <= 0 ) { continue; }
		$x = $Ri - $d;
		if ( suorni_radio_fit_end( $x ) || ( $x > 1 && ! empty( $good[ $x ] ) ) ) { $ok[] = $t; }
	}
	return $ok ? $ok[ wp_rand( 0, count( $ok ) - 1 ) ] : null;
}
/* Varavalinta kun sopivaa yhdistelmää ei löydy: pienin ylitys (kappale häivytetään tasalla), muuten pisin kappale */
function suorni_radio_fit_fallback( $cand, $R ) {
	$best = null; $bx = 0; $long = null;
	foreach ( $cand as $t ) {
		$d = (int) $t['dur'];
		if ( $d <= 0 ) { continue; }
		if ( ! $long || $d > (int) $long['dur'] ) { $long = $t; }
		$o = $d - $R;
		if ( $o >= 0 && ( ! $best || $o < $bx ) ) { $best = $t; $bx = $o; }
	}
	return $best ? $best : $long;
}
/* Lyhentää tasalle asetetun kohteen edellä olevia tulevia kappaleita, jos tasa on myöhästynyt (esim. terveisten lisäys siirsi jonoa). */
function suorni_radio_realign( &$items, $now ) {
	$n = count( $items );
	for ( $i = 1; $i < $n; $i++ ) {
		if ( empty( $items[ $i ]['anc'] ) || $items[ $i ]['start'] <= $now ) { continue; }
		$need = $items[ $i ]['start'] - $items[ $i ]['anc'];
		if ( $need <= 0.5 ) { continue; }
		for ( $k = $i - 1; $k >= 0 && $need > 0.01; $k-- ) {
			if ( 'track' !== $items[ $k ]['type'] || $items[ $k ]['start'] <= $now + 2 ) { break; }
			$can = $items[ $k ]['dur'] - 10;
			if ( $can <= 0 ) { continue; }
			$cut = min( $need, $can );
			if ( empty( $items[ $k ]['full'] ) ) { $items[ $k ]['full'] = $items[ $k ]['dur']; }
			$items[ $k ]['dur'] -= $cut;
			$items[ $k ]['cut']  = 1;
			$items[ $k ]['fade'] = max( 3, (int) ( isset( $items[ $k ]['fade'] ) ? $items[ $k ]['fade'] : 0 ) );
			$need -= $cut;
			for ( $j = $k + 1; $j < $n; $j++ ) { $items[ $j ]['start'] -= $cut; }
		}
	}
}
/* Täyttää aukon ennen tasalle asetettua kohdetta kappaleilla (käytetään kun jonosta poistettu kohde olisi siirtänyt tasaa aikaisemmaksi). */
function suorni_radio_fill_before( &$log, $i, $cfg ) {
	$it  = $log['items'][ $i ];
	$anc = (int) $it['anc'];
	$prev = $i > 0 ? $log['items'][ $i - 1 ] : null;
	if ( ! $prev ) { return; }
	$end = $prev['start'] + $prev['dur'];
	$R   = $anc - $end;
	$prog = suorni_radio_prog_at( $cfg, (int) $end );
	$pool = suorni_radio_pool( $prog['list'], $prog['ids'], $prog['id'] );
	/* Alle 25 s aukkoa ei täytetä kappaleen pätkällä: juonto jää hieman etuajassa */
	if ( ! $pool || $R < min( 25, suorni_radio_min_dur( $pool ) - 6 ) ) { return; }
	$st   = $log['st'];
	$new  = array();
	$g    = 0;
	while ( $R > 1 && $g++ < 8 ) {
		$t = suorni_radio_pick_track( $st, $pool, array( 'R' => $R ) );
		$t['start'] = $end;
		$t['prog']  = $prog['name'];
		if ( $end + $t['dur'] > $anc + 0.5 ) {
			$o = $end + $t['dur'] - $anc;
			$t['full'] = $t['dur']; $t['dur'] = round( $anc - $end, 3 ); $t['cut'] = 1; $t['fade'] = $o <= 4 ? 3 : 6;
			if ( $t['dur'] < 9 ) { $t['fade'] = max( 1, (int) floor( $t['dur'] / 3 ) ); }
		}
		$new[] = $t;
		$end  += $t['dur'];
		$R     = $anc - $end;
	}
	$log['st'] = $st;
	if ( ! $new ) { return; }
	/* alle sekunnin aukko tasalle: jatketaan viimeistä kappaletta tasalle */
	$ln = count( $new ) - 1;
	if ( $R > 0 && $R <= 1 ) { $new[ $ln ]['dur'] += $R; }
	$delta = $anc - $log['items'][ $i ]['start'];
	array_splice( $log['items'], $i, 0, $new );
	for ( $j = $i + count( $new ); $j < count( $log['items'] ); $j++ ) { $log['items'][ $j ]['start'] += $delta; }
}
/* Mainos kappaleen jälkeen (jos radion mainokset on otettu käyttöön). Palauttaa aikajanan kohteen tai null. */
function suorni_radio_ad_item( $cfg, &$st, $items, $end, $anc ) {
	if ( empty( $cfg['radads'] ) ) { return null; }
	$ads = suorni_ads();
	if ( ! $ads['on'] ) { return null; }
	$last = $items ? $items[ count( $items ) - 1 ] : null;
	if ( ! $last || 'track' !== $last['type'] ) { return null; }
	$list = suorni_ads_for( 'r' );
	if ( ! $list ) { return null; }
	if ( empty( $st['adat'] ) ) { $st['adat'] = wp_rand( $ads['rmin'], max( $ads['rmin'], $ads['rmax'] ) ); }
	if ( (int) $st['adn'] < (int) $st['adat'] ) { return null; }
	$t = $list[ (int) $st['adi'] % count( $list ) ];
	/* Ei ahtauteta mainosta aivan tasan alle: sen jälkeen pitää jäädä tilaa kappaleelle */
	if ( $anc && $anc - $end < (int) $t['dur'] + 150 ) { return null; }
	$st['adi']  = (int) $st['adi'] + 1;
	$st['adn']  = 0;
	$st['adat'] = wp_rand( $ads['rmin'], max( $ads['rmin'], $ads['rmax'] ) );
	return array( 'type' => 'ad', 'id' => (int) $t['id'], 'url' => $t['url'], 'dur' => (int) $t['dur'], 'title' => $t['title'], 'artist' => '' );
}

/* Mainosgeneraattori: teksti -> Azure-puhe -> mediakirjaston äänitiedosto, joka merkitään mainokseksi. */
function suorni_radio_adgen_post( $req ) {
	$text = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( (string) $req->get_param( 'text' ) ) ) );
	if ( '' === $text || mb_strlen( $text ) > 400 ) { return new WP_Error( 'suorni_bad', 'Kirjoita mainosteksti (enintään 400 merkkiä).', array( 'status' => 400 ) ); }
	$title = mb_substr( sanitize_text_field( (string) $req->get_param( 'title' ) ), 0, 80 );
	if ( '' === $title ) { $title = 'Mainos: ' . mb_substr( $text, 0, 40 ); }
	$tg    = (string) $req->get_param( 'tg' );
	if ( ! in_array( $tg, array( 's', 'r', 'b' ), true ) ) { $tg = 'r'; }
	$voice = trim( (string) $req->get_param( 'voice' ) );
	$seg   = array( 't' => $text, 'l' => 'fi' );
	if ( '' !== $voice && preg_match( '/^[A-Za-z]{2,3}-[A-Za-z]{2,4}-[A-Za-z0-9]+$/', $voice ) ) { $seg['v'] = $voice; }
	$r = suorni_tts_synth( array( $seg ), 1.0, array() );
	if ( is_wp_error( $r ) ) { return $r; }
	$up  = wp_upload_dir();
	$src = trailingslashit( $up['basedir'] ) . 'suorni-tts/' . basename( $r['url'] );
	if ( ! file_exists( $src ) ) { return new WP_Error( 'suorni_tts', 'Puhetiedostoa ei löytynyt.', array( 'status' => 500 ) ); }
	$dir = trailingslashit( $up['basedir'] ) . 'suorni-ads';
	if ( ! wp_mkdir_p( $dir ) ) { return new WP_Error( 'suorni_tts', 'Tallennuskansiota ei voitu luoda.', array( 'status' => 500 ) ); }
	$name = 'mainos-' . substr( md5( $text . $voice . microtime() ), 0, 10 ) . '.mp3';
	$dest = $dir . '/' . $name;
	if ( ! copy( $src, $dest ) ) { return new WP_Error( 'suorni_tts', 'Tiedoston tallennus epäonnistui.', array( 'status' => 500 ) ); }
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';
	$id = wp_insert_attachment( array( 'post_mime_type' => 'audio/mpeg', 'post_title' => $title, 'post_excerpt' => 'Mainos', 'post_content' => '', 'post_status' => 'inherit' ), $dest );
	if ( is_wp_error( $id ) || ! $id ) { return new WP_Error( 'suorni_tts', 'Mediakirjastoon lisäys epäonnistui.', array( 'status' => 500 ) ); }
	$m = wp_generate_attachment_metadata( $id, $dest );
	if ( ! is_array( $m ) ) { $m = array(); }
	if ( empty( $m['length'] ) ) { $m['length'] = max( 2, (int) ceil( filesize( $dest ) / 12000 ) ); }
	wp_update_attachment_metadata( $id, $m );
	$a   = suorni_ads();
	$ids = $a['ids'];
	if ( ! in_array( (int) $id, $ids, true ) ) { $ids[] = (int) $id; }
	$a['tg'][ (int) $id ] = $tg;
	update_option( 'suorni_ads', array( 'on' => $a['on'], 'min' => $a['min'], 'max' => $a['max'], 'rmin' => $a['rmin'], 'rmax' => $a['rmax'], 'tg' => $a['tg'], 'ids' => $ids ), false );
	suorni_clear_tracks();
	return suorni_json( array( 'ok' => true, 'id' => (int) $id, 'title' => $title, 'dur' => (int) $m['length'], 'tg' => $tg ) );
}
add_action( 'rest_api_init', function () {
	register_rest_route( 'suorni/v1', '/radio/adgen', array( 'methods' => 'POST', 'callback' => 'suorni_radio_adgen_post', 'permission_callback' => 'suorni_can' ) );
} );

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
	$list = array();
	foreach ( suorni_tracks() as $t ) { if ( ! empty( $t['ad'] ) && empty( $t['ann'] ) && $t['dur'] > 0 ) { $list[] = $t; } }
	if ( ! $list ) { return null; }
	if ( empty( $st['adat'] ) ) { $st['adat'] = wp_rand( $ads['min'], max( $ads['min'], $ads['max'] ) ); }
	if ( (int) $st['adn'] < (int) $st['adat'] ) { return null; }
	$t = $list[ (int) $st['adi'] % count( $list ) ];
	/* Ei ahtauteta mainosta aivan tasan alle: sen jälkeen pitää jäädä tilaa kappaleelle */
	if ( $anc && $anc - $end < (int) $t['dur'] + 150 ) { return null; }
	$st['adi']  = (int) $st['adi'] + 1;
	$st['adn']  = 0;
	$st['adat'] = wp_rand( $ads['min'], max( $ads['min'], $ads['max'] ) );
	return array( 'type' => 'ad', 'id' => (int) $t['id'], 'url' => $t['url'], 'dur' => (int) $t['dur'], 'title' => $t['title'], 'artist' => '' );
}

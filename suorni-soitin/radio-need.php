<?php
/* Kappaletarve: laskee oikeasta kappalelistasta, missä ohjelmissa ja kanavilla kappaleita puuttuu, ja kirjoittaa kopioitavan tilannekatsauksen biisien tekoon. */
if ( ! defined( 'ABSPATH' ) ) { exit; }

/* Tavoite: näin monta eri kappaletta ohjelmaa/kanavaa kohti (noin 3 tuntia musiikkia ennen toistoa) */
function suorni_need_goal() { return 45; }

function suorni_need_status( $n ) {
	if ( $n < 10 ) { return 'bad'; }
	if ( $n < 25 ) { return 'low'; }
	if ( $n < suorni_need_goal() ) { return 'ok'; }
	return 'good';
}
/* Mitä ohjelmaan sopivia kappaleita kannattaa tehdä */
function suorni_need_prog_hint( $p ) {
	$k = mb_strtolower( $p['id'] . ' ' . $p['name'] );
	if ( preg_match( '/aamu|aino|morn/u', $k ) ) { return 'pirteitä, reipastempoisia aamubiisejä (kahvi, herääminen, työmatka, hyvä mieli)'; }
	if ( preg_match( '/yö|yo\b|unto|night/u', $k ) ) { return 'rauhallisia, hiljaisia yöbiisejä (hidas tempo, pehmeä laulu, ajomatka yöllä, kaipaus)'; }
	if ( preg_match( '/ilta|eino|even/u', $k ) ) { return 'lämpimiä, rauhallisia iltabiisejä (nostalgia, sauna, koti, ihmissuhteet)'; }
	if ( preg_match( '/päivä|paiva|sanni|day/u', $k ) ) { return 'rentoja, keskitempoisia päiväbiisejä (arki, työ, kesäpäivä, ajomatka)'; }
	return 'ohjelmaan sopivia, vaihtelevia kappaleita';
}
function suorni_need_chan_hint( $name ) {
	$map = array(
		'metal' => array( 'metal', 'rock', 'raskas', 'hevi' ), 'party' => array( 'party', 'juhl', 'bile', 'dance', 'tanss', 'disco' ),
		'iskelma' => array( 'iskelm', 'suomi', 'tango', 'schlager' ), 'mies' => array( 'mies', 'sauna', 'garage', 'autotalli' ), 'calm' => array( 'rauhall', 'chill', 'lounge', 'rento' ),
	);
	$txt = array(
		'metal' => 'raskasta rockia ja metallia: särökitarat, kovat rummut, rosoinen laulu', 'party' => 'tanssittavaa bilemusiikkia: nopea tempo, isot kertosäkeet, yhteislaulettavaa',
		'iskelma' => 'suomalaista iskelmää: lämmin laulu, tarttuva melodia, tarinallinen sanoitus', 'mies' => 'miesten illan kappaleita: rouheaa rockia, kapakkalauluja, autoja, sauna ja urheilu',
		'calm' => 'rauhallisia, rentouttavia kappaleita',
	);
	$n = mb_strtolower( $name );
	foreach ( $map as $k => $words ) { foreach ( $words as $w ) { if ( false !== mb_strpos( $n, $w ) ) { return $txt[ $k ]; } } }
	return 'kanavan nimeen ja tunnelmaan sopivia kappaleita';
}
function suorni_need_season_hint() {
	$m = (int) wp_date( 'n' );
	$h = array(
		1 => 'tammikuu: pakkaset, uusi alku, hiihto', 2 => 'helmikuu: talvilomat, laskiainen, ystävänpäivä', 3 => 'maaliskuu: kevätaurinko, naistenpäivä', 4 => 'huhtikuu: kevät, vappu tulossa', 5 => 'toukokuu: vappu, kevään valo, ylioppilaat',
		6 => 'kesäkuu: juhannus, mökki, kesäyöt', 7 => 'heinäkuu: loma, järvet, festarit', 8 => 'elokuu: kesän loppu, ruska tulossa', 9 => 'syyskuu: ruska, koulun alku', 10 => 'lokakuu: pimenevät illat, halloween',
		11 => 'marraskuu: pimeä syysilta, ensilumi, itsenäisyyspäivä 6.12.', 12 => 'joulukuu: itsenäisyyspäivä, pikkujoulut, vuoden vaihde',
	);
	return $h[ $m ];
}
function suorni_need_report() {
	$goal = suorni_need_goal();
	$all  = suorni_view( suorni_tracks(), '' );
	$excl = suorni_radio_excl();
	$play = array();
	$nodur = 0;
	foreach ( $all as $t ) {
		if ( $t['dur'] <= 0 ) { $nodur++; continue; }
		if ( in_array( (int) $t['id'], $excl, true ) ) { continue; }
		$play[] = $t;
	}
	$tot = 0; $short = 0; $long = 0; $nostyle = 0; $styles = array();
	foreach ( $play as $t ) {
		$tot += (int) $t['dur'];
		if ( $t['dur'] < 120 ) { $short++; }
		if ( $t['dur'] > 330 ) { $long++; }
		$s = trim( (string) ( isset( $t['style'] ) ? $t['style'] : '' ) );
		if ( '' === $s ) { $s = trim( (string) $t['genre'] ); }
		if ( '' === $s ) { $nostyle++; continue; }
		$s = mb_substr( mb_strtolower( $s ), 0, 40 );
		$styles[ $s ] = isset( $styles[ $s ] ) ? $styles[ $s ] + 1 : 1;
	}
	arsort( $styles );
	$stl = array();
	foreach ( array_slice( $styles, 0, 12, true ) as $k => $v ) { $stl[] = array( 'name' => $k, 'n' => $v ); }
	$np  = count( $play );
	$avg = $np ? $tot / $np : 0;

	$progs = array();
	foreach ( suorni_radio_cfg()['programs'] as $p ) {
		$n = 0; $only = 0; $sec = 0;
		foreach ( $play as $t ) {
			if ( ! suorni_track_allowed( $t, $p['id'] ) ) { continue; }
			$n++; $sec += (int) $t['dur'];
			if ( ! empty( $t['progs'] ) && is_array( $t['progs'] ) ) { $only++; }
		}
		$progs[] = array( 'id' => $p['id'], 'name' => $p['name'], 'from' => $p['from'], 'to' => $p['to'], 'n' => $n, 'only' => $only, 'hours' => round( $sec / 3600, 1 ), 'st' => suorni_need_status( $n ), 'need' => max( 0, $goal - $n ), 'hint' => suorni_need_prog_hint( $p ) );
	}
	$chans = array();
	$inch  = array();
	foreach ( suorni_ch_all() as $c ) {
		$ts = suorni_ch_tracks( $c['id'] );
		$sec = 0;
		foreach ( $ts as $t ) { $sec += (int) $t['dur']; $inch[ (int) $t['id'] ] = 1; }
		$n = count( $ts );
		$chans[] = array( 'id' => $c['id'], 'name' => $c['name'], 'icon' => $c['icon'], 'on' => $c['on'], 'n' => $n, 'hours' => round( $sec / 3600, 1 ), 'pub' => $c['on'] && $n >= 3, 'st' => suorni_need_status( $n ), 'need' => max( 0, $goal - $n ), 'hint' => suorni_need_chan_hint( $c['name'] ) );
	}
	$uncl = 0;
	if ( $chans ) { foreach ( $play as $t ) { if ( empty( $inch[ (int) $t['id'] ] ) ) { $uncl++; } } }

	/* Kopioitava teksti */
	$L   = array();
	$L[] = '# Radio Suorni – kappaletarve ' . wp_date( 'j.n.Y' );
	$L[] = '';
	$L[] = 'Radiossa on nyt ' . $np . ' soitettavaa kappaletta (yhteensä ' . round( $tot / 3600, 1 ) . ' h, keskipituus ' . ( $np ? round( $avg / 60, 1 ) : 0 ) . ' min). Tavoite on vähintään ' . $goal . ' kappaletta jokaista ohjelmaa ja kanavaa kohti, jotta sama kappale ei toistu liian usein.';
	$L[] = '';
	$L[] = '## Ohjelmat';
	foreach ( $progs as $p ) {
		$L[] = '- ' . $p['name'] . ' (klo ' . $p['from'] . '–' . $p['to'] . '): ' . $p['n'] . ' kpl, ' . $p['hours'] . ' h' . ( $p['need'] > 0 ? ' → tarvitaan noin +' . $p['need'] . ': ' . $p['hint'] : ' → riittävästi' );
	}
	if ( $chans ) {
		$L[] = '';
		$L[] = '## Kanavat';
		foreach ( $chans as $c ) {
			$L[] = '- ' . trim( $c['icon'] . ' ' . $c['name'] ) . ': ' . $c['n'] . ' kpl, ' . $c['hours'] . ' h' . ( $c['pub'] ? '' : ' (EI vielä näy kuuntelijoille, tarvitaan vähintään 3)' ) . ( $c['need'] > 0 ? ' → tarvitaan noin +' . $c['need'] . ': ' . $c['hint'] : ' → riittävästi' );
		}
	}
	if ( $stl ) {
		$L[] = '';
		$L[] = '## Tyylit, joita on jo paljon';
		$parts = array();
		foreach ( array_slice( $stl, 0, 8 ) as $s ) { $parts[] = $s['name'] . ' (' . $s['n'] . ')'; }
		$L[] = implode( ', ', $parts ) . '. Tee mieluummin jotain muuta kuin näitä.';
	}
	$L[] = '';
	$L[] = '## Muuta huomioitavaa';
	$L[] = '- Nyt ajankohtaista: ' . suorni_need_season_hint() . '.';
	if ( $short ) { $L[] = '- ' . $short . ' kappaletta on alle 2 min. Tee 2,5–4 minuutin kappaleita.'; }
	if ( $long ) { $L[] = '- ' . $long . ' kappaletta on yli 5,5 min. Vältä pitkiä kappaleita.'; }
	if ( $uncl ) { $L[] = '- ' . $uncl . ' kappaletta ei kuulu millekään omalle kanavalle (soivat vain Kaikki-kanavalla). Luokittele ne ohjaamossa.'; }
	if ( $nostyle ) { $L[] = '- ' . $nostyle . ' kappaleella ei ole tyyli- tai genretietoa.'; }
	return array(
		'total' => count( $all ), 'playable' => $np, 'hours' => round( $tot / 3600, 1 ), 'avg' => (int) round( $avg ), 'goal' => $goal, 'nodur' => $nodur,
		'short' => $short, 'long' => $long, 'nostyle' => $nostyle, 'uncl' => $uncl, 'styles' => $stl, 'progs' => $progs, 'chans' => $chans, 'season' => suorni_need_season_hint(), 'text' => implode( "\n", $L ),
	);
}

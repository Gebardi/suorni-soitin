<?php
/* Asemasivun rakenne soittimen ympärille: valikko, ohjelmaopas, juontajakortit ja alatunniste (9.64) */
if ( ! defined( 'ABSPATH' ) ) { exit; }

/* "ma–pe", "joka päivä", "ma, ke, la" */
function suorni_station_days( $days ) {
	$n = array( 1 => 'ma', 2 => 'ti', 3 => 'ke', 4 => 'to', 5 => 'pe', 6 => 'la', 7 => 'su' );
	$d = array_values( array_unique( array_map( 'intval', (array) $days ) ) );
	sort( $d );
	if ( count( $d ) >= 7 ) { return 'joka päivä'; }
	if ( ! $d ) { return ''; }
	$groups = array();
	$start  = $d[0];
	$prev   = $d[0];
	for ( $i = 1; $i <= count( $d ); $i++ ) {
		if ( $i < count( $d ) && $d[ $i ] === $prev + 1 ) { $prev = $d[ $i ]; continue; }
		$groups[] = ( $prev - $start >= 2 ) ? $n[ $start ] . '–' . $n[ $prev ] : implode( ', ', array_map( function ( $x ) use ( $n ) { return $n[ $x ]; }, range( $start, $prev ) ) );
		if ( $i < count( $d ) ) { $start = $d[ $i ]; $prev = $d[ $i ]; }
	}
	return implode( ', ', $groups );
}
function suorni_station_time( $t ) {
	return ( '24:00' === $t ) ? '00:00' : (string) $t;
}
/* Ohjelmat aloitusajan mukaan */
function suorni_station_progs() {
	$cfg = suorni_radio_cfg();
	$p   = $cfg['programs'];
	usort( $p, function ( $a, $b ) { return strcmp( $a['from'], $b['from'] ); } );
	return $p;
}
/* Lisäosan mukana tulevat oletuskuvakkeet (Aino, Sanni, Eino, Unto): ensin juontajan nimen, sitten ohjelman tunnuksen mukaan */
function suorni_station_default_art( $p ) {
	$map  = array( 'aino' => 'aino', 'sanni' => 'sanni', 'eino' => 'eino', 'unto' => 'unto', 'aamu' => 'aino', 'paiva' => 'sanni', 'ilta' => 'eino', 'yo' => 'unto' );
	$keys = array( sanitize_key( suorni_station_plain( (string) $p['host'] ) ), sanitize_key( (string) $p['id'] ) );
	foreach ( $keys as $k ) {
		if ( '' !== $k && isset( $map[ $k ] ) ) { return plugins_url( 'pwa/hosts/' . $map[ $k ] . '.svg', __FILE__ ); }
	}
	return '';
}
function suorni_station_plain( $s ) {
	return function_exists( 'remove_accents' ) ? remove_accents( $s ) : $s;
}
/* Kuva tai värikortti, jossa nimikirjain. Rikkinäinen kuva poistuu ja kortti jää. */
function suorni_station_avatar( $p, $cls = 'gav' ) {
	$name = '' !== $p['host'] ? $p['host'] : $p['name'];
	$h    = abs( crc32( (string) $p['id'] ) ) % 360;
	$ini  = mb_strtoupper( mb_substr( $name, 0, 1 ) );
	$out  = '<span class="' . esc_attr( $cls ) . '" style="--h:' . (int) $h . '" aria-hidden="true"><b>' . esc_html( $ini ) . '</b>';
	if ( '' !== $p['img'] ) { $out .= '<img src="' . esc_url( $p['img'] ) . '" alt="" loading="lazy" referrerpolicy="no-referrer" onerror="this.remove()">'; }
	else {
		$d = suorni_station_default_art( $p );
		if ( '' !== $d ) { $out .= '<img class="gdf" src="' . esc_url( $d ) . '" alt="" loading="lazy" onerror="this.remove()">'; }
	}
	return $out . '</span>';
}
function suorni_station_nav( $cfg ) {
	$h  = '<nav class="rnav" aria-label="Sivun osiot">';
	$h .= '<a href="#sr-kuuntele">📻 Kuuntele</a>';
	$h .= '<a href="#sr-kuuntele" data-navtop>🏆 Top 10</a>';
	$h .= '<a href="#sr-ohjelmat">🗓️ Ohjelmat</a>';
	if ( suorni_station_has_hosts() ) { $h .= '<a href="#sr-juontajat">🎙️ Juontajat</a>'; }
	if ( ! empty( $cfg['chat'] ) ) { $h .= '<a href="#sr-chat">💬 Chat</a>'; }
	$h .= '<a href="#sr-yhteys">✉️ Yhteystiedot</a>';
	return $h . '</nav>';
}
function suorni_station_has_hosts() {
	foreach ( suorni_station_progs() as $p ) {
		if ( '' !== $p['host'] || '' !== $p['img'] ) { return true; }
	}
	return false;
}
/* Ohjelmaopas */
function suorni_station_guide() {
	$h  = '<section class="rsec" id="sr-ohjelmat" aria-labelledby="sr-oh"><h3 id="sr-oh">🗓️ Ohjelmaopas</h3><ul class="rgd">';
	foreach ( suorni_station_progs() as $p ) {
		$who = '' !== $p['host'] ? ' <span class="gwho">· ' . esc_html( $p['host'] ) . '</span>' : '';
		$h  .= '<li class="gp" data-gp data-from="' . esc_attr( $p['from'] ) . '" data-to="' . esc_attr( $p['to'] ) . '" data-days="' . esc_attr( implode( ',', $p['days'] ) ) . '">';
		$h  .= '<span class="gt">' . esc_html( suorni_station_time( $p['from'] ) . '–' . suorni_station_time( $p['to'] ) ) . '</span>';
		$h  .= '<span class="gn"><b>' . esc_html( $p['name'] ) . '</b>' . $who . '<small>' . esc_html( suorni_station_days( $p['days'] ) ) . '</small></span>';
		$h  .= '<span class="gnow" hidden>● Nyt</span></li>';
	}
	return $h . '</ul></section>';
}
/* Juontajakortit */
function suorni_station_hosts() {
	if ( ! suorni_station_has_hosts() ) { return ''; }
	$h = '<section class="rsec" id="sr-juontajat" aria-labelledby="sr-ju"><h3 id="sr-ju">🎙️ Juontajat</h3><div class="rhs">';
	foreach ( suorni_station_progs() as $p ) {
		if ( '' === $p['host'] && '' === $p['img'] ) { continue; }
		$h .= '<article class="hc" data-gp data-from="' . esc_attr( $p['from'] ) . '" data-to="' . esc_attr( $p['to'] ) . '" data-days="' . esc_attr( implode( ',', $p['days'] ) ) . '">';
		$h .= suorni_station_avatar( $p );
		$h .= '<div class="hb"><h4>' . esc_html( '' !== $p['host'] ? $p['host'] : $p['name'] ) . ' <span class="gnow" hidden>● Nyt</span></h4>';
		$h .= '<p class="hp">' . esc_html( $p['name'] ) . ' · klo ' . esc_html( suorni_station_time( $p['from'] ) . '–' . suorni_station_time( $p['to'] ) ) . ' · ' . esc_html( suorni_station_days( $p['days'] ) ) . '</p>';
		if ( '' !== $p['blurb'] ) { $h .= '<p class="hd">' . esc_html( $p['blurb'] ) . '</p>'; }
		$h .= '</div></article>';
	}
	return $h . '</div></section>';
}
/* Alatunniste */
function suorni_station_footer( $cfg ) {
	$s  = suorni_social_cfg();
	$h  = '<footer class="rft" id="sr-yhteys"><div class="rfa"><h3>' . esc_html( $cfg['name'] ) . '</h3><p>' . esc_html( $s['about'] ) . '</p></div>';
	$h .= '<div class="rfb"><h4>Yhteystiedot</h4><ul>';
	if ( '' !== $s['contact'] ) { $h .= '<li><a href="mailto:' . esc_attr( $s['contact'] ) . '">✉️ ' . esc_html( $s['contact'] ) . '</a></li>'; }
	if ( '' !== $s['ig'] ) { $h .= '<li><a href="' . esc_url( $s['ig'] ) . '" target="_blank" rel="noopener">📸 Instagram</a></li>'; }
	if ( '' !== $s['tt'] ) { $h .= '<li><a href="' . esc_url( $s['tt'] ) . '" target="_blank" rel="noopener">🎵 TikTok</a></li>'; }
	if ( $s['promo_on'] && '' !== $s['promo_url'] ) { $h .= '<li><a href="' . esc_url( $s['promo_url'], array( 'http', 'https', 'mailto' ) ) . '">📢 Mainosta radiossa</a></li>'; }
	$pp = function_exists( 'get_privacy_policy_url' ) ? get_privacy_policy_url() : '';
	if ( '' !== $pp ) { $h .= '<li><a href="' . esc_url( $pp ) . '">🔒 Tietosuoja</a></li>'; }
	$h .= '</ul></div><div class="rfc">🇫🇮 ' . esc_html( $cfg['name'] ) . ' – musiikkia Suomesta, Suomen yössä<br><small>© ' . esc_html( wp_date( 'Y' ) ) . ' ' . esc_html( $cfg['name'] ) . '</small></div></footer>';
	return $h;
}

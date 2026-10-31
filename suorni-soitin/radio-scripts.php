<?php
/* Omat käsikirjoitetut juonnot: juontaja puhuu kirjoitetun tekstin sanasta sanaan.
   Ajoitus: 'next' = seuraavassa juontotauossa (kerran), 'rot' = kierrossa joka n:nnessä juontotauossa, 'time' = päivittäin kellonaikaan (tasalta), lisäksi "soita heti". */
if ( ! defined( 'ABSPATH' ) ) { exit; }

function suorni_script_norm( $s ) {
	$days = ( isset( $s['days'] ) && is_array( $s['days'] ) ) ? array_values( array_filter( array_unique( array_map( 'intval', $s['days'] ) ), function ( $d ) { return $d >= 1 && $d <= 7; } ) ) : array( 1, 2, 3, 4, 5, 6, 7 );
	if ( ! $days ) { $days = array( 1, 2, 3, 4, 5, 6, 7 ); }
	$time = ( isset( $s['time'] ) && preg_match( '/^([01]?\d|2[0-3]):([0-5]\d)$/', (string) $s['time'], $m ) ) ? sprintf( '%02d:%s', (int) $m[1], $m[2] ) : '12:00';
	$text = trim( preg_replace( '/[ \t]+/u', ' ', wp_strip_all_tags( isset( $s['text'] ) ? (string) $s['text'] : '' ) ) );
	return array(
		'id'      => isset( $s['id'] ) ? sanitize_key( $s['id'] ) : '',
		'title'   => mb_substr( sanitize_text_field( isset( $s['title'] ) ? (string) $s['title'] : '' ), 0, 60 ),
		'text'    => mb_substr( $text, 0, 1200 ),
		'mode'    => ( isset( $s['mode'] ) && in_array( $s['mode'], array( 'next', 'rot', 'time' ), true ) ) ? $s['mode'] : 'next',
		'time'    => $time,
		'days'    => $days,
		'n'       => isset( $s['n'] ) ? max( 1, min( 10, (int) $s['n'] ) ) : 3,
		'prog'    => isset( $s['prog'] ) ? sanitize_key( $s['prog'] ) : '',
		'on'      => ! isset( $s['on'] ) || ! empty( $s['on'] ),
		'replace' => ! empty( $s['replace'] ),
		'done'    => isset( $s['done'] ) ? (int) $s['done'] : 0,
		'last'    => isset( $s['last'] ) ? (int) $s['last'] : 0,
		'lastday' => isset( $s['lastday'] ) ? preg_replace( '/[^0-9]/', '', (string) $s['lastday'] ) : '',
	);
}
function suorni_scripts() {
	$l = get_option( 'suorni_scripts', array() );
	if ( ! is_array( $l ) ) { return array(); }
	$out = array();
	foreach ( $l as $s ) { if ( is_array( $s ) && ! empty( $s['id'] ) ) { $out[] = suorni_script_norm( $s ); } }
	return $out;
}
function suorni_scripts_save( $req ) {
	$in = $req->get_param( 'scripts' );
	if ( ! is_array( $in ) ) { $in = array(); }
	$old = array();
	foreach ( suorni_scripts() as $s ) { $old[ $s['id'] ] = $s; }
	$out = array();
	foreach ( array_slice( $in, 0, 40 ) as $x ) {
		if ( ! is_array( $x ) ) { continue; }
		$id = isset( $x['id'] ) ? sanitize_key( $x['id'] ) : '';
		if ( '' === $id ) { $id = strtolower( wp_generate_password( 8, false ) ); }
		$o = isset( $old[ $id ] ) ? $old[ $id ] : array( 'done' => 0, 'last' => 0, 'lastday' => '' );
		/* Toistotila nollautuu vain, kun käyttäjä pyytää sitä (done = 0) */
		$reset = isset( $x['done'] ) && ! $x['done'];
		$x['done'] = $reset ? 0 : $o['done'];
		$x['last'] = $o['last'];
		$x['lastday'] = $o['lastday'];
		if ( $reset ) { $x['lastday'] = ''; $mm = get_option( 'suorni_scr_ld', array() ); if ( is_array( $mm ) ) { foreach ( array_keys( $mm ) as $kk ) { if ( 0 === strpos( $kk, $id . '|' ) ) { unset( $mm[ $kk ] ); } } update_option( 'suorni_scr_ld', $mm, false ); } }
		$s = suorni_script_norm( array_merge( $x, array( 'id' => $id ) ) );
		if ( '' === $s['text'] ) { continue; }
		$out[] = $s;
	}
	update_option( 'suorni_scripts', $out, false );
	return suorni_json( array( 'ok' => true, 'scripts' => $out ) );
}
/* Kellonaikajuonto soitetaan jokaisella kanavalla erikseen: viimeisin soittopäivä kanavittain */
function suorni_script_ld( $s ) {
	$c = suorni_ch_cur();
	if ( '' === $c ) { return $s['lastday']; }
	$m = get_option( 'suorni_scr_ld', array() );
	return ( is_array( $m ) && isset( $m[ $s['id'] . '|' . $c ] ) ) ? (string) $m[ $s['id'] . '|' . $c ] : '';
}
function suorni_script_mark( $id, $ts = 0 ) {
	$c = suorni_ch_cur();
	if ( '' !== $c ) {
		$m = get_option( 'suorni_scr_ld', array() );
		if ( ! is_array( $m ) ) { $m = array(); }
		foreach ( suorni_scripts() as $s ) {
			if ( $s['id'] === $id && 'time' === $s['mode'] ) { $m[ $id . '|' . $c ] = wp_date( 'Ymd', $ts ? $ts : time() ); update_option( 'suorni_scr_ld', $m, false ); }
			if ( $s['id'] === $id && 'next' === $s['mode'] ) { $l = suorni_scripts(); foreach ( $l as $i => $z ) { if ( $z['id'] === $id ) { $l[ $i ]['done'] = time(); $l[ $i ]['last'] = time(); } } update_option( 'suorni_scripts', $l, false ); }
			if ( $s['id'] === $id && 'rot' === $s['mode'] ) { $l = suorni_scripts(); foreach ( $l as $i => $z ) { if ( $z['id'] === $id ) { $l[ $i ]['last'] = time(); } } update_option( 'suorni_scripts', $l, false ); }
		}
		return;
	}
	$l = suorni_scripts();
	foreach ( $l as $i => $s ) {
		if ( $s['id'] !== $id ) { continue; }
		$l[ $i ]['last'] = time();
		if ( 'next' === $s['mode'] ) { $l[ $i ]['done'] = time(); }
		if ( 'time' === $s['mode'] ) { $l[ $i ]['lastday'] = wp_date( 'Ymd', $ts ? $ts : time() ); }
	}
	update_option( 'suorni_scripts', $l, false );
}
/* {kello} ja {paiva} korvataan puheeseen */
function suorni_script_text( $s, $ts ) {
	$days = array( 1 => 'maanantai', 2 => 'tiistai', 3 => 'keskiviikko', 4 => 'torstai', 5 => 'perjantai', 6 => 'lauantai', 7 => 'sunnuntai' );
	$t    = $s['text'];
	if ( false !== strpos( $t, '{kello}' ) && function_exists( 'suorni_fin_clock' ) ) { $t = str_replace( '{kello}', suorni_fin_clock( (int) wp_date( 'G', $ts ), (int) wp_date( 'i', $ts ) ), $t ); }
	$t = str_replace( '{paiva}', $days[ (int) wp_date( 'N', $ts ) ], $t );
	return $t;
}
function suorni_script_prog( $cfg, $s, $fallback ) {
	if ( '' !== $s['prog'] ) { foreach ( $cfg['programs'] as $p ) { if ( $p['id'] === $s['prog'] ) { return $p; } } }
	return $fallback;
}
/* Käsikirjoitettu teksti -> äänipätkät, mukaan juontotaukoon (seuraava / kierto) */
function suorni_radio_scripts_for_break( $prog, &$st ) {
	$use = array();
	$rot = array();
	foreach ( suorni_scripts() as $s ) {
		if ( ! $s['on'] || ( '' !== $s['prog'] && $s['prog'] !== $prog['id'] ) ) { continue; }
		if ( 'next' === $s['mode'] && ! $s['done'] ) { $use[] = $s; }
		if ( 'rot' === $s['mode'] ) { $rot[] = $s; }
	}
	if ( $rot ) {
		$st['scr_n'] = ( isset( $st['scr_n'] ) ? (int) $st['scr_n'] : 0 ) + 1;
		$i = ( isset( $st['scr_i'] ) ? (int) $st['scr_i'] : 0 ) % count( $rot );
		if ( $st['scr_n'] >= $rot[ $i ]['n'] ) { $use[] = $rot[ $i ]; $st['scr_n'] = 0; $st['scr_i'] = $i + 1; }
	}
	return $use;
}
/* Seuraava kellonaikaan sidottu juonto ($e:n jälkeen, enintään tunnin päässä) tai 0 */
function suorni_radio_script_next_time( $e ) {
	$e   = (int) $e;
	$mid = $e - ( (int) wp_date( 'G', $e ) * 3600 + (int) wp_date( 'i', $e ) * 60 + (int) wp_date( 's', $e ) );
	$best = 0;
	foreach ( suorni_scripts() as $s ) {
		if ( ! $s['on'] || 'time' !== $s['mode'] ) { continue; }
		list( $h, $m ) = array_map( 'intval', explode( ':', $s['time'] ) );
		foreach ( array( 0, 86400 ) as $add ) {
			$c = $mid + $add + $h * 3600 + $m * 60;
			if ( $c <= $e || $c > $e + 3600 ) { continue; }
			if ( ! in_array( (int) wp_date( 'N', $c ), $s['days'], true ) || wp_date( 'Ymd', $c ) === suorni_script_ld( $s ) ) { continue; }
			if ( ! $best || $c < $best ) { $best = $c; }
		}
	}
	return $best;
}
/* Erääntynyt kellonaikajuonto ($end:n kohdalla): aika on tullut eikä ole yli 10 min myöhässä */
function suorni_radio_script_due( $end ) {
	$e   = (int) floor( $end + 0.5 );
	$mid = $e - ( (int) wp_date( 'G', $e ) * 3600 + (int) wp_date( 'i', $e ) * 60 + (int) wp_date( 's', $e ) );
	foreach ( suorni_scripts() as $s ) {
		if ( ! $s['on'] || 'time' !== $s['mode'] ) { continue; }
		list( $h, $m ) = array_map( 'intval', explode( ':', $s['time'] ) );
		$c = $mid + $h * 3600 + $m * 60;
		if ( $c > $e || $e - $c > 600 ) { continue; }
		if ( ! in_array( (int) wp_date( 'N', $c ), $s['days'], true ) || wp_date( 'Ymd', $c ) === suorni_script_ld( $s ) ) { continue; }
		return $s;
	}
	return null;
}
/* Aikajanan kohde käsikirjoitetusta tekstistä */
function suorni_radio_script_item( $cfg, $s, $ts, $prog ) {
	$pg   = suorni_script_prog( $cfg, $s, $prog );
	$dj   = suorni_radio_dj();
	$text = suorni_script_text( $s, $ts );
	$segs = suorni_ai_to_segs( $text, $dj['titles'] );
	if ( ! $segs ) { return null; }
	$r = suorni_tts_synth( $segs, $pg['rate'] > 0 ? $pg['rate'] : $dj['rate'], array( 'fi' => $pg['vfi'], 'en' => $pg['ven'] ) );
	if ( is_wp_error( $r ) || empty( $r['bytes'] ) ) {
		update_option( 'suorni_radio_djerr', wp_date( 'j.n. H:i' ) . ' Oma juonto "' . $s['title'] . '": ' . ( is_wp_error( $r ) ? $r->get_error_message() : 'puheen luonti ei palauttanut ääntä.' ), false );
		return null;
	}
	suorni_dj_log_add( array( 't' => time(), 'ai' => 0, 'topics' => array( 'oma' ), 'miss' => array(), 'text' => mb_substr( $text, 0, 400 ) ) );
	return array( 'type' => 'dj', 'url' => $r['url'], 'dur' => max( 2, (int) ceil( $r['bytes'] / 12000 ) ), 'title' => 'Juontaja', 'artist' => '', 'text' => $text, 'own' => 1 );
}
/* Esikuuntelu ohjaamossa */
function suorni_script_prev_post( $req ) {
	$s = suorni_script_norm( array( 'id' => 'x', 'text' => (string) $req->get_param( 'text' ), 'prog' => (string) $req->get_param( 'prog' ) ) );
	if ( '' === $s['text'] ) { return new WP_Error( 'suorni_bad', 'Kirjoita ensin teksti.', array( 'status' => 400 ) ); }
	$cfg  = suorni_radio_cfg();
	$prog = suorni_radio_prog_at( $cfg, time() );
	$pg   = suorni_script_prog( $cfg, $s, $prog );
	$dj   = suorni_radio_dj();
	$segs = suorni_ai_to_segs( suorni_script_text( $s, time() ), $dj['titles'] );
	$r    = suorni_tts_synth( $segs, $pg['rate'] > 0 ? $pg['rate'] : $dj['rate'], array( 'fi' => $pg['vfi'], 'en' => $pg['ven'] ) );
	if ( is_wp_error( $r ) ) { return $r; }
	return suorni_json( array( 'ok' => true, 'url' => $r['url'], 'dur' => max( 2, (int) ceil( $r['bytes'] / 12000 ) ) ) );
}
/* Soita heti: lisätään nykyisen kohteen jälkeen */
function suorni_script_now_post( $req ) {
	$id = sanitize_key( (string) $req->get_param( 'id' ) );
	$sc = null;
	foreach ( suorni_scripts() as $s ) { if ( $s['id'] === $id ) { $sc = $s; break; } }
	if ( ! $sc ) { return new WP_Error( 'suorni_bad', 'Juontoa ei löytynyt. Tallenna juonnot ensin.', array( 'status' => 404 ) ); }
	$cfg  = suorni_radio_cfg();
	$now  = microtime( true );
	$prog = suorni_radio_prog_at( $cfg, (int) $now );
	$it   = suorni_radio_script_item( $cfg, $sc, (int) $now + 5, $prog );
	if ( ! $it ) { return new WP_Error( 'suorni_tts', 'Puheen luonti epäonnistui. Katso Valvonta-paneelista syy.', array( 'status' => 502 ) ); }
	$got = false;
	for ( $i = 0; $i < 10 && ! ( $got = suorni_radio_lock() ); $i++ ) { usleep( 300000 ); }
	wp_cache_delete( 'suorni_radio_log', 'options' );
	wp_cache_delete( 'alloptions', 'options' );
	$log = suorni_radio_log();
	$now = microtime( true );
	$k   = -1;
	foreach ( $log['items'] as $i => $x ) { if ( $x['start'] > $now + 2 ) { $k = $i; break; } }
	$it['prog'] = $prog['name'];
	if ( $k < 0 ) {
		$last = $log['items'] ? end( $log['items'] ) : null;
		$it['start'] = $last ? max( $now + 3, $last['start'] + $last['dur'] ) : $now + 3;
		$log['items'][] = $it;
	} else {
		$it['start'] = $log['items'][ $k ]['start'];
		for ( $j = $k; $j < count( $log['items'] ); $j++ ) { $log['items'][ $j ]['start'] += $it['dur']; }
		array_splice( $log['items'], $k, 0, array( $it ) );
		suorni_radio_realign( $log['items'], $now );
	}
	update_option( 'suorni_radio_log', $log, false );
	if ( $got ) { suorni_radio_unlock(); }
	suorni_script_mark( $id );
	return suorni_json( array( 'ok' => true, 'at' => (int) $it['start'] ) );
}
add_action( 'rest_api_init', function () {
	register_rest_route( 'suorni/v1', '/radio/scripts', array( 'methods' => 'POST', 'callback' => 'suorni_scripts_save', 'permission_callback' => 'suorni_can' ) );
	register_rest_route( 'suorni/v1', '/radio/scriptprev', array( 'methods' => 'POST', 'callback' => 'suorni_script_prev_post', 'permission_callback' => 'suorni_can' ) );
	register_rest_route( 'suorni/v1', '/radio/scriptnow', array( 'methods' => 'POST', 'callback' => 'suorni_script_now_post', 'permission_callback' => 'suorni_can' ) );
} );

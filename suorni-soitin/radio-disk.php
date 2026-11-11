<?php
/**
 * Levytila ja kirjasto: levynkäyttö, soittomäärät (tasainen soitto) ja käyttämättömän median raportti.
 * Poistaminen tapahtuu vain ohjaamosta käyttäjän vahvistuksella (POST /radio/diskact).
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

/* ---------- Asetukset ---------- */
function suorni_disk_cfg() {
	$c = get_option( 'suorni_disk', array() );
	if ( ! is_array( $c ) ) { $c = array(); }
	return array(
		'quota' => isset( $c['quota'] ) ? max( 50, min( 100000, (int) $c['quota'] ) ) : 512, /* Mt */
		'incdb' => ! isset( $c['incdb'] ) || ! empty( $c['incdb'] ),
	);
}

/* ---------- Soittomäärät ---------- */
function suorni_plays_opt() { return 'suorni_plays' . suorni_ch_sfx(); }
function suorni_plays_get() {
	$p = get_option( suorni_plays_opt(), array() );
	if ( ! is_array( $p ) ) { $p = array(); }
	if ( ! isset( $p['m'] ) || ! is_array( $p['m'] ) ) { $p['m'] = array(); }
	if ( empty( $p['since'] ) ) { $p['since'] = time(); }
	return $p;
}
/* Kutsutaan, kun kappale otetaan aikajanalle (varsinainen valinta, ei esikatselu) */
function suorni_plays_note( $id ) {
	$id = (int) $id;
	if ( $id < 1 ) { return; }
	$p = suorni_plays_get();
	$p['m'][ $id ] = ( isset( $p['m'][ $id ] ) ? (int) $p['m'][ $id ] : 0 ) + 1;
	$p['last'][ $id ] = time();
	if ( isset( $p['last'] ) && count( $p['last'] ) > 3000 ) { $p['last'] = array_slice( $p['last'], -2000, null, true ); }
	update_option( suorni_plays_opt(), $p, false );
	suorni_fair_mult( null );
}
/* Tasaava kerroin: jäljessä oleva kappale saa suuremman painon, edellä oleva pienemmän (0,2 … 5) */
function suorni_fair_mult( $id ) {
	static $cache = null;
	if ( null === $id ) { $cache = null; return 1.0; }
	if ( null === $cache || $cache['sfx'] !== suorni_ch_sfx() ) {
		$p   = suorni_plays_get();
		$sum = 0; $n = 0;
		$ids = array();
		foreach ( suorni_tracks() as $t ) { if ( $t['dur'] > 0 && empty( $t['ad'] ) && empty( $t['ann'] ) ) { $ids[ (int) $t['id'] ] = 1; } }
		foreach ( $ids as $i => $one ) { $sum += isset( $p['m'][ $i ] ) ? (int) $p['m'][ $i ] : 0; $n++; }
		$cache = array( 'sfx' => suorni_ch_sfx(), 'm' => $p['m'], 'avg' => $n ? $sum / $n : 0 );
	}
	$c = isset( $cache['m'][ (int) $id ] ) ? (int) $cache['m'][ (int) $id ] : 0;
	$f = pow( ( $cache['avg'] + 1 ) / ( $c + 1 ), 1.5 );
	return max( 0.2, min( 5.0, $f ) );
}
function suorni_plays_report() {
	$p   = suorni_plays_get();
	$rows = array();
	$tot = 0;
	foreach ( suorni_tracks() as $t ) {
		if ( ! ( $t['dur'] > 0 ) || ! empty( $t['ad'] ) || ! empty( $t['ann'] ) ) { continue; }
		$n = isset( $p['m'][ (int) $t['id'] ] ) ? (int) $p['m'][ (int) $t['id'] ] : 0;
		$tot += $n;
		$rows[] = array( 'id' => (int) $t['id'], 't' => $t['title'], 'a' => $t['artist'], 'n' => $n, 'last' => isset( $p['last'][ (int) $t['id'] ] ) ? (int) $p['last'][ (int) $t['id'] ] : 0 );
	}
	usort( $rows, function ( $a, $b ) { return $b['n'] <=> $a['n']; } );
	$cnt = count( $rows );
	$avg = $cnt ? $tot / $cnt : 0;
	return array(
		'since' => (int) $p['since'], 'total' => $tot, 'tracks' => $cnt, 'avg' => round( $avg, 1 ),
		'max' => $cnt ? $rows[0]['n'] : 0, 'min' => $cnt ? $rows[ $cnt - 1 ]['n'] : 0,
		'top' => array_slice( $rows, 0, 10 ), 'low' => array_slice( array_reverse( $rows ), 0, 10 ),
		'never' => count( array_filter( $rows, function ( $r ) { return 0 === $r['n']; } ) ),
	);
}

/* ---------- Levyskannaus ---------- */
function suorni_disk_walk( $dir, &$files, $deadline ) {
	if ( ! is_dir( $dir ) ) { return true; }
	try {
		$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS | FilesystemIterator::UNIX_PATHS ), RecursiveIteratorIterator::LEAVES_ONLY, RecursiveIteratorIterator::CATCH_GET_CHILD );
		foreach ( $it as $f ) {
			if ( microtime( true ) > $deadline ) { return false; }
			if ( $f->isFile() ) { $files[] = array( $f->getPathname(), (int) $f->getSize() ); }
		}
	} catch ( Exception $e ) { return false; }
	return true;
}
function suorni_disk_dirsize( $dir, $skip, &$deadline_hit, $deadline ) {
	$sum = 0; $n = 0;
	if ( ! is_dir( $dir ) ) { return array( 0, 0 ); }
	try {
		$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS | FilesystemIterator::UNIX_PATHS ), RecursiveIteratorIterator::LEAVES_ONLY, RecursiveIteratorIterator::CATCH_GET_CHILD );
		foreach ( $it as $f ) {
			if ( microtime( true ) > $deadline ) { $deadline_hit = true; break; }
			$p = $f->getPathname();
			if ( $skip && 0 === strpos( $p, $skip ) ) { continue; }
			if ( $f->isFile() ) { $sum += (int) $f->getSize(); $n++; }
		}
	} catch ( Exception $e ) { $deadline_hit = true; }
	return array( $sum, $n );
}
function suorni_disk_norm( $s ) {
	$s = mb_strtolower( (string) $s );
	$s = preg_replace( '#[\(\[\{][^\)\]\}]*[\)\]\}]#u', ' ', $s );
	$s = preg_replace( '#\b(v|ver|versio|version|mix|remix|radio edit)\s*\d*\b#u', ' ', $s );
	$s = preg_replace( '#[^a-z0-9äöå]+#u', ' ', $s );
	$s = trim( preg_replace( '#\s+\d{1,2}$#u', '', trim( $s ) ) );
	return trim( preg_replace( '#\s+#', ' ', $s ) );
}
function suorni_disk_attach_bytes( $id ) {
	$f = get_attached_file( $id );
	$b = ( $f && file_exists( $f ) ) ? (int) filesize( $f ) : 0;
	$m = wp_get_attachment_metadata( $id );
	if ( $f && is_array( $m ) && ! empty( $m['sizes'] ) && is_array( $m['sizes'] ) ) {
		$d = dirname( $f );
		foreach ( $m['sizes'] as $s ) { if ( ! empty( $s['file'] ) && file_exists( $d . '/' . $s['file'] ) ) { $b += (int) filesize( $d . '/' . $s['file'] ); } }
	}
	return $b;
}
/* Mihin kuvaa/mediaa on käytetty: haetaan tiedostonimi asetuksista ja sisällöstä */
function suorni_disk_haystack() {
	global $wpdb;
	$h = '';
	foreach ( (array) $wpdb->get_col( "SELECT option_value FROM {$wpdb->options} WHERE option_name LIKE 'suorni%' OR option_name LIKE 'theme_mods%' OR option_name IN ('site_icon','site_logo','sidebars_widgets') OR option_name LIKE 'widget_%'" ) as $v ) { $h .= ' ' . $v; }
	foreach ( (array) $wpdb->get_col( "SELECT post_content FROM {$wpdb->posts} WHERE post_type NOT IN ('attachment','revision') AND post_status NOT IN ('trash','auto-draft') LIMIT 500" ) as $v ) { $h .= ' ' . $v; }
	foreach ( (array) $wpdb->get_col( "SELECT meta_value FROM {$wpdb->postmeta} WHERE meta_key IN ('_thumbnail_id') LIMIT 2000" ) as $v ) { $h .= ' id' . $v . ' '; }
	return $h;
}
function suorni_disk_scan( $quick = false ) {
	global $wpdb;
	$t0   = microtime( true );
	$dl   = $t0 + ( $quick ? 6 : 14 );
	$cfg  = suorni_disk_cfg();
	$up   = wp_upload_dir();
	$base = rtrim( str_replace( '\\', '/', $up['basedir'] ), '/' );
	$hit  = false;
	$files = array();
	$ok   = suorni_disk_walk( $base, $files, $dl );
	$cat  = array();
	$names = array( 'suorni-tts' => 'Puhevälimuisti (tekoälyn puheet)', 'suorni-subs' => 'Uusien artistien kappaleet', 'suorni-suno' => 'Tallennetut ulkoiset kappaleet', 'suorni-bg' => 'Kanavien taustakuvat' );
	$upl = 0;
	foreach ( $files as $f ) {
		$rel = ltrim( substr( str_replace( '\\', '/', $f[0] ), strlen( $base ) ), '/' );
		$top = strstr( $rel, '/', true );
		if ( false === $top ) { $top = '(juuri)'; }
		$key = preg_match( '#^\d{4}$#', $top ) ? 'media' : $top;
		if ( ! isset( $cat[ $key ] ) ) { $cat[ $key ] = array( 'k' => $key, 'name' => 'media' === $key ? 'Mediakirjasto (kappaleet ja kuvat)' : ( isset( $names[ $key ] ) ? $names[ $key ] : $key ), 'bytes' => 0, 'files' => 0 ); }
		$cat[ $key ]['bytes'] += $f[1]; $cat[ $key ]['files']++; $upl += $f[1];
	}
	/* Muut WordPress-tiedostot (ydin, lisäosat, teemat) */
	$skip = $base . '/';
	$other = $quick ? array( 0, 0 ) : suorni_disk_dirsize( untrailingslashit( str_replace( '\\', '/', ABSPATH ) ), $skip, $hit, $dl );
	$db = 0;
	foreach ( (array) $wpdb->get_results( 'SHOW TABLE STATUS', ARRAY_A ) as $r ) {
		if ( 0 === strpos( (string) $r['Name'], $wpdb->prefix ) ) { $db += (int) $r['Data_length'] + (int) $r['Index_length']; }
	}
	/* Mediakirjaston liitteet */
	$aud = array(); $img = array(); $oth = array();
	$ids = get_posts( array( 'post_type' => 'attachment', 'post_status' => 'inherit', 'posts_per_page' => -1, 'fields' => 'ids' ) );
	$tracks = array();
	foreach ( suorni_tracks() as $t ) { $tracks[ (int) $t['id'] ] = $t; }
	$plays = suorni_plays_get();
	$excl  = suorni_radio_excl();
	$byk = array( 'audio' => array( 0, 0 ), 'image' => array( 0, 0 ), 'other' => array( 0, 0 ) );
	$all = array();
	foreach ( $ids as $id ) {
		$b = suorni_disk_attach_bytes( $id );
		$mime = (string) get_post_mime_type( $id );
		$k = 0 === strpos( $mime, 'audio' ) ? 'audio' : ( 0 === strpos( $mime, 'image' ) ? 'image' : 'other' );
		$byk[ $k ][0] += $b; $byk[ $k ][1]++;
		$all[] = array( 'id' => (int) $id, 'k' => $k, 'b' => $b, 'title' => get_the_title( $id ), 'file' => basename( (string) get_attached_file( $id ) ) );
	}
	usort( $all, function ( $a, $b ) { return $b['b'] <=> $a['b']; } );
	$big = array();
	foreach ( array_slice( $all, 0, 12 ) as $a ) { $big[] = array( 'id' => $a['id'], 't' => $a['title'], 'k' => $a['k'], 'b' => $a['b'] ); }
	/* Siivousehdokkaat */
	$cand = array( 'dup' => array(), 'hid' => array(), 'broken' => array(), 'ad' => array(), 'img' => array() );
	$groups = array();
	foreach ( $all as $a ) {
		if ( 'audio' !== $a['k'] ) { continue; }
		$tr = isset( $tracks[ $a['id'] ] ) ? $tracks[ $a['id'] ] : null;
		$n  = isset( $plays['m'][ $a['id'] ] ) ? (int) $plays['m'][ $a['id'] ] : 0;
		$row = array( 'id' => $a['id'], 't' => $tr ? $tr['title'] : $a['title'], 'a' => $tr ? $tr['artist'] : '', 'b' => $a['b'], 'dur' => $tr ? (int) $tr['dur'] : 0, 'n' => $n, 'at' => $tr ? (int) $tr['at'] : 0 );
		if ( ! $tr ) { continue; }
		if ( ! empty( $tr['ad'] ) || ! empty( $tr['ann'] ) ) { continue; }
		if ( in_array( $a['id'], $excl, true ) ) { $cand['hid'][] = $row; }
		if ( ! ( $tr['dur'] > 0 ) || 0 === $a['b'] ) { $cand['broken'][] = $row; continue; }
		$nk = suorni_disk_norm( $tr['title'] );
		if ( '' !== $nk ) { $groups[ $nk ][] = $row; }
	}
	foreach ( $groups as $g ) {
		if ( count( $g ) < 2 ) { continue; }
		usort( $g, function ( $x, $y ) { return $y['at'] <=> $x['at']; } );
		$cand['dup'][] = $g;
	}
	/* Mainoksina luodut tiedostot, jotka eivät ole mainoslistalla */
	$adids = suorni_ads()['ids'];
	foreach ( $all as $a ) {
		if ( 'audio' === $a['k'] && 0 === strpos( $a['file'], 'mainos-' ) && ! in_array( $a['id'], $adids, true ) && ( ! isset( $tracks[ $a['id'] ] ) || empty( $tracks[ $a['id'] ]['ann'] ) ) ) {
			$cand['ad'][] = array( 'id' => $a['id'], 't' => $a['title'], 'a' => '', 'b' => $a['b'], 'dur' => isset( $tracks[ $a['id'] ] ) ? (int) $tracks[ $a['id'] ]['dur'] : 0, 'n' => 0, 'at' => 0 );
		}
	}
	/* Kuvat, joita ei löydy käytöstä */
	$hay = suorni_disk_haystack();
	$cnt = 0;
	foreach ( $all as $a ) {
		if ( 'image' !== $a['k'] || $cnt >= 300 ) { continue; }
		$cnt++;
		$f = $a['file'];
		$stem = preg_replace( '#\.[a-z0-9]+$#i', '', $f );
		$stem = preg_replace( '#-\d+x\d+$#', '', $stem );
		if ( '' !== $stem && false !== stripos( $hay, $stem ) ) { continue; }
		if ( false !== strpos( $hay, ' id' . $a['id'] . ' ' ) ) { continue; }
		if ( (int) get_option( 'site_icon' ) === $a['id'] || (int) get_theme_mod( 'custom_logo' ) === $a['id'] ) { continue; }
		$cand['img'][] = array( 'id' => $a['id'], 't' => $a['title'], 'a' => $f, 'b' => $a['b'], 'dur' => 0, 'n' => 0, 'at' => 0, 'url' => wp_get_attachment_url( $a['id'] ) );
	}
	/* Erilliset tiedostot kansiossa suorni-subs, joita yksikään ehdotus ei käytä */
	$orph = array( 'n' => 0, 'b' => 0 );
	$dir  = $base . '/suorni-subs';
	if ( is_dir( $dir ) && function_exists( 'suorni_subs' ) ) {
		$used = array();
		foreach ( suorni_subs() as $s ) { if ( ! empty( $s['file'] ) ) { $used[ $s['file'] ] = 1; } }
		foreach ( (array) glob( $dir . '/*.mp3' ) as $f ) { if ( ! isset( $used[ basename( $f ) ] ) ) { $orph['n']++; $orph['b'] += (int) filesize( $f ); } }
	}
	$sum = function ( $rows ) { $b = 0; foreach ( $rows as $r ) { $b += isset( $r['b'] ) ? $r['b'] : 0; } return $b; };
	$dupb = 0;
	foreach ( $cand['dup'] as $g ) { for ( $i = 1; $i < count( $g ); $i++ ) { $dupb += $g[ $i ]['b']; } }
	$total = $upl + $other[0] + ( $cfg['incdb'] ? $db : 0 );
	$out = array(
		't' => time(), 'quota' => $cfg['quota'], 'incdb' => $cfg['incdb'], 'quick' => $quick, 'partial' => ( ! $ok || $hit ),
		'used' => $total, 'uploads' => $upl, 'wp' => $other[0], 'db' => $db,
		'cats' => array_values( $cat ),
		'kinds' => array( 'audio' => array( 'n' => $byk['audio'][1], 'b' => $byk['audio'][0] ), 'image' => array( 'n' => $byk['image'][1], 'b' => $byk['image'][0] ), 'other' => array( 'n' => $byk['other'][1], 'b' => $byk['other'][0] ) ),
		'big' => $big,
		'cand' => $cand,
		'dupb' => $dupb, 'hidb' => $sum( $cand['hid'] ), 'brokenb' => $sum( $cand['broken'] ), 'adb' => $sum( $cand['ad'] ), 'imgb' => $sum( $cand['img'] ),
		'orph' => $orph, 'plays' => suorni_plays_report(), 'secs' => round( microtime( true ) - $t0, 1 ),
	);
	update_option( 'suorni_disk_last', array( 't' => $out['t'], 'used' => $total, 'quota' => $cfg['quota'], 'partial' => $out['partial'], 'quick' => $quick ), false );
	set_transient( 'suorni_disk_scan', $out, 10 * MINUTE_IN_SECONDS );
	return $out;
}
/* Edellisen skannauksen yhteenveto ohjaamon Tänään-näkymään */
function suorni_disk_summary() {
	$l = get_option( 'suorni_disk_last', array() );
	$cfg = suorni_disk_cfg();
	if ( ! is_array( $l ) || empty( $l['t'] ) || ( time() - (int) $l['t'] ) > 6 * HOUR_IN_SECONDS ) {
		/* Vanha tai puuttuva: nopea skannaus vain mediakansiosta */
		$s = suorni_disk_scan( true );
		$l = array( 't' => $s['t'], 'used' => $s['used'], 'quota' => $cfg['quota'] );
	}
	$l['quota'] = $cfg['quota'];
	return $l;
}
function suorni_disk_get( $req ) {
	$force = (bool) $req->get_param( 'refresh' );
	$c = $force ? false : get_transient( 'suorni_disk_scan' );
	if ( ! is_array( $c ) || ! empty( $c['quick'] ) ) { $c = suorni_disk_scan( false ); }
	return suorni_json( $c );
}
function suorni_disk_cfg_post( $req ) {
	$c = array( 'quota' => max( 50, min( 100000, absint( $req->get_param( 'quota' ) ) ) ), 'incdb' => (bool) $req->get_param( 'incdb' ) );
	update_option( 'suorni_disk', $c, false );
	delete_transient( 'suorni_disk_scan' );
	return suorni_json( array( 'ok' => true ) );
}
/* Onko kappale juuri nyt aikajanalla (soi tai on seuraavana)? Silloin sitä ei poisteta. */
function suorni_disk_in_use_now( $id ) {
	$cur = suorni_ch_cur();
	$now = microtime( true );
	$hit = false;
	foreach ( suorni_ch_ids() as $c ) {
		suorni_ch_set( $c );
		$l = suorni_radio_log();
		foreach ( $l['items'] as $it ) {
			if ( isset( $it['id'] ) && (int) $it['id'] === (int) $id && empty( $it['sub'] ) && $it['start'] + $it['dur'] > $now - 5 ) { $hit = true; break; }
		}
		if ( $hit ) { break; }
	}
	suorni_ch_set( $cur );
	return $hit;
}
function suorni_disk_act_post( $req ) {
	$act = sanitize_key( (string) $req->get_param( 'act' ) );
	if ( 'ttsclear' === $act ) {
		$up = wp_upload_dir();
		$n = 0; $b = 0;
		foreach ( (array) glob( trailingslashit( $up['basedir'] ) . 'suorni-tts/*.mp3' ) as $f ) { $b += (int) filesize( $f ); if ( @unlink( $f ) ) { $n++; } }
		delete_transient( 'suorni_disk_scan' );
		return suorni_json( array( 'ok' => true, 'n' => $n, 'freed' => $b ) );
	}
	if ( 'orphsubs' === $act ) {
		$up = wp_upload_dir();
		$used = array();
		foreach ( suorni_subs() as $s ) { if ( ! empty( $s['file'] ) ) { $used[ $s['file'] ] = 1; } }
		$n = 0; $b = 0;
		foreach ( (array) glob( trailingslashit( $up['basedir'] ) . 'suorni-subs/*.mp3' ) as $f ) { if ( ! isset( $used[ basename( $f ) ] ) ) { $b += (int) filesize( $f ); if ( @unlink( $f ) ) { $n++; } } }
		delete_transient( 'suorni_disk_scan' );
		return suorni_json( array( 'ok' => true, 'n' => $n, 'freed' => $b ) );
	}
	if ( 'del' === $act ) {
		$ids = $req->get_param( 'ids' );
		if ( ! is_array( $ids ) ) { return new WP_Error( 'suorni_bad', 'Ei poistettavia.', array( 'status' => 400 ) ); }
		$done = array(); $skip = array(); $freed = 0;
		foreach ( array_slice( array_unique( array_map( 'absint', $ids ) ), 0, 50 ) as $id ) {
			if ( ! $id || 'attachment' !== get_post_type( $id ) ) { continue; }
			$mime = (string) get_post_mime_type( $id );
			if ( 0 !== strpos( $mime, 'audio' ) && 0 !== strpos( $mime, 'image' ) ) { $skip[] = array( 'id' => $id, 'why' => 'Vain ääni- ja kuvatiedostoja voi poistaa täältä.' ); continue; }
			if ( 0 === strpos( $mime, 'audio' ) && suorni_disk_in_use_now( $id ) ) { $skip[] = array( 'id' => $id, 'why' => 'Kappale soi tai on jonossa juuri nyt. Yritä hetken päästä.' ); continue; }
			$title = get_the_title( $id );
			$b = suorni_disk_attach_bytes( $id );
			if ( wp_delete_attachment( $id, true ) ) {
				$freed += $b;
				$done[] = array( 'id' => $id, 't' => $title, 'b' => $b );
				$e = suorni_radio_excl();
				if ( in_array( $id, $e, true ) ) { update_option( 'suorni_radio_excl', array_values( array_diff( $e, array( $id ) ) ), false ); }
			} else { $skip[] = array( 'id' => $id, 'why' => 'Poisto epäonnistui.' ); }
		}
		$log = get_option( 'suorni_disk_dellog', array() );
		if ( ! is_array( $log ) ) { $log = array(); }
		foreach ( $done as $d ) { $log[] = array( 'at' => time(), 'id' => $d['id'], 't' => $d['t'], 'b' => $d['b'] ); }
		update_option( 'suorni_disk_dellog', array_slice( $log, -100 ), false );
		suorni_clear_tracks();
		delete_transient( 'suorni_disk_scan' );
		return suorni_json( array( 'ok' => true, 'done' => $done, 'skipped' => $skip, 'freed' => $freed ) );
	}
	return new WP_Error( 'suorni_bad', 'Tuntematon toiminto.', array( 'status' => 400 ) );
}
add_action( 'rest_api_init', function () {
	register_rest_route( 'suorni/v1', '/radio/disk', array( 'methods' => 'GET', 'callback' => 'suorni_disk_get', 'permission_callback' => 'suorni_can' ) );
	register_rest_route( 'suorni/v1', '/radio/diskcfg', array( 'methods' => 'POST', 'callback' => 'suorni_disk_cfg_post', 'permission_callback' => 'suorni_can' ) );
	register_rest_route( 'suorni/v1', '/radio/diskact', array( 'methods' => 'POST', 'callback' => 'suorni_disk_act_post', 'permission_callback' => 'suorni_can' ) );
} );

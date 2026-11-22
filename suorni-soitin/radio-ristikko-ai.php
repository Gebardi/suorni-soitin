<?php
/* Kuvaristikko uutislinkistä (ohjaamo, vain ylläpito). Vaiheet:
   1) ristikko/gen  {url|text}  hakee jutun, pyytää tekoälyltä kuvattavia substantiiveja, rakentaa ruudukon ja tallentaa luonnoksen (ei vielä kuvia)
   2) ristikko/img  {n}         tekee yhden kuvan luonnoksen sanalle n (kutsutaan sana kerrallaan; sama kutsu tekee uuden kuvan)
   3) ristikko/pub  {title}     julkaisee luonnoksen pelattavaksi;  ristikko/discard hylkää;  ristikko/pzdel {id} poistaa julkaistun
   Asetukset ristikko/rkcfg {key,tm,im,max,maxi}: OpenAI-yhteensopiva avain (vain ylläpito syöttää, ei koskaan palaudu selaimelle).
   Kulukatot: ristikoita / vrk (oletus 5) ja kuvia / vrk (oletus 40). Kuvat pienennetään 256 px:n jpg-tiedostoiksi (uploads/suorni-rk/).
   Kuvissa ei saa olla tekstiä, logoja eikä oikeita henkilöitä; ylläpito hyväksyy ristikon ennen julkaisua. */
if ( ! defined( 'ABSPATH' ) ) { exit; }

function suorni_rkg_cfg() {
	$c = get_option( 'suorni_rkg_cfg', array() ); if ( ! is_array( $c ) ) { $c = array(); }
	return array(
		'key'  => isset( $c['key'] ) ? (string) $c['key'] : '',
		'tm'   => ( isset( $c['tm'] ) && preg_match( '/^[A-Za-z0-9._:\-]{2,60}$/', $c['tm'] ) ) ? $c['tm'] : 'gpt-4.1-mini',
		'im'   => ( isset( $c['im'] ) && preg_match( '/^[A-Za-z0-9._:\-]{2,60}$/', $c['im'] ) ) ? $c['im'] : 'gpt-image-1-mini',
		'max'  => isset( $c['max'] ) ? max( 1, min( 30, (int) $c['max'] ) ) : 5,
		'maxi' => isset( $c['maxi'] ) ? max( 1, min( 200, (int) $c['maxi'] ) ) : 40,
	);
}
function suorni_rkg_dir() { $u = wp_upload_dir(); return array( 'path' => trailingslashit( $u['basedir'] ) . 'suorni-rk/', 'url' => trailingslashit( $u['baseurl'] ) . 'suorni-rk/' ); }
function suorni_rkg_count( $kind ) { return (int) get_transient( 'suorni_rkg_' . $kind . '_' . wp_date( 'Ymd' ) ); }
function suorni_rkg_bump( $kind, $n = 1 ) { set_transient( 'suorni_rkg_' . $kind . '_' . wp_date( 'Ymd' ), suorni_rkg_count( $kind ) + $n, 2 * DAY_IN_SECONDS ); }

/* ---------- ruudukon sijoittelu (sama menetelmä kuin ennakkogeneraattorissa) ---------- */
function suorni_rkg_try( $words, $R, $C ) {
	$g = array_fill( 0, $R, array_fill( 0, $C, null ) );
	$ents = array();
	$chars = array();
	foreach ( $words as $i => $w ) { $chars[ $i ] = preg_split( '//u', $w['w'], -1, PREG_SPLIT_NO_EMPTY ); }
	$order = array_keys( $words ); shuffle( $order );
	$can = function ( $i, $r, $c, $d ) use ( &$g, $R, $C, $chars ) {
		$dr = 'r' === $d ? 0 : 1; $dc = 'r' === $d ? 1 : 0; $n = count( $chars[ $i ] );
		$pr = $r - $dr; $pc = $c - $dc;
		if ( $pr < 0 || $pc < 0 || null !== $g[ $pr ][ $pc ] ) { return -1; }
		if ( $r + $dr * ( $n - 1 ) >= $R || $c + $dc * ( $n - 1 ) >= $C ) { return -1; }
		$cross = 0;
		for ( $j = 0; $j < $n; $j++ ) {
			$x = $g[ $r + $dr * $j ][ $c + $dc * $j ];
			if ( null === $x ) { continue; }
			if ( 'L' !== $x[0] || $x[1] !== $chars[ $i ][ $j ] ) { return -1; }
			$cross++;
		}
		$er = $r + $dr * $n; $ec = $c + $dc * $n;
		if ( $er < $R && $ec < $C && null !== $g[ $er ][ $ec ] && 'L' === $g[ $er ][ $ec ][0] ) { return -1; }
		return $cross;
	};
	$runs_ok = function () use ( &$g, &$ents, $R, $C ) {
		$starts = array();
		foreach ( $ents as $e ) { $starts[ $e['r'] . ',' . $e['c'] . ',' . $e['d'] . ',' . $e['len'] ] = 1; }
		for ( $r = 0; $r < $R; $r++ ) {
			$c = 0;
			while ( $c < $C ) {
				if ( $g[ $r ][ $c ] && 'L' === $g[ $r ][ $c ][0] ) {
					$s = $c; while ( $c < $C && $g[ $r ][ $c ] && 'L' === $g[ $r ][ $c ][0] ) { $c++; }
					if ( $c - $s >= 2 && ! isset( $starts[ $r . ',' . $s . ',r,' . ( $c - $s ) ] ) ) { return false; }
				} else { $c++; }
			}
		}
		for ( $c = 0; $c < $C; $c++ ) {
			$r = 0;
			while ( $r < $R ) {
				if ( $g[ $r ][ $c ] && 'L' === $g[ $r ][ $c ][0] ) {
					$s = $r; while ( $r < $R && $g[ $r ][ $c ] && 'L' === $g[ $r ][ $c ][0] ) { $r++; }
					if ( $r - $s >= 2 && ! isset( $starts[ $s . ',' . $c . ',d,' . ( $r - $s ) ] ) ) { return false; }
				} else { $r++; }
			}
		}
		return true;
	};
	$put = function ( $i, $r, $c, $d ) use ( &$g, &$ents, $R, $C, $chars ) {
		$dr = 'r' === $d ? 0 : 1; $dc = 'r' === $d ? 1 : 0; $n = count( $chars[ $i ] );
		$ents[] = array( 'i' => $i, 'r' => $r, 'c' => $c, 'd' => $d, 'len' => $n );
		$g[ $r - $dr ][ $c - $dc ] = array( 'P' );
		for ( $j = 0; $j < $n; $j++ ) { $g[ $r + $dr * $j ][ $c + $dc * $j ] = array( 'L', $chars[ $i ][ $j ] ); }
		$er = $r + $dr * $n; $ec = $c + $dc * $n;
		if ( $er < $R && $ec < $C && null === $g[ $er ][ $ec ] ) { $g[ $er ][ $ec ] = array( 'E' ); }
	};
	/* ensimmäinen sana */
	$i0 = $order[0]; $d0 = wp_rand( 0, 1 ) ? 'r' : 'd';
	$r0 = wp_rand( 'd' === $d0 ? 1 : 0, $R - 1 ); $c0 = wp_rand( 'r' === $d0 ? 1 : 0, $C - 1 );
	if ( $can( $i0, $r0, $c0, $d0 ) < 0 ) { return null; }
	$put( $i0, $r0, $c0, $d0 );
	$used = array( $i0 => 1 );
	foreach ( array_slice( $order, 1 ) as $i ) {
		$cands = array();
		foreach ( array( 'r', 'd' ) as $d ) { for ( $r = 0; $r < $R; $r++ ) { for ( $c = 0; $c < $C; $c++ ) { $k = $can( $i, $r, $c, $d ); if ( $k >= 1 ) { $cands[] = array( $k + wp_rand( 0, 99 ) / 100, $r, $c, $d ); } } } }
		if ( ! $cands ) { continue; }
		usort( $cands, function ( $a, $b ) { return $b[0] <=> $a[0]; } );
		foreach ( array_slice( $cands, 0, 6 ) as $cd ) {
			$sg = $g; $se = $ents;
			$put( $i, $cd[1], $cd[2], $cd[3] );
			if ( $runs_ok() ) { $used[ $i ] = 1; break; }
			$g = $sg; $ents = $se;
		}
	}
	$letters = 0; foreach ( $g as $row ) { foreach ( $row as $x ) { if ( $x && 'L' === $x[0] ) { $letters++; } } }
	return array( 'ents' => $ents, 'score' => $letters + 3 * count( $ents ) );
}
function suorni_rkg_layout( $words, $R, $C, $tries ) {
	$best = null;
	for ( $t = 0; $t < $tries; $t++ ) { $r = suorni_rkg_try( $words, $R, $C ); if ( $r && ( ! $best || $r['score'] > $best['score'] ) ) { $best = $r; } }
	return $best;
}

/* ---------- tekoälykutsut (OpenAI-yhteensopiva rajapinta) ---------- */
function suorni_rkg_post( $path, $body, $timeout ) {
	$c = suorni_rkg_cfg();
	if ( '' === $c['key'] ) { return new WP_Error( 'suorni_nokey', 'Tekoälyavainta ei ole vielä tallennettu', array( 'status' => 400 ) ); }
	$r = wp_remote_post( 'https://api.openai.com/v1/' . $path, array( 'timeout' => $timeout, 'headers' => array( 'Authorization' => 'Bearer ' . $c['key'], 'Content-Type' => 'application/json' ), 'body' => wp_json_encode( $body ) ) );
	if ( is_wp_error( $r ) ) { return new WP_Error( 'suorni_net', 'Yhteysvirhe: ' . $r->get_error_message(), array( 'status' => 502 ) ); }
	$code = wp_remote_retrieve_response_code( $r ); $j = json_decode( wp_remote_retrieve_body( $r ), true );
	if ( $code >= 300 || ! is_array( $j ) ) {
		$m = isset( $j['error']['message'] ) ? (string) $j['error']['message'] : 'HTTP ' . $code;
		return new WP_Error( 'suorni_api', 'Tekoälypalvelu: ' . mb_substr( $m, 0, 200 ), array( 'status' => 502 ) );
	}
	return $j;
}
function suorni_rkg_fetch( $url ) {
	if ( ! preg_match( '#^https?://#i', $url ) ) { return new WP_Error( 'suorni_url', 'Linkin pitää alkaa http:// tai https://', array( 'status' => 400 ) ); }
	$r = wp_safe_remote_get( $url, array( 'timeout' => 15, 'redirection' => 3, 'user-agent' => 'Mozilla/5.0 (compatible; SuorniRistikko/1.0)' ) );
	if ( is_wp_error( $r ) ) { return new WP_Error( 'suorni_fetch', 'Sivua ei saatu haettua: ' . $r->get_error_message(), array( 'status' => 502 ) ); }
	if ( wp_remote_retrieve_response_code( $r ) >= 400 ) { return new WP_Error( 'suorni_fetch', 'Sivu vastasi virheellä (' . wp_remote_retrieve_response_code( $r ) . '). Liitä jutun teksti käsin.', array( 'status' => 502 ) ); }
	$h = (string) wp_remote_retrieve_body( $r );
	$title = ''; if ( preg_match( '#<title[^>]*>(.*?)</title>#is', $h, $m ) ) { $title = trim( html_entity_decode( wp_strip_all_tags( $m[1] ), ENT_QUOTES, 'UTF-8' ) ); }
	$h = preg_replace( '#<(script|style|nav|footer|header|aside|form)[^>]*>.*?</\1>#is', ' ', $h );
	$t = trim( preg_replace( '/\s+/u', ' ', html_entity_decode( wp_strip_all_tags( $h ), ENT_QUOTES, 'UTF-8' ) ) );
	return array( 'title' => $title, 'text' => mb_substr( $t, 0, 9000 ) );
}
function suorni_rkg_words( $title, $text ) {
	$c = suorni_rkg_cfg();
	$sys = 'Teet suomenkielistä kuvaristikkoa uutisjutun aiheista. Palauta pelkkä JSON: {"title":"lyhyt ristikon nimi suomeksi (max 40 merkkiä)","words":[{"w":"SANA","h":"vihje suomeksi, max 3 sanaa, ei sisällä vastausta","ip":"short English description of a simple picture of the word"}]}. '
		. 'Anna 22 sanaa. Säännöt: sanan on oltava KONKREETTINEN, helposti piirrettävä substantiivi (esine, eläin, ruoka, paikka, kasvi, ammatti, ajoneuvo), joka liittyy jutun aiheeseen tai sen ympäristöön. '
		. 'Perusmuoto (nominatiivi yksikkö), isot kirjaimet, vain kirjaimet A-Z Å Ä Ö, 3-7 kirjainta, ei yhdyssanoja jotka eivät ole yleisiä. '
		. 'EI erisnimiä, EI oikeita henkilöitä, EI tuotemerkkejä, EI abstrakteja sanoja, EI väkivaltaa, kuolemaa, aseita, huumeita tai muuta synkkää, vaikka juttu käsittelisi sitä: valitse silloin neutraaleja ympäristön esineitä. '
		. 'Kuvakuvaus (ip) on neutraali, ilman tekstiä, ihmisiä tai logoja.';
	$j = suorni_rkg_post( 'chat/completions', array( 'model' => $c['tm'], 'response_format' => array( 'type' => 'json_object' ), 'messages' => array( array( 'role' => 'system', 'content' => $sys ), array( 'role' => 'user', 'content' => "Otsikko: $title\n\nJuttu:\n" . $text ) ) ), 60 );
	if ( is_wp_error( $j ) ) { return $j; }
	$o = json_decode( isset( $j['choices'][0]['message']['content'] ) ? $j['choices'][0]['message']['content'] : '', true );
	if ( ! is_array( $o ) || empty( $o['words'] ) || ! is_array( $o['words'] ) ) { return new WP_Error( 'suorni_ai', 'Tekoäly ei palauttanut sanoja, yritä uudelleen', array( 'status' => 502 ) ); }
	$out = array(); $seen = array();
	foreach ( $o['words'] as $x ) {
		if ( ! is_array( $x ) || empty( $x['w'] ) || empty( $x['ip'] ) ) { continue; }
		$w = mb_strtoupper( trim( (string) $x['w'] ), 'UTF-8' );
		if ( ! preg_match( '/^[A-ZÅÄÖ]{3,7}$/u', $w ) || isset( $seen[ $w ] ) ) { continue; }
		$h = trim( preg_replace( '/[^\p{L}\p{N} ,\-]/u', '', (string) ( isset( $x['h'] ) ? $x['h'] : '' ) ) );
		if ( false !== mb_stripos( $h, $w, 0, 'UTF-8' ) ) { $h = ''; }
		$seen[ $w ] = 1;
		$out[] = array( 'w' => $w, 'h' => mb_substr( $h, 0, 30 ), 'ip' => mb_substr( sanitize_text_field( (string) $x['ip'] ), 0, 120 ) );
	}
	return array( 'title' => isset( $o['title'] ) ? mb_substr( sanitize_text_field( (string) $o['title'] ), 0, 40 ) : '', 'words' => $out );
}
function suorni_rkg_image( $ip, $n ) {
	$c = suorni_rkg_cfg();
	$prompt = 'Simple friendly flat illustration of ' . $ip . ', bold clean shapes, bright cheerful colors, centered on a plain white background. No text, no letters, no numbers, no logos, no real people.';
	$j = suorni_rkg_post( 'images/generations', array( 'model' => $c['im'], 'prompt' => $prompt, 'size' => '1024x1024', 'quality' => 'low', 'n' => 1 ), 90 );
	if ( is_wp_error( $j ) ) { return $j; }
	$b = isset( $j['data'][0]['b64_json'] ) ? base64_decode( $j['data'][0]['b64_json'], true ) : false;
	if ( false === $b || strlen( $b ) < 500 ) { return new WP_Error( 'suorni_img', 'Kuvaa ei saatu (tyhjä vastaus)', array( 'status' => 502 ) ); }
	$d = suorni_rkg_dir(); wp_mkdir_p( $d['path'] );
	$base = 'd' . time() . '_' . (int) $n;
	$raw = $d['path'] . $base . '.png';
	if ( false === file_put_contents( $raw, $b ) ) { return new WP_Error( 'suorni_img', 'Kuvaa ei voitu tallentaa', array( 'status' => 500 ) ); }
	$file = $base . '.png';
	$ed = wp_get_image_editor( $raw );
	if ( ! is_wp_error( $ed ) ) {
		$ed->resize( 256, 256, true ); $ed->set_quality( 82 );
		$sv = $ed->save( $d['path'] . $base . '.jpg', 'image/jpeg' );
		if ( ! is_wp_error( $sv ) && file_exists( $d['path'] . $base . '.jpg' ) ) { @unlink( $raw ); $file = $base . '.jpg'; }
	}
	suorni_rkg_bump( 'i' );
	if ( function_exists( 'suorni_usage_add' ) ) { suorni_usage_add( 'ai', 1 ); }
	return $file;
}
function suorni_rkg_unlink( $files ) { $d = suorni_rkg_dir(); foreach ( (array) $files as $f ) { if ( is_string( $f ) && preg_match( '/^[A-Za-z0-9_]+\.(jpg|png)$/', $f ) ) { @unlink( $d['path'] . $f ); } } }

/* ---------- ylläpidon rajapinnat ---------- */
function suorni_rkg_draft_view( $dr ) {
	if ( ! is_array( $dr ) || empty( $dr['ents'] ) ) { return null; }
	$d = suorni_rkg_dir(); $o = array();
	foreach ( $dr['ents'] as $e ) { $o[] = array( 'w' => $e['w'], 'h' => $e['h'], 'r' => $e['r'], 'c' => $e['c'], 'd' => $e['d'], 'img' => ! empty( $e['f'] ) ? $d['url'] . $e['f'] : '' ); }
	return array( 'title' => $dr['title'], 'src' => $dr['src'], 'rows' => (int) $dr['rows'], 'cols' => (int) $dr['cols'], 'ents' => $o );
}
function suorni_rkg_admin() {
	$c = suorni_rkg_cfg(); $list = array();
	foreach ( (array) get_option( 'suorni_rk_custom', array() ) as $p ) { $list[] = array( 'id' => (int) $p['id'], 'title' => $p['title'], 'n' => count( $p['ents'] ) ); }
	return array( 'haskey' => '' !== $c['key'], 'tm' => $c['tm'], 'im' => $c['im'], 'max' => $c['max'], 'maxi' => $c['maxi'], 'today' => suorni_rkg_count( 'p' ), 'todayi' => suorni_rkg_count( 'i' ),
		'draft' => suorni_rkg_draft_view( get_option( 'suorni_rkg_draft', null ) ), 'list' => $list, 'lb' => suorni_rkg_lb_admin(), 'url' => add_query_arg( 'ristikko', '1', home_url( '/' ) ) );
}
function suorni_rkg_cfg_post( $req ) {
	$c = suorni_rkg_cfg(); $key = trim( (string) $req->get_param( 'key' ) );
	if ( '' !== $key ) { $c['key'] = sanitize_text_field( $key ); }
	if ( $req->get_param( 'clearkey' ) ) { $c['key'] = ''; }
	foreach ( array( 'tm', 'im' ) as $f ) { $v = trim( (string) $req->get_param( $f ) ); if ( '' !== $v ) { $c[ $f ] = sanitize_text_field( $v ); } }
	foreach ( array( 'max', 'maxi' ) as $f ) { $v = (int) $req->get_param( $f ); if ( $v > 0 ) { $c[ $f ] = $v; } }
	update_option( 'suorni_rkg_cfg', $c, false );
	return suorni_json( suorni_rkg_admin() );
}
function suorni_rkg_gen( $req ) {
	$c = suorni_rkg_cfg();
	if ( '' === $c['key'] ) { return new WP_Error( 'suorni_nokey', 'Tallenna ensin tekoälyavain', array( 'status' => 400 ) ); }
	if ( suorni_rkg_count( 'p' ) >= $c['max'] ) { return new WP_Error( 'suorni_cap', 'Päivän ristikkoraja (' . $c['max'] . ') täyttyi', array( 'status' => 429 ) ); }
	$url = trim( (string) $req->get_param( 'url' ) ); $text = trim( (string) $req->get_param( 'text' ) ); $title = ''; $topic = trim( sanitize_text_field( (string) $req->get_param( 'topic' ) ) );
	if ( '' !== $topic ) {
		$title = mb_substr( $topic, 0, 60 ); $url = '';
		$text = 'Ei uutisjuttua. Tee ristikko aiheesta: ' . mb_substr( $topic, 0, 120 ) . '. Keksi aiheeseen liittyviä konkreettisia, kuvattavia sanoja.';
	} elseif ( mb_strlen( $text ) < 200 ) {
		if ( '' === $url ) { return new WP_Error( 'suorni_bad', 'Anna uutisen linkki, liitä jutun teksti (vähintään 200 merkkiä) tai kirjoita aihe', array( 'status' => 400 ) ); }
		$f = suorni_rkg_fetch( $url ); if ( is_wp_error( $f ) ) { return $f; }
		$title = $f['title']; $text = $f['text'];
		if ( mb_strlen( $text ) < 200 ) { return new WP_Error( 'suorni_fetch', 'Sivulta ei saatu tekstiä (maksumuuri?). Liitä jutun teksti käsin.', array( 'status' => 502 ) ); }
	}
	$w = suorni_rkg_words( $title, mb_substr( $text, 0, 9000 ) ); if ( is_wp_error( $w ) ) { return $w; }
	suorni_rkg_bump( 'p' );
	if ( function_exists( 'suorni_usage_add' ) ) { suorni_usage_add( 'ai', 1 ); }
	$R = 8; $C = 7;
	$lay = suorni_rkg_layout( $w['words'], $R, $C, 150 );
	if ( ! $lay || count( $lay['ents'] ) < 5 ) { return new WP_Error( 'suorni_few', 'Sanoista ei saatu sopivaa ristikkoa (' . ( $lay ? count( $lay['ents'] ) : 0 ) . ' sanaa mahtui). Kokeile toista juttua tai uudelleen.', array( 'status' => 422 ) ); }
	$old = get_option( 'suorni_rkg_draft', null ); if ( is_array( $old ) && ! empty( $old['ents'] ) ) { suorni_rkg_unlink( array_column( $old['ents'], 'f' ) ); }
	$ents = array();
	foreach ( $lay['ents'] as $e ) { $x = $w['words'][ $e['i'] ]; $ents[] = array( 'w' => $x['w'], 'h' => '' !== $x['h'] ? $x['h'] : 'kuvassa', 'ip' => $x['ip'], 'r' => $e['r'], 'c' => $e['c'], 'd' => $e['d'], 'f' => '' ); }
	$dr = array( 'title' => '' !== $w['title'] ? $w['title'] : ( '' !== $title ? mb_substr( $title, 0, 40 ) : 'Uutisristikko' ), 'src' => esc_url_raw( $url ), 'rows' => $R, 'cols' => $C, 'ents' => $ents, 't' => time() );
	update_option( 'suorni_rkg_draft', $dr, false );
	return suorni_json( suorni_rkg_admin() );
}
function suorni_rkg_img( $req ) {
	$c = suorni_rkg_cfg(); $dr = get_option( 'suorni_rkg_draft', null ); $n = (int) $req->get_param( 'n' );
	if ( ! is_array( $dr ) || ! isset( $dr['ents'][ $n ] ) ) { return new WP_Error( 'suorni_bad', 'Luonnosta tai sanaa ei löydy', array( 'status' => 404 ) ); }
	if ( suorni_rkg_count( 'i' ) >= $c['maxi'] ) { return new WP_Error( 'suorni_cap', 'Päivän kuvaraja (' . $c['maxi'] . ') täyttyi', array( 'status' => 429 ) ); }
	$f = suorni_rkg_image( $dr['ents'][ $n ]['ip'], $n ); if ( is_wp_error( $f ) ) { return $f; }
	$dr = get_option( 'suorni_rkg_draft', null );
	if ( is_array( $dr ) && isset( $dr['ents'][ $n ] ) ) { $old = $dr['ents'][ $n ]['f']; $dr['ents'][ $n ]['f'] = $f; update_option( 'suorni_rkg_draft', $dr, false ); if ( $old ) { suorni_rkg_unlink( $old ); } }
	return suorni_json( suorni_rkg_admin() );
}
function suorni_rkg_pub( $req ) {
	$dr = get_option( 'suorni_rkg_draft', null );
	if ( ! is_array( $dr ) || empty( $dr['ents'] ) ) { return new WP_Error( 'suorni_bad', 'Ei luonnosta', array( 'status' => 404 ) ); }
	foreach ( $dr['ents'] as $e ) { if ( empty( $e['f'] ) ) { return new WP_Error( 'suorni_bad', 'Kaikilla sanoilla ei vielä ole kuvaa', array( 'status' => 400 ) ); } }
	$title = trim( (string) $req->get_param( 'title' ) ); if ( '' !== $title ) { $dr['title'] = mb_substr( sanitize_text_field( $title ), 0, 40 ); }
	$list = get_option( 'suorni_rk_custom', array() ); if ( ! is_array( $list ) ) { $list = array(); }
	$id = 100; foreach ( $list as $p ) { $id = max( $id, (int) $p['id'] ); } $id++;
	$ents = array(); foreach ( $dr['ents'] as $e ) { $ents[] = array( 'w' => $e['w'], 'e' => '', 'f' => $e['f'], 'h' => $e['h'], 'r' => $e['r'], 'c' => $e['c'], 'd' => $e['d'] ); }
	$list[] = array( 'id' => $id, 'title' => $dr['title'], 'r' => (int) $dr['rows'], 'c' => (int) $dr['cols'], 'ents' => $ents, 't' => time() );
	update_option( 'suorni_rk_custom', $list, false ); delete_option( 'suorni_rkg_draft' );
	return suorni_json( suorni_rkg_admin() );
}
function suorni_rkg_discard() {
	$dr = get_option( 'suorni_rkg_draft', null );
	if ( is_array( $dr ) && ! empty( $dr['ents'] ) ) { suorni_rkg_unlink( array_column( $dr['ents'], 'f' ) ); }
	delete_option( 'suorni_rkg_draft' );
	return suorni_json( suorni_rkg_admin() );
}
function suorni_rkg_pzdel( $req ) {
	$id = (int) $req->get_param( 'id' ); $list = get_option( 'suorni_rk_custom', array() ); $keep = array();
	foreach ( (array) $list as $p ) { if ( (int) $p['id'] === $id ) { suorni_rkg_unlink( array_column( $p['ents'], 'f' ) ); } else { $keep[] = $p; } }
	update_option( 'suorni_rk_custom', $keep, false );
	return suorni_json( suorni_rkg_admin() );
}
add_action( 'rest_api_init', function () {
	foreach ( array( 'rkcfg' => 'suorni_rkg_cfg_post', 'gen' => 'suorni_rkg_gen', 'img' => 'suorni_rkg_img', 'pub' => 'suorni_rkg_pub', 'discard' => 'suorni_rkg_discard', 'pzdel' => 'suorni_rkg_pzdel' ) as $r => $cb ) {
		register_rest_route( 'suorni/v1', '/ristikko/' . $r, array( 'methods' => 'POST', 'callback' => $cb, 'permission_callback' => 'suorni_can' ) );
	}
} );

/* pistepörssin nimien hyväksyntä (ohjaamo) */
function suorni_rkg_lb_admin() {
	$l = get_option( 'suorni_rk_lb', array() ); if ( ! is_array( $l ) ) { return array(); }
	uasort( $l, function ( $a, $b ) { return ( (int) $a['ok'] === 0 ? 0 : 1 ) <=> ( (int) $b['ok'] === 0 ? 0 : 1 ) ?: $b['a'] <=> $a['a']; } );
	$o = array(); foreach ( array_slice( $l, 0, 40, true ) as $h => $x ) { $o[] = array( 'h' => $h, 'n' => $x['n'], 'ok' => (int) $x['ok'], 'a' => (int) $x['a'], 'g' => (int) $x['g'] ); }
	return $o;
}
function suorni_rkg_lbact( $req ) {
	$h = preg_replace( '/[^a-f0-9]/', '', (string) $req->get_param( 'h' ) ); $do = (string) $req->get_param( 'do' );
	$l = get_option( 'suorni_rk_lb', array() ); if ( ! is_array( $l ) || ! isset( $l[ $h ] ) ) { return new WP_Error( 'suorni_bad', 'Pelaajaa ei löydy', array( 'status' => 404 ) ); }
	if ( 'ok' === $do ) { $l[ $h ]['ok'] = 1; } elseif ( 'no' === $do ) { $l[ $h ]['ok'] = 2; } elseif ( 'del' === $do ) { unset( $l[ $h ] ); }
	update_option( 'suorni_rk_lb', $l, false );
	return suorni_json( suorni_rkg_admin() );
}
add_action( 'rest_api_init', function () {
	register_rest_route( 'suorni/v1', '/ristikko/lbact', array( 'methods' => 'POST', 'callback' => 'suorni_rkg_lbact', 'permission_callback' => 'suorni_can' ) );
} );

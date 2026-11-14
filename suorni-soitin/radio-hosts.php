<?php
/* Ihmisjuontajat: omat tunnukset (rooli suorni_host), studionäkymä (?suorni_studio=1) ja äänitettyjen juontojen soitto radiossa.
   Äänitteet eivät ole mediakirjaston liitteitä (muuten ne päätyisivät kappalelistalle): tiedostot uploads/suorni-host/, tiedot optiossa suorni_hclips. */
if ( ! defined( 'ABSPATH' ) ) { exit; }

/* ---------- Rooli ---------- */
add_action( 'init', function () {
	if ( ! get_role( 'suorni_host' ) ) { add_role( 'suorni_host', 'Radiojuontaja', array( 'read' => true ) ); }
	if ( ! wp_next_scheduled( 'suorni_hclip_gc' ) ) { wp_schedule_event( time() + 900, 'daily', 'suorni_hclip_gc' ); }
} );
function suorni_is_host( $u = null ) {
	if ( ! $u ) { $u = wp_get_current_user(); }
	return $u && $u->ID && in_array( 'suorni_host', (array) $u->roles, true );
}
function suorni_host_blocked( $uid ) { return (bool) get_user_meta( (int) $uid, 'suorni_host_blocked', true ); }
/* Studion käyttöoikeus: aktiivinen juontaja tai ohjaamon ylläpitäjä (testaus) */
function suorni_host_ok() {
	if ( ! is_user_logged_in() ) { return false; }
	$u = wp_get_current_user();
	if ( suorni_is_host( $u ) ) { return ! suorni_host_blocked( $u->ID ); }
	return current_user_can( 'upload_files' );
}
function suorni_host_manage() { return current_user_can( 'promote_users' ) && current_user_can( 'upload_files' ); }
function suorni_studio_url() { return add_query_arg( 'suorni_studio', '1', home_url( '/' ) ); }
add_filter( 'wp_authenticate_user', function ( $user ) {
	if ( $user instanceof WP_User && suorni_is_host( $user ) && suorni_host_blocked( $user->ID ) ) { return new WP_Error( 'suorni_blocked', '<strong>Tunnus on suljettu.</strong> Ota yhteys radion ylläpitoon.' ); }
	return $user;
} );
/* Juontaja ei pääse wp-adminiin eikä näe työkalupalkkia: kirjautuminen vie suoraan studioon */
add_action( 'admin_init', function () {
	if ( suorni_is_host() && ! wp_doing_ajax() && ! defined( 'REST_REQUEST' ) ) { wp_safe_redirect( suorni_studio_url() ); exit; }
} );
add_filter( 'show_admin_bar', function ( $s ) { return suorni_is_host() ? false : $s; } );
add_filter( 'login_redirect', function ( $to, $req, $user ) {
	return ( $user instanceof WP_User && suorni_is_host( $user ) ) ? suorni_studio_url() : $to;
}, 20, 3 );

/* ---------- Äänitteiden tallennus ---------- */
function suorni_hclips_raw() { $l = get_option( 'suorni_hclips', array() ); return is_array( $l ) ? $l : array(); }
function suorni_hclip_dir() {
	$u = wp_upload_dir();
	$d = trailingslashit( $u['basedir'] ) . 'suorni-host/';
	if ( ! is_dir( $d ) ) { wp_mkdir_p( $d ); @file_put_contents( $d . 'index.html', '' ); }
	return $d;
}
function suorni_hclip_url( $file ) { $u = wp_upload_dir(); return trailingslashit( $u['baseurl'] ) . 'suorni-host/' . rawurlencode( $file ); }
function suorni_hclip_norm( $c ) {
	return array(
		'id'      => isset( $c['id'] ) ? sanitize_key( $c['id'] ) : '',
		'uid'     => isset( $c['uid'] ) ? (int) $c['uid'] : 0,
		'hn'      => mb_substr( sanitize_text_field( isset( $c['hn'] ) ? (string) $c['hn'] : '' ), 0, 40 ),
		'title'   => mb_substr( sanitize_text_field( isset( $c['title'] ) ? (string) $c['title'] : '' ), 0, 60 ),
		'file'    => isset( $c['file'] ) ? sanitize_file_name( (string) $c['file'] ) : '',
		'dur'     => isset( $c['dur'] ) ? max( 1, min( 600, (int) $c['dur'] ) ) : 1,
		'mode'    => ( isset( $c['mode'] ) && 'time' === $c['mode'] ) ? 'time' : 'now',
		'time'    => ( isset( $c['time'] ) && preg_match( '/^([01]?\d|2[0-3]):([0-5]\d)$/', (string) $c['time'], $m ) ) ? sprintf( '%02d:%s', (int) $m[1], $m[2] ) : '12:00',
		'date'    => isset( $c['date'] ) ? preg_replace( '/[^0-9]/', '', (string) $c['date'] ) : '',
		'daily'   => ! empty( $c['daily'] ),
		'on'      => ! isset( $c['on'] ) || ! empty( $c['on'] ),
		'created' => isset( $c['created'] ) ? (int) $c['created'] : 0,
		'last'    => isset( $c['last'] ) ? (int) $c['last'] : 0,
		'lastday' => isset( $c['lastday'] ) ? preg_replace( '/[^0-9]/', '', (string) $c['lastday'] ) : '',
	);
}
function suorni_hclips() {
	$out = array();
	foreach ( suorni_hclips_raw() as $c ) { if ( is_array( $c ) && ! empty( $c['id'] ) && ! empty( $c['file'] ) ) { $out[] = suorni_hclip_norm( $c ); } }
	return $out;
}
function suorni_hclips_save( $l ) { update_option( 'suorni_hclips', array_values( $l ), false ); }
function suorni_hclip_find( $id ) { foreach ( suorni_hclips() as $c ) { if ( $c['id'] === $id ) { return $c; } } return null; }
function suorni_hclip_unlink( $c ) {
	$f = suorni_hclip_dir() . $c['file'];
	if ( '' !== $c['file'] && is_file( $f ) ) { @unlink( $f ); }
}
function suorni_hclip_delete( $id ) {
	$keep = array();
	foreach ( suorni_hclips() as $c ) { if ( $c['id'] === $id ) { suorni_hclip_unlink( $c ); } else { $keep[] = $c; } }
	suorni_hclips_save( $keep );
}
/* Toistuva tila: lastday (pääkanava) tai suorni_scr_ld (muut kanavat) – sama malli kuin omissa juonnoissa */
function suorni_hclip_mark( $id, $ts = 0 ) {
	$l = suorni_hclips_raw();
	foreach ( $l as $i => $c ) {
		if ( ! is_array( $c ) || ! isset( $c['id'] ) || $c['id'] !== $id ) { continue; }
		$l[ $i ]['last'] = time();
		if ( '' === suorni_ch_cur() ) { $l[ $i ]['lastday'] = wp_date( 'Ymd', $ts ? $ts : time() ); }
	}
	update_option( 'suorni_hclips', $l, false );
	$c = suorni_ch_cur();
	if ( '' !== $c ) {
		$m = get_option( 'suorni_scr_ld', array() );
		if ( ! is_array( $m ) ) { $m = array(); }
		$m[ $id . '|' . $c ] = wp_date( 'Ymd', $ts ? $ts : time() );
		update_option( 'suorni_scr_ld', $m, false );
	}
}
/* Moottorille: ajastetut äänitteet käsikirjoituksen muodossa (aud = äänitiedoston osoite) */
function suorni_hclip_scripts() {
	$out = array();
	foreach ( suorni_hclips() as $c ) {
		if ( 'time' !== $c['mode'] || ! $c['on'] || ! is_file( suorni_hclip_dir() . $c['file'] ) ) { continue; }
		$out[] = array(
			'id' => $c['id'], 'title' => $c['title'], 'text' => '', 'mode' => 'time', 'time' => $c['time'],
			'days' => $c['daily'] ? array( 1, 2, 3, 4, 5, 6, 7 ) : array( 1, 2, 3, 4, 5, 6, 7 ), 'date' => $c['daily'] ? '' : $c['date'],
			'n' => 3, 'prog' => '', 'on' => true, 'replace' => false, 'done' => 0, 'last' => $c['last'], 'lastday' => $c['lastday'],
			'aud' => suorni_hclip_url( $c['file'] ), 'dur' => $c['dur'], 'hn' => $c['hn'],
		);
	}
	return $out;
}
/* Aikajanan kohde äänitteestä */
function suorni_hclip_item( $c ) {
	return array( 'type' => 'dj', 'url' => suorni_hclip_url( $c['file'] ), 'dur' => (int) $c['dur'], 'title' => '' !== $c['hn'] ? $c['hn'] : 'Juontaja', 'artist' => '', 'text' => $c['title'], 'own' => 1, 'human' => 1 );
}
function suorni_hclip_item_from_script( $s ) {
	return array( 'type' => 'dj', 'url' => $s['aud'], 'dur' => (int) $s['dur'], 'title' => '' !== $s['hn'] ? $s['hn'] : 'Juontaja', 'artist' => '', 'text' => $s['title'], 'own' => 1, 'human' => 1 );
}
/* Soita heti: kaikille kanaville nykyisen kohteen jälkeen */
function suorni_hclip_air_now( $c ) {
	$cur = suorni_ch_cur();
	$n   = 0;
	foreach ( suorni_ch_ids() as $ch ) {
		suorni_ch_set( $ch );
		$cfg  = suorni_radio_cfg();
		$prog = suorni_radio_prog_at( $cfg, time() );
		$it   = suorni_hclip_item( $c );
		if ( suorni_radio_insert_now( $it, $prog ) ) { $n++; }
	}
	suorni_ch_set( $cur );
	$l = suorni_hclips_raw();
	foreach ( $l as $i => $x ) { if ( is_array( $x ) && isset( $x['id'] ) && $x['id'] === $c['id'] ) { $l[ $i ]['last'] = time(); } }
	update_option( 'suorni_hclips', $l, false );
	return $n;
}
/* Siivous: kertasoitot ja vanhat "heti"-äänitteet 14 pv jälkeen pois (levytila) */
add_action( 'suorni_hclip_gc', function () {
	$keep = array();
	$lim  = time() - 14 * 86400;
	foreach ( suorni_hclips() as $c ) {
		$old = $c['created'] < $lim && ( 'now' === $c['mode'] || ( ! $c['daily'] && $c['last'] > 0 && $c['last'] < $lim ) );
		if ( $old ) { suorni_hclip_unlink( $c ); } else { $keep[] = $c; }
	}
	suorni_hclips_save( $keep );
} );

/* ---------- Hallinta (ohjaamo) ---------- */
function suorni_hosts_list() {
	$clips = suorni_hclips();
	$out   = array();
	foreach ( get_users( array( 'role' => 'suorni_host', 'orderby' => 'display_name', 'number' => 100 ) ) as $u ) {
		$mine = array();
		foreach ( $clips as $c ) {
			if ( $c['uid'] === (int) $u->ID ) { $mine[] = suorni_hclip_view( $c ); }
		}
		$out[] = array( 'id' => (int) $u->ID, 'name' => $u->display_name, 'login' => $u->user_login, 'email' => $u->user_email, 'blocked' => suorni_host_blocked( $u->ID ), 'clips' => $mine, 'created' => strtotime( $u->user_registered ) );
	}
	return array( 'hosts' => $out, 'studio' => suorni_studio_url() );
}
function suorni_hclip_view( $c ) {
	$now = time();
	if ( 'now' === $c['mode'] ) { $st = $c['last'] ? 'soitettu ' . wp_date( 'j.n. H:i', $c['last'] ) : 'ei vielä soitettu'; $when = 'heti'; }
	else {
		$when = ( $c['daily'] ? 'joka päivä' : ( strlen( $c['date'] ) === 8 ? ltrim( substr( $c['date'], 6, 2 ), '0' ) . '.' . ltrim( substr( $c['date'], 4, 2 ), '0' ) . '.' : '' ) ) . ' klo ' . $c['time'];
		if ( $c['daily'] ) { $st = $c['last'] ? 'viimeksi ' . wp_date( 'j.n. H:i', $c['last'] ) : 'odottaa'; }
		elseif ( $c['last'] ) { $st = 'soitettu ' . wp_date( 'j.n. H:i', $c['last'] ); }
		else {
			$at = ( strlen( $c['date'] ) === 8 ) ? strtotime( substr( $c['date'], 0, 4 ) . '-' . substr( $c['date'], 4, 2 ) . '-' . substr( $c['date'], 6, 2 ) . ' ' . $c['time'] . ':00 ' . wp_timezone_string() ) : 0;
			$st = ( $at && $now - $at > 900 ) ? 'ohitettu' : 'odottaa';
		}
	}
	return array( 'id' => $c['id'], 'title' => $c['title'], 'hn' => $c['hn'], 'uid' => $c['uid'], 'dur' => $c['dur'], 'when' => $when, 'status' => $st, 'mode' => $c['mode'], 'url' => suorni_hclip_url( $c['file'] ), 'created' => $c['created'] );
}
function suorni_host_login_free( $want ) {
	$base = sanitize_user( strtolower( remove_accents( $want ) ), true );
	$base = preg_replace( '/[^a-z0-9._-]/', '', $base );
	if ( strlen( $base ) < 3 ) { $base = 'juontaja' . ( '' === $base ? '' : $base ); }
	$login = $base;
	for ( $i = 2; username_exists( $login ) && $i < 200; $i++ ) { $login = $base . $i; }
	return $login;
}
function suorni_hosts_act_post( $req ) {
	$act = sanitize_key( (string) $req->get_param( 'act' ) );
	$uid = (int) $req->get_param( 'uid' );
	$res = array( 'ok' => true );
	if ( 'add' === $act ) {
		$name = mb_substr( sanitize_text_field( (string) $req->get_param( 'name' ) ), 0, 40 );
		if ( '' === $name ) { return new WP_Error( 'suorni_bad', 'Anna juontajan nimi.', array( 'status' => 400 ) ); }
		$login = suorni_host_login_free( '' !== trim( (string) $req->get_param( 'login' ) ) ? (string) $req->get_param( 'login' ) : $name );
		$email = sanitize_email( (string) $req->get_param( 'email' ) );
		if ( '' !== $email && email_exists( $email ) ) { return new WP_Error( 'suorni_bad', 'Sähköposti on jo käytössä toisella tunnuksella.', array( 'status' => 400 ) ); }
		$pw  = wp_generate_password( 12, false );
		$uid = wp_insert_user( array( 'user_login' => $login, 'user_pass' => $pw, 'user_email' => $email, 'display_name' => $name, 'nickname' => $name, 'role' => 'suorni_host', 'show_admin_bar_front' => 'false' ) );
		if ( is_wp_error( $uid ) ) { return new WP_Error( 'suorni_bad', $uid->get_error_message(), array( 'status' => 400 ) ); }
		$res['cred'] = array( 'name' => $name, 'login' => $login, 'pass' => $pw );
	} else {
		$u = $uid ? get_userdata( $uid ) : null;
		if ( ! $u || ! suorni_is_host( $u ) ) { return new WP_Error( 'suorni_bad', 'Juontajaa ei löytynyt.', array( 'status' => 404 ) ); }
		if ( 'reset' === $act ) {
			$pw = wp_generate_password( 12, false );
			wp_set_password( $pw, $uid );
			$res['cred'] = array( 'name' => $u->display_name, 'login' => $u->user_login, 'pass' => $pw );
		} elseif ( 'block' === $act ) {
			update_user_meta( $uid, 'suorni_host_blocked', 1 );
			WP_Session_Tokens::get_instance( $uid )->destroy_all();
		} elseif ( 'unblock' === $act ) {
			delete_user_meta( $uid, 'suorni_host_blocked' );
		} elseif ( 'delete' === $act ) {
			foreach ( suorni_hclips() as $c ) { if ( $c['uid'] === $uid ) { suorni_hclip_delete( $c['id'] ); } }
			require_once ABSPATH . 'wp-admin/includes/user.php';
			wp_delete_user( $uid );
		} else { return new WP_Error( 'suorni_bad', 'Tuntematon toiminto.', array( 'status' => 400 ) ); }
	}
	return suorni_json( array_merge( $res, suorni_hosts_list() ) );
}
function suorni_hosts_clipdel_post( $req ) {
	suorni_hclip_delete( sanitize_key( (string) $req->get_param( 'id' ) ) );
	return suorni_json( array_merge( array( 'ok' => true ), suorni_hosts_list() ) );
}

/* ---------- Studion rajapinta ---------- */
function suorni_studio_own( $c ) {
	return suorni_host_ok() && ( (int) $c['uid'] === get_current_user_id() || suorni_host_manage() );
}
function suorni_studio_info() {
	$cfg  = suorni_radio_cfg();
	$prog = suorni_radio_prog_at( $cfg, time() );
	$mine = array();
	foreach ( suorni_hclips() as $c ) {
		if ( (int) $c['uid'] === get_current_user_id() ) { $mine[] = suorni_hclip_view( $c ); }
	}
	usort( $mine, function ( $a, $b ) { return $b['created'] - $a['created']; } );
	return array( 'prog' => isset( $prog['name'] ) ? $prog['name'] : '', 'human' => ! empty( $prog['human'] ), 'clips' => $mine, 'max' => 240 );
}
function suorni_studio_get( $req ) { return suorni_json( suorni_studio_info() ); }
function suorni_studio_clip_post( $req ) {
	$u = wp_get_current_user();
	if ( empty( $_FILES['file'] ) || ! is_array( $_FILES['file'] ) || ! isset( $_FILES['file']['tmp_name'] ) || ! is_uploaded_file( $_FILES['file']['tmp_name'] ) ) { return new WP_Error( 'suorni_up', 'Äänitettä ei tullut perille.', array( 'status' => 400 ) ); }
	$tmp  = $_FILES['file']['tmp_name'];
	$size = (int) filesize( $tmp );
	if ( $size < 2000 || $size > 6000000 ) { return new WP_Error( 'suorni_up', 'Äänite on liian lyhyt tai liian suuri.', array( 'status' => 400 ) ); }
	$head = (string) file_get_contents( $tmp, false, null, 0, 4 );
	if ( 'ID3' !== substr( $head, 0, 3 ) && ! ( strlen( $head ) >= 2 && 0xFF === ord( $head[0] ) && 0xE0 === ( ord( $head[1] ) & 0xE0 ) ) ) { return new WP_Error( 'suorni_up', 'Äänite ei ole mp3-muodossa.', array( 'status' => 400 ) ); }
	if ( get_transient( 'suorni_hup_' . $u->ID ) ) { return new WP_Error( 'suorni_up', 'Odota hetki ennen seuraavaa lähetystä.', array( 'status' => 429 ) ); }
	$mine = 0;
	foreach ( suorni_hclips() as $c ) { if ( (int) $c['uid'] === (int) $u->ID ) { $mine++; } }
	if ( $mine >= 40 ) { return new WP_Error( 'suorni_up', 'Äänitteitä on jo 40. Poista vanhoja ennen uuden lisäämistä.', array( 'status' => 400 ) ); }
	require_once ABSPATH . 'wp-admin/includes/media.php';
	$meta = wp_read_audio_metadata( $tmp );
	$dur  = ( is_array( $meta ) && ! empty( $meta['length'] ) ) ? (int) ceil( $meta['length'] ) : 0;
	if ( $dur <= 0 ) { return new WP_Error( 'suorni_up', 'Äänitteen kestoa ei saatu luettua.', array( 'status' => 400 ) ); }
	if ( $dur > 240 ) { return new WP_Error( 'suorni_up', 'Äänite on liian pitkä (enintään 4 min).', array( 'status' => 400 ) ); }
	$mode  = ( 'time' === (string) $req->get_param( 'mode' ) ) ? 'time' : 'now';
	$daily = (bool) $req->get_param( 'daily' );
	$date  = preg_replace( '/[^0-9]/', '', (string) $req->get_param( 'date' ) );
	$time  = (string) $req->get_param( 'time' );
	if ( 'time' === $mode ) {
		if ( ! preg_match( '/^([01]?\d|2[0-3]):([0-5]\d)$/', $time ) ) { return new WP_Error( 'suorni_up', 'Anna kellonaika muodossa 14:30.', array( 'status' => 400 ) ); }
		if ( ! $daily && 8 !== strlen( $date ) ) { return new WP_Error( 'suorni_up', 'Valitse päivä tai rasti "joka päivä".', array( 'status' => 400 ) ); }
	}
	$title = mb_substr( sanitize_text_field( (string) $req->get_param( 'title' ) ), 0, 60 );
	if ( '' === $title ) { $title = 'Juonto ' . wp_date( 'j.n. H:i' ); }
	$id   = 'h_' . strtolower( wp_generate_password( 8, false ) );
	$file = (int) $u->ID . '-' . $id . '.mp3';
	if ( ! move_uploaded_file( $tmp, suorni_hclip_dir() . $file ) ) { return new WP_Error( 'suorni_up', 'Tallennus epäonnistui.', array( 'status' => 500 ) ); }
	set_transient( 'suorni_hup_' . $u->ID, 1, 8 );
	$c = suorni_hclip_norm( array( 'id' => $id, 'uid' => $u->ID, 'hn' => $u->display_name, 'title' => $title, 'file' => $file, 'dur' => $dur, 'mode' => $mode, 'time' => $time, 'date' => $date, 'daily' => $daily, 'created' => time() ) );
	$l = suorni_hclips_raw();
	$l[] = $c;
	suorni_hclips_save( $l );
	if ( 'now' === $mode ) { suorni_hclip_air_now( $c ); }
	return suorni_json( array_merge( array( 'ok' => true ), suorni_studio_info() ) );
}
function suorni_studio_clipdel_post( $req ) {
	$c = suorni_hclip_find( sanitize_key( (string) $req->get_param( 'id' ) ) );
	if ( ! $c || ! suorni_studio_own( $c ) ) { return new WP_Error( 'suorni_bad', 'Äänitettä ei löytynyt.', array( 'status' => 404 ) ); }
	suorni_hclip_delete( $c['id'] );
	return suorni_json( array_merge( array( 'ok' => true ), suorni_studio_info() ) );
}
function suorni_studio_clipnow_post( $req ) {
	$c = suorni_hclip_find( sanitize_key( (string) $req->get_param( 'id' ) ) );
	if ( ! $c || ! suorni_studio_own( $c ) ) { return new WP_Error( 'suorni_bad', 'Äänitettä ei löytynyt.', array( 'status' => 404 ) ); }
	if ( get_transient( 'suorni_hup_' . get_current_user_id() ) ) { return new WP_Error( 'suorni_up', 'Odota hetki.', array( 'status' => 429 ) ); }
	set_transient( 'suorni_hup_' . get_current_user_id(), 1, 8 );
	suorni_hclip_air_now( $c );
	return suorni_json( array_merge( array( 'ok' => true ), suorni_studio_info() ) );
}
function suorni_studio_pass_post( $req ) {
	$pw = (string) $req->get_param( 'pass' );
	if ( strlen( $pw ) < 10 ) { return new WP_Error( 'suorni_bad', 'Salasanan on oltava vähintään 10 merkkiä.', array( 'status' => 400 ) ); }
	$uid = get_current_user_id();
	wp_set_password( $pw, $uid );
	wp_set_current_user( $uid );
	wp_set_auth_cookie( $uid, true );
	return suorni_json( array( 'ok' => true, 'nonce' => wp_create_nonce( 'wp_rest' ) ) );
}
add_action( 'rest_api_init', function () {
	register_rest_route( 'suorni/v1', '/radio/hostact', array( 'methods' => 'POST', 'callback' => 'suorni_hosts_act_post', 'permission_callback' => 'suorni_host_manage' ) );
	register_rest_route( 'suorni/v1', '/radio/hostclipdel', array( 'methods' => 'POST', 'callback' => 'suorni_hosts_clipdel_post', 'permission_callback' => 'suorni_host_manage' ) );
	register_rest_route( 'suorni/v1', '/studio', array( 'methods' => 'GET', 'callback' => 'suorni_studio_get', 'permission_callback' => 'suorni_host_ok' ) );
	register_rest_route( 'suorni/v1', '/studio/clip', array( 'methods' => 'POST', 'callback' => 'suorni_studio_clip_post', 'permission_callback' => 'suorni_host_ok' ) );
	register_rest_route( 'suorni/v1', '/studio/clipdel', array( 'methods' => 'POST', 'callback' => 'suorni_studio_clipdel_post', 'permission_callback' => 'suorni_host_ok' ) );
	register_rest_route( 'suorni/v1', '/studio/clipnow', array( 'methods' => 'POST', 'callback' => 'suorni_studio_clipnow_post', 'permission_callback' => 'suorni_host_ok' ) );
	register_rest_route( 'suorni/v1', '/studio/pass', array( 'methods' => 'POST', 'callback' => 'suorni_studio_pass_post', 'permission_callback' => 'suorni_host_ok' ) );
} );

/* ---------- Studiosivu ---------- */
add_action( 'template_redirect', function () {
	if ( empty( $_GET['suorni_studio'] ) ) { return; }
	nocache_headers();
	header( 'X-Robots-Tag: noindex, nofollow' );
	header( 'Content-Type: text/html; charset=utf-8' );
	$css = '*{box-sizing:border-box}body{margin:0;background:#0f141d;color:#eef1f6;font:16px/1.45 system-ui,-apple-system,Segoe UI,Roboto,sans-serif}.w{max-width:640px;margin:0 auto;padding:18px 16px 60px}h1{font-size:22px;margin:6px 0 2px;color:#ffb347}h2{font-size:16px;margin:0 0 10px;color:#ffb347}.card{background:#182131;border:1px solid #26344d;border-radius:14px;padding:16px;margin:14px 0}.mut{color:#9aa7bd;font-size:14px}button,.btn{font:inherit;border:0;border-radius:10px;padding:11px 16px;background:#ff8a1f;color:#1a1004;font-weight:700;cursor:pointer}button.sec{background:#2a3a57;color:#eef1f6}button.red{background:#c63a3a;color:#fff}button:disabled{opacity:.45;cursor:default}input[type=text],input[type=password],input[type=date],input[type=time]{font:inherit;background:#0f141d;color:#eef1f6;border:1px solid #33466a;border-radius:8px;padding:9px 10px;width:100%}label{display:block;margin:10px 0 4px;font-size:14px;color:#c4cee0}.row{display:flex;gap:10px;flex-wrap:wrap;align-items:center}.row>*{flex:0 0 auto}#rec{width:100%;font-size:19px;padding:16px}#lvl{height:10px;background:#0f141d;border-radius:6px;overflow:hidden;margin:12px 0}#lvl i{display:block;height:100%;width:0;background:#4cd37b}audio{width:100%;margin:10px 0}.cl{border-top:1px solid #26344d;padding:10px 0}.cl b{display:block}.err{color:#ff8a8a}.ok{color:#6fe39a}.on{background:#25402f;border-radius:8px;padding:8px 10px;font-size:14px}';
	echo '<!doctype html><html lang="fi"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Radio Suorni – juontajan studio</title><style>' . $css . '</style></head><body><div class="w">';
	if ( ! is_user_logged_in() ) {
		echo '<h1>🎙️ Juontajan studio</h1><p class="mut">Kirjaudu omilla tunnuksillasi.</p><div class="card">';
		wp_login_form( array( 'echo' => true, 'redirect' => suorni_studio_url(), 'label_username' => 'Käyttäjätunnus', 'label_password' => 'Salasana', 'label_remember' => 'Pysy kirjautuneena', 'label_log_in' => 'Kirjaudu' ) );
		echo '</div></div></body></html>';
		exit;
	}
	if ( ! suorni_host_ok() ) {
		echo '<h1>🎙️ Juontajan studio</h1><div class="card err">Tunnuksellasi ei ole pääsyä studioon. Ota yhteys radion ylläpitoon.</div><p><a style="color:#9aa7bd" href="' . esc_url( wp_logout_url( suorni_studio_url() ) ) . '">Kirjaudu ulos</a></p></div></body></html>';
		exit;
	}
	$u   = wp_get_current_user();
	$cfg = array( 'rest' => esc_url_raw( rest_url( 'suorni/v1/' ) ), 'nonce' => wp_create_nonce( 'wp_rest' ), 'name' => $u->display_name, 'lame' => plugins_url( 'lame.min.js', dirname( __FILE__ ) . '/suorni-soitin.php' ), 'today' => wp_date( 'Y-m-d' ), 'now' => wp_date( 'H:i' ), 'logout' => wp_logout_url( suorni_studio_url() ) );
	echo '<h1>🎙️ Juontajan studio</h1><div class="mut">Hei <b id="hn"></b>! <a style="color:#9aa7bd" id="lo" href="#">Kirjaudu ulos</a></div>';
	echo '<div class="card"><div id="info" class="on">…</div></div>';
	echo '<div class="card"><h2>1. Äänitä juonto</h2><button id="rec">🔴 Aloita äänitys</button><div id="lvl"><i></i></div><div class="mut" id="tm">Enintään 3 minuuttia. Puhu mikrofoniin tavalliseen tapaan; äänite kuullaan radiossa sellaisenaan.</div><div id="pv" hidden><audio id="au" controls></audio></div></div>';
	echo '<div class="card" id="sendc" hidden><h2>2. Lähetä radioon</h2><label>Otsikko (näkyy vain sinulle ja ohjaamossa)</label><input type="text" id="ti" maxlength="60" placeholder="esim. Iltatervehdys">';
	echo '<label><input type="radio" name="md" value="now" checked> Soita heti (alkaa kun nykyinen kappale päättyy)</label><label><input type="radio" name="md" value="time"> Soita ajastettuna</label>';
	echo '<div id="tw" hidden><div class="row"><div><label>Päivä</label><input type="date" id="dt"></div><div><label>Kellonaika</label><input type="time" id="tt"></div></div><label><input type="checkbox" id="dy"> Soita joka päivä tähän aikaan</label></div>';
	echo '<div class="row" style="margin-top:14px"><button id="send">📡 Lähetä radioon</button><button class="sec" id="again">Äänitä uudelleen</button></div><div id="msg" class="mut" style="margin-top:10px"></div></div>';
	echo '<div class="card"><h2>Omat äänitteesi</h2><div id="cl" class="mut">…</div></div>';
	echo '<div class="card"><h2>Vaihda salasana</h2><div class="row"><input type="password" id="pw" placeholder="Uusi salasana (väh. 10 merkkiä)" style="flex:1 1 220px"><button class="sec" id="pwb">Vaihda</button></div><div id="pwm" class="mut" style="margin-top:8px"></div></div>';
	echo '<p class="mut">Äänitteet ovat julkisia: ne kuuluvat radion kuuntelijoille. Älä kerro äänitteessä salasanoja tai yksityisiä tietoja.</p>';
	echo '<script>var ST=' . wp_json_encode( $cfg ) . ';</script><script src="' . esc_url( $cfg['lame'] ) . '"></script><script>' . suorni_studio_js() . '</script></div></body></html>';
	exit;
} );
function suorni_studio_js() {
	return <<<'JS'
(function(){
var $=function(i){return document.getElementById(i)};
function esc(s){return String(s==null?'':s).replace(/[&<>"]/g,function(c){return{'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]})}
function api(m,p,body,isForm){
  var o={method:m,headers:{'X-WP-Nonce':ST.nonce},credentials:'same-origin'};
  if(body){if(isForm){o.body=body}else{o.headers['Content-Type']='application/json';o.body=JSON.stringify(body)}}
  return fetch(ST.rest+p,o).then(function(r){return r.json().then(function(j){if(!r.ok){throw new Error(j&&j.message?j.message:'Virhe')}return j})});
}
$('hn').textContent=ST.name;$('lo').href=ST.logout;
$('dt').value=ST.today;$('tt').value=ST.now;
var mp3=null,rec=null,chunks=[],stream=null,actx=null,timer=null,t0=0,MAX=180;
function info(d){
  $('info').textContent=d.prog?('Radiossa nyt: '+d.prog+(d.human?' – ohjelma on ihmisjuontajalle varattu':'')):'Radio soi.';
  var h='';
  if(!d.clips.length){h='Ei vielä äänitteitä.'}
  d.clips.forEach(function(c){
    h+='<div class="cl"><b>'+esc(c.title)+'</b><span class="mut">'+c.dur+' s · '+esc(c.when)+' · '+esc(c.status)+'</span><audio controls preload="none" src="'+esc(c.url)+'"></audio><div class="row"><button class="sec" data-n="'+esc(c.id)+'">▶ Soita radiossa nyt</button><button class="red" data-d="'+esc(c.id)+'">Poista</button></div></div>';
  });
  $('cl').innerHTML=h;
}
function load(){return api('GET','studio').then(info).catch(function(e){$('info').textContent=e.message})}
$('cl').addEventListener('click',function(e){
  var b=e.target.closest('button');if(!b)return;
  if(b.dataset.d){if(!confirm('Poistetaanko äänite?'))return;api('POST','studio/clipdel',{id:b.dataset.d}).then(info).catch(function(x){alert(x.message)})}
  if(b.dataset.n){b.disabled=true;api('POST','studio/clipnow',{id:b.dataset.n}).then(function(d){info(d);$('msg').textContent='Lisätty radioon: soi kun nykyinen kappale päättyy.'}).catch(function(x){alert(x.message);b.disabled=false})}
});
function stopStream(){if(stream){stream.getTracks().forEach(function(t){t.stop()});stream=null}if(timer){clearInterval(timer);timer=null}}
function setRec(on){$('rec').textContent=on?'⏹ Lopeta äänitys':'🔴 Aloita äänitys';$('rec').dataset.on=on?'1':''}
$('rec').onclick=function(){
  if($('rec').dataset.on){rec&&rec.state!=='inactive'&&rec.stop();return}
  if(!navigator.mediaDevices||!window.MediaRecorder){$('tm').innerHTML='<span class="err">Selaimesi ei tue äänitystä. Käytä Chromea, Edgeä, Firefoxia tai Safaria (iOS 14.3+).</span>';return}
  navigator.mediaDevices.getUserMedia({audio:{channelCount:1,noiseSuppression:true,echoCancellation:true}}).then(function(s){
    stream=s;chunks=[];mp3=null;$('pv').hidden=true;$('sendc').hidden=true;
    rec=new MediaRecorder(s);
    rec.ondataavailable=function(e){if(e.data&&e.data.size)chunks.push(e.data)};
    rec.onstop=function(){var type=rec.mimeType||'audio/webm';var blob=new Blob(chunks,{type:type});stopStream();setRec(false);$('lvl').firstChild.style.width='0';encode(blob)};
    var AC=window.AudioContext||window.webkitAudioContext;actx=new AC();var src=actx.createMediaStreamSource(s);var an=actx.createAnalyser();an.fftSize=512;src.connect(an);var buf=new Uint8Array(an.fftSize);
    rec.start(250);setRec(true);t0=Date.now();
    timer=setInterval(function(){
      an.getByteTimeDomainData(buf);var m=0;for(var i=0;i<buf.length;i++){m=Math.max(m,Math.abs(buf[i]-128))}
      $('lvl').firstChild.style.width=Math.min(100,m/128*160)+'%';
      var sec=Math.floor((Date.now()-t0)/1000);$('tm').textContent='Äänitetään… '+sec+' s / '+MAX+' s';
      if(sec>=MAX){rec.stop()}
    },100);
  }).catch(function(e){$('tm').innerHTML='<span class="err">Mikrofonia ei saatu käyttöön ('+esc(e.name||e.message)+'). Salli mikrofoni selaimen osoiterivin kuvakkeesta ja yritä uudelleen.</span>'});
};
function encode(blob){
  $('tm').textContent='Käsitellään äänitettä…';
  blob.arrayBuffer().then(function(ab){
    var AC=window.AudioContext||window.webkitAudioContext;var c=new AC();
    return new Promise(function(res,rej){c.decodeAudioData(ab,res,rej)}).then(function(buf){try{c.close()}catch(e){}return buf});
  }).then(function(buf){
    var ch=buf.numberOfChannels,n=buf.length,sr=buf.sampleRate,mono=new Float32Array(n);
    for(var k=0;k<ch;k++){var d=buf.getChannelData(k);for(var i=0;i<n;i++){mono[i]+=d[i]/ch}}
    /* hiljaisuus alusta ja lopusta pois */
    var th=0.01,a=0,b=n-1;while(a<n&&Math.abs(mono[a])<th)a++;while(b>a&&Math.abs(mono[b])<th)b--;
    a=Math.max(0,a-Math.floor(sr*0.15));b=Math.min(n-1,b+Math.floor(sr*0.25));
    if(b-a<sr*0.5){throw new Error('Äänite oli liian hiljainen tai lyhyt – yritä uudelleen.')}
    var pcm=new Int16Array(b-a+1);var peak=0;for(var j=a;j<=b;j++){peak=Math.max(peak,Math.abs(mono[j]))}
    var g=peak>0.02?Math.min(3,0.9/peak):1;
    for(var j2=a;j2<=b;j2++){var v=Math.max(-1,Math.min(1,mono[j2]*g));pcm[j2-a]=v<0?v*32768:v*32767}
    var enc=new lamejs.Mp3Encoder(1,sr,96),out=[],blk=1152;
    for(var p=0;p<pcm.length;p+=blk){var x=enc.encodeBuffer(pcm.subarray(p,p+blk));if(x.length)out.push(new Uint8Array(x))}
    var f=enc.flush();if(f.length)out.push(new Uint8Array(f));
    mp3=new Blob(out,{type:'audio/mpeg'});
    var dur=Math.round(pcm.length/sr);
    $('au').src=URL.createObjectURL(mp3);$('pv').hidden=false;$('sendc').hidden=false;
    $('tm').innerHTML='<span class="ok">Valmis ('+dur+' s). Kuuntele ja lähetä, tai äänitä uudelleen.</span>';
  }).catch(function(e){$('tm').innerHTML='<span class="err">'+esc(e.message||'Äänitteen käsittely epäonnistui')+'</span>'});
}
document.querySelectorAll('input[name=md]').forEach(function(r){r.onchange=function(){$('tw').hidden=document.querySelector('input[name=md]:checked').value!=='time'}});
$('again').onclick=function(){mp3=null;$('pv').hidden=true;$('sendc').hidden=true;$('tm').textContent='Aloita uusi äänitys.'};
$('send').onclick=function(){
  if(!mp3)return;
  var md=document.querySelector('input[name=md]:checked').value;
  var fd=new FormData();fd.append('file',mp3,'juonto.mp3');fd.append('title',$('ti').value);fd.append('mode',md);
  if(md==='time'){fd.append('date',$('dt').value);fd.append('time',$('tt').value);if($('dy').checked)fd.append('daily','1')}
  $('send').disabled=true;$('msg').textContent='Lähetetään…';
  api('POST','studio/clip',fd,true).then(function(d){
    info(d);mp3=null;$('pv').hidden=true;$('sendc').hidden=true;$('ti').value='';
    $('tm').innerHTML='<span class="ok">'+(md==='now'?'Lähetetty! Juonto kuuluu radiossa kun nykyinen kappale päättyy.':'Ajastettu!')+'</span>';$('msg').textContent='';
  }).catch(function(e){$('msg').innerHTML='<span class="err">'+esc(e.message)+'</span>'}).then(function(){$('send').disabled=false});
};
$('pwb').onclick=function(){api('POST','studio/pass',{pass:$('pw').value}).then(function(d){if(d.nonce)ST.nonce=d.nonce;$('pw').value='';$('pwm').innerHTML='<span class="ok">Salasana vaihdettu.</span>'}).catch(function(e){$('pwm').innerHTML='<span class="err">'+esc(e.message)+'</span>'})};
load();
})();
JS;
}

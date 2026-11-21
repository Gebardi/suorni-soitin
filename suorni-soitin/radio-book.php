<?php
/* Äänikirjatesti (ohjaamo → Juontaja): ylläpitäjä liittää tekstiä ja saa mp3:n Azuren suomenkielisellä äänellä.
   Käyttää samaa Azure-avainta kuin juontajan puhe. Kulusuoja: enintään 6 000 merkkiä kerralla ja 30 000 merkkiä vuorokaudessa (asetus suorni_book_max).
   Tiedostot: uploads/suorni-tts/kirja_*.mp3 (siivotaan 14 pv:n kuluttua samalla tavalla kuin juontajan puheet). */
if ( ! defined( 'ABSPATH' ) ) { exit; }

function suorni_book_voices() {
	return array( 'fi-FI-NooraNeural' => 'Noora (nainen)', 'fi-FI-SelmaNeural' => 'Selma (nainen)', 'fi-FI-HarriNeural' => 'Harri (mies)' );
}
function suorni_book_paras( $t ) {
	$t = str_replace( array( "\r\n", "\r" ), "\n", (string) $t );
	$t = preg_replace( '/[\x{2010}\x{00AD}]\n[ \t]*/u', '', $t ); // rivinvaihdon tavuviiva
	$t = preg_replace( '/[ \t]+/', ' ', $t );
	$out = array();
	foreach ( preg_split( '/\n\s*\n/', $t ) as $p ) {
		$p = trim( preg_replace( '/\s*\n\s*/', ' ', $p ) );
		if ( '' !== $p ) { $out[] = $p; }
	}
	return $out;
}
function suorni_book_ssml( $paras, $voice, $pct ) {
	$in = '';
	foreach ( $paras as $p ) {
		$sents = preg_split( '/(?<=[.!?…])\s+/u', $p, -1, PREG_SPLIT_NO_EMPTY );
		$last  = count( $sents ) - 1;
		foreach ( $sents as $k => $sn ) {
			$end = mb_substr( $sn, -1 );
			$br  = ( $k === $last ) ? 750 : ( ( '?' === $end || '!' === $end ) ? 380 : 320 );
			$in .= htmlspecialchars( $sn, ENT_XML1 | ENT_QUOTES, 'UTF-8' ) . "<break time='" . $br . "ms'/>";
		}
	}
	return "<speak version='1.0' xml:lang='fi-FI' xmlns='http://www.w3.org/2001/10/synthesis'><voice name='" . esc_attr( $voice ) . "'><prosody rate='" . ( $pct >= 0 ? '+' : '' ) . (int) $pct . "%'>" . $in . '</prosody></voice></speak>';
}
function suorni_book_post( $req ) {
	$c = suorni_tts_cfg();
	if ( '' === $c['key'] ) { return new WP_Error( 'suorni_tts', 'Azure-avainta ei ole tallennettu (Juontaja-välilehti → Pilviääni).', array( 'status' => 400 ) ); }
	$voices = suorni_book_voices();
	$voice  = (string) $req->get_param( 'voice' );
	if ( ! isset( $voices[ $voice ] ) ) { $voice = 'fi-FI-NooraNeural'; }
	$rate   = (float) $req->get_param( 'rate' );
	$rate   = ( $rate > 0 ) ? max( 0.8, min( 1.2, $rate ) ) : 1.0;
	$pct    = (int) round( ( $rate - 1 ) * 100 );
	$paras  = suorni_book_paras( wp_strip_all_tags( (string) $req->get_param( 'text' ) ) );
	$chars  = 0;
	foreach ( $paras as $p ) { $chars += mb_strlen( $p ); }
	if ( $chars < 20 ) { return new WP_Error( 'suorni_book', 'Liitä tekstiä (vähintään pari lausetta).', array( 'status' => 400 ) ); }
	if ( $chars > 6000 ) { return new WP_Error( 'suorni_book', 'Liian pitkä teksti: ' . $chars . ' merkkiä (enintään 6 000 kerralla).', array( 'status' => 400 ) ); }
	$up   = wp_upload_dir();
	$dir  = trailingslashit( $up['basedir'] ) . 'suorni-tts';
	$hash = md5( $voice . '|' . $pct . '|' . implode( "\n", $paras ) );
	$file = $dir . '/kirja_' . $hash . '.mp3';
	$url  = trailingslashit( $up['baseurl'] ) . 'suorni-tts/kirja_' . $hash . '.mp3';
	if ( file_exists( $file ) && filesize( $file ) > 200 ) { return suorni_json( array( 'url' => $url, 'chars' => $chars, 'cached' => true, 'bytes' => filesize( $file ) ) ); }
	$day = 'suorni_book_' . wp_date( 'Ymd' );
	$used = (int) get_transient( $day );
	if ( $used + $chars > (int) get_option( 'suorni_book_max', 30000 ) ) { return new WP_Error( 'suorni_book', 'Päivän kulukatto täynnä (' . $used . ' merkkiä käytetty). Raja on asetus suorni_book_max.', array( 'status' => 429 ) ); }
	/* Pilkotaan enintään ~1 800 merkin osiin kappaleiden rajalla */
	$chunks = array(); $cur = array(); $len = 0;
	foreach ( $paras as $p ) {
		if ( mb_strlen( $p ) > 1800 ) { // hyvin pitkä kappale: pilkotaan lauseisiin
			$parts = preg_split( '/(?<=[.!?…])\s+/u', $p, -1, PREG_SPLIT_NO_EMPTY );
			foreach ( $parts as $sn ) {
				if ( $len + mb_strlen( $sn ) > 1800 && $cur ) { $chunks[] = $cur; $cur = array(); $len = 0; }
				$cur[] = $sn; $len += mb_strlen( $sn );
			}
			continue;
		}
		if ( $len + mb_strlen( $p ) > 1800 && $cur ) { $chunks[] = $cur; $cur = array(); $len = 0; }
		$cur[] = $p; $len += mb_strlen( $p );
	}
	if ( $cur ) { $chunks[] = $cur; }
	$data = '';
	foreach ( $chunks as $ch ) {
		$r = wp_remote_post( 'https://' . $c['region'] . '.tts.speech.microsoft.com/cognitiveservices/v1', array(
			'timeout' => 60,
			'headers' => array( 'Ocp-Apim-Subscription-Key' => $c['key'], 'Content-Type' => 'application/ssml+xml', 'X-Microsoft-OutputFormat' => 'audio-24khz-96kbitrate-mono-mp3', 'User-Agent' => 'SuorniSoitin' ),
			'body'    => suorni_book_ssml( $ch, $voice, $pct ),
		) );
		if ( is_wp_error( $r ) ) { return new WP_Error( 'suorni_tts', 'Yhteys Azureen epäonnistui: ' . $r->get_error_message(), array( 'status' => 502 ) ); }
		$code = (int) wp_remote_retrieve_response_code( $r );
		$b    = wp_remote_retrieve_body( $r );
		if ( 200 !== $code || strlen( $b ) < 200 ) { return new WP_Error( 'suorni_tts', 'Azure vastasi virheellä ' . $code . ( ( 401 === $code || 403 === $code ) ? ' (tarkista avain ja alue)' : '' ) . '.', array( 'status' => 502 ) ); }
		$data .= $b;
	}
	if ( ! wp_mkdir_p( $dir ) || false === file_put_contents( $file, $data ) ) { return new WP_Error( 'suorni_tts', 'Tiedoston tallennus epäonnistui.', array( 'status' => 500 ) ); }
	set_transient( $day, $used + $chars, DAY_IN_SECONDS );
	if ( function_exists( 'suorni_usage_add' ) ) { suorni_usage_add( 'tts', count( $chunks ), $chars ); }
	return suorni_json( array( 'url' => $url, 'chars' => $chars, 'cached' => false, 'bytes' => strlen( $data ) ) );
}
add_action( 'rest_api_init', function () {
	register_rest_route( 'suorni/v1', '/radio/booktest', array( 'methods' => 'POST', 'callback' => 'suorni_book_post', 'permission_callback' => 'suorni_can' ) );
} );

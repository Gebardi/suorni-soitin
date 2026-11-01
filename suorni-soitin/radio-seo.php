<?php
/**
 * Suorni Radio: jaettavuus ja näkyvyys.
 * - Open Graph / Twitter -esikatselutiedot etusivulle, kanavalinkeille (?kanava=) ja kappalelinkeille (?biisi=)
 * - Kappaleen jakolinkki: ?biisi=ID näyttää "jaettu kappale" -kortin pienellä soittimella
 * - Hakukonekiellon poisto (WordPressin asetus blog_public), kerran automaattisesti ja napista
 * - Instagram- ja TikTok-linkit sekä mainoslinkki soittimen alla
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

function suorni_social_cfg() {
	$c = get_option( 'suorni_social', array() );
	if ( ! is_array( $c ) ) { $c = array(); }
	$ok = function ( $u, $mail = false ) {
		$u = trim( (string) $u );
		if ( '' === $u ) { return ''; }
		if ( $mail && 0 === stripos( $u, 'mailto:' ) ) { return 'mailto:' . sanitize_email( substr( $u, 7 ) ); }
		return esc_url_raw( $u, array( 'http', 'https' ) );
	};
	return array(
		'ig'         => isset( $c['ig'] ) ? $ok( $c['ig'] ) : 'https://www.instagram.com/radiosuorni/',
		'tt'         => isset( $c['tt'] ) ? $ok( $c['tt'] ) : '',
		'promo_on'   => ! isset( $c['promo_on'] ) || ! empty( $c['promo_on'] ),
		'promo_text' => ( isset( $c['promo_text'] ) && '' !== trim( $c['promo_text'] ) ) ? mb_substr( sanitize_text_field( $c['promo_text'] ), 0, 80 ) : 'Mainospaikka vapaana – ota haltuun!',
		'promo_url'  => ( isset( $c['promo_url'] ) && '' !== trim( $c['promo_url'] ) ) ? $ok( $c['promo_url'], true ) : 'mailto:admin@suorni.fi',
		'og_img'     => isset( $c['og_img'] ) ? $ok( $c['og_img'] ) : '',
		'desc'       => ( isset( $c['desc'] ) && '' !== trim( $c['desc'] ) ) ? mb_substr( sanitize_text_field( $c['desc'] ), 0, 200 ) : 'Suomalaista musiikkia suorana: oma tekoälyjuontaja, kanavat, terveiset ja toiveet. Paina play ja kuuntele.',
		'about'      => ( isset( $c['about'] ) && '' !== trim( $c['about'] ) ) ? mb_substr( sanitize_textarea_field( $c['about'] ), 0, 600 ) : 'Suorni on suomalainen nettiradio, jossa soi oma musiikki ympäri vuorokauden. Ohjelmia juontavat tekoälyjuontajat, ja kuuntelijat voivat lähettää terveisiä, toivoa kappaleita ja keskustella chatissa.',
		'contact'    => isset( $c['contact'] ) ? sanitize_email( (string) $c['contact'] ) : 'admin@suorni.fi',
	);
}
function suorni_seo_status() {
	return array( 'public' => '0' !== (string) get_option( 'blog_public', '1' ) );
}
/* Kerran päivityksen jälkeen: poista hakukonekielto (Markus antoi luvan 1.11.2026) */
add_action( 'init', function () {
	if ( get_option( 'suorni_seo_done' ) ) { return; }
	update_option( 'suorni_seo_done', 1, false );
	if ( '0' === (string) get_option( 'blog_public', '1' ) ) { update_option( 'blog_public', '1' ); }
} );

/* Jaettu kappale (?biisi=ID) tai null */
function suorni_share_track() {
	if ( empty( $_GET['biisi'] ) ) { return null; }
	$id = absint( wp_unslash( $_GET['biisi'] ) );
	if ( ! $id ) { return null; }
	$excl = suorni_radio_excl();
	foreach ( suorni_view( suorni_tracks(), '' ) as $t ) {
		if ( (int) $t['id'] === $id && $t['dur'] > 0 && ! in_array( $id, $excl, true ) ) { return $t; }
	}
	return null;
}
function suorni_seo_info() {
	$cfg = suorni_radio_cfg();
	$soc = suorni_social_cfg();
	$name = $cfg['name'];
	$info = array( 'title' => $name, 'desc' => $soc['desc'], 'url' => home_url( '/' ), 'img' => '', 'custom' => false, 'type' => 'website' );
	$img  = '' !== $soc['og_img'] ? $soc['og_img'] : ( '' !== $cfg['cover'] ? $cfg['cover'] : plugins_url( 'pwa/share.png', __FILE__ ) );
	$ch   = ( ! empty( $_GET['kanava'] ) ) ? suorni_ch_clean( wp_unslash( $_GET['kanava'] ) ) : '';
	if ( '' !== $ch ) {
		foreach ( suorni_ch_public() as $c ) {
			if ( $c['id'] !== $ch ) { continue; }
			$info['title']  = trim( $c['icon'] . ' ' . $c['name'] ) . ' – ' . $name;
			$info['desc']   = 'Kanava ' . $c['name'] . ': ' . $soc['desc'];
			$info['url']    = add_query_arg( 'kanava', $ch, home_url( '/' ) );
			$info['custom'] = true;
			if ( ! empty( $c['th']['img'] ) ) { $img = $c['th']['img']; }
		}
	}
	$tr = suorni_share_track();
	if ( $tr ) {
		$info['title']  = $tr['title'] . ' – ' . $tr['artist'];
		$info['desc']   = '' !== $tr['info'] ? mb_substr( $tr['info'], 0, 200 ) : 'Kuuntele kappale ' . $tr['title'] . ' Radio Suornissa.';
		$info['url']    = add_query_arg( 'biisi', (int) $tr['id'], home_url( '/' ) );
		$info['custom'] = true;
		$info['type']   = 'music.song';
	}
	$info['img'] = $img;
	return $info;
}
add_action( 'wp_head', function () {
	if ( ! function_exists( 'suorni_radio_is_page' ) || ! suorni_radio_is_page() ) { return; }
	if ( defined( 'WPSEO_VERSION' ) || defined( 'RANK_MATH_VERSION' ) || defined( 'AIOSEO_VERSION' ) ) { return; }
	$i = suorni_seo_info();
	$cfg = suorni_radio_cfg();
	echo '<meta name="description" content="' . esc_attr( $i['desc'] ) . '">' . "\n";
	echo '<meta property="og:site_name" content="' . esc_attr( $cfg['name'] ) . '">' . "\n";
	echo '<meta property="og:locale" content="fi_FI">' . "\n";
	echo '<meta property="og:type" content="' . esc_attr( $i['type'] ) . '">' . "\n";
	echo '<meta property="og:title" content="' . esc_attr( $i['title'] ) . '">' . "\n";
	echo '<meta property="og:description" content="' . esc_attr( $i['desc'] ) . '">' . "\n";
	echo '<meta property="og:url" content="' . esc_url( $i['url'] ) . '">' . "\n";
	if ( '' !== $i['img'] ) {
		echo '<meta property="og:image" content="' . esc_url( $i['img'] ) . '">' . "\n";
		if ( false !== strpos( $i['img'], 'pwa/share.png' ) ) { echo '<meta property="og:image:width" content="1200"><meta property="og:image:height" content="630">' . "\n"; }
	}
	echo '<meta name="twitter:card" content="summary_large_image">' . "\n";
	echo '<meta name="twitter:title" content="' . esc_attr( $i['title'] ) . '">' . "\n";
	echo '<meta name="twitter:description" content="' . esc_attr( $i['desc'] ) . '">' . "\n";
	if ( '' !== $i['img'] ) { echo '<meta name="twitter:image" content="' . esc_url( $i['img'] ) . '">' . "\n"; }
}, 6 );
/* Sivun otsikko kanava- ja kappalelinkeille */
add_filter( 'pre_get_document_title', function ( $t ) {
	if ( ! function_exists( 'suorni_radio_is_page' ) || ! suorni_radio_is_page() ) { return $t; }
	$i = suorni_seo_info();
	return $i['custom'] ? $i['title'] : $t;
}, 20 );

/* Jaettu kappale -kortti soittimen yläpuolelle */
function suorni_share_card_html() {
	$t = suorni_share_track();
	if ( ! $t ) { return ''; }
	return '<div class="rshr" data-shr><b>🎵 Sinulle jaettu kappale</b><span>' . esc_html( $t['title'] ) . ' – ' . esc_html( $t['artist'] ) . '</span><audio controls preload="none" src="' . esc_url( $t['url'] ) . '" aria-label="Jaettu kappale"></audio><small>Radio jatkuu, kun painat play yläpuolelta.</small></div>';
}
/* Some-palkki soittimen alle */
function suorni_social_html() {
	$s = suorni_social_cfg();
	if ( '' === $s['ig'] && '' === $s['tt'] && ! ( $s['promo_on'] && '' !== $s['promo_url'] ) ) { return ''; }
	$h = '<div class="rsoc" data-soc>';
	if ( '' !== $s['ig'] || '' !== $s['tt'] ) {
		$h .= '<span class="rsl">Seuraa meitä:</span>';
		if ( '' !== $s['ig'] ) { $h .= '<a class="rg" href="' . esc_url( $s['ig'] ) . '" target="_blank" rel="noopener" aria-label="Radio Suorni Instagramissa">📸 Instagram</a>'; }
		if ( '' !== $s['tt'] ) { $h .= '<a class="rg" href="' . esc_url( $s['tt'] ) . '" target="_blank" rel="noopener" aria-label="Radio Suorni TikTokissa">🎵 TikTok</a>'; }
	}
	if ( $s['promo_on'] && '' !== $s['promo_url'] ) {
		$h .= '<a class="rpro" href="' . esc_url( $s['promo_url'], array( 'http', 'https', 'mailto' ) ) . '"' . ( 0 === strpos( $s['promo_url'], 'http' ) ? ' target="_blank" rel="noopener"' : '' ) . '>📢 ' . esc_html( $s['promo_text'] ) . '</a>';
	}
	return $h . '</div>';
}
function suorni_social_state() {
	$c = suorni_social_cfg();
	return array_merge( $c, array( 'seo' => suorni_seo_status() ) );
}
function suorni_social_post( $req ) {
	$cur = get_option( 'suorni_social', array() );
	if ( ! is_array( $cur ) ) { $cur = array(); }
	foreach ( array( 'ig', 'tt', 'promo_text', 'promo_url', 'og_img', 'desc', 'about', 'contact' ) as $k ) {
		if ( null !== $req->get_param( $k ) ) { $cur[ $k ] = trim( (string) $req->get_param( $k ) ); }
	}
	if ( null !== $req->get_param( 'promo_on' ) ) { $cur['promo_on'] = (bool) $req->get_param( 'promo_on' ); }
	update_option( 'suorni_social', $cur, false );
	if ( $req->get_param( 'allow_index' ) ) { update_option( 'blog_public', '1' ); }
	return suorni_json( array( 'social' => suorni_social_state() ) );
}
add_action( 'rest_api_init', function () {
	register_rest_route( 'suorni/v1', '/radio/social', array( 'methods' => 'POST', 'callback' => 'suorni_social_post', 'permission_callback' => 'suorni_can' ) );
} );

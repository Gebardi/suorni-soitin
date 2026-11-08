<?php
/**
 * Suorni Radio: "teostovapaa nettiradio" -näkyvyys hakukoneille ja kuuntelijoille.
 * - Näkyvä esittelyosio (otsikko, teksti, merkit, usein kysytyt kysymykset) soittimen alla
 * - JSON-LD-rakenteinen data (WebSite, RadioStation, FAQPage), vain jos ei SEO-lisäosaa
 * - Asetukset suorni_social: tv_on (oletus päällä), tv_text (oma esittelyteksti)
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

function suorni_tv_on() {
	$c = get_option( 'suorni_social', array() );
	return ! is_array( $c ) || ! isset( $c['tv_on'] ) || ! empty( $c['tv_on'] );
}
function suorni_tv_default_text() {
	return 'Radio Suornin musiikki on teostovapaata: kaikki kappaleet ovat Suornin omaa tuotantoa, eikä niistä makseta Teosto- eikä Gramex-korvauksia. Radio soi ympäri vuorokauden ilmaiseksi, ilman kirjautumista ja ilman sovellusta. Kuuntele kotona, töissä, kaupassa, kahvilassa, parturissa tai autossa: avaa sivu, paina play ja musiikki alkaa.';
}
function suorni_tv_text() {
	$c = get_option( 'suorni_social', array() );
	if ( is_array( $c ) && isset( $c['tv_text'] ) && '' !== trim( $c['tv_text'] ) ) { return mb_substr( sanitize_textarea_field( $c['tv_text'] ), 0, 700 ); }
	return suorni_tv_default_text();
}
function suorni_tv_faq() {
	return array(
		array( 'Mitä teostovapaa nettiradio tarkoittaa?', 'Teostovapaa tarkoittaa, että radiossa soiva musiikki ei kuulu Teoston eikä Gramexin korvausten piiriin. Radio Suornissa soi vain Suornin omaa tuotantoa, joten kuuntelu on ilmaista ja musiikin soittamisesta ei synny erillisiä esityskorvauksia.' ),
		array( 'Voinko soittaa Suornia kaupassa, kahvilassa tai yrityksessäni?', 'Kyllä. Koska musiikki on teostovapaata, sen soittamiseen yrityksen tiloissa ei tarvita Teosto- eikä Gramex-lupaa. Avaa radio puhelimella, tabletilla tai tietokoneella, liitä laite kaiuttimiin ja paina play.' ),
		array( 'Maksaako Radio Suorni mitään?', 'Ei. Radion kuuntelu on ilmaista, eikä sitä varten tarvitse luoda tiliä tai kirjautua.' ),
		array( 'Miten kuuntelen Suornia puhelimella?', 'Avaa suorni.fi puhelimen selaimessa ja paina play. Halutessasi voit lisätä radion puhelimen aloitusnäytölle, jolloin se avautuu kuin sovellus.' ),
		array( 'Millaista musiikkia Suornista kuuluu?', 'Suomalaista musiikkia eri tyyleissä. Soittimen yläpuolelta voit valita kanavan makusi mukaan, ja päivän aikana ohjelmia juontavat Aino, Sanni, Eino ja Unto.' ),
	);
}
function suorni_tv_html() {
	if ( ! suorni_tv_on() ) { return ''; }
	$h  = '<section class="rsec rtv" id="sr-teostovapaa" aria-labelledby="sr-tvh"><h2 id="sr-tvh" class="tvh">Teostovapaa nettiradio – kuuntele missä vain</h2>';
	$h .= '<p class="tvp">' . esc_html( suorni_tv_text() ) . '</p>';
	$h .= '<ul class="tvb" aria-label="Radion ominaisuudet"><li>' . suorni_ic( 'check' ) . ' Teostovapaa</li><li>' . suorni_ic( 'check' ) . ' Ilmainen</li><li>' . suorni_ic( 'check' ) . ' Soi 24/7</li><li>' . suorni_ic( 'check' ) . ' Ei kirjautumista</li><li>' . suorni_ic( 'check' ) . ' Toimii puhelimella</li></ul>';
	$h .= '<div class="tvq">';
	foreach ( suorni_tv_faq() as $qa ) { $h .= '<details><summary>' . esc_html( $qa[0] ) . '</summary><p>' . esc_html( $qa[1] ) . '</p></details>'; }
	return $h . '</div></section>';
}
function suorni_tv_title() {
	$c = function_exists( 'suorni_radio_cfg' ) ? suorni_radio_cfg() : array();
	return ( ! empty( $c['name'] ) ? $c['name'] : 'Suorni Radio' ) . ' – teostovapaa nettiradio, kuuntele ilmaiseksi';
}
/* Rakenteinen data etusivulle (vain perussivu, ei kanava-/kappalelinkkejä) */
add_action( 'wp_head', function () {
	if ( ! function_exists( 'suorni_radio_is_page' ) || ! suorni_radio_is_page() || ! suorni_tv_on() ) { return; }
	if ( defined( 'WPSEO_VERSION' ) || defined( 'RANK_MATH_VERSION' ) || defined( 'AIOSEO_VERSION' ) ) { return; }
	if ( ! empty( $_GET['biisi'] ) || ! empty( $_GET['kanava'] ) ) { return; }
	$cfg  = suorni_radio_cfg();
	$name = ! empty( $cfg['name'] ) ? $cfg['name'] : 'Suorni Radio';
	$url  = home_url( '/' );
	$soc  = suorni_social_cfg();
	$same = array();
	foreach ( array( 'ig', 'tt' ) as $k ) { if ( '' !== $soc[ $k ] ) { $same[] = $soc[ $k ]; } }
	$faq = array();
	foreach ( suorni_tv_faq() as $qa ) { $faq[] = array( '@type' => 'Question', 'name' => $qa[0], 'acceptedAnswer' => array( '@type' => 'Answer', 'text' => $qa[1] ) ); }
	$g = array(
		'@context' => 'https://schema.org',
		'@graph'   => array(
			array( '@type' => 'WebSite', '@id' => $url . '#website', 'url' => $url, 'name' => $name, 'inLanguage' => 'fi' ),
			array_filter( array(
				'@type'               => 'RadioStation',
				'@id'                 => $url . '#radio',
				'name'                => $name,
				'url'                 => $url,
				'description'         => 'Teostovapaa suomalainen nettiradio. Oma musiikki, ei Teosto- eikä Gramex-korvauksia. Ilmainen ja soi ympäri vuorokauden.',
				'inLanguage'          => 'fi',
				'isAccessibleForFree' => true,
				'areaServed'          => 'FI',
				'sameAs'              => $same,
				'potentialAction'     => array( '@type' => 'ListenAction', 'target' => $url ),
			) ),
			array( '@type' => 'FAQPage', '@id' => $url . '#faq', 'mainEntity' => $faq ),
		),
	);
	echo '<script type="application/ld+json">' . wp_json_encode( $g, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . '</script>' . "\n";
}, 7 );

/* Google Search Console -vahvistus (HTML-tunniste): liitetään vain koodin arvo, hyväksytään myös koko meta-tagi */
function suorni_gsc_code() {
	$c = get_option( 'suorni_social', array() );
	/* Oletuksena suorni.fi:n vahvistuskoodi (julkinen, näkyy sivun lähdekoodissa); tyhjäksi tallennettu kenttä poistaa tunnisteen */
	$v = ( is_array( $c ) && isset( $c['gsc'] ) ) ? trim( (string) $c['gsc'] ) : '4shUWGSmecW6Kktg0sP9bJQJxXXwrib7y0otIHr8mjY';
	if ( preg_match( '#content=["\']([A-Za-z0-9_\-]{20,100})["\']#', $v, $m ) ) { return $m[1]; }
	return preg_match( '#^[A-Za-z0-9_\-]{20,100}$#', $v ) ? $v : '';
}
add_action( 'wp_head', function () {
	if ( ! is_front_page() ) { return; }
	$g = suorni_gsc_code();
	if ( '' !== $g ) { echo '<meta name="google-site-verification" content="' . esc_attr( $g ) . '">' . "\n"; }
}, 1 );

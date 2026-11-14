<?php
/* Taajuus-peli (peli/): staattinen PWA lisäosan kansiossa. Sivusto käyttää "tavallisia" kestolinkkejä, joten /peli/-polulle ei voi tehdä uudelleenkirjoitussääntöä;
   lyhyt osoite https://suorni.fi/?peli=1 ohjaa pelin omaan osoitteeseen (service workerin scope on silloin vain peli-kansio, ei koko sivusto). */
if ( ! defined( 'ABSPATH' ) ) { exit; }

function suorni_peli_url() { return plugins_url( 'peli/index.html', dirname( __FILE__ ) . '/suorni-soitin.php' ); }
add_action( 'template_redirect', function () {
	if ( ! isset( $_GET['peli'] ) ) { return; }
	$q = isset( $_GET['src'] ) ? sanitize_key( wp_unslash( $_GET['src'] ) ) : '';
	wp_redirect( suorni_peli_url() . ( '' !== $q ? '?src=' . $q : '' ), 302 );
	exit;
}, 1 );

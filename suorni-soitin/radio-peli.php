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

/* Etusivun pelilinkki: Taajuus-peli, jonka voi lisätä kotinäkymään kuvakkeeksi */
function suorni_peli_html() {
	$u = esc_url( add_query_arg( array( 'peli' => '1', 'src' => 'sivu' ), home_url( '/' ) ) );
	return '<section class="rpeli"><div class="rpi"><img src="' . esc_url( plugins_url( 'peli/icon-192.png', dirname( __FILE__ ) . '/suorni-soitin.php' ) ) . '" alt="" width="56" height="56"></div>'
		. '<div class="rpt"><b>🎮 Taajuus – pelaa omaa radioasemaa</b><span>Rento peli: rakenna autotallilähettimestä valtakunnan radio. Voit lisätä pelin puhelimen tai tietokoneen kotinäkymään omaksi sovelluksekseen (selaimen valikosta: &quot;Lisää kotinäyttöön&quot; tai &quot;Asenna&quot;).</span>'
		. '<a class="rpb" href="' . $u . '">Pelaa ja asenna →</a></div></section>'
		. '<style>.sr .rpeli{margin:26px 0 0;display:flex;gap:14px;align-items:center;padding:14px;border:1px solid rgba(255,255,255,.14);border-radius:12px;background:rgba(255,255,255,.04)}.sr .rpi img{display:block;border-radius:12px}.sr .rpt{display:flex;flex-direction:column;gap:6px;min-width:0}.sr .rpt b{font-size:15px}.sr .rpt span{font-size:13px;line-height:1.5;opacity:.85}.sr .rpb{align-self:flex-start;margin-top:4px;padding:8px 14px;border-radius:8px;background:var(--a,#4da3ff);color:#06101f;font-weight:700;text-decoration:none;font-size:14px}@media(max-width:480px){.sr .rpeli{align-items:flex-start}}</style>';
}

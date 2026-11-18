<?php
/**
 * Radio Suorni – vaihdettava sivuston teema (9.94)
 *
 * Asetus suorni_social['theme']: '' = oletus (vanha ilme, ei muutu) | 'neon' = neon-auringonlasku (Miami-henkinen).
 * Väreiltään ja tunnelmaltaan Vice City -henkinen (pinkki, oranssi, turkoosi, yöllinen horisontti). Ei kenenkään logoja, hahmoja tai kuvitusta:
 * palmut, aurinko ja flamingot ovat yleisiä siluetteja, jotka on piirretty itse SVG:llä.
 * Otsikkofontti Bowlby One (SIL OFL) tulee sivun omalta palvelimelta (pwa/fonts/), joten vierailijoiden tietoja ei lähde kolmannelle osapuolelle.
 * Esikatselu ylläpitäjälle: ?teema=neon tai ?teema=vanha. Neon ohittaa kanavien omat värit ja soittimen oman kansikuvan.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

function suorni_theme_saved() {
	$c = get_option( 'suorni_social', array() );
	$t = ( is_array( $c ) && isset( $c['theme'] ) ) ? (string) $c['theme'] : '';
	return in_array( $t, array( 'neon' ), true ) ? $t : '';
}
function suorni_theme_id() {
	$id = suorni_theme_saved();
	if ( isset( $_GET['teema'] ) && function_exists( 'current_user_can' ) && current_user_can( 'manage_options' ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		$p = sanitize_key( wp_unslash( $_GET['teema'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
		if ( 'neon' === $p ) { $id = 'neon'; } elseif ( 'vanha' === $p ) { $id = ''; }
	}
	return $id;
}
function suorni_theme_color() {
	return 'neon' === suorni_theme_id() ? '#1a0638' : '#0a2350';
}
add_filter( 'body_class', function ( $c ) {
	if ( 'neon' === suorni_theme_id() && function_exists( 'suorni_radio_is_page' ) && suorni_radio_is_page() ) { $c[] = 'sn'; }
	return $c;
} );
add_action( 'wp_head', function () {
	if ( 'neon' !== suorni_theme_id() || ! function_exists( 'suorni_radio_is_page' ) || ! suorni_radio_is_page() ) { return; }
	$f = plugins_url( 'pwa/fonts/bowlby-one-latin-400-normal.woff2', __FILE__ );
	echo '<link rel="preload" href="' . esc_url( $f ) . '" as="font" type="font/woff2" crossorigin>' . "\n";
	echo '<style id="suorni-neon">@font-face{font-family:"Bowlby One";font-style:normal;font-weight:400;font-display:swap;src:url("' . esc_url( $f ) . '") format("woff2")}' . suorni_neon_css() . '</style>' . "\n";
}, 99 );
function suorni_neon_banner() {
	$g = '';
	for ( $x = -400; $x <= 1200; $x += 100 ) { $g .= "<path d='M400 142L{$x} 170'/>"; }
	foreach ( array( 147, 154, 162 ) as $y ) { $g .= "<path d='M0 {$y}H800'/>"; }
	$palm = function ( $x, $dir ) {
		$t = $x + $dir * 8; $o = '';
		$o .= "<path d='M{$x} 170Q" . ( $x + $dir * 14 ) . " 122 {$t} 74' stroke='#0d0220' stroke-width='7' fill='none' stroke-linecap='round'/>";
		foreach ( array( array( -44, 12 ), array( -34, -14 ), array( -10, -30 ), array( 16, -28 ), array( 40, -8 ), array( 44, 18 ) ) as $f ) {
			$o .= "<path d='M{$t} 74Q" . ( $t + $f[0] * .5 ) . ' ' . ( 74 + $f[1] - 14 ) . ' ' . ( $t + $f[0] ) . ' ' . ( 74 + $f[1] + 14 ) . "' stroke='#0d0220' stroke-width='5' fill='none' stroke-linecap='round'/>";
		}
		return $o;
	};
	$stars = '';
	foreach ( array( array( 60, 14, 1.4 ), array( 150, 36, 1 ), array( 205, 12, 1.6 ), array( 280, 28, 1 ), array( 330, 8, 1.2 ), array( 470, 14, 1.2 ), array( 530, 34, 1 ), array( 600, 10, 1.6 ), array( 660, 30, 1.2 ), array( 740, 18, 1 ), array( 110, 60, 1 ), array( 700, 62, 1.2 ) ) as $st ) {
		$stars .= "<circle cx='{$st[0]}' cy='{$st[1]}' r='{$st[2]}' fill='#fff' opacity='.85'/>";
	}
	$fl = function ( $x, $d ) {
		$n = function ( $dx ) use ( $x, $d ) { return $x + $d * $dx; };
		return "<g fill='#ff4fa3' stroke='#ff4fa3' stroke-linecap='round'>"
			. "<path d='M{$x} 160V142M" . $n( 5 ) . " 152L" . $n( 12 ) . " 160' stroke-width='1.8' fill='none'/>"
			. "<ellipse cx='{$x}' cy='136' rx='13' ry='8' stroke='none'/><path d='M" . $n( 11 ) . " 132L" . $n( 24 ) . " 138L" . $n( 11 ) . " 140Z' stroke='none'/>"
			. "<path d='M" . $n( -8 ) . " 130Q" . $n( -26 ) . " 120 " . $n( -14 ) . " 102Q" . $n( -8 ) . " 90 " . $n( -17 ) . " 84' stroke-width='3.5' fill='none'/>"
			. "<circle cx='" . $n( -18 ) . "' cy='83' r='4.2' stroke='none'/><path d='M" . $n( -21 ) . " 82L" . $n( -29 ) . " 87L" . $n( -21 ) . " 87Z' fill='#1a0638' stroke='none'/></g>";
	};
	$svg = "<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 800 170' preserveAspectRatio='xMidYMax slice'>"
		. "<defs><linearGradient id='s' x1='0' y1='0' x2='0' y2='1'><stop offset='0' stop-color='#14042e'/><stop offset='.5' stop-color='#7a1f7e'/><stop offset='1' stop-color='#ff5e7a'/></linearGradient>"
		. "<linearGradient id='u' x1='0' y1='0' x2='0' y2='1'><stop offset='0' stop-color='#ffe66d'/><stop offset='.5' stop-color='#ff8a3d'/><stop offset='1' stop-color='#ff2d95'/></linearGradient>"
		. "<mask id='m'><rect x='0' y='0' width='800' height='170' fill='#fff'/><rect y='100' width='800' height='3' fill='#000'/><rect y='112' width='800' height='4' fill='#000'/><rect y='124' width='800' height='5' fill='#000'/><rect y='135' width='800' height='7' fill='#000'/></mask></defs>"
		. "<rect width='800' height='170' fill='url(#s)'/>" . $stars . "<circle cx='400' cy='112' r='80' fill='url(#u)' mask='url(#m)'/>"
		. "<rect y='142' width='800' height='28' fill='#12042a'/><g stroke='#00f0ff' stroke-width='1' opacity='.75' fill='none'>{$g}</g>"
		. $fl( 238, 1 ) . $fl( 572, -1 ) . $palm( 128, 1 ) . $palm( 672, -1 ) . '</svg>';
	return 'url("data:image/svg+xml,' . rawurlencode( $svg ) . '")';
}
function suorni_neon_css() {
	$P = 'html body.sn ';
	$css = <<<'CSS'
@@ .sr{--a:#ff4fa3;--b:#ff8a3d;--ar:255,79,163;color:#fff3e8;border-radius:4px;border:1px solid rgba(255,79,163,.5);background:radial-gradient(120% 50% at 50% 0%,rgba(255,79,163,.22),transparent 62%),radial-gradient(90% 40% at 50% 100%,rgba(255,138,61,.16),transparent 70%),linear-gradient(180deg,#240847 0%,#17052f 55%,#10041f 100%);box-shadow:0 0 0 1px rgba(255,138,61,.14),0 0 52px rgba(255,45,149,.30),0 20px 60px rgba(0,0,0,.6),inset 0 1px 0 rgba(255,255,255,.08)}
@@ .sr h2{font-family:"Bowlby One",Impact,system-ui,sans-serif;font-weight:400;font-size:24px;letter-spacing:.05em;color:#fff;text-shadow:0 0 14px rgba(255,79,163,.75),0 0 34px rgba(255,138,61,.35)}
@@ .sr .rsec h3,@@ .sr .rft h3,@@ .sr .rvh b,@@ .sr .rn b,@@ .sr .hc h4{font-family:"Bowlby One",Impact,system-ui,sans-serif;font-weight:400;letter-spacing:.06em}
@@ .sr .rsec h3,@@ .sr .rft h3{font-size:13px;border-left:3px solid #ff8a3d;color:#fff}
@@ .sr .rn b{font-size:17px;letter-spacing:.03em;text-transform:uppercase}
@@ .sr .rs,@@ .sr .rf,@@ .sr .rhr .tm,@@ .sr .rvn,@@ .sr .rwx .wxh,@@ .sr .rn span{color:#cdb0e0}
@@ .sr .rc,@@ .sr .rwx .wxd{color:#f3e3f6}
@@ .sr .rb,@@ .sr .rn,@@ .sr .rdial,@@ .sr .rwx,@@ .sr .rtape,@@ .sr .rart{background:rgba(20,4,45,.78);border:1px solid rgba(255,79,163,.30);box-shadow:inset 0 0 22px rgba(255,79,163,.07)}
@@ .sr .ri,@@ .sr .rch,@@ .sr .gp,@@ .sr .hc,@@ .sr details.rdd{background:rgba(255,255,255,.035);border-color:rgba(255,138,61,.28)}
@@ .sr .gp.on,@@ .sr .hc.on{border-color:#ff4fa3;background:rgba(255,79,163,.14);box-shadow:0 0 18px rgba(255,79,163,.25)}
@@ .sr .rp,@@ .sr .rvb [data-vup].on{background:linear-gradient(135deg,#ff2d95 0%,#ff8a3d 100%);color:#1a0638;box-shadow:0 0 24px rgba(255,79,163,.6),0 8px 22px rgba(255,138,61,.35),inset 0 1px 0 rgba(255,255,255,.4)}
@@ .sr .rp:hover{box-shadow:0 0 34px rgba(255,79,163,.8),0 10px 28px rgba(255,138,61,.45)}
@@ .sr button.rg,@@ .sr a.rg,@@ .sr .rteject{background:rgba(255,79,163,.10);border:1px solid rgba(255,79,163,.42);color:#fff3e8}
@@ .sr button.rg:hover,@@ .sr a.rg:hover{background:rgba(255,79,163,.24);border-color:#ff8a3d}
@@ .sr .rvt{background:linear-gradient(180deg,rgba(255,79,163,.16),rgba(255,138,61,.05));border:1px solid rgba(255,79,163,.5)}
@@ .sr .rvh small,@@ .sr .rpre button.rg b,@@ .sr .gt{color:#ffb15c}
@@ .sr .rnav{border-bottom:1px solid rgba(255,79,163,.4)}
@@ .sr .rnav a{color:#d9bde8}
@@ .sr .rnav a:hover,@@ .sr .rnav a:focus-visible{background:rgba(255,79,163,.10);border-bottom-color:#ff8a3d;color:#fff}
@@ .sr .rneedle{background:#ff8a3d;box-shadow:0 0 12px #ff8a3d,0 0 24px rgba(255,79,163,.6)}
@@ .sr input.rt,@@ .sr textarea.rt{background:rgba(255,255,255,.06);border:1px solid rgba(255,79,163,.35)}
@@ .sr details.rdd>summary::after{border-color:#ff8a3d}
@@ .sr .rwx .wxt{background:rgba(255,79,163,.18)}
@@ .sr .rwx .wxt button{background:rgba(20,4,45,.92);color:#fff3e8}
@@ .sr .rwx .wxt button:hover{background:rgba(70,14,100,.95)}
@@ .sr .rwx .wxt button.on{background:rgba(255,79,163,.22);box-shadow:inset 0 -2px 0 #ff8a3d}
@@ .sr .rchn button.rcc{background:linear-gradient(135deg,rgba(36,8,71,.95),rgba(255,79,163,.24)),#17052f;border:1px solid rgba(255,79,163,.5);color:#fff3e8}
@@ .sr .rchn button.rcc{--ca:255,79,163 !important;--cb:36,8,71 !important}
@@ .sr .rscale:after{color:#c9aadd}
@@ .sr .rchn button.rcc.on{border-color:#ff8a3d;box-shadow:0 0 18px rgba(255,138,61,.4)}
html body.sn .srbar{background:rgba(18,4,42,.95);border-top:1px solid rgba(255,79,163,.55);color:#fff3e8;box-shadow:0 -6px 30px rgba(255,45,149,.25)}
html body.sn .srbar .bp{background:linear-gradient(135deg,#ff2d95,#ff8a3d);color:#1a0638;box-shadow:0 0 18px rgba(255,79,163,.6)}
html body.sn.home,html.sch-on body.sn.home{background:radial-gradient(90% 38% at 50% 100%,rgba(255,170,70,.55),rgba(255,79,163,.28) 45%,transparent 75%),linear-gradient(180deg,#0d0220 0%,#240847 50%,#5a1565 82%,#b0306f 100%) fixed !important}
html body.sn.home .site-bar,html.sch-on body.sn.home .site-bar{background:linear-gradient(90deg,#ff2d95,#ff8a3d) !important}
html body.sn.home .site-head,html body.sn.home .site-foot,html.sch-on body.sn.home .site-head,html.sch-on body.sn.home .site-foot{background:#10041f !important;border-color:rgba(255,79,163,.4) !important}
html body.sn.home .brand,html body.sn.home a,html.sch-on body.sn.home .brand,html.sch-on body.sn.home a{color:#ff8fc4}
html body.sn.home .brand{font-family:"Bowlby One",Impact,system-ui,sans-serif;text-shadow:0 0 14px rgba(255,79,163,.7)}
html body.sn.home .menu a,html.sch-on body.sn.home .menu a{color:#e8c9f2}
html body.sn.home .menu a:hover,html body.sn.home .menu .current-menu-item a,html.sch-on body.sn.home .menu a:hover{background:rgba(255,79,163,.16);color:#fff}
html body.sn.home .hero h1{font-family:"Bowlby One",Impact,system-ui,sans-serif;font-weight:400;color:#fff;text-shadow:0 0 18px rgba(255,79,163,.8),0 0 40px rgba(255,138,61,.4)}
@media(max-width:480px){@@ .sr h2{font-size:19px}}
@media(prefers-reduced-motion:no-preference){@keyframes snpulse{0%,100%{box-shadow:0 0 22px rgba(255,79,163,.55),0 8px 22px rgba(255,138,61,.3)}50%{box-shadow:0 0 38px rgba(255,79,163,.85),0 10px 28px rgba(255,138,61,.5)}}html body.sn .sr .rp{animation:snpulse 2.6s ease-in-out infinite}}

/* ---- Miami-lisä: turkoosi + pinkki, raidallinen aurinko, palmut, ruudukkolattia ---- */
@@ .sr{border:2px solid transparent;border-image:linear-gradient(135deg,#ff2d95,#ff8a3d 45%,#00f0ff) 1;background:linear-gradient(90deg,#00f0ff,#ff2d95 50%,#ff8a3d) top/100% 4px no-repeat,radial-gradient(120% 50% at 50% 0%,rgba(255,79,163,.2),transparent 62%),linear-gradient(180deg,rgba(36,8,71,.86),rgba(16,4,31,.9));box-shadow:0 0 0 1px rgba(0,240,255,.25),-8px 0 42px rgba(255,45,149,.38),8px 0 42px rgba(0,240,255,.30),0 20px 60px rgba(0,0,0,.6)}
@@ .sr .rcv{display:block !important;height:clamp(110px,18vw,170px);background:{{BANNER}} center bottom/cover no-repeat;border-bottom:2px solid #00f0ff;box-shadow:0 0 22px rgba(0,240,255,.45)}
@@ .sr .rcv>*{display:none}
@@ .sr img.rcv{visibility:hidden}
@@ .sr svg.rcv{visibility:visible}
@@ .sr h2{font-size:32px;transform:skewX(-7deg);transform-origin:left bottom;text-shadow:3px 3px 0 #00d5e8,-1px -1px 0 #ff2d95,0 0 22px rgba(255,45,149,.9),0 0 48px rgba(255,138,61,.5)}
@@ .sr .rsec h3,@@ .sr .rft h3{border-left:4px solid #00f0ff;text-shadow:0 0 10px rgba(0,240,255,.75)}
@@ .sr .rn{border:1px solid rgba(0,240,255,.6);box-shadow:inset 0 0 22px rgba(0,240,255,.12),0 0 18px rgba(0,240,255,.28)}
@@ .sr .rn b{color:#fff;text-shadow:0 0 5px rgba(0,240,255,.9),0 0 14px rgba(0,240,255,.4)}
@@ .sr .gt,@@ .sr .rwx .wxp{color:#00f0ff;text-shadow:0 0 8px rgba(0,240,255,.7)}
@@ .sr .rp{outline:2px solid #00f0ff;outline-offset:4px}
@@ .sr button.rg:hover,@@ .sr a.rg:hover{border-color:#00f0ff;box-shadow:0 0 16px rgba(0,240,255,.55)}
@@ .sr .rnav a:hover,@@ .sr .rnav a:focus-visible{border-bottom-color:#00f0ff;text-shadow:0 0 10px rgba(0,240,255,.8)}
@@ .sr .rchn button.rcc.on{border-color:#00f0ff;box-shadow:0 0 20px rgba(0,240,255,.55),inset 0 0 14px rgba(0,240,255,.15)}
@@ .sr .gp.on,@@ .sr .hc.on{border-color:#00f0ff;box-shadow:0 0 20px rgba(0,240,255,.4)}
html body.sn .site-bar{height:10px !important;background:repeating-linear-gradient(135deg,#ff2d95 0 14px,#00f0ff 14px 28px,#ff8a3d 28px 42px) !important}
html body.sn .brand{text-shadow:2px 2px 0 #00d5e8,0 0 16px rgba(255,79,163,.9)}
html body.sn,html.sch-on body.sn{background:{{BANNER}} center bottom/100% auto no-repeat fixed,linear-gradient(180deg,#0d0220 0%,#240847 55%,#5a1565 100%) fixed !important}
html body.sn:before{content:"";position:fixed;left:-20%;right:-20%;bottom:0;height:34vh;z-index:-1;pointer-events:none;background:repeating-linear-gradient(0deg,rgba(0,240,255,.45) 0 1px,transparent 1px 34px),repeating-linear-gradient(90deg,rgba(0,240,255,.4) 0 1px,transparent 1px 70px);-webkit-mask-image:linear-gradient(0deg,#000 20%,transparent 100%);mask-image:linear-gradient(0deg,#000 20%,transparent 100%);transform:perspective(240px) rotateX(62deg);transform-origin:50% 100%}
@media(max-width:480px){@@ .sr h2{font-size:23px}}
@media(prefers-reduced-motion:no-preference){@keyframes snflick{0%,18%,22%,62%,64%,100%{opacity:1}20%,63%{opacity:.7}}@keyframes sngrid{to{background-position:0 34px,0 0}}html body.sn .sr h2{animation:snflick 5s infinite}html body.sn:before{animation:sngrid 1.6s linear infinite}}

/* ---- Överi-taso: kromiotsikko, VHS-raidat, putkireunat, värinvaihto ---- */
@@ .sr{background:repeating-linear-gradient(0deg,rgba(255,255,255,.03) 0 1px,transparent 1px 3px),linear-gradient(90deg,#00f0ff,#ff2d95 50%,#ff8a3d) top/100% 4px no-repeat,radial-gradient(120% 50% at 50% 0%,rgba(255,79,163,.22),transparent 62%),linear-gradient(180deg,rgba(36,8,71,.86),rgba(16,4,31,.9))}
@@ .sr h2{background:linear-gradient(180deg,#fff 0%,#ffe3f2 42%,#ff7cc0 50%,#ff2d95 100%);-webkit-background-clip:text;background-clip:text;color:transparent;-webkit-text-fill-color:transparent;text-shadow:none;filter:drop-shadow(3px 3px 0 #00d5e8) drop-shadow(0 0 14px rgba(255,45,149,.95)) drop-shadow(0 0 30px rgba(255,138,61,.5));padding-right:.2em}
@@ .sr .rb{box-shadow:0 0 0 1px rgba(255,45,149,.85),0 0 16px rgba(255,45,149,.55),inset 0 0 18px rgba(255,45,149,.22);border-color:transparent}
@@ .sr .rvt{box-shadow:0 0 0 1px rgba(0,240,255,.7),0 0 16px rgba(0,240,255,.4),inset 0 0 20px rgba(0,240,255,.12);border-color:transparent}
@@ details.rdd{box-shadow:0 0 0 1px rgba(255,138,61,.45),0 0 12px rgba(255,138,61,.25)}
@@ .sr button.rg,@@ .sr a.rg{text-transform:uppercase;letter-spacing:.09em;font-weight:800;font-size:13px}
@@ .sr .gnow{text-shadow:0 0 8px #ff5c5c}
@@ .sr .rchn button.rcc{text-transform:none;letter-spacing:.03em;font-size:13px}
html body.sn .site-foot{border-top:4px solid #00f0ff !important;box-shadow:0 -6px 24px rgba(0,240,255,.35)}
html body.sn,html.sch-on body.sn{background:{{BANNER}} center bottom/100% auto no-repeat fixed,radial-gradient(rgba(255,255,255,.07) 1px,transparent 1.6px) 0 0/22px 22px,linear-gradient(180deg,#0d0220 0%,#240847 55%,#5a1565 100%) fixed !important}
@media(prefers-reduced-motion:no-preference){@keyframes snhue{0%,100%{filter:hue-rotate(-12deg)}50%{filter:hue-rotate(14deg)}}@keyframes snsky{0%,100%{filter:hue-rotate(0deg) saturate(1)}50%{filter:hue-rotate(-22deg) saturate(1.35)}}@keyframes snsign{0%,41%,45%,100%{opacity:1}43%{opacity:.5}}html body.sn .sr .rp{animation:snpulse 2.6s ease-in-out infinite,snhue 7s ease-in-out infinite}html body.sn .sr .rcv{animation:snsky 11s ease-in-out infinite}html body.sn .brand{animation:snsign 6s infinite}}
CSS;
	// Selektorit: "@@ X" -> "html body.sn X" (korkeampi tarkkuus kuin lisäosan peruskoodi ja kanavateemat)
	return str_replace( array( '@@ ', '{{BANNER}}' ), array( $P, suorni_neon_banner() ), $css );
}

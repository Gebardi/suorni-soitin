<?php
/**
 * Suorni Radio: seuranta ilman kirjautumista (iPhonen widget ja seurantasivu).
 * Pääsy vain salaisella avaimella, tiedot vain luettavissa.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

function suorni_mon_key( $renew = false ) {
	$k = (string) get_option( 'suorni_mon_key', '' );
	if ( $renew || strlen( $k ) < 24 ) {
		$k = strtolower( wp_generate_password( 32, false, false ) );
		update_option( 'suorni_mon_key', $k, false );
	}
	return $k;
}
function suorni_mon_ok( $given ) {
	$k = (string) get_option( 'suorni_mon_key', '' );
	return strlen( $k ) >= 24 && is_string( $given ) && hash_equals( $k, $given );
}
function suorni_mon_data() {
	$cfg = suorni_radio_cfg();
	$log = suorni_radio_log();
	$now = microtime( true );
	$cur = null;
	foreach ( $log['items'] as $it ) { if ( $it['start'] <= $now && $now < $it['start'] + $it['dur'] ) { $cur = $it; break; } }
	$st    = suorni_radio_stats_get();
	$today = $st['days'][0];
	$chat  = array();
	$all   = suorni_chat();
	$vis   = array();
	foreach ( $all as $x ) {
		$at = isset( $x['at'] ) ? (int) $x['at'] : (int) $x['t'];
		if ( $at <= time() ) { $x['_at'] = $at; $vis[] = $x; }
	}
	usort( $vis, function ( $a, $b ) { return $a['_at'] <=> $b['_at']; } );
	foreach ( array_slice( $vis, -10 ) as $x ) {
		$chat[] = array( 'n' => $x['n'], 'm' => $x['m'], 'dj' => ! empty( $x['dj'] ), 'bot' => ! empty( $x['b'] ), 'time' => wp_date( 'H.i', $x['_at'] ) );
	}
	$gp = 0;
	foreach ( suorni_greets() as $g ) { if ( ! in_array( $g['status'], array( 'ok', 'no', 'played' ), true ) ) { $gp++; } }
	$sp = 0;
	foreach ( suorni_subs() as $s ) { if ( 'pending' === $s['status'] ) { $sp++; } }
	return array(
		'name'      => $cfg['name'],
		'on'        => (bool) $cfg['on'],
		'live'      => (int) $st['live'],
		'today'     => array( 'unique' => (int) $today['unique'], 'minutes' => (int) $today['minutes'], 'avg' => $today['avg'] ),
		'week'      => (int) $st['week_sum'],
		'peak_hour' => (int) $st['peak_hour'],
		'now'       => $cur ? array( 'type' => $cur['type'], 'title' => $cur['title'], 'artist' => $cur['artist'], 'prog' => isset( $cur['prog'] ) ? $cur['prog'] : '' ) : null,
		'chat'      => $chat,
		'pending'   => array( 'greets' => $gp, 'songs' => $sp ),
		'at'        => wp_date( 'H.i' ),
	);
}
function suorni_mon_get( $req ) {
	if ( ! suorni_mon_ok( (string) $req->get_param( 'key' ) ) ) { return new WP_Error( 'suorni_auth', 'Väärä avain.', array( 'status' => 403 ) ); }
	return suorni_json( suorni_mon_data() );
}
function suorni_mon_renew() {
	suorni_mon_key( true );
	return suorni_json( suorni_mon_admin() );
}
function suorni_mon_api_url( $k ) {
	return add_query_arg( 'key', $k, rest_url( 'suorni/v1/radio/monitor' ) );
}
function suorni_mon_admin() {
	$k = suorni_mon_key();
	$u = suorni_mon_api_url( $k );
	$s = <<<'JS'
// Suorni Radio -seuranta (Scriptable)
const API = "__URL__";
let d = null;
try { d = await new Request(API).loadJSON(); } catch (e) {}
const w = new ListWidget();
w.backgroundColor = new Color("#14161a");
w.setPadding(12, 14, 12, 14);
const amber = new Color("#ffb347");
if (!d || !d.name) {
  const t = w.addText("Ei yhteyttä radioon");
  t.textColor = Color.white();
} else {
  const h = w.addText("📻 " + d.name.toUpperCase());
  h.font = Font.boldSystemFont(11); h.textColor = amber;
  const l = w.addText("👥 " + d.live + (d.live === 1 ? " kuuntelee nyt" : " kuuntelee nyt"));
  l.font = Font.boldSystemFont(20); l.textColor = Color.white();
  const t2 = w.addText("Tänään " + d.today.unique + " kuuntelijaa · " + d.today.minutes + " min");
  t2.font = Font.systemFont(11); t2.textColor = new Color("#b9b09c");
  if (d.now) {
    const n = w.addText("🎵 " + (d.now.type === "dj" ? "Juontaja puhuu" : d.now.artist + " – " + d.now.title));
    n.font = Font.systemFont(12); n.textColor = amber; n.lineLimit = 1;
  }
  w.addSpacer(4);
  const msgs = d.chat.slice(-3);
  if (!msgs.length) { const e = w.addText("Ei chat-viestejä"); e.font = Font.systemFont(11); e.textColor = new Color("#8b8472"); }
  msgs.forEach(function (m) {
    const x = w.addText((m.dj ? "🎙 " : m.bot ? "🤖 " : "") + m.n + ": " + m.m);
    x.font = Font.systemFont(11); x.textColor = Color.white(); x.lineLimit = 2;
  });
  const p = d.pending.greets + d.pending.songs;
  if (p > 0) {
    w.addSpacer(2);
    const q = w.addText("⏳ Odottaa hyväksyntää: " + p);
    q.font = Font.boldSystemFont(11); q.textColor = new Color("#ff8a1f");
  }
}
w.refreshAfterDate = new Date(Date.now() + 5 * 60 * 1000);
if (config.runsInWidget) { Script.setWidget(w); } else { await w.presentMedium(); }
Script.complete();
JS;
	return array( 'key' => $k, 'api' => $u, 'page' => home_url( '/?suorni_monitor=' . $k ), 'shop' => home_url( '/?suorni_kauppa=1' ), 'script' => str_replace( '__URL__', $u, $s ) );
}
function suorni_mon_admin_get() { return suorni_json( suorni_mon_admin() ); }
add_action( 'rest_api_init', function () {
	register_rest_route( 'suorni/v1', '/radio/monitor', array( 'methods' => 'GET', 'callback' => 'suorni_mon_get', 'permission_callback' => '__return_true' ) );
	register_rest_route( 'suorni/v1', '/radio/monget', array( 'methods' => 'GET', 'callback' => 'suorni_mon_admin_get', 'permission_callback' => 'suorni_can' ) );
	register_rest_route( 'suorni/v1', '/radio/monrenew', array( 'methods' => 'POST', 'callback' => 'suorni_mon_renew', 'permission_callback' => 'suorni_can' ) );
} );
/* Seurantasivu: /?suorni_monitor=AVAIN (lisää iPhonessa kotivalikkoon) */
add_action( 'init', function () {
	if ( ! isset( $_GET['suorni_monitor'] ) ) { return; }
	$k = sanitize_text_field( wp_unslash( (string) $_GET['suorni_monitor'] ) );
	nocache_headers();
	header( 'X-Robots-Tag: noindex, nofollow' );
	if ( ! suorni_mon_ok( $k ) ) { status_header( 403 ); echo 'Ei oikeutta.'; exit; }
	$api = suorni_mon_api_url( $k );
	header( 'Content-Type: text/html; charset=utf-8' );
	?><!doctype html><html lang="fi"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><meta name="apple-mobile-web-app-capable" content="yes"><meta name="apple-mobile-web-app-title" content="Radio seuranta"><meta name="theme-color" content="#14161a"><link rel="apple-touch-icon" href="<?php echo esc_url( plugins_url( 'pwa/apple-touch-icon.png', __FILE__ ) ); ?>"><title>Radio seuranta</title>
<style>body{margin:0;background:#14161a;color:#efe8d8;font:16px/1.5 system-ui,-apple-system,sans-serif;padding:14px}h1{font-size:14px;letter-spacing:3px;color:#ffb347;margin:0 0 10px;text-transform:uppercase}.c{background:#1d1f24;border:1px solid #3b3f47;border-radius:14px;padding:14px;margin-bottom:12px}.big{font-size:40px;font-weight:700;color:#fff}.s{color:#b9b09c;font-size:14px}.m{padding:6px 0;border-bottom:1px solid #2a2d33;word-break:break-word}.m:last-child{border:0}.dj{color:#ffb347}.p{color:#ff8a1f;font-weight:700}</style></head><body>
<h1 id="n">Radio seuranta</h1>
<div class="c"><div class="big" id="l">–</div><div class="s" id="l2">kuuntelijaa nyt</div><div class="s" id="t"></div></div>
<div class="c"><div class="s">Nyt soi</div><div id="np">–</div><div class="p" id="pe"></div></div>
<div class="c"><div class="s">Chat</div><div id="ch"></div></div>
<div class="s" id="u"></div>
<script>
var API=<?php echo wp_json_encode( $api ); ?>;
function e(s){var d=document.createElement('div');d.textContent=s==null?'':s;return d.innerHTML;}
function load(){fetch(API+(API.indexOf('?')<0?'?':'&')+'r='+Date.now(),{cache:'no-store'}).then(function(r){return r.json();}).then(function(d){
document.getElementById('n').textContent='📻 '+d.name;
document.getElementById('l').textContent=d.live;
document.getElementById('l2').textContent=d.live===1?'kuuntelija nyt':'kuuntelijaa nyt';
document.getElementById('t').textContent='Tänään '+d.today.unique+' kuuntelijaa · '+d.today.minutes+' min · viikossa '+d.week+(d.peak_hour>=0?' · vilkkain tunti '+d.peak_hour+':00':'');
document.getElementById('np').innerHTML=d.now?(d.now.type==='dj'?'🎙️ Juontaja puhuu':e(d.now.artist+' – '+d.now.title)+'<div class="s">'+e(d.now.prog)+'</div>'):'–';
var p=d.pending.greets+d.pending.songs;document.getElementById('pe').textContent=p?'⏳ Odottaa hyväksyntää: '+d.pending.greets+' terveistä, '+d.pending.songs+' kappaletta':'';
var h='';d.chat.slice().reverse().forEach(function(m){h+='<div class="m'+(m.dj?' dj':'')+'"><span class="s">'+e(m.time)+'</span> <b>'+(m.dj?'🎙️ ':'')+(m.bot?'🤖 ':'')+e(m.n)+':</b> '+e(m.m)+'</div>';});
document.getElementById('ch').innerHTML=h||'<span class="s">Ei viestejä</span>';
document.getElementById('u').textContent='Päivitetty '+d.at;}).catch(function(){document.getElementById('u').textContent='Yhteys katkesi, yritetään uudelleen…';});}
load();setInterval(load,15000);
</script></body></html><?php
	exit;
}, 1 );

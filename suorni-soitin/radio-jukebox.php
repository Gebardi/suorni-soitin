<?php
/* Jukeboksi: kuulija valitsee minkä tahansa soitettavan kappaleen ja kuuntelee sen omalla laitteellaan (ei vaikuta yhteiseen radiolähetykseen).
   GET  /jukebox/list   julkinen kappalelista (sama joukko kuin radiossa: ei mainoksia/tiedotteita, ei radiosta piilotettuja)
   POST /jukebox/play   soittolaskuri (tilastoon; per laite sama kappale max kerran 10 min, 60 soittoa / tunti)
   Tallennus: suorni_jb_plays = array( 't' => id => määrä, 'd' => päivä => määrä ). Ei henkilötietoja. */
function suorni_jb_tracks() {
	$excl = function_exists( 'suorni_radio_excl' ) ? suorni_radio_excl() : array();
	$out  = array();
	foreach ( suorni_view( suorni_tracks(), '' ) as $t ) {
		if ( (int) $t['dur'] > 0 && ! in_array( (int) $t['id'], $excl, true ) ) { $out[ (int) $t['id'] ] = $t; }
	}
	return $out;
}
function suorni_jb_plays() {
	$p = get_option( 'suorni_jb_plays', array() );
	if ( ! is_array( $p ) ) { $p = array(); }
	if ( ! isset( $p['t'] ) || ! is_array( $p['t'] ) ) { $p['t'] = array(); }
	if ( ! isset( $p['d'] ) || ! is_array( $p['d'] ) ) { $p['d'] = array(); }
	return $p;
}
function suorni_jb_list() {
	$c = get_transient( 'suorni_jb_list' );
	if ( ! is_array( $c ) ) {
		$tr = suorni_jb_tracks();
		$chs = array();
		$by  = array();
		if ( function_exists( 'suorni_ch_all' ) ) {
			foreach ( suorni_ch_all() as $x ) {
				if ( ! $x['on'] ) { continue; }
				$ids = array();
				foreach ( suorni_ch_tracks( $x['id'] ) as $t ) { $ids[] = (int) $t['id']; }
				if ( count( $ids ) < 3 ) { continue; }
				$chs[] = array( 'id' => $x['id'], 'name' => $x['name'] );
				foreach ( $ids as $i ) { $by[ $i ][] = $x['id']; }
			}
		}
		$pl  = suorni_jb_plays();
		$rows = array();
		foreach ( $tr as $id => $t ) {
			$tv = suorni_tvote_get( $id );
			$rows[] = array(
				'id' => $id,
				't'  => $t['title'],
				'a'  => $t['artist'],
				'u'  => $t['url'],
				'l'  => (int) $t['dur'],
				'at' => (int) $t['at'],
				'c'  => isset( $by[ $id ] ) ? $by[ $id ] : array(),
				'v'  => (int) $tv['u'] - (int) $tv['d'],
				'p'  => isset( $pl['t'][ $id ] ) ? (int) $pl['t'][ $id ] : 0,
			);
		}
		$c = array( 'tracks' => $rows, 'chs' => $chs );
		set_transient( 'suorni_jb_list', $c, 60 );
	}
	return suorni_json( $c );
}
function suorni_jb_play( $req ) {
	$id = absint( $req->get_param( 'id' ) );
	$tr = suorni_jb_tracks();
	if ( ! $id || ! isset( $tr[ $id ] ) ) { return new WP_Error( 'suorni_nf', 'Kappaletta ei löydy.', array( 'status' => 404 ) ); }
	$h  = function_exists( 'suorni_comm_vhash' ) ? suorni_comm_vhash() : 'x';
	$rk = 'suorni_jbr_' . $h;
	$n  = (int) get_transient( $rk );
	if ( $n >= 60 ) { return suorni_json( array( 'ok' => true, 'wait' => true ) ); }
	$sk = 'suorni_jbs_' . md5( $h . $id );
	if ( get_transient( $sk ) ) { return suorni_json( array( 'ok' => true, 'dup' => true ) ); }
	set_transient( $sk, 1, 10 * MINUTE_IN_SECONDS );
	set_transient( $rk, $n + 1, HOUR_IN_SECONDS );
	$p = suorni_jb_plays();
	$p['t'][ $id ] = ( isset( $p['t'][ $id ] ) ? (int) $p['t'][ $id ] : 0 ) + 1;
	$d = wp_date( 'Y-m-d' );
	$p['d'][ $d ] = ( isset( $p['d'][ $d ] ) ? (int) $p['d'][ $d ] : 0 ) + 1;
	ksort( $p['d'] );
	$p['d'] = array_slice( $p['d'], -60, null, true );
	update_option( 'suorni_jb_plays', $p, false );
	return suorni_json( array( 'ok' => true ) );
}
add_action( 'rest_api_init', function () {
	register_rest_route( 'suorni/v1', '/jukebox/list', array( 'methods' => 'GET', 'callback' => 'suorni_jb_list', 'permission_callback' => '__return_true' ) );
	register_rest_route( 'suorni/v1', '/jukebox/play', array( 'methods' => 'POST', 'callback' => 'suorni_jb_play', 'permission_callback' => '__return_true' ) );
} );
/* Ohjaamon tilasto */
function suorni_jb_admin() {
	$p  = suorni_jb_plays();
	$tr = suorni_jb_tracks();
	arsort( $p['t'] );
	$top = array();
	foreach ( $p['t'] as $id => $n ) {
		if ( count( $top ) >= 15 ) { break; }
		$t = isset( $tr[ $id ] ) ? $tr[ $id ] : null;
		$top[] = array( 'n' => $t ? $t['title'] : ( '#' . $id ), 'p' => (int) $n );
	}
	$days = array_slice( array_reverse( $p['d'], true ), 0, 14, true );
	$out = array();
	foreach ( $days as $d => $n ) { $out[] = array( 'd' => $d, 'p' => (int) $n ); }
	return array( 'top' => $top, 'days' => $out, 'tot' => array_sum( $p['t'] ) );
}
/* Etusivun kortti ja ikkuna (kutsutaan radiosivulta) */
function suorni_jb_html() {
	wp_enqueue_script( 'suorni-jukebox', plugins_url( 'jukebox.js', __FILE__ ), array(), SUORNI_VER, true );
	ob_start();
	?>
<style>
.sr .rjb{margin:18px 0 6px}
.sr .rjb button.rjo{display:flex;align-items:center;gap:12px;width:100%;text-align:left;padding:12px 14px;border-radius:12px;border:1px solid rgba(var(--ar),.35);background:rgba(var(--ar),.08);color:inherit;font:inherit;cursor:pointer}
.sr .rjb button.rjo b{display:block;font-size:16px}.sr .rjb button.rjo small{display:block;opacity:.8;font-size:13px}
.sr .rjb .rje{font-size:26px;line-height:1}
.rjw{position:fixed;inset:0;z-index:100000;background:#121316;color:#efe8d8;display:none;flex-direction:column;font:16px/1.45 system-ui,-apple-system,"Segoe UI",sans-serif}
.rjw.on{display:flex}
.rjw *{box-sizing:border-box}
.rjw header{display:flex;align-items:center;gap:10px;padding:12px 14px;border-bottom:1px solid #2a2d34}
.rjw header h3{margin:0;font-size:18px;flex:1}
.rjw button{font:inherit;color:inherit;cursor:pointer}
.rjw .rjx{padding:8px 14px;border-radius:8px;border:1px solid #4a4e57;background:#2a2c32}
.rjw .rjt{padding:10px 14px;display:flex;flex-direction:column;gap:8px;border-bottom:1px solid #2a2d34}
.rjw input[type=search]{width:100%;padding:10px 12px;border-radius:8px;border:1px solid #4a4e57;background:#1c1f25;color:#fff;font:inherit}
.rjw .rjf{display:flex;gap:6px;overflow-x:auto;padding-bottom:2px}
.rjw .rjf button{flex:none;padding:6px 12px;border-radius:999px;border:1px solid #4a4e57;background:#1c1f25;font-size:14px}
.rjw .rjf button.on{background:#4da3ff;color:#06121f;border-color:#4da3ff;font-weight:700}
.rjw .rjl{flex:1;overflow-y:auto;padding:4px 0 90px}
.rjw .rjr{display:flex;align-items:center;gap:10px;padding:9px 14px;border-bottom:1px solid #20232a}
.rjw .rjr.cur{background:rgba(77,163,255,.12)}
.rjw .rjr .rjp{flex:none;width:42px;height:42px;border-radius:50%;border:0;background:#4da3ff;color:#06121f;font-size:18px}
.rjw .rjr .rji{flex:1;min-width:0}
.rjw .rjr .rji b{display:block;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.rjw .rjr .rji span{display:block;font-size:13px;opacity:.7;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.rjw .rjr .rjv{flex:none;border:1px solid #3a3e47;background:#1c1f25;border-radius:8px;padding:6px 9px;font-size:13px}
.rjw .rjr .rjv.on{background:#2f6bff;border-color:#2f6bff}
.rjw .rjn{padding:22px 14px;opacity:.7}
.rjw footer{position:absolute;left:0;right:0;bottom:0;background:#1d1f24;border-top:1px solid #3a3e47;padding:10px 14px;display:none}
.rjw footer.on{display:block}
.rjw footer .rjm{display:flex;align-items:center;gap:10px}
.rjw footer .rjm b{flex:1;min-width:0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.rjw footer button{padding:8px 12px;border-radius:8px;border:1px solid #4a4e57;background:#2a2c32}
.rjw footer input[type=range]{width:100%;margin-top:6px;accent-color:#4da3ff}
.rjw footer .rjtm{display:flex;justify-content:space-between;font-size:12px;opacity:.7}
</style>
<section class="rjb"><button type="button" class="rjo" data-jbopen><span class="rje" aria-hidden="true">🎵</span><span><b>Jukeboksi</b><small>Valitse itse mikä tahansa Suornin kappale ja kuuntele se</small></span></button></section>
<div class="rjw" data-jbwin role="dialog" aria-modal="true" aria-label="Jukeboksi" data-list="<?php echo suorni_ep( 'jukebox/list' ); ?>" data-play="<?php echo suorni_ep( 'jukebox/play' ); ?>" data-vote="<?php echo suorni_ep( 'radio/vote' ); ?>">
	<header><h3>🎵 Jukeboksi</h3><button type="button" class="rjx" data-jbclose>Sulje</button></header>
	<div class="rjt"><input type="search" data-jbq placeholder="Hae kappaletta tai tunnelmaa" aria-label="Haku" maxlength="40"><div class="rjf" data-jbch></div><div class="rjf" data-jbsort></div></div>
	<div class="rjl" data-jblist><p class="rjn">Ladataan kappaleita...</p></div>
	<footer data-jbbar><div class="rjm"><button type="button" data-jbpp aria-label="Tauko">⏸</button><b data-jbnow></b><button type="button" data-jbnext aria-label="Seuraava">⏭</button><button type="button" data-jbradio>📻 Radioon</button></div><input type="range" min="0" max="1000" value="0" data-jbseek aria-label="Kohta kappaleessa"><div class="rjtm"><span data-jbt1>0:00</span><span data-jbt2>0:00</span></div></footer>
</div>
	<?php
	return ob_get_clean();
}

(function () {
	'use strict';
	var box = document.querySelector('[data-radio-box]');
	if (!box) { return; }
	var det = box.closest('details');
	var U = { admin: box.getAttribute('data-admin'), cfg: box.getAttribute('data-cfg'), greets: box.getAttribute('data-greets'), info: box.getAttribute('data-info'), ai: box.getAttribute('data-ai'), tracks: box.getAttribute('data-tracks'), aitest: box.getAttribute('data-aitest'), feedtest: box.getAttribute('data-feedtest'), chatdel: box.getAttribute('data-chatdel'), chatair: box.getAttribute('data-chatair'), chatlist: box.getAttribute('data-chatlist') };
	var redraw = { c: null, g: null, ib: null };
	setInterval(function () {
		if (!loaded || document.hidden || !U.chatlist) { return; }
		api('GET', q(U.chatlist)).then(function (r) { if (r.chat) { D.chat = r.chat; } if (r.greets) { D.greets = r.greets; } if (r.reqs) { D.reqs = r.reqs; } if (r.subs_pending != null) { D.subs_pending = r.subs_pending; } if (redraw.c) { redraw.c(); } if (redraw.g) { redraw.g(); } if (redraw.ib) { redraw.ib(); } }).catch(function () {});
	}, 6000);
	function q(u) { return u + (u.indexOf('?') < 0 ? '?' : '&') + 'r=' + Date.now(); }
	var nonce = box.getAttribute('data-nonce');
	var D = null, loaded = false;
	var DAYS = ['Ma', 'Ti', 'Ke', 'To', 'Pe', 'La', 'Su'];
	var EV = { hour: 'Joka tasatunti', half: 'Joka puolituntia', songs: 'Joka n:s kappale', off: 'Ei juontajaa' };

	function api(m, u, b) {
		var o = { method: m, credentials: 'same-origin', cache: 'no-store', headers: { 'X-WP-Nonce': nonce } };
		if (b !== undefined) { o.headers['Content-Type'] = 'application/json'; o.body = JSON.stringify(b); }
		return fetch(u, o).then(function (r) { return r.json().then(function (j) { if (!r.ok) { throw new Error(j.message || 'Virhe'); } return j; }); });
	}
	function vopt(vals, cur, names) { return vals.map(function (v, i) { return '<option value="' + v + '"' + ((cur || '') === v ? ' selected' : '') + '>' + names[i] + '</option>'; }).join(''); }
	function el(t, a, h) { var e = document.createElement(t); if (a) { for (var k in a) { e.setAttribute(k, a[k]); } } if (h !== undefined) { e.innerHTML = h; } return e; }
	function esc(s) { return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }
	var st = 'width:100%;margin:4px 0;padding:8px;border-radius:8px;border:1px solid #363d4a;background:#1b1f27;color:#eef0f4;font:inherit;font-size:16px';

	function load() {
		box.innerHTML = '<p>Ladataan…</p>';
		api('GET', U.admin).then(function (j) { D = j; loaded = true; draw(); }).catch(function (e) { box.innerHTML = '<p>Virhe: ' + esc(e.message) + '</p>'; });
	}
	if (det) { det.addEventListener('toggle', function () { if (det.open && !loaded) { load(); } }); } else { load(); }


	/* Studio-säätimet: numerokentille liukusäädin (fader), arvo pysyy samassa kentässä */
	function faders(root) {
		[].forEach.call(root.querySelectorAll('input[type=number][min][max]'), function (n) {
			if (n.getAttribute('data-fd')) { return; }
			var mn = parseFloat(n.getAttribute('min')), mx = parseFloat(n.getAttribute('max')), stp = n.getAttribute('step') || '1';
			if (!(mx > mn) || mx - mn > 1000 || n.type === 'hidden') { return; }
			n.setAttribute('data-fd', '1');
			var w = document.createElement('div'); w.className = 'st-fader';
			var r = document.createElement('input'); r.type = 'range'; r.min = mn; r.max = mx; r.step = stp; r.value = n.value === '' ? mn : n.value;
			r.setAttribute('aria-label', 'Säädin');
			n.parentNode.insertBefore(w, n); w.appendChild(r); w.appendChild(n);
			var pc = function () { r.style.setProperty('--v', ((parseFloat(r.value) - mn) / (mx - mn) * 100) + '%'); };
			r.addEventListener('input', function () { n.value = r.value; pc(); n.dispatchEvent(new Event('input', { bubbles: true })); n.dispatchEvent(new Event('change', { bubbles: true })); });
			n.addEventListener('input', function () { if (n.value !== '') { r.value = n.value; pc(); } });
			pc();
		});
	}
	function draw() {
		var c = D.cfg;
		box.innerHTML = '';
		var info = el('p', null, 'Radiossa voi soida vain <b>omat äänitiedostot</b> (mediakirjasto, kesto tiedossa). Suno-linkit ja suorat äänilinkit käyvät myös (kesto täytyy olla tiedossa). Soitettavia kappaleita nyt: <b>' + D.usable + '</b>. Kaupan soitin on aivan erillinen.');
		box.appendChild(info);
		if (D.djerr) { box.appendChild(el('p', { style: 'color:#ffb4b4' }, '⚠️ Juontajan viimeisin virhe: ' + esc(D.djerr))); }
		if (!D.tts.haskey) { box.appendChild(el('p', null, '⚠️ Juontajan ääni vaatii Azure-avaimen (Juontaja-kohta). Ilman sitä radio soittaa vain musiikkia.')); }

		/* Tilastot */
		var s = D.stats, sd = el('div');
		var rows = s.days.slice(0, 7).map(function (d) { return '<tr><td>' + d.day + '</td><td>' + d.unique + '</td><td>' + d.minutes + ' min</td><td>' + d.avg + ' min</td></tr>'; }).join('');
		var pendS = (D.subs || []).filter(function (x) { return x.status === 'pending'; }).length, pendG = (D.greets || []).filter(function (g) { return ['ok', 'no', 'played'].indexOf(g.status) < 0; }).length, pendR = (D.reqs || []).filter(function (x) { return x.st === 'pending'; }).length;
		var srcTot = 0, srcMax = 1; (s.src || []).forEach(function (r) { srcTot += r[1]; if (r[1] > srcMax) { srcMax = r[1]; } });
		var srcUnk = Math.max(0, (s.week_sum || 0) - srcTot);
		function barRow(lbl, n, mx) { return '<div style="display:flex;align-items:center;gap:8px;margin:3px 0;font-size:14px"><span style="width:92px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">' + esc(lbl) + '</span><span style="flex:1;background:rgba(255,255,255,.08);border-radius:4px;height:12px"><i style="display:block;height:12px;border-radius:4px;background:var(--sp-acc,#ffb347);width:' + Math.max(2, Math.round(n / mx * 100)) + '%"></i></span><b style="width:34px;text-align:right">' + n + '</b></div>'; }
		var srcHtml = (s.src || []).length ? (s.src || []).map(function (r) { return barRow(r[0], r[1], srcMax); }).join('') + (srcUnk > 0 ? '<div style="font-size:13px;opacity:.65;margin-top:4px">Lähde tuntematon: ' + srcUnk + ' (ennen seurannan käyttöönottoa)</div>' : '') : '<p style="font-size:13px;opacity:.7">Ei vielä tietoja. Lähde alkaa kertyä, kun kuuntelijat tulevat sivulle.</p>';
		var hrs = s.hours14 || [], hrMax = 1; hrs.forEach(function (v) { if (v > hrMax) { hrMax = v; } });
		var hourHtml = '<div style="display:flex;align-items:flex-end;gap:2px;height:70px">' + hrs.map(function (v, i) { return '<i title="' + i + ':00 – ' + v + ' min" style="flex:1;border-radius:2px 2px 0 0;background:var(--sp-acc,#ffb347);opacity:' + (v ? 1 : .18) + ';height:' + Math.max(3, Math.round(v / hrMax * 100)) + '%"></i>'; }).join('') + '</div><div style="display:flex;justify-content:space-between;font-size:11px;opacity:.6"><span>0</span><span>6</span><span>12</span><span>18</span><span>23</span></div>';
		function tile(v, l) { return '<div class="st-tile"><b>' + v + '</b><span>' + l + '</span></div>'; }
		sd.innerHTML = '<h3>Yleiskatsaus</h3><div class="st-tiles">' + tile(s.live, 'Kuuntelijaa nyt') + tile(s.week_sum, 'Kuuntelijaa viikolla') + tile(s.peak_hour >= 0 ? s.peak_hour + ':00' : '–', 'Vilkkain tunti tänään') + tile(D.usable, 'Soitettavaa kappaletta') + tile(pendS + pendG + pendR, 'Odottaa käsittelyä') + '</div>' +
			'<h3>Kuuntelijat viikolla</h3>' + '<table style="width:100%;font-size:14px;border-collapse:collapse"><tr style="text-align:left"><th>Päivä</th><th>Kuuntelijoita</th><th>Kuunteluaika</th><th>Keskim.</th></tr>' + rows + '</table>' +
			'<h3>Mistä kuuntelijat tulevat (7 pv)</h3>' + srcHtml + ((s.chs || []).length > 1 ? '<h3>Kuunteluaika kanavittain (7 pv, min)</h3>' + (function () { var mx = 1; s.chs.forEach(function (r) { if (r[1] > mx) { mx = r[1]; } }); return s.chs.map(function (r) { return barRow(r[0], r[1], mx); }).join(''); })() : '') + '<h3>Kuunteluaika tunneittain (14 pv, min)</h3>' + hourHtml +
			'<p style="font-size:13px;opacity:.7">Tilastot ovat anonyymejä (ei evästeitä, ei tunnistetietoja). Sama kuuntelija lasketaan kerran päivässä. Huippu viikolla: <b>' + (s.peak_live || 0) + '</b> kuuntelijaa yhtä aikaa.</p>' +
			'<p style="font-size:13px;opacity:.7">💡 Käytä somessa linkkiä <b>suorni.fi/?src=tiktok</b>. Eri videoille voit tehdä omat tunnisteet (esim. <b>?src=video1</b>), jolloin näet, mikä video toi kuuntelijoita. Tunniste on 1–20 merkkiä: a–z, 0–9, - ja _.</p>';
		box.appendChild(sd);

		/* Asetukset */
		var h = el('div');
		var hA = el('div');
		hA.innerHTML = '<h3 style="margin:14px 0 6px">Kanava</h3>' +
			'<label><input type="checkbox" data-on' + (c.on ? ' checked' : '') + '> Radio on käynnissä</label><br>' +
			'<input style="' + st + '" data-name maxlength="60" placeholder="Kanavan nimi" value="' + esc(c.name) + '">' +
			'<input style="' + st + '" data-cover placeholder="Oma kansikuva (kuvan osoite, tyhjä = oletus Suomi-maisema)" value="' + esc(c.cover) + '">' +
			'<label><input type="checkbox" data-sc' + (c.showcount ? ' checked' : '') + '> Näytä kuuntelijamäärä sivulla</label><br><label><input type="checkbox" data-wel' + (c.welcome !== false ? ' checked' : '') + '> Juontaja toivottaa uuden kuuntelijan tervetulleeksi (kun ensimmäinen kappale on kuunneltu loppuun)</label><br><label><input type="checkbox" data-wxs' + (c.wx_show ? ' checked' : '') + '> Näytä sää sivulla (paikka ja koordinaatit: Juontaja-välilehti)</label>';
		(function () {
			var ci = hA.querySelector('[data-cover]'); if (!ci) { return; }
			var row = el('div', { style: 'display:flex;gap:6px;align-items:center;flex-wrap:wrap;margin:2px 0 8px' });
			var fi2 = el('input', { type: 'file', accept: 'image/jpeg,image/png,image/webp,image/gif', style: 'display:none' });
			var bu = el('button', { type: 'button' }, '📷 Lataa kansikuva');
			var bd = el('button', { type: 'button' }, 'Palauta oletusmaisema');
			var mg = el('span', { style: 'font-size:13px;opacity:.85' });
			bu.addEventListener('click', function () { fi2.click(); });
			bd.addEventListener('click', function () { ci.value = ''; mg.textContent = 'Muista painaa Tallenna.'; });
			fi2.addEventListener('change', function () {
				var f = fi2.files && fi2.files[0]; if (!f) { return; }
				mg.textContent = '⏳ Ladataan…'; bu.disabled = true;
				var fd = new FormData(); fd.append('file', f);
				fetch(D.urls.coverup, { method: 'POST', credentials: 'same-origin', headers: { 'X-WP-Nonce': nonce }, body: fd }).then(function (r) { return r.json().then(function (j) { if (!r.ok) { throw new Error(j.message || 'Virhe'); } return j; }); }).then(function (j) {
					ci.value = j.url; mg.textContent = '✅ Kuva ladattu. Paina lopuksi Tallenna.'; bu.disabled = false; fi2.value = '';
				}).catch(function (e) { mg.textContent = '❌ ' + e.message; bu.disabled = false; });
			});
			row.appendChild(bu); row.appendChild(bd); row.appendChild(fi2); row.appendChild(mg);
			ci.parentNode.insertBefore(row, ci.nextSibling);
		})();
		var hB = el('div');
		hB.innerHTML = '<h3 style="margin:14px 0 6px">💬 Terveiset ja chat</h3>' + '<p style="margin:10px 0 2px"><b>Kuuntelijoiden terveiset</b></p><select style="' + st + '" data-gm><option value="hold"' + (c.greet_mode !== 'live' ? ' selected' : '') + '>Tarkistan itse ennen kuin ne luetaan</option><option value="live"' + (c.greet_mode === 'live' ? ' selected' : '') + '>Lue suoraan heti kesken kappaleen (linkit ja numerot suodatetaan pois)</option></select>' +
			'<p style="margin:6px 0 2px">Terveisiä / kuuntelija / tunti (0 = ei rajaa)</p><input style="' + st + '" type="number" min="0" max="100" data-gl value="' + (c.greet_limit || 0) + '">' +
			'<label><input type="checkbox" data-gv' + (c.greet_voices ? ' checked' : '') + '> Kuuntelija saa valita äänen terveisilleen (möreä mies, robotti, lordi…)</label><br>' +
			'<label><input type="checkbox" data-chon' + (c.chat ? ' checked' : '') + '> Kuuntelijoiden chat käytössä</label><br><label><input type="checkbox" data-chai' + (c.chat_ai ? ' checked' : '') + '> Juontaja vastaa chatissa tekoälyllä</label>' +
			'<p style="margin:6px 0 2px">Chatin lukeminen radiossa</p><select style="' + st + '" data-chair><option value="owner"' + (c.chat_air !== 'auto' ? ' selected' : '') + '>Vain kun painan "Lue radiossa"</option><option value="auto"' + (c.chat_air === 'auto' ? ' selected' : '') + '>Automaattisesti juontajan vastaukset (enintään 5 min välein)</option></select>' +
			'<p style="margin:10px 0 2px"><b>🤖 Tekoälyvieraat chatissa</b></p><p style="font-size:13px;opacity:.7;margin:0">Tekoälyhahmot kirjoittavat chattiin viestejä kappaleista, uutisista ja keskustelusta. Heidän viestinsä on aina merkitty 🤖-kuvakkeella, ja chatissa kerrotaan, että kuvake tarkoittaa tekoälyvierasta. Heitä ei lasketa kuuntelijoihin. Vaatii tekoälyjuontajan avaimen. Poista ominaisuus ottamalla valinta pois.</p>' +
			'<label><input type="checkbox" data-gon' + (c.guests ? ' checked' : '') + '> Tekoälyvieraat käytössä</label>' +
			'<p style="margin:6px 0 2px">Viesti keskimäärin n. kerran / min (40 = 1–2 viestiä tunnissa, kun joku kuuntelee)</p><input style="' + st + '" type="number" min="2" max="240" data-gev value="' + c.guests_every + '">' +
			'<p style="margin:6px 0 2px">Viestejä enintään päivässä (kulukatto)</p><input style="' + st + '" type="number" min="1" max="1000" data-gmax value="' + c.guests_max + '">' +
			'<p style="margin:6px 0 2px">Todennäköisyys, että juontaja vastaa vieraan viestiin (%)</p><input style="' + st + '" type="number" min="0" max="100" data-grp value="' + c.guests_reply + '">' +
			'<p style="margin:6px 0 2px">Poista vieraat automaattisesti, kun päivän kuuntelijoita on vähintään (0 = ei automaattista poistoa)</p><input style="' + st + '" type="number" min="0" max="100000" data-goff value="' + c.guests_off + '">' + (c.guests_note ? '<p style="color:#8ff0df">' + esc(c.guests_note) + '</p>' : '') +
			'<p style="margin:6px 0 2px">Vieraat (yksi per rivi, muodossa Nimi: persoona)</p><textarea style="' + st + '" rows="5" data-glist>' + esc(c.guests_list) + '</textarea>' +
			'<p style="margin:6px 0 2px">Kiellettyjä sanoja (yksi per rivi; viesti ei mene läpi)</p><textarea style="' + st + '" rows="3" data-chbl>' + esc(c.chat_block) + '</textarea>' +
			'<p style="margin:6px 0 2px">Tallennettavia viestejä enintään</p><input style="' + st + '" type="number" min="1" max="500" data-chmx value="' + c.chat_max + '">';
		var hC = el('div');
		hC.innerHTML = '<h3 style="margin:14px 0 6px">Juontajan puhe (vain radio)</h3><p style="font-size:13px;opacity:.7">Nämä ovat radion omat asetukset, kaupan soitin ei vaikuta niihin. Azure-ääni on yhteinen.</p>' +
			'<label><input type="checkbox" data-djt' + (c.dj.time ? ' checked' : '') + '> Kerro kellonaika</label><br>' +
			'<label><input type="checkbox" data-djw' + (c.dj.weather ? ' checked' : '') + '> Kerro sää</label>' +
			'<input style="' + st + '" data-djwh maxlength="40" placeholder="Paikka (esim. Lohjalla)" value="' + esc(c.dj.where) + '">' +
			'<div style="display:flex;gap:8px"><input style="' + st + '" type="number" step="0.0001" data-djla placeholder="Leveysaste" value="' + c.dj.lat + '"><input style="' + st + '" type="number" step="0.0001" data-djlo placeholder="Pituusaste" value="' + c.dj.lon + '"></div>' +
			'<p style="margin:6px 0 2px">Montako seuraavaa kappaletta kerrotaan</p><select style="' + st + '" data-djn>' + [0,1,2,3].map(function (n) { return '<option value="' + n + '"' + (c.dj.next === n ? ' selected' : '') + '>' + (n === 0 ? 'Ei yhtään' : n) + '</option>'; }).join('') + '</select>' +
			'<p style="margin:6px 0 2px">Kappaleiden nimien ääntäminen</p><select style="' + st + '" data-djti><option value="auto"' + (c.dj.titles === 'auto' ? ' selected' : '') + '>Automaattinen (suomi/englanti)</option><option value="fi"' + (c.dj.titles === 'fi' ? ' selected' : '') + '>Aina suomeksi</option><option value="off"' + (c.dj.titles === 'off' ? ' selected' : '') + '>Ei kerrota nimiä</option></select>' +
			'<p style="margin:6px 0 2px">Puhenopeus (0,6–1,4)</p><input style="' + st + '" type="number" min="0.6" max="1.4" step="0.05" data-djr value="' + c.dj.rate + '">' +
			'<p style="margin:10px 0 2px"><b>Juonto musiikin päälle</b></p><p style="font-size:12px;opacity:.75;margin:0 0 4px">Juontaja alkaa puhua edellisen kappaleen viimeisten sekuntien päälle ja/tai lopettaa puheensa seuraavan kappaleen alun päälle. Musiikki hiljenee puheen ajaksi taustalle. Ei lisää kuluja. iPhonessa ja myymälässä toimii entiseen tapaan (kappale häivytetään / juonto kuunnellaan loppuun).</p>' +
			'<select style="' + st + '" data-djtk>' + [['off', 'Pois päältä'], ['out', 'Edellisen kappaleen lopun päälle'], ['in', 'Seuraavan kappaleen alun päälle'], ['both', 'Molemmat']].map(function (o) { return '<option value="' + o[0] + '"' + ((c.dj.talk || 'off') === o[0] ? ' selected' : '') + '>' + o[1] + '</option>'; }).join('') + '</select>' +
			'<div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-top:4px;font-size:13px"><label>Lopun päälle <input style="' + st.replace('width:100%;', 'width:64px;') + '" type="number" min="3" max="10" data-djts value="' + (c.dj.talks || 6) + '"> s</label><label>Alun päälle <input style="' + st.replace('width:100%;', 'width:64px;') + '" type="number" min="2" max="8" data-djti2 value="' + (c.dj.talki || 4) + '"> s</label><label>Musiikin voimakkuus puheen aikana <input style="' + st.replace('width:100%;', 'width:64px;') + '" type="number" min="10" max="60" step="5" data-djdk value="' + (c.dj.duck || 25) + '"> %</label></div>' +
			'<p style="margin:10px 0 2px"><b>Päivän jutut / vinkit</b> (yksi per rivi, juontaja lukee ne vuorotellen)</p>' +
			'<textarea style="' + st + '" rows="4" data-tips>' + esc(c.tips) + '</textarea>';
		var hD = el('div');
		hD.innerHTML = '<p style="margin:10px 0 2px"><b>🎵 Yhteisön kappaleet (uudet artistit)</b></p><p style="font-size:13px;opacity:.7;margin:0">Kuka tahansa voi lähettää mp3-kappaleen. Kuuntelet ja hyväksyt jokaisen ennen kuin se soi. Hyväksytty uusi kappale soi seuraavaksi omien kappaleidesi edellä, ja sen jälkeen kuuntelijoiden peukut ratkaisevat. Kappaleet tallennetaan erilleen mediakirjastosta.</p>' +
			'<label><input type="checkbox" data-cmon' + (c.comm ? ' checked' : '') + '> Kappaleiden lähetys ja yhteisön kappaleet käytössä</label>' +
			'<p style="margin:6px 0 2px">Kuinka usein hyväksytty, jo soitettu kappale soi (% kappaleista)</p><input style="' + st + '" type="number" min="0" max="100" data-cmsh value="' + c.comm_share + '">' +
			'<p style="margin:6px 0 2px">Pudotus: vähintään näin monta ääntä ennen arviointia</p><input style="' + st + '" type="number" min="3" max="1000" data-cmmv value="' + c.comm_min_votes + '">' +
			'<p style="margin:6px 0 2px">Pudotus: putoaa, jos ylöspeukkuja on alle näin monta % äänistä</p><input style="' + st + '" type="number" min="1" max="90" data-cmmp value="' + c.comm_min_pct + '">' +
			'<p style="margin:6px 0 2px">Suurin tiedostokoko (Mt)</p><input style="' + st + '" type="number" min="1" max="50" data-cmmb value="' + c.comm_max_mb + '">';
		var h = el('div'); h.appendChild(hA); h.appendChild(hB); h.appendChild(hC); h.appendChild(hD);
		box.appendChild(h);

		var A = D.ai, aw = el('div');
		aw.innerHTML = '<h3 style="margin:14px 0 6px">🤖 Tekoälyjuontaja</h3>' +
			'<p style="font-size:13px;opacity:.7">Claude kirjoittaa jokaisen juontajan puheen tilanteen mukaan (aika, sää, sanonnat, kappaleet). Jos tekoäly ei vastaa tai raja täyttyy, käytetään valmiita lauseita. Avain tallennetaan vain palvelimelle (console.anthropic.com).</p>' +
			'<label><input type="checkbox" data-aion' + (A.on ? ' checked' : '') + '> Käytä tekoälyä juontajan puheisiin</label>' +
			'<input style="' + st + '" type="password" data-aikey autocomplete="off" placeholder="' + (A.haskey ? 'API-avain tallennettu (kirjoita uusi vaihtaaksesi)' : 'Anthropic API-avain (sk-ant-…)') + '">' +
			'<p style="margin:6px 0 2px">Malli</p><select style="' + st + '" data-aimodel><option value="claude-haiku-5-5"' + (A.model === 'claude-haiku-5-5' ? ' selected' : '') + '>Haiku 5.5 (nopea ja halpa, suositus)</option><option value="claude-sonnet-5-5"' + (A.model === 'claude-sonnet-5-5' ? ' selected' : '') + '>Sonnet 5.5 (fiksumpi, kalliimpi)</option></select>' +
			'<p style="margin:6px 0 2px">Juontajan persoona</p><textarea style="' + st + '" rows="4" maxlength="800" data-aipersona>' + esc(A.persona) + '</textarea>' +
			'<p style="margin:6px 0 2px">Puheita enintään päivässä (kulukatto). Tänään käytetty: <b>' + A.used + '</b></p><input style="' + st + '" type="number" min="1" max="1000" data-aimax value="' + A.max + '">' +
			(A.err ? '<p style="color:#ffb4b4">Viimeisin virhe: ' + esc(A.err) + '</p>' : '');
		var abtn = el('button', { type: 'button', style: 'flex:none;margin-right:8px' }, 'Tallenna tekoälyasetukset');
		var atest = el('button', { type: 'button', style: 'flex:none' }, 'Kokeile tekoälyä');
		var amsg = el('p', { style: 'white-space:pre-wrap' });
		abtn.addEventListener('click', function () {
			api('POST', U.ai, { on: aw.querySelector('[data-aion]').checked, key: aw.querySelector('[data-aikey]').value, model: aw.querySelector('[data-aimodel]').value, persona: aw.querySelector('[data-aipersona]').value, max: +aw.querySelector('[data-aimax]').value || 80 })
				.then(function (r) { D.ai = r; amsg.textContent = 'Tallennettu.' + (r.haskey ? '' : ' Muista lisätä API-avain.'); aw.querySelector('[data-aikey]').value = ''; })
				.catch(function (e) { amsg.textContent = 'Virhe: ' + e.message; });
		});
		atest.addEventListener('click', function () {
			amsg.textContent = 'Kokeillaan…';
			api('POST', U.aitest, {}).then(function (r) { amsg.textContent = r.ok ? r.text : 'Ei onnistunut: ' + r.message; }).catch(function (e) { amsg.textContent = 'Virhe: ' + e.message; });
		});
		aw.appendChild(abtn); aw.appendChild(atest); aw.appendChild(amsg);
		box.appendChild(aw);

		var fw = el('div');
		fw.innerHTML = '<h3 style="margin:14px 0 6px">📰 Uutiset ja urheilu</h3><p style="font-size:13px;opacity:.7">Juontaja voi kertoa päivän uutisia, Liigan ja muun urheilun tuloksia sekä NHL:n edellisen illan tulokset suomalaisineen. Valitse ohjelmakohtaisesti (Uutiset, Urheilu/Liiga, NHL). Tiedot haetaan automaattisesti, ja tekoäly kertoo vain annetut tiedot. Tarkista Ylen RSS-syötteiden käyttöehdot, erityisesti jos radio tuottaa tuloja.</p>' +
			'<p style="margin:6px 0 2px">Uutissyöte (RSS)</p><input style="' + st + '" data-nf value="' + esc(c.news_feed) + '">' +
			'<p style="margin:6px 0 2px">Urheilusyöte (RSS)</p><input style="' + st + '" data-sf value="' + esc(c.sport_feed) + '">' +
			'<p style="margin:6px 0 2px">Seurattavat suomalaiset NHL-pelaajat (yksi per rivi, esim. A. Barkov)</p><textarea style="' + st + '" rows="5" data-fin>' + esc(c.finns) + '</textarea>';
		var ftest = el('button', { type: 'button', style: 'flex:none' }, 'Kokeile uutisia ja urheilua');
		var fmsg = el('p', { style: 'white-space:pre-wrap;font-size:14px' });
		ftest.addEventListener('click', function () {
			fmsg.textContent = 'Haetaan… (tallenna ensin asetukset, jos muutit syötteitä)';
			api('POST', U.feedtest, {}).then(function (r) { fmsg.textContent = r.text; }).catch(function (e) { fmsg.textContent = 'Virhe: ' + e.message; });
		});
		fw.appendChild(ftest); fw.appendChild(fmsg);
		box.appendChild(fw);

		var cw = el('div');
		cw.innerHTML = '<h3 style="margin:14px 0 6px">💬 Chat-viestit</h3>';
		var cl = el('div');
		cw.appendChild(cl); box.appendChild(cw);
		function drawC() {
			cl.innerHTML = '';
			if (!D.chat.length) { cl.innerHTML = '<p style="opacity:.7">Ei viestejä.</p>'; return; }
			var bots = el('button', { type: 'button', style: 'margin:0 6px 6px 0' }, 'Poista tekoälyvieraiden viestit');
			bots.addEventListener('click', function () { api('POST', U.chatdel, { id: 'bots' }).then(function (r) { D.chat = r.chat; drawC(); }); });
			cl.appendChild(bots);
			var all = el('button', { type: 'button', style: 'margin-bottom:6px' }, 'Tyhjennä koko chat');
			all.addEventListener('click', function () { api('POST', U.chatdel, { id: 'all' }).then(function (r) { D.chat = r.chat; drawC(); }); });
			cl.appendChild(all);
			D.chat.forEach(function (m) {
				var r = el('div', { style: 'border:1px solid #363d4a;border-radius:10px;padding:6px 8px;margin:4px 0;display:flex;gap:8px;align-items:center' });
				r.innerHTML = '<span style="flex:1"><b>' + (m.dj ? '🎙️ ' : '') + (m.b ? '🤖 ' : '') + esc(m.n) + ':</b> ' + esc(m.m) + '</span>';
				if (!m.dj) { var ar = el('button', { type: 'button' }, 'Lue radiossa'); ar.addEventListener('click', function () { ar.disabled = true; api('POST', U.chatair, { id: m.id }).then(function () { ar.textContent = 'Jonossa ✓'; }).catch(function (e) { ar.disabled = false; alert(e.message); }); }); r.appendChild(ar); }
				var d = el('button', { type: 'button' }, 'Poista');
				d.addEventListener('click', function () { api('POST', U.chatdel, { id: m.id }).then(function (r2) { D.chat = r2.chat; drawC(); }); });
				r.appendChild(d); cl.appendChild(r);
			});
		}
		redraw.c = drawC;
		drawC();

		var pw = el('div');
		pw.innerHTML = '<h3 style="margin:14px 0 6px">Ohjelmat</h3><p style="font-size:13px;opacity:.7">Ylin sopiva ohjelma voittaa. Jos mikään ei osu kohdalle, käytetään ensimmäistä.</p>';
		var plist = el('div');
		pw.appendChild(plist);
		box.appendChild(pw);
		var progs = JSON.parse(JSON.stringify(c.programs));
		function drawProgs() {
			plist.innerHTML = '';
			progs.forEach(function (p, i) {
				var w = el('div', { style: 'border:1px solid #363d4a;border-radius:10px;padding:10px;margin:8px 0' });
				var days = DAYS.map(function (d, k) { return '<label style="margin-right:6px"><input type="checkbox" data-d="' + (k + 1) + '"' + (p.days.indexOf(k + 1) >= 0 ? ' checked' : '') + '>' + d + '</label>'; }).join('');
				var lists = '<option value="">Kaikki kappaleet</option>' + D.lists.map(function (l) { return '<option value="' + esc(l.id || l.slug || l.name) + '"' + ((l.id || l.slug || l.name) === p.list ? ' selected' : '') + '>' + esc(l.name) + '</option>'; }).join('');
				var ev = Object.keys(EV).map(function (k) { return '<option value="' + k + '"' + (p.every === k ? ' selected' : '') + '>' + EV[k] + '</option>'; }).join('');
				var cb = function (k, t) { return '<label style="margin-right:10px"><input type="checkbox" data-f="' + k + '"' + (p[k] ? ' checked' : '') + '> ' + t + '</label>'; };
				w.innerHTML = '<input style="' + st + '" data-f="name" value="' + esc(p.name) + '" placeholder="Ohjelman nimi">' +
					'<div>' + days + '</div>' +
					'<div style="display:flex;gap:8px;align-items:center">Klo <input style="' + st + '" type="time" data-f="from" value="' + esc(p.from === '24:00' ? '00:00' : p.from) + '"> – <input style="' + st + '" type="time" data-f="to" value="' + esc(p.to === '24:00' ? '00:00' : p.to) + '"></div>' +
					'<p style="margin:6px 0 2px">Ohjelman kappaleet</p><details data-tdet><summary>' + (p.ids && p.ids.length ? p.ids.length + ' kappaletta valittu' : 'Kaikki radion kappaleet') + '</summary>' +
					'<div style="font-size:13px;opacity:.7;margin:4px 0">Valitse kappaleet, joita tässä ohjelmassa soitetaan. Jos mitään ei valita, soitetaan kaikkia radion kappaleita.</div>' +
					D.tracks.filter(function (t) { return !t.hidden; }).map(function (t) { return '<label style="display:block;margin:3px 0"><input type="checkbox" data-t="' + t.id + '"' + ((p.ids || []).indexOf(t.id) >= 0 ? ' checked' : '') + '> ' + esc(t.title) + '</label>'; }).join('') + '</details>' +
					'<p style="margin:6px 0 2px">Tai kaupan soittimen teema (vain jos kappaleita ei ole valittu)</p><select style="' + st + '" data-f="list">' + lists + '</select>' +
					'<p style="margin:6px 0 2px">Juontajan tiheys (kappaleväli näkyy vain valinnalla "Joka n:s kappale")</p><select style="' + st + '" data-f="every">' + ev + '</select>' +
					'<div data-songs style="display:' + (p.every === 'songs' ? 'flex' : 'none') + ';gap:8px;align-items:center">Kappaleväli <input style="' + st + '" type="number" min="1" max="20" data-f="min" value="' + p.min + '"> – <input style="' + st + '" type="number" min="1" max="20" data-f="max" value="' + p.max + '"></div>' +
					'<input style="' + st + '" data-f="host" maxlength="30" placeholder="Juontajan nimi (valinnainen, esim. Aino)" value="' + esc(p.host) + '">' +
					'<textarea style="' + st + '" rows="3" maxlength="600" data-f="persona" placeholder="Tämän ohjelman juontajan persoona (tyhjä = yhteinen persoona)">' + esc(p.persona) + '</textarea>' +
					'<p style="margin:6px 0 2px">Juontajan ääni (suomi)</p><select style="' + st + '" data-f="vfi">' + vopt(['', 'fi-FI-NooraNeural', 'fi-FI-SelmaNeural', 'fi-FI-HarriNeural', 'en-US-AvaMultilingualNeural', 'en-US-EmmaMultilingualNeural', 'en-US-AndrewMultilingualNeural', 'en-US-BrianMultilingualNeural'], p.vfi, ['Oletus (yhteinen)', 'Noora (nainen)', 'Selma (nainen)', 'Harri (mies)', 'Ava – monikielinen, luonnollisempi (nainen, kokeellinen)', 'Emma – monikielinen (nainen, kokeellinen)', 'Andrew – monikielinen (mies, kokeellinen)', 'Brian – monikielinen (mies, kokeellinen)']) + '</select>' +
					'<p style="margin:6px 0 2px">Englanninkielisten nimien ääni</p><select style="' + st + '" data-f="ven">' + vopt(['', 'en-US-JennyNeural', 'en-US-AriaNeural', 'en-US-GuyNeural', 'en-GB-SoniaNeural', 'en-GB-RyanNeural'], p.ven, ['Oletus (yhteinen)', 'Jenny (nainen, US)', 'Aria (nainen, US)', 'Guy (mies, US)', 'Sonia (nainen, UK)', 'Ryan (mies, UK)']) + '</select>' +
					'<p style="margin:6px 0 2px">Puhenopeus tälle ohjelmalle (tyhjä/0 = radion oletus)</p><input style="' + st + '" type="number" min="0" max="1.4" step="0.05" data-f="rate" value="' + (p.rate || 0) + '">' +
					'<div style="margin:6px 0">' + cb('weather', 'Sää') + cb('tip', 'Päivän juttu') + cb('intro', 'Kappaleen esittely') + cb('greet', 'Terveiset') + cb('ann', 'Ohjelman aloitus') + cb('ai', 'Tekoäly') + cb('news', 'Uutiset') + cb('sports', 'Urheilu/Liiga') + cb('nhl', 'NHL ja suomalaiset') + '</div>' +
					'<button type="button" data-del style="flex:none">Poista ohjelma</button>';
				w.addEventListener('input', sync(i, w)); w.addEventListener('change', sync(i, w));
				w.querySelector('[data-del]').addEventListener('click', function () { if (progs.length > 1) { progs.splice(i, 1); drawProgs(); } });
				plist.appendChild(w);
			});
		}
		function sync(i, w) {
			return function () {
				var p = progs[i];
				p.ids = [].slice.call(w.querySelectorAll('[data-t]')).filter(function (x) { return x.checked; }).map(function (x) { return +x.getAttribute('data-t'); });
				var sg = w.querySelector('[data-songs]'); if (sg) { sg.style.display = (w.querySelector('[data-f="every"]').value === 'songs') ? 'flex' : 'none'; }
				var sm = w.querySelector('[data-tdet] summary'); if (sm) { sm.textContent = p.ids.length ? p.ids.length + ' kappaletta valittu' : 'Kaikki radion kappaleet'; }
				p.days = [].slice.call(w.querySelectorAll('[data-d]')).filter(function (x) { return x.checked; }).map(function (x) { return +x.getAttribute('data-d'); });
				w.querySelectorAll('[data-f]').forEach(function (f) {
					var k = f.getAttribute('data-f');
					if (f.type === 'checkbox') { p[k] = f.checked; }
					else if (f.type === 'number') { p[k] = k === 'rate' ? (+f.value || 0) : (+f.value || 1); }
					else { p[k] = f.value; }
				});
				if (p.to === '00:00') { p.to = '24:00'; }
			};
		}
		drawProgs();
		var quick = el('button', { type: 'button', style: 'flex:none;margin:6px 8px 6px 0' }, 'Luo aamu-, päivä-, ilta- ja yöohjelmat (korvaa nykyiset)');
		quick.addEventListener('click', function () {
			var base = { days: [1, 2, 3, 4, 5, 6, 7], list: '', ids: [], news: false, sports: false, nhl: false, every: 'hour', min: 3, max: 5, ven: '', rate: 0, ai: true, weather: true, tip: true, intro: true, greet: true, ann: true };
			var set = [
				{ id: '', name: 'Aamu', from: '05:00', to: '10:00', host: 'Aino', news: true, sports: true, nhl: true, vfi: 'fi-FI-NooraNeural', persona: 'Olet energinen ja iloinen aamujuontaja Aino. Herätät kuulijat hyvällä fiiliksellä, puhut kahvista, aamun aurinkoisuudesta ja tulevasta päivästä. Puheesi on pirteää ja nopeaa.' },
				{ id: '', name: 'Päivä', from: '10:00', to: '17:00', host: 'Sanni', news: true, vfi: 'fi-FI-SelmaNeural', persona: 'Olet rento ja ystävällinen päiväjuontaja Sanni. Pidät tunnelman kevyenä ja positiivisena, vitsailet maltillisesti ja olet kuin hyvä työkaveri radion äärellä.' },
				{ id: '', name: 'Ilta', from: '17:00', to: '22:00', host: 'Eino', sports: true, vfi: 'fi-FI-HarriNeural', persona: 'Olet lämminhenkinen iltajuontaja Eino. Puhut rauhallisesti, kiität päivästä ja tunnelmoit illan tuloa. Sallit itsellesi pienen huumorin.' },
				{ id: '', name: 'Yö', from: '22:00', to: '05:00', host: 'Unto', vfi: 'fi-FI-HarriNeural', rate: 0.9, persona: 'Olet rauhallinen ja leppoisa yöjuontaja Unto. Puhut hiljaisella, unenomaisella äänellä, lyhyesti ja pehmeästi. Sopii valvojille ja yövuoroihin.' }
			];
			progs.length = 0;
			set.forEach(function (x) { var p = {}; for (var k in base) { p[k] = base[k]; } for (var k2 in x) { p[k2] = x[k2]; } progs.push(p); });
			drawProgs();
			msg.textContent = 'Neljä ohjelmaa luotu (korvasivat vanhat). Paina Tallenna radion asetukset.';
		});
		var add = el('button', { type: 'button', style: 'flex:none;margin:6px 8px 6px 0' }, '+ Lisää ohjelma');
		add.addEventListener('click', function () { progs.push({ id: '', name: 'Uusi ohjelma', days: [1, 2, 3, 4, 5, 6, 7], from: '00:00', to: '24:00', list: '', every: 'hour', min: 3, max: 5, ids: [], vfi: '', ven: '', rate: 0, ai: true, news: false, sports: false, nhl: false, host: '', persona: '', weather: true, tip: true, intro: true, greet: true, ann: true }); drawProgs(); });
		var save = el('button', { type: 'button', 'class': 'pri', style: 'flex:none' }, '💾 Tallenna asetukset ja ohjelmat');
		var msg = el('p');
		save.addEventListener('click', function () {
			progs.forEach(function (p) { if (p.to === '00:00') { p.to = '24:00'; } });
			api('POST', U.cfg, { on: box.querySelector('[data-on]').checked, name: box.querySelector('[data-name]').value, cover: box.querySelector('[data-cover]').value, greet_mode: box.querySelector('[data-gm]').value, greet_voices: box.querySelector('[data-gv]').checked, chat: box.querySelector('[data-chon]').checked, chat_ai: box.querySelector('[data-chai]').checked, chat_air: box.querySelector('[data-chair]').value, guests: box.querySelector('[data-gon]').checked, comm: box.querySelector('[data-cmon]').checked, comm_share: +box.querySelector('[data-cmsh]').value, comm_min_votes: +box.querySelector('[data-cmmv]').value || 10, comm_min_pct: +box.querySelector('[data-cmmp]').value || 40, comm_max_mb: +box.querySelector('[data-cmmb]').value || 15, guests_every: +box.querySelector('[data-gev]').value || 40, guests_off: +box.querySelector('[data-goff]').value || 0, guests_reply: +box.querySelector('[data-grp]').value, guests_max: +box.querySelector('[data-gmax]').value || 60, guests_list: box.querySelector('[data-glist]').value, chat_block: box.querySelector('[data-chbl]').value, chat_max: +box.querySelector('[data-chmx]').value || 60, news_feed: box.querySelector('[data-nf]').value, sport_feed: box.querySelector('[data-sf]').value, finns: box.querySelector('[data-fin]').value, dj_time: box.querySelector('[data-djt]').checked, dj_weather: box.querySelector('[data-djw]').checked, dj_where: box.querySelector('[data-djwh]').value, dj_lat: +box.querySelector('[data-djla]').value, dj_lon: +box.querySelector('[data-djlo]').value, dj_next: +box.querySelector('[data-djn]').value, dj_titles: box.querySelector('[data-djti]').value, dj_rate: +box.querySelector('[data-djr]').value || 1, dj_talk: box.querySelector('[data-djtk]').value, dj_talks: +box.querySelector('[data-djts]').value || 6, dj_talki: +box.querySelector('[data-djti2]').value || 4, dj_duck: +box.querySelector('[data-djdk]').value || 25, greet_limit: +box.querySelector('[data-gl]').value || 0, showcount: box.querySelector('[data-sc]').checked, welcome: box.querySelector('[data-wel]').checked, wx_show: box.querySelector('[data-wxs]').checked, new_default: box.querySelector('[data-nd]').value, boost_on: box.querySelector('[data-bon]').checked, boost_auto: box.querySelector('[data-bau]').checked, boost_days: +box.querySelector('[data-bdy]').value || 7, boost_share: +box.querySelector('[data-bsh]').value, tips: box.querySelector('[data-tips]').value, programs: progs })
				.then(function () { msg.textContent = 'Tallennettu. Uusi ohjelmajono rakentuu seuraavan kuuntelijan myötä.'; })
				.catch(function (e) { msg.textContent = 'Virhe: ' + e.message; });
		});
		box.appendChild(quick); box.appendChild(add); box.appendChild(save); box.appendChild(msg);

		/* Terveiset */
		var gw = el('div');
		gw.innerHTML = '<h3 style="margin:14px 0 6px">Kuuntelijoiden terveiset</h3>';
		var gl = el('div');
		gw.appendChild(gl); box.appendChild(gw);
		function drawG() {
			gl.innerHTML = '';
			var list = D.greets.filter(function (g) { return g.status !== 'played'; }).slice(-30).reverse();
			if (!list.length) { gl.innerHTML = '<p style="opacity:.7">Ei uusia terveisiä.</p>'; return; }
			list.forEach(function (g) {
				var r = el('div', { style: 'border:1px solid #363d4a;border-radius:10px;padding:8px;margin:6px 0' });
				r.innerHTML = '<b>' + esc(g.name) + '</b> <span style="opacity:.6;font-size:13px">' + (g.status === 'ok' ? '✅ hyväksytty' : g.status === 'no' ? '🚫 hylätty' : '⏳ odottaa') + '</span><br>' + esc(g.msg) + (g.voice && g.voice !== 'oletus' && D.gvoices ? '<br><span style="opacity:.7;font-size:13px">Ääni: ' + esc(D.gvoices[g.voice] || g.voice) + '</span>' : '') +
					'<div style="display:flex;gap:6px;margin-top:6px"><button type="button" data-a="now">Lue heti</button><button type="button" data-a="ok">Hyväksy</button><button type="button" data-a="no">Hylkää</button><button type="button" data-a="del">Poista</button></div>';
				r.querySelectorAll('[data-a]').forEach(function (b) {
					b.addEventListener('click', function () {
						api('POST', U.greets, { id: g.id, act: b.getAttribute('data-a') }).then(function (j) { D.greets = j.greets; drawG(); });
					});
				});
				gl.appendChild(r);
			});
		}
		redraw.g = drawG;
		drawG();

		/* Yhteisön kappaleet */
		var scw = el('div'), scl = el('div');
		scw.innerHTML = '<h3 style="margin:14px 0 6px">🎵 Yhteisön kappaleet</h3>';
		scw.appendChild(scl); box.appendChild(scw);
		function drawS() {
			scl.innerHTML = '';
			var order = { pending: 0, dropped: 1, ok: 2 };
			var list = (D.subs || []).slice().sort(function (x, y) { return (order[x.status] - order[y.status]) || (y.at - x.at); });
			var np = list.filter(function (x) { return x.status === 'pending'; }).length;
			scw.querySelector('h3').textContent = '🎵 Yhteisön kappaleet' + (np ? ' (' + np + ' odottaa hyväksyntää)' : '');
			if (!list.length) { scl.innerHTML = '<p style="opacity:.7">Ei lähetettyjä kappaleita.</p>'; return; }
			list.forEach(function (s) {
				var r = el('div', { style: 'border:1px solid #363d4a;border-radius:10px;padding:8px;margin:6px 0' });
				var stt = s.status === 'pending' ? '⏳ odottaa' : s.status === 'ok' ? '✅ soi' : '📉 pudotettu';
				r.innerHTML = '<b>' + esc(s.artist) + ' – ' + esc(s.title) + '</b> <span style="opacity:.6;font-size:13px">' + stt + ' · ' + Math.floor(s.dur / 60) + ':' + ('0' + (s.dur % 60)).slice(-2) + ' · 👍 ' + s.up + ' 👎 ' + s.down + ' · soitettu ' + s.plays + '×</span><br><span style="font-size:13px;opacity:.7">' + esc(s.email) + '</span><br><audio controls preload="none" style="width:100%;margin:6px 0" src="' + esc(s.url) + '"></audio>';
				var row = el('div', { style: 'display:flex;gap:6px;flex-wrap:wrap' });
				var mk = function (label, act, conf) {
					var b = el('button', { type: 'button' }, label);
					b.addEventListener('click', function () {
						if (conf && !confirm(conf)) { return; }
						api('POST', D.urls.commact, { id: s.id, act: act }).then(function (j) { D.subs = j.subs; drawS(); }).catch(function (e) { alert(e.message); });
					});
					row.appendChild(b);
				};
				if (s.status === 'pending') { mk('Hyväksy radioon', 'ok'); mk('Hylkää ja poista', 'no', 'Poistetaanko kappale kokonaan?'); }
				if (s.status === 'dropped') { mk('Palauta (nollaa äänet)', 'restore'); }
				if (s.status !== 'pending') { mk('Poista kokonaan', 'del', 'Poistetaanko kappale kokonaan?'); }
				r.appendChild(row); scl.appendChild(r);
			});
		}
		drawS();

		/* Radion soittolista: poisto koskee vain radiota, ei mediakirjastoa */
		var bw = el('div');
		bw.innerHTML = '<h3 style="margin:14px 0 6px">⚡ Tehosoitto</h3><p style="font-size:13px;opacity:.7;margin:0">Tehosoitossa oleva kappale soi ensin seuraavaksi (omien ja yhteisön uusien kappaleiden jälkeen) ja sen jälkeen muita useammin määräajan. Uudet kappaleet eivät saa tehosoittoa itsestään, ellet laita automaatiota päälle. Voit valita tehosoiton päälle tai pois yksittäiselle kappaleelle Soittolista-välilehdellä.</p>' +
			'<label><input type="checkbox" data-bon' + (c.boost_on ? ' checked' : '') + '> Tehosoitto käytössä (kappaleille, joille valitset "Aina päällä")</label><br>' +
			'<label><input type="checkbox" data-bau' + (c.boost_auto ? ' checked' : '') + '> Uudet kappaleet saavat tehosoiton automaattisesti (oletus pois)</label>' +
			'<p style="margin:10px 0 2px"><b>Uuden kappaleen oletus</b></p><select style="' + st + '" data-nd><option value="">Soi kaikissa ohjelmissa (yhteinen)</option>' + c.programs.map(function (p) { return '<option value="' + esc(p.id) + '"' + (c.new_default === p.id ? ' selected' : '') + '>Vain ohjelmassa: ' + esc(p.name) + '</option>'; }).join('') + '</select><p style="font-size:13px;opacity:.7;margin:2px 0">Koskee vasta lisättäviä kappaleita. Voit aina muuttaa kappaleen ohjelmat soittolistalla.</p>' +
			'<p style="margin:6px 0 2px">Kuinka monta päivää uusi kappale on tehosoitossa</p><input style="' + st + '" type="number" min="1" max="90" data-bdy value="' + c.boost_days + '">' +
			'<p style="margin:6px 0 2px">Kuinka suuri osa kappaleista on tehosoittokappaleita (%)</p><input style="' + st + '" type="number" min="0" max="100" data-bsh value="' + c.boost_share + '">';
		box.appendChild(bw);
		var mw = el('div');
		function drawM() {
			var M = D.mon;
			mw.innerHTML = '<h3 style="margin:14px 0 6px">📱 Seuranta iPhonella (ilman kirjautumista)</h3><p style="font-size:13px;opacity:.7;margin:0">Näet kuuntelijat nyt, tilastot, soivan kappaleen, chatin viestit ja odottavat hyväksynnät. Linkki ja avain ovat salaisia: kuka tahansa linkin tietävä näkee nämä tiedot, joten älä jaa sitä. Jos linkki vuotaa, luo uusi avain.</p>' +
				'<p style="margin:10px 0 2px"><b>🏬 Myymälän radio</b> (avaa tämä myymälän puhelimessa: aukioloajat, kuulutukset ja mainokset kuten myymäläsoittimessa, ei chattia)</p><input style="' + st + '" readonly onclick="this.select()" value="' + esc(M.shop) + '">' +
				'<p style="margin:8px 0 2px"><b>1. Seurantasivu</b> (avaa Safarissa, paina Jaa → Lisää Koti-valikkoon)</p><input style="' + st + '" readonly data-mpage onclick="this.select()" value="' + esc(M.page) + '">' +
				'<p style="margin:8px 0 2px"><b>2. Widget kotinäytölle</b>: asenna ilmainen Scriptable-sovellus (App Store), luo uusi skripti, liitä tämä koodi, ja lisää kotinäytölle Scriptable-widget (valitse tämä skripti).</p><textarea style="' + st + ';font-size:12px" rows="6" readonly data-mscript onclick="this.select()">' + esc(M.script) + '</textarea>';
			var nb = el('button', { type: 'button', style: 'margin-top:6px' }, 'Luo uusi avain (vanhat linkit lakkaavat toimimasta)');
			nb.addEventListener('click', function () { if (!confirm('Vanha linkki ja widget lakkaavat toimimasta. Jatketaanko?')) { return; } api('POST', D.urls.monrenew, {}).then(function (j) { D.mon = j; drawM(); }).catch(function (e) { alert(e.message); }); });
			mw.appendChild(nb);
		}
		drawM();
		var tw = el('div');
		tw.innerHTML = '<h3 style="margin:14px 0 6px">Radion soittolista</h3><p style="font-size:13px;opacity:.7">Täällä näkyvät vain radiossa soitettavat kappaleet. "Poista radiosta" ei poista kappaletta WordPressistä eikä kaupan soittimesta. Kirjoita lyhyt esittely, jonka juontaja lukee ennen kappaletta.' + (D.skipped ? ' ' + D.skipped + ' kappaletta ei voi soittaa radiossa (esim. kesto puuttuu) ja ne on piilotettu.' : '') + '</p>';
		var tb = el('div', { style: 'margin:8px 0' });
		var sbtn = el('button', { type: 'button', style: 'margin:0 6px 6px 0' }, '✨ Ehdota ohjelmat kappaleille');
		var sall = el('button', { type: 'button', style: 'margin:0 6px 6px 0' }, 'Hyväksy kaikki ehdotukset');
		var smsg = el('p', { style: 'font-size:13px;white-space:pre-wrap;margin:4px 0' });
		if (D.sunoleft) {
			var cbtn = el('button', { type: 'button', style: 'margin:0 6px 6px 0' }, '💾 Tallenna Suno-kappaleet omalle palvelimelle (' + D.sunoleft + ' jäljellä)');
			cbtn.addEventListener('click', function () {
				cbtn.disabled = true; smsg.textContent = 'Ladataan… (voi kestää hetken)';
				(function loop() {
					api('POST', D.urls.sunocache, {}).then(function (j) {
						cbtn.textContent = '💾 Tallenna Suno-kappaleet omalle palvelimelle (' + j.left + ' jäljellä)';
						if (j.left > 0 && j.done > 0) { smsg.textContent = 'Tallennettu, jatketaan… ' + j.left + ' jäljellä'; loop(); }
						else { cbtn.disabled = false; smsg.textContent = j.left ? ('Kaikkia ei saatu ladattua. ' + (j.err || '')) : 'Kaikki Suno-kappaleet tallennettu. Lataa sivu uudelleen.'; }
					}).catch(function (e) { cbtn.disabled = false; smsg.textContent = e.message; });
				})();
			});
			tb.appendChild(cbtn);
		}
		var rowMap = {}, badList = [];
		var hbtn = el('button', { type: 'button', style: 'margin:0 6px 6px 0' }, 'Poista ⚠️-kappaleet radiosta'); hbtn.hidden = true;
		hbtn.addEventListener('click', function () {
			if (!badList.length || !confirm('Piilotetaanko ' + badList.length + ' kappaletta radiosta? Ne siirtyvät kohtaan "Poistetut radiosta" ja ne voi palauttaa sieltä.')) { return; }
			hbtn.disabled = true; var n = 0, list = badList.slice();
			(function nx() {
				if (!list.length) { hbtn.hidden = true; hbtn.disabled = false; badList = []; smsg.textContent = 'Piilotettu ' + n + ' kappaletta.'; drawT(); return; }
				var t = list.shift();
				api('POST', U.tracks, { id: t.id, act: 'hide' }).then(function () { t.hidden = true; n++; nx(); }).catch(function (e) { hbtn.disabled = false; smsg.textContent = e.message; });
			})();
		});
		var kbtn = el('button', { type: 'button', style: 'margin:0 6px 6px 0' }, '🔍 Tarkista, aukeavatko kappaleiden tiedostot');
		kbtn.addEventListener('click', function () {
			var list = (D.tracks || []).filter(function (t) { return t.url && !t.hidden; }), i = 0, bad = 0; badList = [];
			kbtn.disabled = true;
			function mark(t, ok) {
				var r = rowMap[t.id]; if (!r) { return; }
				var h = r.querySelector('[data-chk]'); if (!h) { h = el('span', { 'data-chk': '1', style: 'margin-left:6px;font-size:13px' }); r.firstChild.appendChild(h); }
				h.innerHTML = ok ? '<span style="color:#5eead4">✓ tiedosto aukeaa</span>' : '<b style="color:#ff9a9a">⚠️ tiedosto ei aukea – vaihda tiedostoon</b>';
				if (!ok) { r.style.borderColor = '#e5484d'; }
			}
			(function next() {
				if (i >= list.length) { kbtn.disabled = false; hbtn.hidden = !bad; hbtn.textContent = 'Poista ⚠️-kappaleet radiosta (' + bad + ')'; smsg.textContent = bad ? ('Valmis. ' + bad + ' kappaleen tiedosto ei aukea (merkitty ⚠️ punaisella).') : 'Valmis. Kaikki tiedostot aukeavat.'; return; }
				var t = list[i++]; smsg.textContent = 'Tarkistetaan ' + i + '/' + list.length + '…';
				var a = new Audio(), done = false;
				try { a.referrerPolicy = 'no-referrer'; } catch (e) {}
				a.preload = 'metadata';
				function fin(ok) { if (done) { return; } done = true; clearTimeout(to); a.removeAttribute('src'); a.load(); if (!ok) { bad++; badList.push(t); } mark(t, ok); next(); }
				var to = setTimeout(function () { fin(false); }, 9000);
				a.addEventListener('loadedmetadata', function () { fin(true); });
				a.addEventListener('error', function () { fin(false); });
				a.src = t.url;
			})();
		});
		tb.appendChild(kbtn); tb.appendChild(hbtn);
		sbtn.addEventListener('click', function () {
			var todo = D.tracks.filter(function (t) { return !t.hidden && !(t.progs && t.progs.length) && !t.sugg; });
			if (!todo.length) { smsg.textContent = 'Kaikilla kappaleilla on jo ehdotus tai ohjelmarajaus.'; return; }
			if (todo.length > 30 && !confirm('Ehdotuksia tehdään ' + todo.length + ' kappaleelle (pieni tekoälykulu). Jatketaanko?')) { return; }
			sbtn.disabled = true; var done = 0;
			(function next() {
				var batch = todo.slice(done, done + 6);
				if (!batch.length) { sbtn.disabled = false; smsg.textContent = 'Valmis. Tarkista ehdotukset ja hyväksy ne.'; drawT(); return; }
				smsg.textContent = 'Tekoäly miettii… (' + done + '/' + todo.length + ')';
				api('POST', D.urls.suggest, { ids: batch.map(function (t) { return t.id; }) }).then(function (r) {
					if (r.err && !Object.keys(r.sugg || {}).length) { sbtn.disabled = false; smsg.textContent = 'Virhe: ' + r.err; return; }
					batch.forEach(function (t) { if (r.sugg && r.sugg[t.id]) { t.sugg = r.sugg[t.id]; } });
					done += batch.length; drawT(); next();
				}).catch(function (e) { sbtn.disabled = false; smsg.textContent = 'Virhe: ' + e.message; });
			})();
		});
		sall.addEventListener('click', function () {
			if (!confirm('Hyväksytäänkö kaikki odottavat ehdotukset?')) { return; }
			api('POST', D.urls.suggact, { act: 'okall' }).then(function (r) { (r.done || []).forEach(function (id) { D.tracks.forEach(function (t) { if (t.id === id && t.sugg) { t.progs = t.sugg.progs; t.sugg = null; } }); }); smsg.textContent = 'Hyväksytty: ' + (r.done || []).length; drawT(); }).catch(function (e) { alert(e.message); });
		});
		tb.appendChild(sbtn); tb.appendChild(sall); tb.appendChild(smsg);
		var tl = el('div'), hl = el('details'), hb = el('div');
		hl.appendChild(el('summary', null, 'Poistetut radiosta'));
		hl.appendChild(hb);
		function drawT() {
			tl.innerHTML = ''; hb.innerHTML = '';
			var vis = D.tracks.filter(function (t) { return !t.hidden; }), hid = D.tracks.filter(function (t) { return t.hidden; });
			D.usable = vis.length;
			tw.querySelector('h3').textContent = 'Radion soittolista (' + vis.length + ' kappaletta)';
			rowMap = {};
			function mkRow(t) {
				var r = el('div', { style: 'margin:8px 0;padding:8px;border:1px solid #363d4a;border-radius:10px' });
				rowMap[t.id] = r;
				r.innerHTML = '<div><b>' + esc(t.title) + '</b> <span style="opacity:.7;font-size:13px">👍 ' + ((t.votes && t.votes.u) || 0) + ' 👎 ' + ((t.votes && t.votes.d) || 0) + '</span> <a href="' + esc(t.url || '#') + '" target="_blank" rel="noopener noreferrer" style="font-size:13px;margin-left:6px">▶ avaa tiedosto</a></div>';
				var inp = el('input', { style: st, maxlength: '300', placeholder: 'Esittely (valinnainen)' });
				inp.value = t.info || '';
				inp.addEventListener('change', function () { t.info = inp.value; api('POST', U.info, { id: t.id, text: inp.value }).catch(function (e) { alert(e.message); }); });
				var rm = el('button', { type: 'button', style: 'margin-top:4px' }, 'Poista radiosta');
				rm.addEventListener('click', function () { api('POST', U.tracks, { id: t.id, act: 'hide' }).then(function () { t.hidden = true; drawT(); }).catch(function (e) { alert(e.message); }); });
				var pr = el('div', { style: 'margin:6px 0;font-size:14px' }, '<span style="opacity:.7">Soi ohjelmissa:</span> ');
				var all = c.programs, cur = (t.progs && t.progs.length) ? t.progs : all.map(function (p) { return p.id; });
				all.forEach(function (p) {
					var lb = el('label', { style: 'margin-right:10px;white-space:nowrap' });
					var cb = el('input', { type: 'checkbox' }); cb.checked = cur.indexOf(p.id) >= 0;
					cb.addEventListener('change', function () {
						var sel = [].slice.call(pr.querySelectorAll('input')).map(function (x, k) { return x.checked ? all[k].id : null; }).filter(Boolean);
						if (!sel.length) { cb.checked = true; alert('Kappaleen pitää soida vähintään yhdessä ohjelmassa. Poista se muuten radiosta.'); return; }
						api('POST', D.urls.trackprogs, { id: t.id, progs: sel }).then(function (j) { t.progs = j.progs; }).catch(function (e) { alert(e.message); });
					});
					lb.appendChild(cb); lb.appendChild(document.createTextNode(' ' + p.name)); pr.appendChild(lb);
				});
				var isNew = c.boost_on && c.boost_auto && t.at && (Date.now() / 1000 - t.at) < c.boost_days * 86400;
				var bm = el('div', { style: 'margin:6px 0;font-size:14px' }, '<span style="opacity:.7">⚡ Tehosoitto:</span> ');
				var bs = el('select', { style: 'padding:6px;border-radius:8px;font-size:14px' }, '<option value="auto">Automaattinen' + (isNew ? ' (nyt päällä, uusi kappale)' : '') + '</option><option value="on">Aina päällä</option><option value="off">Ei koskaan</option>');
				bs.value = t.boost === 'on' || t.boost === 'off' ? t.boost : 'auto';
				bs.addEventListener('change', function () { api('POST', D.urls.trackboost, { id: t.id, mode: bs.value }).then(function (j) { t.boost = j.mode === 'auto' ? '' : j.mode; }).catch(function (e) { alert(e.message); }); });
				bm.appendChild(bs);
				var sin = el('input', { style: st, maxlength: '400', placeholder: 'Tyylikuvaus / Suno-tyyli (auttaa tekoälyä ehdottamaan ohjelmat)' });
				sin.value = t.style || '';
				sin.addEventListener('change', function () { t.style = sin.value; t.sugg = null; api('POST', D.urls.trackstyle, { id: t.id, style: sin.value }).then(function () { drawT(); }).catch(function (e) { alert(e.message); }); });
				var sg = t.sugg;
				if (sg && sg.status === 'pending') {
					var names = (sg.progs && sg.progs.length) ? sg.progs.map(function (id) { var p = c.programs.filter(function (z) { return z.id === id; })[0]; return p ? p.name : id; }).join(', ') : 'Kaikki ohjelmat (yhteinen)';
					var sb = el('div', { style: 'margin:6px 0;padding:8px;border:1px solid #ffb347;border-radius:8px;background:rgba(255,179,71,.08);font-size:14px' }, '✨ <b>Ehdotus: ' + esc(names) + '</b>' + (sg.why ? '<br><span style="opacity:.8">' + esc(sg.why) + '</span>' : '') + '<br>');
					var ok = el('button', { type: 'button', style: 'margin:6px 6px 0 0' }, 'Hyväksy');
					ok.addEventListener('click', function () { api('POST', D.urls.suggact, { id: t.id, act: 'ok' }).then(function () { t.progs = sg.progs; t.sugg = null; drawT(); }).catch(function (e) { alert(e.message); }); });
					var no = el('button', { type: 'button', style: 'margin-top:6px' }, 'Hylkää');
					no.addEventListener('click', function () { api('POST', D.urls.suggact, { id: t.id, act: 'no' }).then(function () { t.sugg = { status: 'no' }; drawT(); }).catch(function (e) { alert(e.message); }); });
					sb.appendChild(ok); sb.appendChild(no); r.appendChild(sb);
				}
				r.appendChild(pr); r.appendChild(bm); r.appendChild(sin); r.appendChild(inp); r.appendChild(rm);
				return r;
			}
			var groups = [{ name: 'Yhteiset (soivat kaikissa ohjelmissa)', rows: vis.filter(function (t) { return !(t.progs && t.progs.length); }), open: true }];
			c.programs.forEach(function (p) { groups.push({ name: 'Vain ohjelmassa: ' + p.name, rows: vis.filter(function (t) { return t.progs && t.progs.indexOf(p.id) >= 0; }), open: false }); });
			groups.forEach(function (g) {
				var d = el('details'); d.open = g.open && g.rows.length > 0;
				d.appendChild(el('summary', { style: 'font-weight:700;margin:8px 0' }, esc(g.name) + ' (' + g.rows.length + ')'));
				g.rows.forEach(function (t) { d.appendChild(mkRow(t)); });
				if (!g.rows.length) { d.appendChild(el('p', { style: 'opacity:.7;font-size:13px' }, 'Ei kappaleita.')); }
				tl.appendChild(d);
			});
			if (!hid.length) { hb.innerHTML = '<p style="opacity:.7">Ei poistettuja.</p>'; }
			hid.forEach(function (t) {
				var r = el('div', { style: 'margin:6px 0;display:flex;gap:8px;align-items:center' });
				r.innerHTML = '<span style="flex:1">' + esc(t.title) + '</span>';
				var b2 = el('button', { type: 'button' }, 'Palauta radioon');
				b2.addEventListener('click', function () { api('POST', U.tracks, { id: t.id, act: 'show' }).then(function () { t.hidden = false; drawT(); }).catch(function (e) { alert(e.message); }); });
				r.appendChild(b2); hb.appendChild(r);
			});
		}
		drawT();
		tw.appendChild(tb); tw.appendChild(tl); tw.appendChild(hl);
		box.appendChild(tw);

		/* Välilehdet: koko ohjaamo jaetaan selkeisiin osiin */
		var sn = document.querySelector('[data-sb-name]'); if (sn && D.cfg) { sn.textContent = '📻 ' + (D.cfg.name || 'Radion ohjaamo'); }
		var sl = document.querySelector('[data-sb-live]'); if (sl && D.stats) { sl.textContent = D.stats.live; }
		var sc = document.querySelector('[data-sb-clock]');
		if (sc) { if (window.__suorniClk) { clearInterval(window.__suorniClk); } var tickc = function () { var d = new Date(); sc.textContent = ('0' + d.getHours()).slice(-2) + ':' + ('0' + d.getMinutes()).slice(-2) + ':' + ('0' + d.getSeconds()).slice(-2); }; tickc(); window.__suorniClk = setInterval(tickc, 1000); }
		var loose = [].slice.call(box.children);
		var TABS = [['tanaan', '📌 Tänään'], ['yleinen', '🏠 Yleiskatsaus'], ['ohjelmat', '🗓️ Ohjelmat'], ['yleiso', '💬 Yleisö'], ['juontaja', '🎙️ Juontaja'], ['yhteiso', '🎵 Uudet artistit'], ['lista', '📀 Soittolista'], ['kanavat', '📻 Kanavat'], ['tarve', '🎯 Kappaletarve'], ['mainokset', '📢 Mainokset'], ['sivusto', '🌐 Sivusto']];

		/* Massalisäys + päivittäinen tarkistus */
		var upw = el('div', { style: 'margin:6px 0 14px;padding:10px;border:1px solid #363d4a;border-radius:12px;background:#151922;color:#e8ecf3' });
		upw.innerHTML = '<h3 style="margin:0 0 6px">📥 Lisää kappaleita tiedostoina</h3><p style="font-size:13px;opacity:.75;margin:0 0 8px">Valitse tai vedä tähän mp3-, m4a-, wav-, ogg-, aac- tai flac-tiedostoja. Nimi ja esittäjä luetaan tiedoston tiedoista tai nimestä muodossa <b>Esittäjä - Kappale.mp3</b>. Suurin sallittu tiedosto: ' + Math.round((D.maxup || 0) / 1048576) + ' Mt.</p>';
		var dz = el('div', { style: 'border:2px dashed #5a6577;border-radius:10px;padding:16px;text-align:center;cursor:pointer' }, '⬆️ Valitse tiedostot tai vedä ne tähän');
		var fi = el('input', { type: 'file', multiple: 'multiple', accept: '.mp3,.m4a,.wav,.ogg,.aac,.flac,audio/*', style: 'display:none' });
		var ulog = el('div', { style: 'font-size:13px;margin-top:8px;max-height:200px;overflow:auto' });
		function upFiles(files) {
			files = [].slice.call(files); if (!files.length) { return; }
			ulog.innerHTML = ''; var i = 0, ok = 0;
			(function nx() {
				if (i >= files.length) {
					var done = el('div', { style: 'margin-top:6px' }, '<b>Valmis: ' + ok + '/' + files.length + ' lisätty.</b> ');
					var rb = el('button', { type: 'button' }, 'Päivitä lista'); rb.addEventListener('click', load); done.appendChild(rb); ulog.appendChild(done); return;
				}
				var f = files[i++]; var row = el('div', null, '⏳ ' + esc(f.name)); ulog.appendChild(row);
				var fd = new FormData(); fd.append('file', f);
				fetch(D.urls.upload, { method: 'POST', credentials: 'same-origin', headers: { 'X-WP-Nonce': nonce }, body: fd }).then(function (r) { return r.json().then(function (j) { if (!r.ok) { throw new Error(j.message || 'Virhe'); } return j; }); }).then(function (j) {
					ok++; row.innerHTML = '✅ ' + esc(j.artist) + ' – ' + esc(j.title) + (j.dur ? ' (' + Math.floor(j.dur / 60) + ':' + ('0' + (j.dur % 60)).slice(-2) + ')' : '') + (j.warn ? ' <span style="color:#ffb347">⚠️ ' + esc(j.warn) + '</span>' : ''); nx();
				}).catch(function (e) { row.innerHTML = '❌ ' + esc(f.name) + ': ' + esc(e.message); nx(); });
			})();
		}
		dz.addEventListener('click', function () { fi.click(); });
		fi.addEventListener('change', function () { upFiles(fi.files); fi.value = ''; });
		['dragover', 'dragenter'].forEach(function (ev) { dz.addEventListener(ev, function (e) { e.preventDefault(); dz.style.background = '#2b2318'; }); });
		['dragleave', 'drop'].forEach(function (ev) { dz.addEventListener(ev, function (e) { e.preventDefault(); dz.style.background = ''; }); });
		dz.addEventListener('drop', function (e) { upFiles(e.dataTransfer.files); });
		var hl2 = el('div', { style: 'margin-top:10px;font-size:13px' });
		function drawHealth(h) {
			var t = h.at ? new Date(h.at * 1000).toLocaleString('fi-FI') : 'ei vielä';
			var html = '<b>🩺 Tiedostojen tarkistus</b> – viimeksi: ' + t + (h.n ? ' · ' + h.n + ' kappaletta tarkistettu' : '');
			var any = (h.bad && h.bad.length) || (h.ext && h.ext.length);
			if (h.bad && h.bad.length) { html += '<div style="color:#ff9a9a;margin-top:4px">Piilotettu radiosta (tiedosto puuttuu): ' + h.bad.map(function (x) { return esc(x.t); }).join(', ') + '</div>'; }
			if (h.ext && h.ext.length) { html += '<div style="color:#ffb347;margin-top:4px">Ulkoinen linkki ei vastaa: ' + h.ext.map(function (x) { return esc(x.t) + ' (' + esc(x.why) + ')'; }).join(', ') + '</div>'; }
			if (h.nodur) { html += '<div style="opacity:.8;margin-top:4px">' + h.nodur + ' kappaleelta puuttuu kesto, eivätkä ne soi radiossa.</div>'; }
			if (!any && h.at) { html += '<div style="color:#5eead4;margin-top:4px">✓ Ongelmia ei löytynyt.</div>'; }
			hl2.innerHTML = html + ' ';
			var hb2 = el('button', { type: 'button', style: 'margin-top:6px' }, 'Tarkista nyt');
			hb2.addEventListener('click', function () { hb2.disabled = true; hb2.textContent = 'Tarkistetaan…'; api('POST', D.urls.health, {}).then(function (r) { D.health = r; drawHealth(r); }).catch(function (e) { hb2.disabled = false; hb2.textContent = e.message; }); });
			hl2.appendChild(hb2);
		}
		drawHealth(D.health || { at: 0 });
		upw.appendChild(dz); upw.appendChild(fi); upw.appendChild(ulog); upw.appendChild(hl2);

		/* Toiveet */
		var rqw = el('div', { style: 'margin:6px 0 14px' });
		function drawReq() {
			var rc = D.reqcfg || { on: true, auto: true };
			rqw.innerHTML = '<h3 style="margin:14px 0 6px">🎁 Toivekappaleet</h3><p style="font-size:13px;opacity:.75">Kuuntelija voi toivoa kappaleen kirjastostasi. Hyväksytty toive soi seuraavien joukossa, kun kappale sopii käynnissä olevaan ohjelmaan, ja juontaja mainitsee toiveen. Yksi toive kymmenessä minuutissa per kuuntelija.</p>';
			var o1 = el('label', { style: 'display:block;margin:4px 0' }, '<input type="checkbox"' + (rc.on ? ' checked' : '') + '> Toiveet käytössä');
			var o2 = el('label', { style: 'display:block;margin:4px 0' }, '<input type="checkbox"' + (rc.auto ? ' checked' : '') + '> Hyväksy toiveet automaattisesti (jos pois, hyväksyt ne itse tästä)');
			function sv() { rc = { on: o1.firstChild.checked, auto: o2.firstChild.checked }; D.reqcfg = rc; api('POST', D.urls.reqcfg, rc).catch(function (e) { alert(e.message); }); }
			o1.firstChild.addEventListener('change', sv); o2.firstChild.addEventListener('change', sv);
			rqw.appendChild(o1); rqw.appendChild(o2);
			var lst = el('div', { style: 'margin-top:6px' });
			var L = D.reqs || [];
			if (!L.length) { lst.appendChild(el('div', { style: 'opacity:.7' }, 'Ei toiveita vielä.')); }
			var STN = { pending: '⏳ odottaa hyväksyntää', ok: '✅ jonossa', no: '🚫 hylätty', played: '▶ soitettu' };
			L.forEach(function (x) {
				var r = el('div', { style: 'display:flex;gap:8px;align-items:center;flex-wrap:wrap;padding:6px 0;border-top:1px solid #2a303b' }, '<div style="flex:1;min-width:150px"><b>' + esc(x.song) + '</b><div style="font-size:12px;opacity:.75">' + esc(x.name) + ' · ' + new Date(x.at * 1000).toLocaleTimeString('fi-FI', { hour: '2-digit', minute: '2-digit' }) + ' · ' + STN[x.st] + '</div></div>');
				function act(a, label) { var b = el('button', { type: 'button' }, label); b.addEventListener('click', function () { b.disabled = true; api('POST', D.urls.reqact, { n: x.n, act: a }).then(function (j) { D.reqs = j.list; drawReq(); if (redraw.ib) { redraw.ib(); } }).catch(function (e) { alert(e.message); }); }); r.appendChild(b); }
				if (x.st === 'pending') { act('ok', 'Hyväksy'); }
				if (x.st === 'pending' || x.st === 'ok') { act('no', 'Hylkää'); }
				lst.appendChild(r);
			});
			rqw.appendChild(lst);
		}
		drawReq();

		/* Valvonta: tila, hälytykset ja käytön yhteenveto */
		var wtw = el('div', { 'class': 'st-watch', style: 'margin:6px 0 14px;padding:12px;border:1px solid #363d4a;border-radius:12px;background:#151922;color:#e8ecf3' });
		function drawWatch() {
			var W = D.watch; if (!W) { wtw.style.display = 'none'; return; }
			var col = { ok: '#3ddc84', warn: '#ffb347', bad: '#ff5c5c' }, ico = { ok: '✅', warn: '⚠️', bad: '🛑' };
			var head = W.lvl === 'ok' ? 'Kaikki toimii' : (W.lvl === 'warn' ? 'Huomioitavaa' : 'Vaatii toimenpiteitä');
			var h = '<h3 style="margin:0 0 8px;display:flex;align-items:center;gap:8px"><span style="display:inline-block;width:12px;height:12px;border-radius:50%;background:' + col[W.lvl] + ';box-shadow:0 0 8px ' + col[W.lvl] + '"></span>🩺 Valvonta: ' + head + '</h3>';
			var order = { bad: 0, warn: 1, ok: 2 };
			var cs = W.checks.slice().sort(function (a, b) { return order[a.lvl] - order[b.lvl]; });
			h += cs.map(function (c) { return '<div style="padding:5px 0;border-top:1px solid #272c36;font-size:14px"><b style="color:' + col[c.lvl] + '">' + ico[c.lvl] + ' ' + esc(c.title) + '</b><div style="opacity:.8;font-size:13px">' + esc(c.detail) + '</div></div>'; }).join('');
			if (W.hidden && W.hidden.length) {
				h += '<h4 style="margin:12px 0 4px">🚫 Piilotetut rikkinäiset kappaleet</h4><p style="font-size:12px;opacity:.7;margin:0 0 6px">Nämä eivät soi radiossa. Lataa niiden tilalle mp3-tiedosto (Soittolista-välilehti) tai palauta kappale, jos tiedosto toimii taas.</p>';
				h += W.hidden.map(function (x, i) { return '<div style="display:flex;gap:8px;align-items:center;padding:4px 0;border-top:1px solid #272c36;font-size:13px"><span style="flex:1;min-width:0"><b>' + esc(x.t || ('#' + x.id)) + '</b><span style="opacity:.65"> · ' + esc(x.why) + '</span></span><button type="button" data-unh="' + i + '">Palauta</button></div>'; }).join('');
			}
			if (W.djlog && W.djlog.length) {
				var TN = { news: 'uutiset', sport: 'urheilu', nhl: 'NHL' };
				h += '<h4 style="margin:12px 0 4px">🎙️ Viimeisimmät juontopuheet</h4>' + W.djlog.map(function (x) {
					var tm = new Date(x.t * 1000).toLocaleTimeString('fi-FI', { hour: '2-digit', minute: '2-digit' });
					var tp = (x.topics || []).map(function (k) { return (x.miss || []).indexOf(k) >= 0 ? TN[k] + ' (lisätty valmiina)' : '✓ ' + TN[k]; }).join(', ');
					return '<div style="padding:4px 0;border-top:1px solid #272c36;font-size:13px"><span style="opacity:.6">' + tm + (x.ai ? ' · tekoäly' : ' · valmis teksti') + (tp ? ' · ' + esc(tp) : '') + '</span><div style="opacity:.85">' + esc(x.text) + '</div></div>';
				}).join('');
			}
			var u = W.usage || {}, t = u.today || {}, w = u.week || {}, m = u.month || {}, c$ = u.tts_usd || {};
			h += '<h4 style="margin:12px 0 4px">📊 Käyttö ja kulut</h4><table style="width:100%;font-size:13px;border-collapse:collapse"><tr style="opacity:.7"><td></td><td>Tänään</td><td>7 pv</td><td>30 pv</td></tr>' +
				'<tr><td>Tekoälypuheet</td><td>' + (t.ai || 0) + '</td><td>' + (w.ai || 0) + '</td><td>' + (m.ai || 0) + '</td></tr>' +
				'<tr><td>Uudet äänitiedostot</td><td>' + (t.tts || 0) + '</td><td>' + (w.tts || 0) + '</td><td>' + (m.tts || 0) + '</td></tr>' +
				'<tr><td>Puhuttuja merkkejä</td><td>' + (t.ch || 0) + '</td><td>' + (w.ch || 0) + '</td><td>' + (m.ch || 0) + '</td></tr>' +
				'<tr><td>Puhesynteesi, arvio</td><td>' + (c$.today || 0) + ' $</td><td>' + (c$.week || 0) + ' $</td><td>' + (c$.month || 0) + ' $</td></tr></table>' +
				'<p style="font-size:12px;opacity:.65;margin:6px 0 8px">Puhesynteesin hinta on arvio (noin 16 $ miljoonaa merkkiä kohden); tarkka hinta näkyy Azuren laskussa. Tekoälyn kulu riippuu mallista, katso Anthropic-tilisi käyttö.</p>';
			wtw.innerHTML = h;
			[].forEach.call(wtw.querySelectorAll('[data-unh]'), function (b) { b.addEventListener('click', function () { var x = W.hidden[+b.getAttribute('data-unh')]; b.disabled = true; api('POST', D.urls.unhide, { id: x.id }).then(function (j) { D.watch = j; drawWatch(); }).catch(function (e) { b.disabled = false; alert(e.message); }); }); });
			var lab = el('label', { style: 'display:flex;gap:8px;align-items:center;font-size:14px;cursor:pointer' });
			var cb = el('input', { type: 'checkbox' }); cb.checked = !!W.mail;
			lab.appendChild(cb); lab.appendChild(document.createTextNode('✉️ Lähetä vakavista ongelmista sähköposti sivuston ylläpitäjän osoitteeseen'));
			cb.addEventListener('change', function () { api('POST', D.urls.watchmail, { on: cb.checked }).then(function (j) { D.watch = j; }).catch(function (e) { alert(e.message); cb.checked = !cb.checked; }); });
			wtw.appendChild(lab);
			var rf = el('button', { type: 'button', style: 'margin-top:8px' }, '🔄 Päivitä tila'); rf.addEventListener('click', function () { api('GET', D.urls.watch).then(function (j) { D.watch = j; drawWatch(); }); });
			wtw.appendChild(rf);
		}
		drawWatch();
		if (window.__suorniWatch) { clearInterval(window.__suorniWatch); }
		window.__suorniWatch = setInterval(function () { if (!document.body.contains(wtw)) { return; } api('GET', D.urls.watch).then(function (j) { D.watch = j; drawWatch(); }).catch(function () {}); }, 60000);
		/* Omat käsikirjoitetut juonnot */
		var ow = el('div', { style: 'margin:6px 0 14px;padding:10px;border:1px solid #363d4a;border-radius:12px;background:#151922;color:#e8ecf3' });
		(function () {
			var L = (D.scripts || []).map(function (x) { return JSON.parse(JSON.stringify(x)); }), progs = (D.cfg && D.cfg.programs) || [];
			var DAYS = ['Ma', 'Ti', 'Ke', 'To', 'Pe', 'La', 'Su'];
			ow.innerHTML = '<h3 style="margin:0 0 6px">✍️ Omat juonnot</h3><p style="font-size:13px;opacity:.8;margin:0 0 8px">Kirjoita teksti, jonka juontaja lukee radiossa <b>sanasta sanaan</b> (tekoäly ei muuta sitä). Ajoitus: <b>seuraavassa juontotauossa</b> (kerran), <b>kierrossa</b> (joka n:nnessä tauossa) tai <b>kellonaikaan</b> (juonto alkaa tasan silloin ja musiikki häipyy sen tieltä). <b>Soita heti</b> lisää juonnon nykyisen kappaleen jälkeen. Tekstissä voi käyttää merkintöjä {kello} ja {paiva}. Kappaleen nimen voi kirjoittaa merkkien « » sisään, jolloin se luetaan oikealla kielellä.</p><div data-owl></div><div style="margin-top:8px;display:flex;gap:8px;flex-wrap:wrap;align-items:center"><button type="button" data-own>＋ Uusi oma juonto</button><button type="button" data-ows>💾 Tallenna juonnot</button><span data-owst style="font-size:13px"></span></div>';
			var box2 = ow.querySelector('[data-owl]'), stt = ow.querySelector('[data-owst]'), sm = st.replace('width:100%;', 'width:auto;');
			function fdate(t) { if (!t) { return ''; } var d = new Date(t * 1000); return d.getDate() + '.' + (d.getMonth() + 1) + '. ' + ('0' + d.getHours()).slice(-2) + ':' + ('0' + d.getMinutes()).slice(-2); }
			function saveAll(msg) {
				var send = L.filter(function (x) { return (x.text || '').trim(); });
				stt.textContent = 'Tallennetaan…';
				return api('POST', D.urls.scripts, { scripts: send }).then(function (j) {
					var k = 0; L.forEach(function (x) { if ((x.text || '').trim()) { var n = j.scripts[k++]; if (n) { for (var f in n) { x[f] = n[f]; } } } });
					stt.textContent = msg || '✅ Tallennettu.'; draw2(); return j;
				}).catch(function (e) { stt.textContent = '⚠️ ' + e.message; throw e; });
			}
			function draw2() {
				box2.innerHTML = L.length ? '' : '<p style="font-size:13px;opacity:.7">Ei vielä omia juontoja.</p>';
				L.forEach(function (x, i) {
					var c = el('div', { style: 'padding:10px;margin:8px 0;border:1px solid #2a303b;border-radius:10px;background:#101319' });
					var progOpts = '<option value="">Juontaja: vuorossa oleva</option>' + progs.map(function (p) { return '<option value="' + esc(p.id) + '"' + (x.prog === p.id ? ' selected' : '') + '>' + esc(p.name) + (p.host ? ' (' + esc(p.host) + ')' : '') + '</option>'; }).join('');
					var dayBoxes = DAYS.map(function (d, k) { return '<label style="margin-right:6px"><input type="checkbox" data-d="' + (k + 1) + '"' + ((x.days || []).indexOf(k + 1) >= 0 ? ' checked' : '') + '> ' + d + '</label>'; }).join('');
					var status = x.mode === 'next' ? (x.done ? '✅ Puhuttu ' + fdate(x.done) + ' <button type="button" data-redo>Käytä uudelleen</button>' : '⏳ Odottaa seuraavaa juontotaukoa') : (x.mode === 'time' ? (x.lastday ? 'Viimeksi puhuttu: ' + fdate(x.last) : 'Ei vielä puhuttu') : (x.last ? 'Viimeksi puhuttu: ' + fdate(x.last) : 'Ei vielä puhuttu'));
					c.innerHTML = '<div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap"><input style="' + st.replace('width:100%;', 'flex:1;min-width:160px;') + '" data-t maxlength="60" placeholder="Nimi (vain sinulle)" value="' + esc(x.title) + '"><label><input type="checkbox" data-on' + (x.on ? ' checked' : '') + '> käytössä</label></div>' +
						'<textarea style="' + st + ';min-height:110px" data-x maxlength="1200" placeholder="Kirjoita juontoteksti sellaisena kuin haluat juontajan sanovan">' + esc(x.text) + '</textarea><div data-cnt style="font-size:12px;opacity:.65"></div>' +
						'<div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-top:6px"><select style="' + sm + '" data-m>' + [['next', 'Seuraavassa juontotauossa (kerran)'], ['rot', 'Kierrossa'], ['time', 'Kellonaikaan']].map(function (o) { return '<option value="' + o[0] + '"' + (x.mode === o[0] ? ' selected' : '') + '>' + o[1] + '</option>'; }).join('') + '</select>' +
						'<span data-rot' + (x.mode === 'rot' ? '' : ' hidden') + '>joka <input type="number" min="1" max="10" data-n style="width:56px;' + sm.replace('width:auto;', '') + '" value="' + x.n + '">. tauko</span>' +
						'<span data-tm' + (x.mode === 'time' ? '' : ' hidden') + '><input type="time" data-time style="' + sm + '" value="' + esc(x.time) + '"> ' + dayBoxes + ' <label><input type="checkbox" data-rep' + (x.replace ? ' checked' : '') + '> korvaa tavallinen juonto</label></span>' +
						'<select style="' + sm + '" data-p>' + progOpts + '</select></div>' +
						'<div style="font-size:13px;margin-top:6px" data-stt>' + status + '</div>' +
						'<div style="display:flex;gap:6px;flex-wrap:wrap;margin-top:8px"><button type="button" data-pv>▶ Esikuuntele</button><button type="button" data-nw>📢 Soita heti</button><button type="button" data-rm>🗑 Poista</button> <span data-ms style="font-size:13px"></span></div>';
					var q = function (s2) { return c.querySelector(s2); };
					function cnt() { var n = q('[data-x]').value.length; q('[data-cnt]').textContent = n + ' merkkiä, noin ' + Math.max(1, Math.round(n / 14)) + ' s puhetta'; }
					function pull() {
						x.title = q('[data-t]').value; x.on = q('[data-on]').checked; x.text = q('[data-x]').value; x.mode = q('[data-m]').value; x.n = +q('[data-n]').value || 3; x.time = q('[data-time]').value || '12:00'; x.prog = q('[data-p]').value; x.replace = q('[data-rep]').checked;
						x.days = [].slice.call(c.querySelectorAll('[data-d]')).filter(function (b) { return b.checked; }).map(function (b) { return +b.getAttribute('data-d'); });
					}
					cnt();
					q('[data-x]').addEventListener('input', function () { x.text = q('[data-x]').value; cnt(); });
					['[data-t]', '[data-on]', '[data-n]', '[data-time]', '[data-p]', '[data-rep]'].forEach(function (k) { q(k).addEventListener('change', pull); });
					c.querySelectorAll('[data-d]').forEach(function (b) { b.addEventListener('change', pull); });
					q('[data-m]').addEventListener('change', function () { pull(); q('[data-rot]').hidden = x.mode !== 'rot'; q('[data-tm]').hidden = x.mode !== 'time'; });
					var rd = q('[data-redo]'); if (rd) { rd.addEventListener('click', function () { pull(); x.done = 0; saveAll('✅ Juonto on taas odottamassa seuraavaa taukoa.'); }); }
					q('[data-pv]').addEventListener('click', function () {
						pull(); var m = q('[data-ms]'); if (!x.text.trim()) { m.textContent = 'Kirjoita ensin teksti.'; return; }
						m.textContent = 'Tehdään puhetta…';
						api('POST', D.urls.scriptprev, { text: x.text, prog: x.prog }).then(function (j) { m.textContent = 'Soi (' + j.dur + ' s)'; var a = new Audio(j.url); a.play().catch(function () { m.textContent = 'Selain esti toiston: ' + j.url; }); }).catch(function (e) { m.textContent = '⚠️ ' + e.message; });
					});
					q('[data-nw]').addEventListener('click', function () {
						pull(); var m = q('[data-ms]'); if (!x.text.trim()) { m.textContent = 'Kirjoita ensin teksti.'; return; }
						if (!confirm('Soitetaanko juonto radiossa heti nykyisen kappaleen jälkeen?')) { return; }
						m.textContent = 'Tallennetaan ja tehdään puhetta…';
						var pos = L.filter(function (y) { return (y.text || '').trim(); }).indexOf(x);
						saveAll('✅ Tallennettu.').then(function (j) { var id = j.scripts[pos] && j.scripts[pos].id; return api('POST', D.urls.scriptnow, { id: id }); })
							.then(function () { stt.textContent = '✅ Juonto lisätty lähetykseen nykyisen kappaleen jälkeen.'; draw2(); }).catch(function (e) { stt.textContent = '⚠️ ' + e.message; });
					});
					q('[data-rm]').addEventListener('click', function () { if (confirm('Poistetaanko tämä juonto? Poisto tulee voimaan, kun painat Tallenna juonnot.')) { L.splice(i, 1); draw2(); } });
					box2.appendChild(c);
				});
			}
			draw2();
			ow.querySelector('[data-own]').addEventListener('click', function () { L.push({ id: '', title: '', text: '', mode: 'next', time: '12:00', days: [1, 2, 3, 4, 5, 6, 7], n: 3, prog: '', on: true, replace: false, done: 0, last: 0, lastday: '' }); draw2(); });
			ow.querySelector('[data-ows]').addEventListener('click', function () { saveAll().catch(function () {}); });
		})();

		/* Kanavat: musiikkikanavat ja kappaleiden luokittelu */
		var chw = el('div', { style: 'margin:6px 0 14px;padding:10px;border:1px solid #363d4a;border-radius:12px;background:#151922;color:#e8ecf3' });
		var clw = el('div', { style: 'margin:6px 0 14px;padding:10px;border:1px solid #363d4a;border-radius:12px;background:#151922;color:#e8ecf3' });
		(function () {
			var CH = D.channels || { main: { name: 'Kaikki', icon: '📻' }, list: [] };
			var mainC = JSON.parse(JSON.stringify(CH.main)), L = JSON.parse(JSON.stringify(CH.list));
			var sm = st.replace('width:100%;', 'width:auto;');
			chw.innerHTML = '<h3 style="margin:0 0 6px">📻 Kanavat</h3><p style="font-size:13px;opacity:.8;margin:0 0 8px">Kuuntelija valitsee soittimen yläpuolelta kanavan, ja kanavalla soi vain sen soittolistan musiikki. Juontajat, ohjelmat, sää, uutiset ja mainokset ovat kaikilla kanavilla samat. <b>Kaikki</b>-kanavalla soi kaikki musiikki ja kuuntelijoiden lähettämät kappaleet. Kanava näkyy kuuntelijoille, kun sillä on vähintään 3 kappaletta. Kanavan aikajana rakennetaan vain, kun sillä on kuuntelija.</p><div data-chl></div><div style="margin-top:8px;display:flex;gap:8px;flex-wrap:wrap;align-items:center"><button type="button" data-chn>＋ Uusi kanava</button><button type="button" data-chs>💾 Tallenna kanavat</button><span data-chst style="font-size:13px"></span></div>';
			var box = chw.querySelector('[data-chl]'), stt = chw.querySelector('[data-chst]');
			/* Ulkoasu: valmis tyyli + omat värit + taustakuva (näkyy kuuntelijalla koko etusivulla) */
			function thEditor(o) {
				if (!o.th) { o.th = { st: '', a: '', b: '', bg: '', img: '' }; }
				var t = o.th, S = (CH.styles && CH.styles.length) ? CH.styles : [{ id: 'classic', name: 'Retro-oranssi (oletus)', a: '#ffb347', b: '#ff8a1f', bg: '#0f141d' }];
				var w = el('div', { style: 'flex-basis:100%;width:100%;margin-top:6px;padding-top:6px;border-top:1px dashed #2a303b' });
				var so = '<option value="">(oletus: retro-oranssi)</option>' + S.filter(function (x) { return x.id !== 'classic'; }).map(function (x) { return '<option value="' + esc(x.id) + '"' + (x.id === t.st ? ' selected' : '') + '>' + esc(x.name) + '</option>'; }).join('');
				w.innerHTML = '<div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center"><b style="font-size:13px">🎨 Ulkoasu</b><select style="' + sm + '" data-ts aria-label="Ulkoasun tyyli">' + so + '</select>' +
					'<label style="font-size:13px">Pääväri <input type="color" data-ta></label><label style="font-size:13px">Toinen väri <input type="color" data-tb></label><label style="font-size:13px">Tausta <input type="color" data-tg></label>' +
					'<button type="button" data-tr title="Palauta tyylin omat värit">↺ Tyylin värit</button><span data-tp style="display:inline-block;min-width:92px;padding:5px 10px;border-radius:8px;font-size:13px;font-weight:700;text-align:center;border:1px solid #444">Esikatselu</span></div>' +
					'<div style="display:flex;gap:6px;flex-wrap:wrap;align-items:center;margin-top:6px"><input style="' + sm + 'flex:1;min-width:200px" data-ti placeholder="Taustakuva (kuvan osoite, tyhjä = pelkkä väri)" value="' + esc(t.img || '') + '"><button type="button" data-tu>📷 Lataa taustakuva</button><button type="button" data-tx>Poista kuva</button><input type="file" accept="image/jpeg,image/png,image/webp,image/gif" style="display:none" data-tf><span data-tm style="font-size:12px;opacity:.85"></span></div>';
				var g = function (k) { return w.querySelector(k); };
				function base() { return S.filter(function (x) { return x.id === (t.st || 'classic'); })[0] || S[0]; }
				function paint() {
					var b = base(), a = t.a || b.a, c2 = t.b || b.b, bgc = t.bg || b.bg;
					g('[data-ta]').value = a; g('[data-tb]').value = c2; g('[data-tg]').value = bgc;
					var pv = g('[data-tp]'); pv.style.background = 'linear-gradient(135deg,' + bgc + ',' + c2 + ')'; pv.style.color = a;
				}
				g('[data-ts]').addEventListener('change', function (e) { t.st = e.target.value; t.a = ''; t.b = ''; t.bg = ''; paint(); });
				g('[data-ta]').addEventListener('input', function (e) { t.a = e.target.value; paint(); });
				g('[data-tb]').addEventListener('input', function (e) { t.b = e.target.value; paint(); });
				g('[data-tg]').addEventListener('input', function (e) { t.bg = e.target.value; paint(); });
				g('[data-tr]').addEventListener('click', function () { t.a = ''; t.b = ''; t.bg = ''; paint(); });
				g('[data-ti]').addEventListener('input', function (e) { t.img = e.target.value.trim(); });
				g('[data-tx]').addEventListener('click', function () { t.img = ''; g('[data-ti]').value = ''; g('[data-tm]').textContent = 'Muista Tallenna kanavat.'; });
				g('[data-tu]').addEventListener('click', function () { g('[data-tf]').click(); });
				g('[data-tf]').addEventListener('change', function (e) {
					var f = e.target.files && e.target.files[0]; if (!f) { return; }
					var mg = g('[data-tm]'), bu = g('[data-tu]'); mg.textContent = '⏳ Ladataan…'; bu.disabled = true;
					var fd = new FormData(); fd.append('file', f);
					fetch(D.urls.coverup, { method: 'POST', credentials: 'same-origin', headers: { 'X-WP-Nonce': nonce }, body: fd }).then(function (r) { return r.json().then(function (j) { if (!r.ok) { throw new Error(j.message || 'Virhe'); } return j; }); }).then(function (j) {
						t.img = j.url; g('[data-ti]').value = j.url; mg.textContent = '✅ Kuva ladattu. Paina lopuksi Tallenna kanavat.'; bu.disabled = false; e.target.value = '';
					}).catch(function (er) { mg.textContent = '❌ ' + er.message; bu.disabled = false; });
				});
				paint();
				return w;
			}
			function drawCh() {
				box.innerHTML = '';
				var m = el('div', { style: 'padding:8px;margin:6px 0;border:1px solid #2a303b;border-radius:10px;background:#101319;display:flex;gap:8px;flex-wrap:wrap;align-items:center' }, '<input style="' + sm + 'width:64px" data-mi maxlength="8" value="' + esc(mainC.icon) + '" aria-label="Kuvake"><input style="' + sm + 'flex:1;min-width:140px" data-mn maxlength="24" value="' + esc(mainC.name) + '" aria-label="Pääkanavan nimi"><span style="font-size:13px;opacity:.75">Pääkanava, kaikki musiikki (aina käytössä)</span>');
				m.querySelector('[data-mi]').addEventListener('input', function (e) { mainC.icon = e.target.value; });
				m.querySelector('[data-mn]').addEventListener('input', function (e) { mainC.name = e.target.value; });
				m.appendChild(thEditor(mainC));
				box.appendChild(m);
				L.forEach(function (c, i) {
					var n = D.lists.filter(function (l) { return l.id === c.list; })[0];
					var cnt = n ? n.ids.filter(function (id) { return D.tracks.some(function (t) { return t.id === id; }); }).length : 0;
					var r = el('div', { style: 'padding:8px;margin:6px 0;border:1px solid #2a303b;border-radius:10px;background:#101319' });
					var lopts = '<option value="">(luo uusi lista nimellä)</option>' + D.lists.map(function (l) { return '<option value="' + esc(l.id) + '"' + (l.id === c.list ? ' selected' : '') + '>' + esc(l.name) + '</option>'; }).join('');
					r.innerHTML = '<div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center"><input style="' + sm + 'width:64px" data-ci maxlength="8" value="' + esc(c.icon) + '" aria-label="Kuvake" placeholder="🎸"><input style="' + sm + 'flex:1;min-width:140px" data-cn maxlength="24" value="' + esc(c.name) + '" aria-label="Kanavan nimi" placeholder="Kanavan nimi"><label><input type="checkbox" data-co' + (c.on ? ' checked' : '') + '> käytössä</label></div>' +
						'<div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-top:4px"><label style="font-size:13px">Soittolista <select style="' + sm + '" data-cl>' + lopts + '</select></label><span style="font-size:13px;' + (cnt < 3 ? 'color:#ffb347' : 'opacity:.75') + '">' + cnt + ' kappaletta' + (cnt < 3 ? ' – ei näy kuuntelijoille ennen kuin kappaleita on vähintään 3' : '') + '</span><button type="button" data-cd>🗑 Poista kanava</button></div>' +
						(c.id ? '<div style="font-size:12px;opacity:.65;margin-top:4px">Suora linkki: sivun osoitteen perään <b>?kanava=' + esc(c.id) + '</b></div>' : '<div style="font-size:12px;opacity:.65;margin-top:4px">Linkki ilmestyy, kun tallennat.</div>');
					var q2 = function (k) { return r.querySelector(k); };
					q2('[data-ci]').addEventListener('input', function (e) { c.icon = e.target.value; });
					q2('[data-cn]').addEventListener('input', function (e) { c.name = e.target.value; });
					q2('[data-co]').addEventListener('change', function (e) { c.on = e.target.checked; });
					q2('[data-cl]').addEventListener('change', function (e) { c.list = e.target.value; });
					q2('[data-cd]').addEventListener('click', function () { if (confirm('Poistetaanko kanava ' + (c.name || '') + '? Soittolista ja sen kappaleet säilyvät.')) { L.splice(i, 1); drawCh(); } });
					r.appendChild(thEditor(c));
					box.appendChild(r);
				});
			}
			function afterSave(j) {
				D.channels = j.ch; D.lists = j.lists;
				mainC = JSON.parse(JSON.stringify(j.ch.main)); L = JSON.parse(JSON.stringify(j.ch.list));
				drawCh(); drawCl();
			}
			drawCh();
			chw.querySelector('[data-chn]').addEventListener('click', function () { if (L.length >= 12) { stt.textContent = 'Enintään 12 kanavaa.'; return; } L.push({ id: '', name: '', icon: '', list: '', on: true }); drawCh(); });
			chw.querySelector('[data-chs]').addEventListener('click', function () {
				stt.textContent = 'Tallennetaan…';
				api('POST', D.urls.channels, { main: mainC, channels: L.filter(function (c) { return (c.name || '').trim(); }) }).then(function (j) { afterSave(j); stt.textContent = '✅ Tallennettu. Muutos näkyy kuuntelijoille parin minuutin kuluessa.'; }).catch(function (e) { stt.textContent = '⚠️ ' + e.message; });
			});

			/* Luokittelu: jokaiselle kappaleelle kanavanapit */
			var f = { q: '', un: false, ch: '' }, shown = 80, sel = {};
			clw.innerHTML = '<h3 style="margin:0 0 6px">🏷️ Kappaleiden luokittelu</h3><p style="font-size:13px;opacity:.8;margin:0 0 8px">Merkitse jokaiselle kappaleelle kanavat, joilla se saa soida. Kappale voi kuulua useammalle kanavalle. Muutokset tallentuvat heti. Luo ensin kanavat ja tallenna ne yllä.</p><div data-clf style="display:flex;gap:8px;flex-wrap:wrap;align-items:center"></div><div data-clb style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin:8px 0"></div><div data-clt></div><div data-clm style="margin-top:8px"></div><div data-cls style="font-size:13px;margin-top:4px"></div>';
			var fb = clw.querySelector('[data-clf]'), bb = clw.querySelector('[data-clb]'), tb = clw.querySelector('[data-clt]'), mb = clw.querySelector('[data-clm]'), cst = clw.querySelector('[data-cls]');
			function chans() { return (D.channels && D.channels.list || []).filter(function (c) { return c.list; }); }
			function inList(c, id) { var l = D.lists.filter(function (z) { return z.id === c.list; })[0]; return !!(l && l.ids.indexOf(id) >= 0); }
			function setLocal(c, id, on) { D.lists.forEach(function (l) { if (l.id === c.list) { var k = l.ids.indexOf(id); if (on && k < 0) { l.ids.push(id); } if (!on && k >= 0) { l.ids.splice(k, 1); } } }); }
			function send(ops) {
				cst.textContent = 'Tallennetaan…';
				return api('POST', D.urls.classify, { ops: ops }).then(function (j) { D.lists = j.lists; D.channels = j.ch; cst.textContent = '✅ Tallennettu.'; }).catch(function (e) { cst.textContent = '⚠️ ' + e.message; });
			}
			function drawCl() {
				var cs = chans();
				fb.innerHTML = '<input style="' + sm + 'flex:1;min-width:160px" data-q placeholder="Hae kappaletta tai esittäjää" value="' + esc(f.q) + '"><label><input type="checkbox" data-un' + (f.un ? ' checked' : '') + '> vain luokittelemattomat</label><select style="' + sm + '" data-fc><option value="">Kaikki kanavat</option>' + cs.map(function (c) { return '<option value="' + esc(c.id) + '"' + (f.ch === c.id ? ' selected' : '') + '>' + esc((c.icon ? c.icon + ' ' : '') + c.name) + '</option>'; }).join('') + '</select>';
				fb.querySelector('[data-q]').addEventListener('input', function (e) { f.q = e.target.value; shown = 80; drawRows(); });
				fb.querySelector('[data-un]').addEventListener('change', function (e) { f.un = e.target.checked; shown = 80; drawRows(); });
				fb.querySelector('[data-fc]').addEventListener('change', function (e) { f.ch = e.target.value; shown = 80; drawRows(); });
				if (!cs.length) { bb.innerHTML = ''; tb.innerHTML = '<p style="font-size:13px;opacity:.75">Ei vielä kanavia. Lisää kanava ylhäällä ja tallenna se.</p>'; mb.innerHTML = ''; return; }
				bb.innerHTML = '<span style="font-size:13px">Valitut kappaleet (<b data-sn>0</b>):</span><select style="' + sm + '" data-bc>' + cs.map(function (c) { return '<option value="' + esc(c.id) + '">' + esc((c.icon ? c.icon + ' ' : '') + c.name) + '</option>'; }).join('') + '</select><button type="button" data-ba>＋ Lisää kanavalle</button><button type="button" data-br>− Poista kanavalta</button><button type="button" data-bx>Tyhjennä valinta</button>';
				var bulk = function (add) {
					var c = cs.filter(function (z) { return z.id === bb.querySelector('[data-bc]').value; })[0], ids = Object.keys(sel).map(Number);
					if (!c || !ids.length) { cst.textContent = 'Valitse ensin kappaleita.'; return; }
					ids.forEach(function (id) { setLocal(c, id, add); });
					var op = { list: c.list }; op[add ? 'add' : 'remove'] = ids;
					send([op]).then(function () { sel = {}; drawRows(); });
				};
				bb.querySelector('[data-ba]').addEventListener('click', function () { bulk(true); });
				bb.querySelector('[data-br]').addEventListener('click', function () { bulk(false); });
				bb.querySelector('[data-bx]').addEventListener('click', function () { sel = {}; drawRows(); });
				drawRows();
			}
			function drawRows() {
				var cs = chans(), qq = f.q.trim().toLowerCase(), fc = cs.filter(function (c) { return c.id === f.ch; })[0];
				var rows = D.tracks.filter(function (t) {
					if (qq && ((t.artist || '') + ' ' + (t.title || '')).toLowerCase().indexOf(qq) < 0) { return false; }
					if (f.un && cs.some(function (c) { return inList(c, t.id); })) { return false; }
					if (fc && !inList(fc, t.id)) { return false; }
					return true;
				});
				var un = D.tracks.filter(function (t) { return !cs.some(function (c) { return inList(c, t.id); }); }).length;
				tb.innerHTML = '<div style="font-size:12px;opacity:.75;margin:4px 0">' + rows.length + ' kappaletta · luokittelemattomia ' + un + '</div>';
				var sn = bb.querySelector('[data-sn]'); if (sn) { sn.textContent = Object.keys(sel).length; }
				rows.slice(0, shown).forEach(function (t) {
					var r = el('div', { style: 'display:flex;gap:8px;align-items:center;flex-wrap:wrap;padding:6px 4px;border-top:1px solid #2a303b' });
					r.innerHTML = '<label style="flex:none"><input type="checkbox" data-s' + (sel[t.id] ? ' checked' : '') + ' aria-label="Valitse"></label><span style="flex:1;min-width:150px;font-size:14px">' + esc((t.artist ? t.artist + ' – ' : '') + t.title) + '</span><span style="display:flex;gap:6px;flex-wrap:wrap">' + cs.map(function (c) { var on = inList(c, t.id); return '<button type="button" data-c="' + esc(c.id) + '" aria-pressed="' + on + '" style="padding:4px 10px;border-radius:14px;border:1px solid ' + (on ? '#ffb347' : '#363d4a') + ';background:' + (on ? 'rgba(255,179,71,.2)' : '#1b1f27') + ';color:#eef0f4;font-size:13px;cursor:pointer">' + esc((c.icon ? c.icon + ' ' : '') + c.name) + '</button>'; }).join('') + '</span>';
					r.querySelector('[data-s]').addEventListener('change', function (e) { if (e.target.checked) { sel[t.id] = 1; } else { delete sel[t.id]; } var sn2 = bb.querySelector('[data-sn]'); if (sn2) { sn2.textContent = Object.keys(sel).length; } });
					r.querySelectorAll('[data-c]').forEach(function (b) {
						b.addEventListener('click', function () {
							var c = cs.filter(function (z) { return z.id === b.getAttribute('data-c'); })[0], on = !inList(c, t.id);
							setLocal(c, t.id, on);
							var op = { list: c.list }; op[on ? 'add' : 'remove'] = [t.id];
							b.setAttribute('aria-pressed', on); b.style.borderColor = on ? '#ffb347' : '#363d4a'; b.style.background = on ? 'rgba(255,179,71,.2)' : '#1b1f27';
							send([op]);
						});
					});
					tb.appendChild(r);
				});
				mb.innerHTML = rows.length > shown ? '<button type="button" data-more>Näytä lisää (' + (rows.length - shown) + ')</button>' : '';
				var mo = mb.querySelector('[data-more]'); if (mo) { mo.addEventListener('click', function () { shown += 120; drawRows(); }); }
			}
			drawCl();
		})();

		/* Mainokset: myymälä / radio / molemmat, omat välit, mainosgeneraattori */
		var adw = el('div', { style: 'margin:6px 0 14px;padding:10px;border:1px solid #363d4a;border-radius:12px;background:#151922;color:#e8ecf3' });
		(function () {
			var A = D.ads || { list: [], cand: [], on: false, min: 3, max: 3, rmin: 4, rmax: 6, radads: false }, list = (A.list || []).map(function (x) { return { id: x.id, title: x.title, dur: x.dur, tg: x.tg || 's' }; });
			var SCR = [
				['Kuuntele Radio Suornia', 'Kuuntele Radio Suornia! Uutta musiikkia, uutiset ja sää joka päivä. Avaa suorni piste f i ja paina play.'],
				['Mikä soi nyt', 'Etkö tiedä, mitä kuuntelisit? Radio Suorni soittaa omaa musiikkia, ja juontaja pitää seuraa. Osoite on suorni piste f i.'],
				['Lähetä terveiset', 'Hei sinä! Radio Suorni soi juuri nyt. Paina play osoitteessa suorni piste f i ja lähetä terveisesi suoraan lähetykseen.'],
				['Oma nettiradio', 'Radio Suorni, sinun oma nettiradiosi. Kuuntele, äänestä lempikappaleesi ja lähetä toive. Suorni piste f i.']
			];
			adw.innerHTML = '<h3 style="margin:0 0 6px">📢 Mainokset</h3>' +
				'<p style="font-size:13px;opacity:.8;margin:0 0 8px">Jokaiselle mainokselle voi valita kohteen: <b>myymälä</b>, <b>radio</b> (julkinen) tai <b>molemmat</b>. Radiossa mainos tulee lähetykseen kappaleen jälkeen, ja musiikki jatkuu sen jälkeen. Myymälän radiossa mainos soi myös kappaleen jälkeen: myymälän kuuntelija jää mainoksen ajaksi hieman lähetyksestä jälkeen, ja seuraavan juonnon kohdalla viimeisen kappaleen loppu ohitetaan, jotta juonto kuuluu myymälässäkin tasalta.</p>' +
				'<label><input type="checkbox" data-adon' + (A.on ? ' checked' : '') + '> Mainokset käytössä</label><br>' +
				'<label><input type="checkbox" data-adrad' + (A.radads ? ' checked' : '') + '> Soita mainoksia julkisessa radiossa (ilman tätä mainokset kuuluvat vain myymälässä)</label>' +
				'<div style="display:flex;gap:10px;flex-wrap:wrap;margin:8px 0;align-items:center"><span>Myymälä: joka</span><input type="number" min="1" max="20" data-admin style="width:64px;' + st.replace('width:100%;', '') + '" value="' + A.min + '"><span>–</span><input type="number" min="1" max="20" data-admax style="width:64px;' + st.replace('width:100%;', '') + '" value="' + A.max + '"><span>kappale</span></div>' +
				'<div style="display:flex;gap:10px;flex-wrap:wrap;margin:8px 0;align-items:center"><span>Radio: joka</span><input type="number" min="1" max="40" data-adrmin style="width:64px;' + st.replace('width:100%;', '') + '" value="' + A.rmin + '"><span>–</span><input type="number" min="1" max="40" data-adrmax style="width:64px;' + st.replace('width:100%;', '') + '" value="' + A.rmax + '"><span>kappale</span></div>' +
				'<div data-adlist style="margin:8px 0"></div>' +
				'<div style="display:flex;gap:6px;flex-wrap:wrap;align-items:center;margin:6px 0"><select data-adcand style="' + st.replace('width:100%;', 'flex:1;min-width:180px;') + '"><option value="">Lisää olemassa oleva kappale mainokseksi…</option>' + (A.cand || []).map(function (c) { return '<option value="' + c.id + '">' + esc(c.title) + '</option>'; }).join('') + '</select><button type="button" data-adaddb>Lisää</button></div>' +
				'<button type="button" data-adsave style="margin:6px 0">💾 Tallenna mainosasetukset</button> <span data-adst style="font-size:13px"></span>' +
				'<h3 style="margin:16px 0 6px">🎙️ Tee uusi mainos tekstistä</h3>' +
				'<p style="font-size:13px;opacity:.8;margin:0 0 6px">Teksti muutetaan puheeksi Azure-äänellä ja tallennetaan mediakirjastoon mainoksena. 10 sekuntia on noin 25 sanaa. Yksi mainos kuluttaa vain muutaman sadan merkin puhesynteesin.' + (A.haskey ? '' : ' <b style="color:#ffb4b4">Azure-avainta ei ole tallennettu (Juontaja-välilehti), joten puhetta ei voi vielä tehdä.</b>') + '</p>' +
				'<div data-adscr style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:6px"></div>' +
				'<input style="' + st + '" data-adtitle maxlength="80" placeholder="Mainoksen nimi (esim. Kuuntele Radio Suornia)">' +
				'<textarea style="' + st + ';min-height:90px" data-adtext maxlength="400" placeholder="Mainosteksti. Kirjoita osoite kuten se luetaan: suorni piste f i"></textarea>' +
				'<div style="display:flex;gap:6px;flex-wrap:wrap;align-items:center"><select data-advoice style="' + st.replace('width:100%;', 'flex:1;min-width:140px;') + '"><option value="">Ääni: oletus (' + esc(A.vfi || '') + ')</option><option value="fi-FI-NooraNeural">Noora</option><option value="fi-FI-SelmaNeural">Selma</option><option value="fi-FI-HarriNeural">Harri</option><option value="en-US-AvaMultilingualNeural">Ava (monikielinen, kokeellinen)</option><option value="en-US-AndrewMultilingualNeural">Andrew (monikielinen, kokeellinen)</option></select>' +
				'<select data-adtg style="' + st.replace('width:100%;', 'flex:1;min-width:140px;') + '"><option value="r">Kohde: radio</option><option value="s">Kohde: myymälä</option><option value="b">Kohde: molemmat</option></select><button type="button" data-adgen>🎙️ Tee mainos</button></div><div data-adgst style="font-size:13px;margin-top:6px"></div>';
			var $ = function (q) { return adw.querySelector(q); };
			var lbox = $('[data-adlist]');
			function rows() {
				lbox.innerHTML = list.length ? '' : '<p style="font-size:13px;opacity:.7">Ei vielä mainoksia. Lisää kappale mainokseksi tai tee uusi alta.</p>';
				list.forEach(function (a, i) {
					var r = el('div', { style: 'display:flex;gap:6px;align-items:center;flex-wrap:wrap;padding:6px 0;border-top:1px solid #2a303b' });
					r.innerHTML = '<span style="flex:1;min-width:140px"><b>' + esc(a.title) + '</b> <span style="opacity:.6;font-size:12px">' + (a.dur || '?') + ' s</span></span>' +
						'<select style="' + st.replace('width:100%;', 'width:auto;') + '">' + [['s', 'Myymälä'], ['r', 'Radio'], ['b', 'Molemmat']].map(function (o) { return '<option value="' + o[0] + '"' + (a.tg === o[0] ? ' selected' : '') + '>' + o[1] + '</option>'; }).join('') + '</select>' +
						'<button type="button" title="Poista mainoslistalta (kappale jää mediakirjastoon)">✕</button>';
					r.querySelector('select').addEventListener('change', function (e) { a.tg = e.target.value; });
					r.querySelector('button').addEventListener('click', function () { list.splice(i, 1); rows(); });
					lbox.appendChild(r);
				});
			}
			rows();
			var sc = $('[data-adscr]');
			SCR.forEach(function (x) { var b = el('button', { type: 'button' }, '✍️ ' + esc(x[0])); b.addEventListener('click', function () { $('[data-adtext]').value = x[1]; $('[data-adtitle]').value = x[0]; }); sc.appendChild(b); });
			$('[data-adaddb]').addEventListener('click', function () {
				var sel = $('[data-adcand]'), id = +sel.value; if (!id) { return; }
				var c = (A.cand || []).filter(function (x) { return +x.id === id; })[0]; if (!c || list.some(function (x) { return +x.id === id; })) { return; }
				list.push({ id: id, title: c.title, dur: 0, tg: 'r' }); sel.value = ''; rows();
			});
			$('[data-adsave]').addEventListener('click', function () {
				var m = $('[data-adst]'), tg = {}; list.forEach(function (x) { tg[x.id] = x.tg; });
				m.textContent = 'Tallennetaan…';
				api('POST', D.urls.ads, { on: $('[data-adon]').checked, radads: $('[data-adrad]').checked, min: +$('[data-admin]').value || 3, max: +$('[data-admax]').value || 3, rmin: +$('[data-adrmin]').value || 4, rmax: +$('[data-adrmax]').value || 6, ids: list.map(function (x) { return x.id; }), tg: tg })
					.then(function () { m.textContent = '✅ Tallennettu. Radion jono päivittyy seuraavan tasan jälkeen.'; }).catch(function (e) { m.textContent = '⚠️ ' + e.message; });
			});
			$('[data-adgen]').addEventListener('click', function () {
				var g = $('[data-adgst]'), b = $('[data-adgen]'), txt = $('[data-adtext]').value.trim();
				if (!txt) { g.textContent = 'Kirjoita ensin mainosteksti tai valitse valmis teksti.'; return; }
				b.disabled = true; g.textContent = 'Tehdään puhetta…';
				api('POST', D.urls.adgen, { text: txt, title: $('[data-adtitle]').value, voice: $('[data-advoice]').value, tg: $('[data-adtg]').value })
					.then(function (j) { b.disabled = false; list.push({ id: j.id, title: j.title, dur: j.dur, tg: j.tg }); rows(); g.innerHTML = '✅ Valmis: <b>' + esc(j.title) + '</b> (' + j.dur + ' s)' + (j.dur > 13 ? ' <b style="color:#ffb347">– yli 13 s, lyhennä tekstiä jos haluat 10 sekunnin mainoksen</b>' : '') + '. Se on jo mainoslistalla; paina vielä Tallenna, jos muutit asetuksia.'; })
					.catch(function (e) { b.disabled = false; g.textContent = '⚠️ ' + e.message; });
			});
		})();

		/* Kappaletarve: missä ohjelmissa ja kanavilla kappaleita puuttuu */
		var nw = el('div', { style: 'margin:6px 0 14px;padding:10px;border:1px solid #363d4a;border-radius:12px;background:#151922;color:#e8ecf3' });
		(function () {
			var N = D.need || { progs: [], chans: [], styles: [], text: '' };
			var col = { bad: '#ff5a4f', low: '#ffb347', ok: '#e5d04f', good: '#4cc27a' };
			var lbl = { bad: 'Liian vähän', low: 'Vähän', ok: 'Melkein', good: 'Riittävästi' };
			function row(name, sub, n, hours, stt, need, hint, warn) {
				var w = Math.max(3, Math.min(100, Math.round(n / (N.goal || 45) * 100)));
				return '<div style="padding:8px;margin:5px 0;border:1px solid #2a303b;border-radius:10px;background:#101319"><div style="display:flex;gap:8px;flex-wrap:wrap;align-items:baseline;justify-content:space-between"><b>' + esc(name) + '</b><span style="font-size:13px;opacity:.75">' + esc(sub) + '</span><span style="font-size:13px"><b style="color:' + col[stt] + '">' + lbl[stt] + '</b> · ' + n + ' kpl · ' + hours + ' h</span></div>' +
					'<div style="height:7px;border-radius:4px;background:#252b36;margin:6px 0"><div style="height:7px;border-radius:4px;width:' + w + '%;background:' + col[stt] + '"></div></div>' +
					(need > 0 ? '<div style="font-size:13px">Tarvitaan noin <b>+' + need + '</b>: ' + esc(hint) + '</div>' : '<div style="font-size:13px;opacity:.7">Riittävästi kappaleita.</div>') + (warn ? '<div style="font-size:12px;color:#ffb347;margin-top:2px">' + esc(warn) + '</div>' : '') + '</div>';
			}
			var h = '<h3 style="margin:0 0 6px">🎯 Kappaletarve</h3><p style="font-size:13px;opacity:.8;margin:0 0 8px">Lasketaan oikeasta kappalelistasta. Tavoite on vähintään ' + (N.goal || 45) + ' eri kappaletta jokaista ohjelmaa ja kanavaa kohti (noin 3 tuntia musiikkia ennen toistoa). Päivittyy, kun avaat ohjaamon uudelleen.</p>';
			h += '<div style="display:flex;gap:10px;flex-wrap:wrap;margin:0 0 8px"><span><b>' + (N.playable || 0) + '</b> soitettavaa kappaletta</span><span><b>' + (N.hours || 0) + '</b> h musiikkia</span><span>keskipituus <b>' + (N.avg ? Math.floor(N.avg / 60) + ':' + ('0' + (N.avg % 60)).slice(-2) : '–') + '</b></span>' + (N.nodur ? '<span style="color:#ffb347">' + N.nodur + ' ilman kestoa (ei soi)</span>' : '') + '</div>';
			h += '<p style="margin:10px 0 2px"><b>Ohjelmat</b></p>';
			(N.progs || []).forEach(function (p) { h += row(p.name, 'klo ' + p.from + '–' + p.to, p.n, p.hours, p.st, p.need, p.hint, p.only ? p.only + ' kappaletta rajattu vain tiettyihin ohjelmiin' : ''); });
			if ((N.chans || []).length) {
				h += '<p style="margin:12px 0 2px"><b>Kanavat</b></p>';
				N.chans.forEach(function (c) { h += row((c.icon ? c.icon + ' ' : '') + c.name, c.pub ? 'näkyy kuuntelijoille' : (c.on ? 'ei vielä näy (alle 3 kappaletta)' : 'ei käytössä'), c.n, c.hours, c.st, c.need, c.hint, ''); });
			}
			var notes = [];
			if (N.uncl) { notes.push(N.uncl + ' kappaletta ei kuulu millekään omalle kanavalle. Luokittele ne Kanavat-välilehdellä.'); }
			if (N.short) { notes.push(N.short + ' kappaletta on alle 2 min.'); }
			if (N.long) { notes.push(N.long + ' kappaletta on yli 5,5 min.'); }
			if (N.nostyle) { notes.push(N.nostyle + ' kappaleella ei ole tyyli- tai genretietoa.'); }
			notes.push('Nyt ajankohtaista: ' + (N.season || ''));
			h += '<p style="margin:12px 0 2px"><b>Muuta</b></p><ul style="margin:0 0 6px 18px;font-size:13px">' + notes.map(function (x) { return '<li>' + esc(x) + '</li>'; }).join('') + '</ul>';
			if ((N.styles || []).length) { h += '<p style="font-size:13px;margin:6px 0"><b>Yleisimmät tyylit:</b> ' + N.styles.slice(0, 10).map(function (x) { return esc(x.name) + ' (' + x.n + ')'; }).join(', ') + '</p>'; }
			h += '<p style="margin:12px 0 4px"><b>Kopioitava tilannekatsaus biisien tekoon</b></p><textarea data-nt readonly rows="12" style="' + st + 'font:13px/1.45 ui-monospace,Menlo,Consolas,monospace"></textarea><div style="margin-top:6px;display:flex;gap:8px;align-items:center;flex-wrap:wrap"><button type="button" data-nc>📋 Kopioi teksti</button><span data-nm style="font-size:13px"></span></div>';
			nw.innerHTML = h;
			nw.querySelector('[data-nt]').value = N.text || '';
			nw.querySelector('[data-nc]').addEventListener('click', function () {
				var ta = nw.querySelector('[data-nt]'), m = nw.querySelector('[data-nm]');
				function ok() { m.textContent = '✅ Kopioitu. Liitä se biisiapurille.'; }
				if (navigator.clipboard && navigator.clipboard.writeText) { navigator.clipboard.writeText(ta.value).then(ok, function () { ta.select(); try { document.execCommand('copy'); ok(); } catch (e) { m.textContent = 'Valitse teksti ja kopioi käsin.'; } }); }
				else { ta.select(); try { document.execCommand('copy'); ok(); } catch (e) { m.textContent = 'Valitse teksti ja kopioi käsin.'; } }
			});
		})();
		/* Ilmoitus puhelimeen uudesta kuuntelijasta (ntfy) */
		var psw = el('div', { style: 'margin:6px 0 14px;padding:10px;border:1px solid #363d4a;border-radius:12px;background:#151922;color:#e8ecf3' });
		(function () {
			var P = D.push || { on: false, topic: '', int: 0, url: '' };
			var sm = st.replace('width:100%;', 'width:auto;');
			function draw(msg) {
				psw.innerHTML = '<h3 style="margin:0 0 6px">📲 Ilmoitus uudesta kuuntelijasta</h3>' +
					'<p style="font-size:13px;opacity:.8;margin:0 0 8px">Puhelimeesi tulee pling-ilmoitus, kun uusi kuuntelija tulee radioon. Ilmoitus kertoo, montako kuuntelee, mistä kuuntelija tuli (esim. tiktok) ja millä kanavalla. Tunnistetietoja ei lähetetä. Palvelu on ilmainen <b>ntfy</b>-sovellus (iPhone ja Android).</p>' +
					'<label><input type="checkbox" data-pon' + (P.on ? ' checked' : '') + '> Ilmoitukset päällä</label>' +
					'<p style="margin:8px 0 2px;font-size:13px">Ilmoitus enintään</p><select style="' + sm + '" data-pint><option value="0"' + (P.int === 0 ? ' selected' : '') + '>jokaisesta uudesta kuuntelijasta</option><option value="60"' + (P.int === 60 ? ' selected' : '') + '>kerran minuutissa</option><option value="300"' + (P.int === 300 ? ' selected' : '') + '>kerran 5 minuutissa</option><option value="900"' + (P.int === 900 ? ' selected' : '') + '>kerran 15 minuutissa</option><option value="3600"' + (P.int === 3600 ? ' selected' : '') + '>kerran tunnissa</option></select>' +
					'<div style="margin-top:8px;display:flex;gap:8px;flex-wrap:wrap;align-items:center"><button type="button" data-psv>💾 Tallenna</button><button type="button" data-pts>🔔 Lähetä testi</button><button type="button" data-pnw>🔑 Uusi osoite</button><span data-pmsg style="font-size:13px">' + (msg || '') + '</span></div>' +
					(P.topic ? '<div style="margin-top:10px;font-size:13px;line-height:1.6"><b>Näin otat käyttöön:</b><ol style="margin:4px 0 0 18px"><li>Asenna puhelimeen ilmainen <b>ntfy</b>-sovellus (App Store tai Google Play).</li><li>Paina sovelluksessa <b>＋</b> ja kirjoita aiheen nimeksi: <b data-ptp style="user-select:all;word-break:break-all">' + esc(P.topic) + '</b></li><li>Jätä palvelimeksi ntfy.sh, paina Tilaa. Paina tässä ruudussa vielä "Lähetä testi".</li></ol><div style="opacity:.7;margin-top:4px">Aiheen nimi toimii salasanana: älä jaa sitä. Jos se vuotaa, paina "Uusi osoite" ja tilaa uusi nimi sovelluksessa.</div></div>' : '<div style="margin-top:8px;font-size:13px;opacity:.75">Paina "Tallenna", niin ilmoitusosoite luodaan.</div>') +
					(P.err ? '<div style="margin-top:6px;font-size:12px;color:#ffb347">Viimeisin virhe: ' + esc(P.err) + '</div>' : '');
				var g = function (k) { return psw.querySelector(k); };
				function send(extra) {
					var body = { on: g('[data-pon]').checked, int: parseInt(g('[data-pint]').value, 10) || 0 };
					Object.keys(extra || {}).forEach(function (k) { body[k] = extra[k]; });
					g('[data-pmsg]').textContent = 'Tallennetaan…';
					return api('POST', D.urls.push, body).then(function (j) { P = j.push || P; D.push = P; var m = '✅ Tallennettu.'; if (j.test) { m = j.test === 'ok' ? '✅ Testi lähetetty. Jos et saanut sitä, tarkista tilaus ntfy-sovelluksessa.' : '⚠️ ' + j.test; } draw(m); }).catch(function (e) { g('[data-pmsg]').textContent = '⚠️ ' + e.message; });
				}
				g('[data-psv]').addEventListener('click', function () { send(); });
				g('[data-pts]').addEventListener('click', function () { send({ test: true }); });
				g('[data-pnw]').addEventListener('click', function () { if (confirm('Luodaanko uusi osoite? Vanha lakkaa toimimasta ja sinun pitää tilata uusi nimi ntfy-sovelluksessa.')) { send({ renew: true }); } });
			}
			draw('');
		})();
		/* Radio-tagit: sanoitusapurin antamat kanava- ja ohjelmatiedot kappaleille */
		var tgw = el('div', { style: 'margin:6px 0 14px;padding:10px;border:1px solid #363d4a;border-radius:12px;background:#151922;color:#e8ecf3' });
		(function () {
			var G = D.tags || { list: [], chn: [], prn: [] };
			var sm = st.replace('width:100%;', 'width:auto;');
			function guide() {
				return 'Kun teet kappaleesta valmiin, anna lopuksi jokaisesta kappaleesta yksi Radio-tagi -rivi tässä muodossa:\n\n' +
					'Kappaleen nimi | kanavat: Iskelmä, Miesten ilta | ohjelmat: Ilta, Yö | tyyli: lämmin iskelmä, haitari, keskitempo | kuvaus: yksi lause kappaleen tarinasta\n\n' +
					'Säännöt:\n- Kappaleen nimi on täsmälleen sama kuin tiedoston/kappaleen nimi.\n- Kanavat valitaan vain näistä: ' + (G.chn.length ? G.chn.join(', ') : '(ei omia kanavia vielä)') + '. Kappale voi kuulua usealle kanavalle. Jos kappale sopii vain pääkanavalle, jätä kanavat-kenttä pois.\n- Ohjelmat valitaan näistä: ' + (G.prn.length ? G.prn.join(', ') : '–') + ' (1–3 kpl). Jos kappale sopii kaikkiin, kirjoita: ohjelmat: kaikki.\n- Tyyli kuvataan soittimilla, tempolla ja tunnelmalla, ei artistin nimellä.\n- Kuvaus on yksi lyhyt lause, ilman Suno-mainintoja.\n- Anna kaikki rivit samassa viestissä peräkkäin, yksi rivi per kappale.';
			}
			function draw(msg) {
				var rows = G.list.map(function (e) {
					var st2 = e.n > 0 ? '<span style="color:#4cc27a">✅ käytetty ' + e.n + ' kappaleelle</span>' : '<span style="color:#ffb347">⏳ odottaa kappaletta</span>';
					return '<div style="padding:7px 8px;margin:4px 0;border:1px solid #2a303b;border-radius:10px;background:#101319;font-size:13px"><div style="display:flex;gap:8px;flex-wrap:wrap;align-items:baseline;justify-content:space-between"><b>' + esc(e.t) + '</b><span>' + st2 + ' <button type="button" data-tgd="' + esc(e.key) + '" style="padding:2px 8px" title="Poista rivi (kappaleiden luokitus säilyy)">🗑</button></span></div>' +
						'<div style="opacity:.85">Kanavat: ' + (e.ch.length ? esc(e.ch.join(', ')) : '–') + ' · Ohjelmat: ' + (e.pr.length ? esc(e.pr.join(', ')) : '–') + (e.st ? ' · Tyyli: ' + esc(e.st) : '') + '</div>' +
						(e.bad.length ? '<div style="color:#ffb347;margin-top:2px">⚠️ Tuntematon: ' + esc(e.bad.join(', ')) + ' (tarkista nimi tai luo kanava)</div>' : '') + '</div>';
				}).join('');
				tgw.innerHTML = '<h3 style="margin:0 0 6px">🏷️ Sanoittajan tiedot (Radio-tagit)</h3>' +
					'<p style="font-size:13px;opacity:.8;margin:0 0 8px">Liitä tähän sanoitusapurin antamat rivit. Kun samanniminen kappale on tai tulee mediakirjastoon, se lisätään heti oikeille kanaville ja ohjelmiin, ja sille tallennetaan tyyli ja kuvaus. Rivin muoto:<br><code style="font-size:12px">Kappaleen nimi | kanavat: Iskelmä, Miesten ilta | ohjelmat: Ilta | tyyli: lämmin iskelmä, haitari | kuvaus: lyhyt tarina</code></p>' +
					'<textarea data-tgt rows="6" style="' + st + 'font:13px/1.45 ui-monospace,Menlo,Consolas,monospace" placeholder="Liitä rivit tähän, yksi rivi per kappale"></textarea>' +
					'<div style="margin-top:6px;display:flex;gap:8px;flex-wrap:wrap;align-items:center"><button type="button" data-tgs>💾 Tallenna tiedot</button><button type="button" data-tgc>📋 Kopioi ohje sanoitusapurille</button><span data-tgm style="font-size:13px">' + (msg || '') + '</span></div>' +
					'<p style="margin:12px 0 2px"><b>Tallennetut rivit (' + G.list.length + ')</b></p>' + (rows || '<div style="font-size:13px;opacity:.7">Ei vielä rivejä.</div>');
				var g = function (k) { return tgw.querySelector(k); };
				g('[data-tgs]').addEventListener('click', function () {
					var txt = g('[data-tgt]').value; if (!txt.trim()) { g('[data-tgm]').textContent = 'Liitä ensin rivit.'; return; }
					g('[data-tgm]').textContent = 'Tallennetaan…';
					api('POST', D.urls.tags, { text: txt }).then(function (j) { G = j.tags; D.tags = G; draw('✅ ' + j.added + ' uutta, ' + j.replaced + ' korvattua. Käytetty heti ' + j.applied + ' kappaleelle.'); }).catch(function (e) { g('[data-tgm]').textContent = '⚠️ ' + e.message; });
				});
				g('[data-tgc]').addEventListener('click', function () {
					var tx = guide(), m = g('[data-tgm]');
					function ok() { m.textContent = '✅ Ohje kopioitu. Liitä se sanoitusapurille.'; }
					if (navigator.clipboard && navigator.clipboard.writeText) { navigator.clipboard.writeText(tx).then(ok, function () { m.textContent = 'Kopiointi ei onnistunut.'; }); } else { m.textContent = 'Kopiointi ei onnistunut.'; }
				});
				tgw.querySelectorAll('[data-tgd]').forEach(function (b) { b.addEventListener('click', function () { api('POST', D.urls.tagdel, { key: b.getAttribute('data-tgd') }).then(function (j) { G = j.tags; D.tags = G; draw(''); }).catch(function (e) { alert(e.message); }); }); });
			}
			draw('');
		})();
		/* Sivusto: some-linkit, mainospaikka, jakokuva, hakukoneet, varmuuskopio */
		var sow = el('div', { style: 'margin:6px 0 14px;padding:10px;border:1px solid #363d4a;border-radius:12px;background:#151922;color:#e8ecf3' });
		(function () {
			var S = D.social || { ig: '', tt: '', promo_on: true, promo_text: '', promo_url: '', og_img: '', desc: '', seo: { public: true } };
			function draw(msg) {
				var f = function (lbl, k, ph, hint) { return '<label style="display:block;margin-top:8px;font-size:14px">' + lbl + '<input type="text" data-so="' + k + '" value="' + esc(S[k] || '') + '" placeholder="' + esc(ph || '') + '" style="' + st + '">' + (hint ? '<span style="font-size:12px;opacity:.65">' + hint + '</span>' : '') + '</label>'; };
				sow.innerHTML = '<h3 style="margin:0 0 6px">🌐 Sivusto, some ja jakaminen</h3>' +
					'<p style="font-size:13px;opacity:.8;margin:0 0 4px">Linkit näkyvät soittimen alla pienenä nauhana. Tyhjä kenttä piilottaa linkin.</p>' +
					f('📸 Instagram-osoite', 'ig', 'https://www.instagram.com/tilisi/') + f('🎵 TikTok-osoite', 'tt', 'https://www.tiktok.com/@tilisi') +
					'<h4 style="margin:14px 0 2px">📣 Mainospaikka-nauha</h4><label><input type="checkbox" data-so-on' + (S.promo_on ? ' checked' : '') + '> Näytä nauha soittimen alla</label>' +
					f('Teksti (enintään 80 merkkiä)', 'promo_text', 'Mainospaikka vapaana – ota haltuun!') + f('Mihin nauha vie', 'promo_url', 'mailto:admin@suorni.fi', 'Sähköposti muodossa mailto:osoite@esimerkki.fi tai www-osoite.') +
					'<h4 style="margin:14px 0 2px">🔗 Jaettavan linkin esikatselu</h4><p style="font-size:12px;opacity:.7;margin:0">Kun radion, kanavan tai kappaleen linkki jaetaan (WhatsApp, Facebook, Instagram, Messenger), näkyy tämä kuva ja kuvaus.</p>' +
					f('Kuvan osoite (tyhjä = oletuskuva)', 'og_img', 'https://…/kuva.jpg', 'Sopiva koko 1200 × 630.') + f('Lyhyt kuvaus', 'desc', 'Suorni – suomalaista radiota ympäri vuorokauden') +
					'<div style="margin-top:10px;display:flex;gap:8px;flex-wrap:wrap;align-items:center"><button type="button" data-sosv>💾 Tallenna</button><span data-somsg style="font-size:13px">' + (msg || '') + '</span></div>' +
					'<h4 style="margin:16px 0 4px">🔎 Hakukoneet</h4><div style="font-size:14px">' + (S.seo && S.seo.public === false ? '🛑 Hakukoneet on estetty (WordPressin asetus). <button type="button" data-soidx>Salli hakukoneet</button>' : '✅ Hakukoneet saavat indeksoida sivuston.') + '</div>';
				var g = function (k) { return sow.querySelector(k); };
				function body() { var b = { promo_on: g('[data-so-on]').checked }; ['ig', 'tt', 'promo_text', 'promo_url', 'og_img', 'desc'].forEach(function (k) { b[k] = g('[data-so="' + k + '"]').value; }); return b; }
				function send(extra) {
					var b = body(); Object.keys(extra || {}).forEach(function (k) { b[k] = extra[k]; });
					g('[data-somsg]').textContent = 'Tallennetaan…';
					return api('POST', D.urls.social, b).then(function (j) { S = j.social || S; D.social = S; draw('✅ Tallennettu.'); redraw.tn && redraw.tn(); }).catch(function (e) { g('[data-somsg]').textContent = '⚠️ ' + e.message; });
				}
				g('[data-sosv]').addEventListener('click', function () { send(); });
				var ix = g('[data-soidx]'); if (ix) { ix.addEventListener('click', function () { send({ allow_index: true }); }); }
			}
			draw('');
		})();
		var bkw = el('div', { style: 'margin:6px 0 14px;padding:10px;border:1px solid #363d4a;border-radius:12px;background:#151922;color:#e8ecf3' });
		(function () {
			var B = D.bk || { list: [], last: 0 };
			function draw(msg) {
				bkw.innerHTML = '<h3 style="margin:0 0 6px">💾 Varmuuskopio</h3>' +
					'<p style="font-size:13px;opacity:.8;margin:0 0 8px">Kopioon tulevat radion asetukset, kanavat, ohjelmat, juontajat, mainokset, listat ja kappaleiden ohjelma-/tyylimerkinnät. <b>Salasanoja ja avaimia (Anthropic, Azure, ntfy) ei viedä</b>, eikä kappaletiedostoja. Kone ottaa kopion itsekseen viikoittain ja säilyttää viimeiset viisi.</p>' +
					'<div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center"><button type="button" data-bkmk>📸 Ota kopio nyt</button><button type="button" data-bkdl>⬇️ Lataa tiedostoksi</button><button type="button" data-bkup>⬆️ Tuo tiedostosta</button><input type="file" accept=".json,application/json" data-bkfi style="display:none"><span data-bkmsg style="font-size:13px">' + (msg || '') + '</span></div>' +
					'<div style="margin-top:10px;font-size:14px">' + (B.list.length ? B.list.map(function (x) { return '<div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;padding:6px 0;border-top:1px solid #272c36"><span style="flex:1;min-width:150px">' + esc(x.when) + ' <span style="opacity:.65;font-size:12px">(' + esc(x.why) + ', ' + x.opts + ' asetusta, ' + x.tracks + ' kappaletta)</span></span><button type="button" data-bkr="' + x.at + '">↩️ Palauta</button><button type="button" data-bkd="' + x.at + '">🗑️</button></div>'; }).join('') : '<span style="opacity:.7">Ei vielä kopioita.</span>') + '</div>';
				var g = function (k) { return bkw.querySelector(k); };
				function setB(j) { B = j.bk || B; D.bk = B; }
				g('[data-bkmk]').addEventListener('click', function () { api('POST', D.urls.bkmake, {}).then(function (j) { setB(j); draw('✅ Kopio otettu.'); redraw.tn && redraw.tn(); }).catch(function (e) { g('[data-bkmsg]').textContent = '⚠️ ' + e.message; }); });
				g('[data-bkdl]').addEventListener('click', function () {
					api('GET', D.urls.bkexport).then(function (j) { var bl = new Blob([JSON.stringify(j, null, 1)], { type: 'application/json' }), a = document.createElement('a'), d = new Date(); a.href = URL.createObjectURL(bl); a.download = 'suorni-kopio-' + d.getFullYear() + ('0' + (d.getMonth() + 1)).slice(-2) + ('0' + d.getDate()).slice(-2) + '.json'; document.body.appendChild(a); a.click(); setTimeout(function () { URL.revokeObjectURL(a.href); a.remove(); }, 500); g('[data-bkmsg]').textContent = '✅ Ladattu.'; }).catch(function (e) { g('[data-bkmsg]').textContent = '⚠️ ' + e.message; });
				});
				g('[data-bkup]').addEventListener('click', function () { g('[data-bkfi]').click(); });
				g('[data-bkfi]').addEventListener('change', function (ev) {
					var f = ev.target.files[0]; if (!f) { return; }
					var rd = new FileReader();
					rd.onload = function () {
						var data; try { data = JSON.parse(rd.result); } catch (e) { g('[data-bkmsg]').textContent = '⚠️ Tiedosto ei ole kelvollinen kopio.'; return; }
						if (!confirm('Palautetaanko asetukset tiedostosta? Nykyiset asetukset korvataan. Niistä otetaan ensin automaattinen kopio, jonka voi palauttaa tästä listasta.')) { return; }
						api('POST', D.urls.bkimport, { data: data }).then(function (j) { setB(j); draw('✅ Palautettu. Päivitä sivu (Ctrl+Shift+R), niin näet uudet asetukset.'); }).catch(function (e) { g('[data-bkmsg]').textContent = '⚠️ ' + e.message; });
					};
					rd.readAsText(f);
				});
				bkw.querySelectorAll('[data-bkr]').forEach(function (b) { b.addEventListener('click', function () { if (!confirm('Palautetaanko tämä kopio? Nykyisistä asetuksista otetaan ensin kopio.')) { return; } api('POST', D.urls.bkrestore, { at: +b.getAttribute('data-bkr') }).then(function (j) { setB(j); draw('✅ Palautettu. Päivitä sivu (Ctrl+Shift+R).'); }).catch(function (e) { g('[data-bkmsg]').textContent = '⚠️ ' + e.message; }); }); });
				bkw.querySelectorAll('[data-bkd]').forEach(function (b) { b.addEventListener('click', function () { api('POST', D.urls.bkdel, { at: +b.getAttribute('data-bkd') }).then(function (j) { setB(j); draw(''); }).catch(function (e) { g('[data-bkmsg]').textContent = '⚠️ ' + e.message; }); }); });
			}
			draw('');
		})();
		/* Tänään: mitä tarvitsee huomiota juuri nyt */
		var tnw = el('div', { style: 'margin:6px 0 14px;padding:12px;border:1px solid #363d4a;border-radius:12px;background:#151922;color:#e8ecf3' });
		var gotoTab = function () {};
		function todoItems() {
			var T = [];
			var W = D.watch || { checks: [] };
			(W.checks || []).forEach(function (c) { if (c.lvl === 'bad' || c.lvl === 'warn') { T.push({ lvl: c.lvl === 'bad' ? 0 : 1, ico: c.lvl === 'bad' ? '🛑' : '⚠️', t: c.title, d: c.detail, tab: 'yleinen', btn: 'Valvonta' }); } });
			var np = (D.subs || []).filter(function (x) { return x.status === 'pending'; }).length;
			if (np) { T.push({ lvl: 1, ico: '🎵', t: np + ' uutta kappaletta odottaa hyväksyntää', d: 'Yhteisön lähettämät kappaleet.', tab: 'yhteiso', btn: 'Avaa' }); }
			var ng = (D.greets || []).filter(function (g) { return ['ok', 'no', 'played'].indexOf(g.status) < 0; }).length;
			if (ng) { T.push({ lvl: 1, ico: '💌', t: ng + ' tervehdystä odottaa', d: 'Hyväksy tai hylkää ne ennen kuin ne soivat.', tab: 'yleiso', btn: 'Avaa' }); }
			var nr = (D.reqs || []).filter(function (x) { return x.st === 'pending'; }).length;
			if (nr) { T.push({ lvl: 1, ico: '🙋', t: nr + ' toivetta odottaa', d: 'Kuuntelijoiden kappaletoiveet.', tab: 'yleiso', btn: 'Avaa' }); }
			var N = D.need || {}, lows = [];
			(N.progs || []).concat(N.chans || []).forEach(function (r) { if ((r.st === 'bad' || r.st === 'low') && (r.from || r.on)) { lows.push(r); } });
			if (lows.length) { T.push({ lvl: 2, ico: '🎯', t: 'Kappaleita puuttuu: ' + lows.slice(0, 3).map(function (r) { return r.name + ' (+' + r.need + ')'; }).join(', ') + (lows.length > 3 ? ' ja ' + (lows.length - 3) + ' muuta' : ''), d: 'Tuota näitä seuraavaksi.', tab: 'tarve', btn: 'Kappaletarve' }); }
			if (D.push && D.push.err) { T.push({ lvl: 2, ico: '📲', t: 'Pling-ilmoitus ei lähde', d: D.push.err, tab: 'yleinen', btn: 'Avaa' }); }
			var S = D.social || {};
			if (S.seo && S.seo.public === false) { T.push({ lvl: 2, ico: '🔎', t: 'Hakukoneet on estetty', d: 'Sivusto ei löydy Googlesta.', tab: 'sivusto', btn: 'Korjaa' }); }
			if (!S.tt) { T.push({ lvl: 3, ico: '🎵', t: 'TikTok-linkki puuttuu', d: 'Lisää tilisi osoite, niin linkki näkyy soittimen alla.', tab: 'sivusto', btn: 'Lisää' }); }
			var B = D.bk || { last: 0 };
			if (!B.last || (Date.now() / 1000 - B.last) > 14 * 86400) { T.push({ lvl: 3, ico: '💾', t: 'Varmuuskopio on vanha', d: B.last ? 'Viimeisin yli kaksi viikkoa sitten.' : 'Kopiota ei ole vielä otettu.', tab: 'sivusto', btn: 'Ota kopio' }); }
			T.sort(function (a, b) { return a.lvl - b.lvl; });
			return T;
		}
		function drawToday() {
			var T = todoItems(), live = D.stats ? D.stats.live : 0;
			var h = '<h3 style="margin:0 0 4px">📌 Tänään</h3><p style="font-size:13px;opacity:.75;margin:0 0 8px">' + (live ? '🎧 Nyt kuuntelee ' + live + '. ' : '') + (T.length ? 'Tässä asiat, jotka kaipaavat sinua.' : '') + '</p>';
			if (!T.length) { h += '<div style="padding:14px;text-align:center;font-size:15px">✅ Ei mitään tehtävää. Radio pyörii itsekseen.</div>'; }
			h += T.map(function (x, i) { return '<div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;padding:8px 0;border-top:1px solid #272c36"><span style="font-size:20px">' + x.ico + '</span><div style="flex:1;min-width:180px"><b style="font-size:14px">' + esc(x.t) + '</b><div style="font-size:12px;opacity:.75">' + esc(x.d) + '</div></div><button type="button" data-tn="' + i + '" style="min-height:40px">' + esc(x.btn) + ' →</button></div>'; }).join('');
			tnw.innerHTML = h;
			tnw.querySelectorAll('[data-tn]').forEach(function (b) { b.addEventListener('click', function () { var x = T[+b.getAttribute('data-tn')]; gotoTab(x.tab); window.scrollTo(0, 0); }); });
			return T.length;
		}
		redraw.tn = function () { var n = drawToday(); if (tbtn.tanaan) { tbtn.tanaan.innerHTML = TABS[0][1] + (n ? ' <b class="bdg">' + n + '</b>' : ''); } };
		drawToday();
		var bucket = { tanaan: [tnw], yleinen: [wtw, info, sd, mw, psw, hA], ohjelmat: [pw, quick, add], yleiso: [hB, gw, cw, rqw], juontaja: [hC, ow, aw, fw], yhteiso: [hD, scw], lista: [upw, tgw, bw, tw], kanavat: [chw, clw], tarve: [nw], mainokset: [adw], sivusto: [sow, bkw] };
		var used = [];
		Object.keys(bucket).forEach(function (k) { bucket[k].forEach(function (n) { used.push(n); }); });
		loose.forEach(function (n) { if (used.indexOf(n) < 0 && n !== save && n !== msg) { bucket.yleinen.unshift(n); } });
		box.innerHTML = '';
		var nav = el('div', { 'class': 'st-nav' });
		var panes = {}, tbtn = {};
		var npend = (D.subs || []).filter(function (x) { return x.status === 'pending'; }).length;
		var gpend = (D.greets || []).filter(function (g) { return ['ok', 'no', 'played'].indexOf(g.status) < 0; }).length;
		var badge = { yhteiso: npend, yleiso: gpend, tanaan: todoItems().length };
		TABS.forEach(function (t) {
			var b = el('button', { type: 'button' }, t[1] + (badge[t[0]] ? ' <b class="bdg">' + badge[t[0]] + '</b>' : ''));
			b.addEventListener('click', function () { show(t[0]); });
			nav.appendChild(b); tbtn[t[0]] = b;
			var p = el('div'); p.hidden = true; panes[t[0]] = p;
			bucket[t[0]].forEach(function (n) { p.appendChild(n); });
		});

		/* Lähetyspaneeli: aina ylhäällä, erillään välilehdistä */
		var qp = el('div', { 'class': 'st-air st-top' });
		qp.innerHTML = '<div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap"><b style="font-size:16px;letter-spacing:.04em">🎛️ LÄHETYS</b><span data-qs style="font-size:12px;opacity:.6"></span><button type="button" data-qr style="margin-left:auto">↻ Päivitä</button></div><div data-qb style="margin-top:6px">Ladataan…</div>';
		var qb = qp.querySelector('[data-qb]'), qs = qp.querySelector('[data-qs]'), qbusy = false;
		function hm(t) { var d = new Date(t * 1000); return ('0' + d.getHours()).slice(-2) + ':' + ('0' + d.getMinutes()).slice(-2) + ':' + ('0' + d.getSeconds()).slice(-2); }
		function qrow(it, isCur, srvNow) {
			var dj = it.type === 'dj', ad = it.type === 'ad';
			var r = el('div', { style: 'display:flex;gap:10px;align-items:flex-start;padding:' + (isCur ? '4px 0 10px' : '8px 0') + ';' + (isCur ? '' : 'border-top:1px solid #2a303b;') + 'border-left:3px solid ' + (dj ? '#5eead4' : (ad ? '#f87171' : '#ffb347')) + ';padding-left:10px' });
			var chip = '<span class="st-chip ' + (dj ? 'dj' : 'tr') + '">' + (dj ? 'Juonto' : (ad ? 'Mainos' : 'Kappale')) + '</span>';
			var left = isCur ? el('div', { 'class': 'st-eq go' }, '<i></i><i></i><i></i><i></i><i></i>') : el('div', { style: 'min-width:64px;font:13px ui-monospace,Menlo,Consolas,monospace;color:#ffb347;padding-top:2px' }, hm(it.start));
			var title = dj ? (it.shout ? 'Tervehdys' : 'Juonto') : esc(it.title) + (it.artist ? ' <span style="opacity:.7;font-weight:400">– ' + esc(it.artist) + '</span>' : '');
			var tx = chip + '<b style="' + (isCur ? 'font-size:19px' : '') + '">' + title + '</b>' + (it.sub ? ' <span style="font-size:11px;opacity:.7">(yhteisö)</span>' : '');
			if (dj) { tx += '<div style="font-size:13px;opacity:.85;white-space:pre-wrap;margin-top:3px">' + esc(it.text || '(ei tekstiä)') + '</div>'; }
			var rem = Math.max(0, Math.round(it.start + it.dur - srvNow)), pct = isCur ? Math.min(100, Math.max(0, (srvNow - it.start) / it.dur * 100)) : 0;
			tx += '<div style="font-size:11px;opacity:.6;margin-top:2px">' + Math.round(it.dur) + ' s' + (isCur ? ' · jäljellä ' + Math.floor(rem / 60) + ':' + ('0' + (rem % 60)).slice(-2) : '') + '</div>';
			if (isCur) { tx += '<div class="st-prog"><i style="width:' + pct + '%"></i></div>'; }
			var mid = el('div', { style: 'flex:1;min-width:0' }, tx);
			var x = el('button', { type: 'button', style: 'flex:none' }, isCur ? '⏭ Ohita' : '✕ Ota pois');
			x.addEventListener('click', function () {
				if (isCur && !confirm('Ohitetaanko soiva? Kuuntelijoilla soitin siirtyy seuraavaan muutaman sekunnin kuluessa.')) { return; }
				x.disabled = true;
				api('POST', D.urls.queuecut, { key: it.key }).then(function () { qload(); }).catch(function (e) { qs.textContent = e.message; qload(); });
			});
			r.appendChild(left); r.appendChild(mid); r.appendChild(x);
			return r;
		}
		function setAir(on, live) {
			var a = document.querySelector('[data-sb-air]'), l = document.querySelector('[data-sb-live]');
			if (a) { a.className = 'onair' + (on ? ' on' : ''); }
			if (l && live != null) { l.textContent = live; }
		}
		function qload(build) {
			if (qbusy || !qp.isConnected) { return; }
			qbusy = true;
			api('GET', D.urls.queue + (build === true ? (D.urls.queue.indexOf('?') < 0 ? '?' : '&') + 'build=1' : '')).then(function (j) {
				qbusy = false;
				qb.innerHTML = '';
				if (!j.on) { qb.textContent = 'Radio ei ole päällä.'; setAir(false, j.live); return; }
				setAir(true, j.live);
				if (j.cur) { qb.appendChild(qrow(j.cur, true, j.now)); } else { qb.appendChild(el('div', { style: 'opacity:.7;padding:6px 0' }, 'Ei soivaa kohdetta juuri nyt.')); }
				if (!j.next.length) {
					if (!j.live) {
						var w0 = el('div', { style: 'opacity:.85;padding:6px 0' }, 'Radio odottaa kuuntelijaa: ohjelma ja juontopuheet rakennetaan vasta, kun joku avaa radion.');
						var bb = el('button', { type: 'button', style: 'margin-left:8px' }, 'Rakenna esikatselu (kuluttaa tekoälyä)');
						bb.addEventListener('click', function () { bb.disabled = true; qload(true); });
						w0.appendChild(bb); qb.appendChild(w0);
					} else { qb.appendChild(el('div', { style: 'opacity:.7;padding:6px 0' }, 'Seuraavia ei ole vielä jonossa.')); }
				}
				j.next.forEach(function (it) { qb.appendChild(qrow(it, false, j.now)); });
				qs.textContent = 'päivitetty ' + hm(j.now);
			}).catch(function (e) { qbusy = false; qs.textContent = e.message; });
		}
		qp.querySelector('[data-qr]').addEventListener('click', function () { qload(); });
		if (window.__suorniQT) { clearInterval(window.__suorniQT); }
		window.__suorniQT = setInterval(function () { if (!qp.isConnected) { clearInterval(window.__suorniQT); return; } if (!document.hidden) { qload(); } }, 10000);

		/* Studion postilaatikko: chat, terveiset, toiveet ja uudet artistit aina näkyvillä */
		var ib = el('div', { 'class': 'st-air st-ib' });
		var ibTab = 'chat', ibSeen = 0, ibBody = null, ibHead = null;
		function ibCounts() {
			var chatN = 0;
			if (ibTab !== 'chat' && ibSeen) { var idx = -1; (D.chat || []).forEach(function (m, i) { if (m.id === ibSeen && idx < 0) { idx = i; } }); var lim = idx < 0 ? (D.chat || []).length : idx; for (var i = 0; i < lim; i++) { if (!D.chat[i].dj) { chatN++; } } }
			var gN = (D.greets || []).filter(function (g) { return ['ok', 'no', 'played'].indexOf(g.status) < 0; }).length;
			var rN = (D.reqs || []).filter(function (x) { return x.st === 'pending'; }).length;
			var sN = D.subs_pending != null ? D.subs_pending : (D.subs || []).filter(function (x) { return x.status === 'pending'; }).length;
			return { chat: chatN, greets: gN, reqs: rN, subs: sN };
		}
		function ibTabBtn(k, label, n) {
			var b = el('button', { type: 'button', 'class': ibTab === k ? 'on' : '' }, label + (n ? ' <b class="bdg">' + n + '</b>' : ''));
			b.addEventListener('click', function () { ibTab = k; if (k === 'chat' && D.chat && D.chat.length) { ibSeen = D.chat[0].id; } drawInbox(); });
			return b;
		}
		function ibRow(html, btns) {
			var r = el('div', { 'class': 'ib-row' });
			r.appendChild(el('div', { style: 'flex:1;min-width:0;word-break:break-word' }, html));
			btns.forEach(function (b) { r.appendChild(b); });
			return r;
		}
		function ibBtn(label, fn) { var b = el('button', { type: 'button' }, label); b.addEventListener('click', function () { b.disabled = true; fn(b); }); return b; }
		/* Vastauspalkki: pysyy samana elementtinä, jotta kirjoitus ei katoa päivityksissä */
		var ibRep = { id: '', name: '' };
		var rbar = el('div', { 'class': 'ib-reply', style: 'margin-top:8px;padding:8px;border:1px solid #363d4a;border-radius:10px;background:#10141b' });
		rbar.innerHTML = '<div data-rt style="font-size:12px;opacity:.75;margin-bottom:4px">Vastaa chattiin juontajana</div><div style="display:flex;gap:6px"><input data-ri maxlength="220" placeholder="Kirjoita vastaus…" style="flex:1;min-width:0;padding:8px;border-radius:8px;border:1px solid #363d4a;background:#1b1f27;color:#eef0f4;font:inherit;font-size:16px"><button type="button" data-rs>Lähetä</button></div><label style="display:flex;gap:6px;align-items:center;font-size:12px;margin-top:4px;cursor:pointer"><input type="checkbox" data-ra> Lue vastaus myös radiossa</label><div data-rm style="font-size:12px;margin-top:2px"></div>';
		var rI = rbar.querySelector('[data-ri]'), rT = rbar.querySelector('[data-rt]'), rM = rbar.querySelector('[data-rm]'), rA = rbar.querySelector('[data-ra]'), rS = rbar.querySelector('[data-rs]');
		function repTarget() { rT.textContent = ibRep.id ? '↩️ Vastaus käyttäjälle ' + ibRep.name + ' (peruuta: tyhjennä valinta)' : 'Vastaa chattiin juontajana'; }
		rT.addEventListener('click', function () { if (ibRep.id) { ibRep = { id: '', name: '' }; repTarget(); } });
		function sendRep() {
			var t = rI.value.trim(); if (!t) { return; }
			rS.disabled = true; rM.textContent = '';
			api('POST', D.urls.chatreply, { id: ibRep.id, text: t, air: rA.checked }).then(function (r) {
				D.chat = r.chat; rI.value = ''; ibRep = { id: '', name: '' }; repTarget(); rM.textContent = r.air ? '✓ Lähetetty ja menossa radioon' : '✓ Lähetetty chattiin'; rS.disabled = false;
				drawInbox(); if (redraw.c) { redraw.c(); }
			}).catch(function (e) { rS.disabled = false; rM.textContent = e.message; });
		}
		rS.addEventListener('click', sendRep);
		rI.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); sendRep(); } });
		function drawInbox() {
			if (ibTab === 'chat' && ib.contains(rbar) && rbar.contains(document.activeElement)) { return; }
			if (ibTab === 'chat' && D.chat && D.chat.length) { ibSeen = D.chat[0].id; }
			var c = ibCounts();
			ib.innerHTML = '';
			ibHead = el('div', { 'class': 'ib-tabs' });
			ibHead.appendChild(ibTabBtn('chat', '💬 Chat', c.chat));
			ibHead.appendChild(ibTabBtn('greets', '✉️ Terveiset', c.greets));
			ibHead.appendChild(ibTabBtn('reqs', '🎁 Toiveet', c.reqs));
			ibHead.appendChild(ibTabBtn('subs', '🎵 Artistit', c.subs));
			ib.appendChild(ibHead);
			ibBody = el('div', { 'class': 'ib-body' });
			if (ibTab === 'chat') {
				var L = (D.chat || []).slice(0, 14);
				if (!L.length) { ibBody.appendChild(el('div', { style: 'opacity:.7;padding:8px 0' }, 'Ei viestejä vielä.')); }
				L.forEach(function (m) {
					var t = (m.at || m.t) ? new Date((m.at || m.t) * 1000).toLocaleTimeString('fi-FI', { hour: '2-digit', minute: '2-digit' }) : '';
					var btns = [];
					if (!m.dj) {
						btns.push(ibBtn('📢', function (b) { api('POST', U.chatair, { id: m.id }).then(function () { b.textContent = '✓'; }).catch(function (e) { b.disabled = false; alert(e.message); }); }));
						btns[0].title = 'Lue radiossa';
						var rp = ibBtn('↩️', function (b) { b.disabled = false; ibRep = { id: m.id, name: m.n }; repTarget(); rI.focus(); }); rp.title = 'Vastaa'; btns.push(rp);
					}
					var d = ibBtn('✕', function () { api('POST', U.chatdel, { id: m.id }).then(function (r2) { D.chat = r2.chat; drawInbox(); if (redraw.c) { redraw.c(); } }).catch(function (e) { alert(e.message); }); }); d.title = 'Poista'; btns.push(d);
					ibBody.appendChild(ibRow('<span style="opacity:.55;font-size:12px">' + t + '</span> <b style="color:' + (m.dj ? '#5eead4' : (m.b ? '#8a93a3' : '#ffb347')) + '">' + (m.dj ? '🎙️ ' : '') + (m.b ? '🤖 ' : '') + esc(m.n) + '</b> ' + esc(m.m), btns));
				});
				ibBody.appendChild(rbar); repTarget();
			} else if (ibTab === 'greets') {
				var G = (D.greets || []).filter(function (g) { return ['ok', 'no', 'played'].indexOf(g.status) < 0; }).slice(-10).reverse();
				if (!G.length) { ibBody.appendChild(el('div', { style: 'opacity:.7;padding:8px 0' }, 'Ei uusia terveisiä.')); }
				G.forEach(function (g) {
					function ga(a) { return function () { api('POST', U.greets, { id: g.id, act: a }).then(function (j) { D.greets = j.greets; drawInbox(); if (redraw.g) { redraw.g(); } }).catch(function (e) { alert(e.message); }); }; }
					ibBody.appendChild(ibRow('<b>' + esc(g.name) + '</b> ' + esc(g.msg), [ibBtn('Lue heti', ga('now')), ibBtn('✓', ga('ok')), ibBtn('✕', ga('no'))]));
				});
			} else if (ibTab === 'reqs') {
				var R = (D.reqs || []).filter(function (x) { return x.st === 'pending' || x.st === 'ok'; }).slice(0, 10);
				if (!R.length) { ibBody.appendChild(el('div', { style: 'opacity:.7;padding:8px 0' }, 'Ei toiveita jonossa.')); }
				R.forEach(function (x) {
					function ra(a) { return function () { api('POST', D.urls.reqact, { n: x.n, act: a }).then(function (j) { D.reqs = j.list; drawInbox(); if (window.__suorniRq) { window.__suorniRq(); } }).catch(function (e) { alert(e.message); }); }; }
					var btns = x.st === 'pending' ? [ibBtn('✓', ra('ok')), ibBtn('✕', ra('no'))] : [ibBtn('✕', ra('no'))];
					ibBody.appendChild(ibRow('<b>' + esc(x.song) + '</b> <span style="opacity:.7;font-size:12px">' + esc(x.name) + ' · ' + (x.st === 'ok' ? '✅ jonossa' : '⏳ odottaa') + '</span>', btns));
				});
			} else {
				if (!c.subs) { ibBody.appendChild(el('div', { style: 'opacity:.7;padding:8px 0' }, 'Ei uusia kappaleita odottamassa.')); }
				else {
					ibBody.appendChild(el('div', { style: 'padding:6px 0' }, '<b>' + c.subs + '</b> uutta kappaletta odottaa hyväksyntääsi.'));
					var go = el('button', { type: 'button', 'class': 'pri' }, 'Avaa Uudet artistit'); go.addEventListener('click', function () { show('yhteiso'); window.scrollTo({ top: 0, behavior: 'smooth' }); }); ibBody.appendChild(go);
				}
			}
			ib.appendChild(ibBody);
			var parts = []; if (c.chat) { parts.push('💬 ' + c.chat); } if (c.greets) { parts.push('✉️ ' + c.greets); } if (c.reqs) { parts.push('🎁 ' + c.reqs); } if (c.subs) { parts.push('🎵 ' + c.subs); }
			var lamp = document.querySelector('[data-sb-inbox]');
			if (lamp) {
				lamp.hidden = !parts.length; lamp.textContent = parts.length ? '📬 ' + parts.join('  ') : '';
				lamp.onclick = function () { ibTab = c.chat ? 'chat' : (c.greets ? 'greets' : (c.reqs ? 'reqs' : 'subs')); drawInbox(); window.scrollTo({ top: 0, behavior: 'smooth' }); };
			}
		}
		redraw.ib = drawInbox;
		drawInbox();
		var tg = el('div', { 'class': 'st-top st-topgrid' }); tg.appendChild(qp); tg.appendChild(ib);
		box.appendChild(tg);
		qload();
		box.appendChild(nav);
		TABS.forEach(function (t) { box.appendChild(panes[t[0]]); });
		function show(k) {
			TABS.forEach(function (t) { panes[t[0]].hidden = t[0] !== k; tbtn[t[0]].className = t[0] === k ? 'on' : ''; });
			try { sessionStorage.setItem('suorni_tab', k); } catch (e) {}
		}
		gotoTab = show;
		var cur = 'tanaan';
		try { cur = sessionStorage.getItem('suorni_tab') || cur; } catch (e) {}
		show(panes[cur] ? cur : 'tanaan');
		var bar = el('div', { 'class': 'st-main-bar', style: 'display:flex;gap:10px;align-items:center;flex-wrap:wrap' });
		bar.appendChild(save); bar.appendChild(msg);
		box.appendChild(bar);
		faders(box);
		if (window.__suorniFO) { window.__suorniFO.disconnect(); }
		window.__suorniFO = new MutationObserver(function () { faders(box); });
		window.__suorniFO.observe(box, { childList: true, subtree: true });
	}
})();

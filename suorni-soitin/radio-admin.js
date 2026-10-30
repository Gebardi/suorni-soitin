(function () {
	'use strict';
	var box = document.querySelector('[data-radio-box]');
	if (!box) { return; }
	var det = box.closest('details');
	var U = { admin: box.getAttribute('data-admin'), cfg: box.getAttribute('data-cfg'), greets: box.getAttribute('data-greets'), info: box.getAttribute('data-info'), ai: box.getAttribute('data-ai'), tracks: box.getAttribute('data-tracks'), aitest: box.getAttribute('data-aitest'), feedtest: box.getAttribute('data-feedtest'), chatdel: box.getAttribute('data-chatdel'), chatair: box.getAttribute('data-chatair'), chatlist: box.getAttribute('data-chatlist') };
	var redraw = { c: null, g: null };
	setInterval(function () {
		if (!loaded || document.hidden || !U.chatlist) { return; }
		api('GET', q(U.chatlist)).then(function (r) { if (r.chat) { D.chat = r.chat; } if (r.greets) { D.greets = r.greets; } if (redraw.c) { redraw.c(); } if (redraw.g) { redraw.g(); } }).catch(function () {});
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
		function tile(v, l) { return '<div class="st-tile"><b>' + v + '</b><span>' + l + '</span></div>'; }
		sd.innerHTML = '<h3>Yleiskatsaus</h3><div class="st-tiles">' + tile(s.live, 'Kuuntelijaa nyt') + tile(s.week_sum, 'Kuuntelijaa viikolla') + tile(s.peak_hour >= 0 ? s.peak_hour + ':00' : '–', 'Vilkkain tunti tänään') + tile(D.usable, 'Soitettavaa kappaletta') + tile(pendS + pendG + pendR, 'Odottaa käsittelyä') + '</div>' +
			'<h3>Kuuntelijat viikolla</h3>' + '<table style="width:100%;font-size:14px;border-collapse:collapse"><tr style="text-align:left"><th>Päivä</th><th>Kuuntelijoita</th><th>Kuunteluaika</th><th>Keskim.</th></tr>' + rows + '</table>' +
			'<p style="font-size:13px;opacity:.7">Tilastot ovat anonyymejä (ei evästeitä, ei tunnistetietoja). Sama kuuntelija lasketaan kerran päivässä.</p>';
		box.appendChild(sd);

		/* Asetukset */
		var h = el('div');
		var hA = el('div');
		hA.innerHTML = '<h3 style="margin:14px 0 6px">Kanava</h3>' +
			'<label><input type="checkbox" data-on' + (c.on ? ' checked' : '') + '> Radio on käynnissä</label><br>' +
			'<input style="' + st + '" data-name maxlength="60" placeholder="Kanavan nimi" value="' + esc(c.name) + '">' +
			'<input style="' + st + '" data-cover placeholder="Oma kansikuva (kuvan osoite, tyhjä = oletus Suomi-maisema)" value="' + esc(c.cover) + '">' +
			'<label><input type="checkbox" data-sc' + (c.showcount ? ' checked' : '') + '> Näytä kuuntelijamäärä sivulla</label><br><label><input type="checkbox" data-wxs' + (c.wx_show ? ' checked' : '') + '> Näytä sää sivulla (paikka ja koordinaatit: Juontaja-välilehti)</label>';
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
					'<p style="margin:6px 0 2px">Juontajan ääni (suomi)</p><select style="' + st + '" data-f="vfi">' + vopt(['', 'fi-FI-NooraNeural', 'fi-FI-SelmaNeural', 'fi-FI-HarriNeural'], p.vfi, ['Oletus (yhteinen)', 'Noora (nainen)', 'Selma (nainen)', 'Harri (mies)']) + '</select>' +
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
			api('POST', U.cfg, { on: box.querySelector('[data-on]').checked, name: box.querySelector('[data-name]').value, cover: box.querySelector('[data-cover]').value, greet_mode: box.querySelector('[data-gm]').value, greet_voices: box.querySelector('[data-gv]').checked, chat: box.querySelector('[data-chon]').checked, chat_ai: box.querySelector('[data-chai]').checked, chat_air: box.querySelector('[data-chair]').value, guests: box.querySelector('[data-gon]').checked, comm: box.querySelector('[data-cmon]').checked, comm_share: +box.querySelector('[data-cmsh]').value, comm_min_votes: +box.querySelector('[data-cmmv]').value || 10, comm_min_pct: +box.querySelector('[data-cmmp]').value || 40, comm_max_mb: +box.querySelector('[data-cmmb]').value || 15, guests_every: +box.querySelector('[data-gev]').value || 40, guests_off: +box.querySelector('[data-goff]').value || 0, guests_reply: +box.querySelector('[data-grp]').value, guests_max: +box.querySelector('[data-gmax]').value || 60, guests_list: box.querySelector('[data-glist]').value, chat_block: box.querySelector('[data-chbl]').value, chat_max: +box.querySelector('[data-chmx]').value || 60, news_feed: box.querySelector('[data-nf]').value, sport_feed: box.querySelector('[data-sf]').value, finns: box.querySelector('[data-fin]').value, dj_time: box.querySelector('[data-djt]').checked, dj_weather: box.querySelector('[data-djw]').checked, dj_where: box.querySelector('[data-djwh]').value, dj_lat: +box.querySelector('[data-djla]').value, dj_lon: +box.querySelector('[data-djlo]').value, dj_next: +box.querySelector('[data-djn]').value, dj_titles: box.querySelector('[data-djti]').value, dj_rate: +box.querySelector('[data-djr]').value || 1, greet_limit: +box.querySelector('[data-gl]').value || 0, showcount: box.querySelector('[data-sc]').checked, wx_show: box.querySelector('[data-wxs]').checked, new_default: box.querySelector('[data-nd]').value, boost_on: box.querySelector('[data-bon]').checked, boost_days: +box.querySelector('[data-bdy]').value || 7, boost_share: +box.querySelector('[data-bsh]').value, tips: box.querySelector('[data-tips]').value, programs: progs })
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
		bw.innerHTML = '<h3 style="margin:14px 0 6px">⚡ Tehosoitto</h3><p style="font-size:13px;opacity:.7;margin:0">Uusi kappale lisätään heti radion soittolistalle, ja se soi seuraavaksi (omien ja yhteisön uusien kappaleiden jälkeen). Sen jälkeen se soi muita useammin määräajan. Voit myös pakottaa tehosoiton päälle tai pois yksittäiselle kappaleelle.</p>' +
			'<label><input type="checkbox" data-bon' + (c.boost_on ? ' checked' : '') + '> Uudet kappaleet saavat tehosoiton automaattisesti</label>' +
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
				var isNew = c.boost_on && t.at && (Date.now() / 1000 - t.at) < c.boost_days * 86400;
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
		var TABS = [['yleinen', '🏠 Yleiskatsaus'], ['ohjelmat', '🗓️ Ohjelmat'], ['yleiso', '💬 Yleisö'], ['juontaja', '🎙️ Juontaja'], ['yhteiso', '🎵 Uudet artistit'], ['lista', '📀 Soittolista']];

		/* Massalisäys + päivittäinen tarkistus */
		var upw = el('div', { style: 'margin:6px 0 14px;padding:10px;border:1px solid #363d4a;border-radius:12px;background:#151922' });
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
				function act(a, label) { var b = el('button', { type: 'button' }, label); b.addEventListener('click', function () { b.disabled = true; api('POST', D.urls.reqact, { n: x.n, act: a }).then(function (j) { D.reqs = j.list; drawReq(); }).catch(function (e) { alert(e.message); }); }); r.appendChild(b); }
				if (x.st === 'pending') { act('ok', 'Hyväksy'); }
				if (x.st === 'pending' || x.st === 'ok') { act('no', 'Hylkää'); }
				lst.appendChild(r);
			});
			rqw.appendChild(lst);
		}
		drawReq();
		var bucket = { yleinen: [info, sd, mw, hA], ohjelmat: [pw, quick, add], yleiso: [hB, gw, cw, rqw], juontaja: [hC, aw, fw], yhteiso: [hD, scw], lista: [upw, bw, tw] };
		var used = [];
		Object.keys(bucket).forEach(function (k) { bucket[k].forEach(function (n) { used.push(n); }); });
		loose.forEach(function (n) { if (used.indexOf(n) < 0 && n !== save && n !== msg) { bucket.yleinen.unshift(n); } });
		box.innerHTML = '';
		var nav = el('div', { 'class': 'st-nav' });
		var panes = {}, tbtn = {};
		var npend = (D.subs || []).filter(function (x) { return x.status === 'pending'; }).length;
		var gpend = (D.greets || []).filter(function (g) { return ['ok', 'no', 'played'].indexOf(g.status) < 0; }).length;
		var badge = { yhteiso: npend, yleiso: gpend };
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
			var dj = it.type === 'dj';
			var r = el('div', { style: 'display:flex;gap:10px;align-items:flex-start;padding:' + (isCur ? '4px 0 10px' : '8px 0') + ';' + (isCur ? '' : 'border-top:1px solid #2a303b') });
			var chip = '<span class="st-chip ' + (dj ? 'dj' : 'tr') + '">' + (dj ? 'Juonto' : 'Kappale') + '</span>';
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
		function qload() {
			if (qbusy || !qp.isConnected) { return; }
			qbusy = true;
			api('GET', D.urls.queue).then(function (j) {
				qbusy = false;
				qb.innerHTML = '';
				if (!j.on) { qb.textContent = 'Radio ei ole päällä.'; setAir(false, j.live); return; }
				setAir(true, j.live);
				if (j.cur) { qb.appendChild(qrow(j.cur, true, j.now)); } else { qb.appendChild(el('div', { style: 'opacity:.7;padding:6px 0' }, 'Ei soivaa kohdetta juuri nyt.')); }
				if (!j.next.length) { qb.appendChild(el('div', { style: 'opacity:.7;padding:6px 0' }, 'Seuraavia ei ole vielä jonossa.')); }
				j.next.forEach(function (it) { qb.appendChild(qrow(it, false, j.now)); });
				qs.textContent = 'päivitetty ' + hm(j.now);
			}).catch(function (e) { qbusy = false; qs.textContent = e.message; });
		}
		qp.querySelector('[data-qr]').addEventListener('click', qload);
		if (window.__suorniQT) { clearInterval(window.__suorniQT); }
		window.__suorniQT = setInterval(function () { if (!qp.isConnected) { clearInterval(window.__suorniQT); return; } if (!document.hidden) { qload(); } }, 10000);
		box.appendChild(qp);
		qload();
		box.appendChild(nav);
		TABS.forEach(function (t) { box.appendChild(panes[t[0]]); });
		function show(k) {
			TABS.forEach(function (t) { panes[t[0]].hidden = t[0] !== k; tbtn[t[0]].className = t[0] === k ? 'on' : ''; });
			try { sessionStorage.setItem('suorni_tab', k); } catch (e) {}
		}
		var cur = 'yleinen';
		try { cur = sessionStorage.getItem('suorni_tab') || cur; } catch (e) {}
		show(panes[cur] ? cur : 'yleinen');
		var bar = el('div', { 'class': 'st-main-bar', style: 'display:flex;gap:10px;align-items:center;flex-wrap:wrap' });
		bar.appendChild(save); bar.appendChild(msg);
		box.appendChild(bar);
	}
})();

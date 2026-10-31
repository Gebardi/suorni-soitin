(function () {
	'use strict';
	var root = document.querySelector('.sr');
	if (!root) { return; }
	var $ = function (s) { return root.querySelector(s); };
	var U = { now: root.getAttribute('data-now'), greet: root.getAttribute('data-greet') };
	var shopUrl = root.getAttribute('data-shop') || '';
	var au = new Audio();
	try { au.referrerPolicy = 'no-referrer'; } catch (e) {}
	au.preload = 'auto';
	var fixes = 0, lastFix = 0, loadTok = 0, cid = Math.random().toString(36).slice(2, 12), hasOff = false, warm = (function () { var w = new Audio(); try { w.referrerPolicy = 'no-referrer'; } catch (e) {} return w; })(), warmUrl = '', items = [], off = 0, playing = false, curKey = '', lastPing = 0, lastFetch = 0, busy = false;
	var elPlay = $('[data-play]'), elTitle = $('[data-title]'), elArtist = $('[data-artist]'), elNext = $('[data-next]');
	var elCap = $('[data-cap]'), elCount = $('[data-count]'), elProg = $('[data-prog]');

	/* Nimetön tulolähde: ?src=tiktok / ?utm_source=... tai viittaava sivusto. Muistetaan istunnon ajan. */
	var srcLbl = (function () {
		try { var z = sessionStorage.getItem('suorni_src'); if (z) { return z; } } catch (e) {}
		var v = '';
		try { var pp = new URLSearchParams(location.search); v = (pp.get('src') || pp.get('utm_source') || '').toLowerCase().replace(/[^a-z0-9_-]/g, '').slice(0, 20); } catch (e) {}
		if (!v) {
			var hh = ''; try { hh = document.referrer ? new URL(document.referrer).hostname.toLowerCase() : ''; } catch (e) {}
			if (!hh) { v = 'suora'; }
			else if (hh === location.hostname) { v = ''; }
			else if (/tiktok\./.test(hh)) { v = 'tiktok'; }
			else if (/instagram\./.test(hh)) { v = 'instagram'; }
			else if (/(^|\.)(facebook|fb)\.|^l\.messenger/.test(hh)) { v = 'facebook'; }
			else if (/youtube\.|youtu\.be/.test(hh)) { v = 'youtube'; }
			else if (/google\.|bing\.|duckduckgo\./.test(hh)) { v = 'haku'; }
			else if (/(^|\.)(t\.co|twitter\.com|x\.com)$/.test(hh)) { v = 'x'; }
			else { v = 'muu'; }
		}
		if (v) { try { sessionStorage.setItem('suorni_src', v); } catch (e) {} }
		return v;
	})();
	function q(u, p) { return u + (u.indexOf('?') < 0 ? '?' : '&') + p; }
	/* lag = myymälän mainoksen ajaksi kertynyt viive (s): myymälän kuuntelija on lähetyksestä jäljessä, kunnes seuraava juonto palauttaa hänet reaaliaikaan */
	var lag = 0, shopAdHold = false, shopSeen = '';
	function srv() { return Date.now() / 1000 + off - lag; }

	var shift = null;
	function fetchNow(listen) {
		if (busy) { return; }
		busy = true;
		var t0 = Date.now();
		fetch(q(U.now, 'r=' + t0 + (listen ? '&l=1&cid=' + cid + (srcLbl ? '&src=' + encodeURIComponent(srcLbl) : '') + (shopUrl ? '&shop=1' : '') : '') + (lag > 0 ? '&back=' + Math.ceil(lag + 5) : '')), { cache: 'no-store' })
			.then(function (r) { return r.json(); })
			.then(function (j) {
				busy = false;
				lastFetch = Date.now();
				if (j.now) { var no = j.now - (t0 + (Date.now() - t0) / 2) / 1000; if (!hasOff || Math.abs(no - off) > 1.5) { off = no; hasOff = true; } else { off += (no - off) * 0.2; } }
				if (j.off) { elTitle.textContent = 'Radio on tauolla'; elArtist.textContent = ''; items = []; return; }
				items = j.items || [];
				comm = j.comm || null;
				tv = j.tv || {};
				shift = j.shift || null;
				if (j.chat) { drawChat(j.chat); setTyping(j.typing || ''); if (j.typing) { fastPoll(); } var gn = chatEl && chatEl.querySelector('[data-gnote]'); if (gn) { gn.hidden = !j.guests; } }
				if (j.name) { var h = root.querySelector('h2'); if (h) { h.textContent = j.name; } }
				if (typeof j.listeners === 'number') { elCount.textContent = j.listeners > 0 ? '👥 ' + j.listeners + (j.listeners === 1 ? ' kuuntelija nyt' : ' kuuntelijaa nyt') : ''; }
				if (j.idle) { elTitle.textContent = 'Paina play, niin radio käynnistyy'; elArtist.textContent = ''; }
				if (j.empty) { elTitle.textContent = 'Lähetys alkaa pian'; elArtist.textContent = 'Radiolla ei ole vielä soitettavia kappaleita'; }
				render();
				if (isApp && !autoTried && items.length) { autoTried = true; if (!playing) { elPlay.click(); } }
			})
			.catch(function () { busy = false; });
	}
	var wxEl = $('[data-wxbox]');
	function drawWx() {
		if (!wxEl) { return; }
		fetch(q(root.getAttribute('data-wx'), 'r=' + Date.now()), { cache: 'no-store' }).then(function (r) { return r.json(); }).then(function (j) {
			var w = j && j.wx;
			if (!w) { wxEl.hidden = true; return; }
			var sg = function (n) { return (n > 0 ? '+' : '') + n; };
			var parts = ['<span><b>' + w.ic + ' ' + sg(w.t) + ' °C</b></span>'];
			if (w.txt) { parts.push('<span>' + w.txt + '</span>'); }
			if (w.w !== null) { parts.push('<span>💨 ' + w.w + ' m/s' + (w.wd ? ' ' + w.wd : '') + (w.g !== null && w.g > w.w + 2 ? ', puuskat ' + w.g + ' m/s' : '') + '</span>'); }
			if (w.fl !== null && Math.abs(w.fl - w.t) >= 2) { parts.push('<span>tuntuu ' + sg(w.fl) + ' °C</span>'); }
			if (w.h !== null) { parts.push('<span>💧 ' + w.h + ' %</span>'); }
			if (w.p) { parts.push('<span>🌧 ' + w.p + ' mm</span>'); }
			if (w.hi !== null) { parts.push('<span>ylin ' + sg(w.hi) + ' / alin ' + sg(w.lo) + ' °C</span>'); }
			wxEl.innerHTML = '<div style="opacity:.7;font-size:11px;letter-spacing:1px">SÄÄ · ' + String(w.place || '').toUpperCase().replace(/[<>&]/g, '') + ' · klo ' + w.at + '</div>' + parts.join('');
			wxEl.hidden = false;
		}).catch(function () {});
	}
	drawWx(); setInterval(drawWx, 600000);
	var comm = null, vbox = $('[data-votebox]'), mine = {};
	try { mine = JSON.parse(localStorage.getItem('suorni_votes') || '{}') || {}; } catch (e) {}
	var tv = {};
	function drawComm(sub, tid) {
		if (!vbox) { return; }
		var cv = comm && comm.v ? comm.v : {};
		var key = sub ? 's' + sub : (tid ? 't' + tid : ''), d = sub ? cv[sub] : (tid ? (tv[tid] || { u: 0, d: 0 }) : null);
		if (key && d) {
			vbox.hidden = false; vbox.setAttribute('data-key', key);
			var lb = $('[data-vlabel]'); if (lb) { lb.textContent = sub ? '🎵 Uuden artistin kappale' : 'Pidätkö tästä kappaleesta?'; }
			$('[data-vu]').textContent = d.u; $('[data-vd]').textContent = d.d;
			$('[data-vup]').className = 'rg' + (mine[key] === 1 ? ' on' : '');
			$('[data-vdn]').className = 'rg' + (mine[key] === -1 ? ' on' : '');
		} else { vbox.hidden = true; vbox.removeAttribute('data-key'); }
		var cq = $('[data-cq]');
		if (cq) {
			var qq = comm && comm.q ? comm.q : [];
			cq.hidden = !qq.length;
			var lst = cq.querySelector('[data-cqlist]'); lst.innerHTML = '';
			qq.forEach(function (x) {
				var d = document.createElement('div'); d.className = 'cqr';
				var a = document.createElement('span'); a.textContent = x.a + ' – ' + x.t;
				var b = document.createElement('span'); b.textContent = '👍 ' + x.u + '  👎 ' + x.d;
				d.appendChild(a); d.appendChild(b); lst.appendChild(d);
			});
		}
	}
	if (vbox) {
		var vote = function (v) {
			var key = vbox.getAttribute('data-key') || '';
			if (!key) { return; }
			var kind = key.charAt(0), id = +key.slice(1), vs = $('[data-vst]');
			fetch(root.getAttribute('data-vote'), { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ id: id, v: v, k: kind }) })
				.then(function (r) { return r.json().then(function (x) { return { ok: r.ok, x: x }; }); })
				.then(function (o) {
					var x = o.x;
					if (!o.ok || !x || !x.ok) { if (vs) { vs.textContent = (x && x.message) || 'Äänestys ei onnistunut.'; } return; }
					if (vs) { vs.textContent = ''; }
					if (kind === 's') { if (!comm) { comm = {}; } if (!comm.v) { comm.v = {}; } comm.v[id] = { u: x.u, d: x.d }; } else { tv[id] = { u: x.u, d: x.d }; }
					if (x.mine) { mine[key] = x.mine; } else { delete mine[key]; }
					try { localStorage.setItem('suorni_votes', JSON.stringify(mine)); } catch (e) {}
					drawComm(kind === 's' ? id : 0, kind === 't' ? id : 0);
				}).catch(function () { if (vs) { vs.textContent = 'Äänestys ei onnistunut. Yritä uudelleen.'; } });
		};
		$('[data-vup]').addEventListener('click', function () { vote(1); });
		$('[data-vdn]').addEventListener('click', function () { vote(-1); });
		$('[data-vchat]').addEventListener('click', function () {
			var ci = root.querySelector('[data-cmsg]');
			if (!ci) { return; }
			var c = current(), t = c && c.it.type === 'track' ? c.it.title : '';
			if (t && ci.value.indexOf('🎵') !== 0) { ci.value = '🎵 ' + t + ': ' + ci.value; }
			try { ci.scrollIntoView({ block: 'center', behavior: 'smooth' }); } catch (e) {}
			ci.focus();
		});
	}
	var ss = $('[data-ssend]');
	if (ss) {
		ss.addEventListener('click', function () {
			var st = $('[data-sst]'), fl = $('[data-sfile]').files[0];
			if (!fl) { st.textContent = 'Valitse mp3-tiedosto.'; return; }
			var fd = new FormData();
			fd.append('artist', $('[data-sartist]').value); fd.append('title', $('[data-stitle]').value); fd.append('email', $('[data-semail]').value);
			fd.append('rights', $('[data-srights]').checked ? '1' : ''); fd.append('hp', $('[data-shp]').value); fd.append('file', fl);
			ss.disabled = true; st.textContent = 'Lähetetään… (iso tiedosto voi kestää hetken)';
			fetch(root.getAttribute('data-submit'), { method: 'POST', body: fd })
				.then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
				.then(function (x) {
					ss.disabled = false;
					if (x.ok) { st.textContent = 'Kiitos! Kappaleesi odottaa ylläpidon kuuntelua.'; $('[data-sfile]').value = ''; $('[data-stitle]').value = ''; }
					else { st.textContent = (x.j && x.j.message) || 'Lähetys epäonnistui.'; }
				})
				.catch(function () { ss.disabled = false; st.textContent = 'Lähetys epäonnistui. Tiedosto voi olla liian iso.'; });
		});
	}
	var chatEl = $('[data-chat]');
	function drawChat(list) {
		if (!chatEl || !list) { return; }
		var box = chatEl.querySelector('[data-chatlist]');
		box.innerHTML = '';
		if (!list.length) { var e = document.createElement('span'); e.className = 'rs'; e.textContent = 'Ei viestejä vielä. Kirjoita ensimmäinen!'; box.appendChild(e); return; }
		list.forEach(function (m) {
			var d = document.createElement('div'); d.className = 'rcm' + (m.dj ? ' dj' : '');
			var b = document.createElement('b'); b.textContent = (m.dj ? '🎙️ ' : '') + (m.b ? '🤖 ' : '') + m.n + ': ';
			d.appendChild(b); d.appendChild(document.createTextNode(m.m)); box.appendChild(d);
		});
	}
	var fastUntil = 0, fastTimer = 0;
	function setTyping(w) {
		if (!chatEl) { return; }
		var t = chatEl.querySelector('[data-typing]');
		if (w) { t.textContent = '✍️ ' + w + ' kirjoittaa…'; t.hidden = false; fastUntil = Math.max(fastUntil, Date.now() + 8000); } else { t.hidden = true; }
	}
	function fastPoll() {
		if (fastTimer) { return; }
		fastTimer = setInterval(function () {
			if (Date.now() > fastUntil) { clearInterval(fastTimer); fastTimer = 0; return; }
			fetch(q(chatEl.getAttribute('data-chaturl'), 'r=' + Date.now()), { cache: 'no-store' }).then(function (x) { return x.json(); }).then(function (j) { drawChat(j.chat); setTyping(j.typing || ''); }).catch(function () {});
		}, 2000);
	}
	if (chatEl) {
		var nm = chatEl.querySelector('[data-cname]');
		try { nm.value = localStorage.getItem('suorni_nick') || ''; } catch (e) {}
		chatEl.querySelector('[data-csend]').addEventListener('click', function () {
			var b = this, st = chatEl.querySelector('[data-cst]'), mi = chatEl.querySelector('[data-cmsg]');
			if (!mi.value.trim()) { return; }
			b.disabled = true; st.textContent = 'Lähetetään…';
			try { localStorage.setItem('suorni_nick', nm.value); } catch (e) {}
			fetch(chatEl.getAttribute('data-chaturl'), { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ name: nm.value, msg: mi.value, hp: chatEl.querySelector('[data-chp]').value }) })
				.then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
				.then(function (x) { b.disabled = false; if (x.ok) { mi.value = ''; st.textContent = ''; drawChat(x.j.chat); fastUntil = Date.now() + 45000; fastPoll(); setTyping(x.j.typing || ('')); } else { st.textContent = (x.j && x.j.message) || 'Lähetys epäonnistui.'; } })
				.catch(function () { b.disabled = false; st.textContent = 'Lähetys epäonnistui.'; });
		});
	}
	function current() {
		var t = srv();
		for (var i = 0; i < items.length; i++) {
			if (t >= items[i].start && t < items[i].start + items[i].dur) { return { it: items[i], next: items[i + 1] || null, pos: t - items[i].start }; }
		}
		return null;
	}
	var ndl = $('[data-needle]');
	function needle() {
		if (!ndl) { return; }
		var c = current();
		var pr = c ? Math.max(0, Math.min(1, c.pos / c.it.dur)) : 0;
		ndl.style.left = (pr * 100) + '%';
		var tps = root.querySelectorAll('.tp');
		if (tps.length > 1) { var s1 = 22 + 16 * (1 - pr), s2 = 22 + 16 * pr; tps[0].style.width = tps[0].style.height = s1 + 'px'; tps[1].style.width = tps[1].style.height = s2 + 'px'; }
		var key = c ? c.it.url + '@' + c.it.start : '';
		var tt = c ? (c.it.type === 'dj' ? '🎙 Juontaja' : (c.it.artist ? c.it.artist + ' – ' : '') + c.it.title) : (root.getAttribute('data-name') || 'Suorni Radio');
		if (key !== tapeKey && c) { swapTape(tt, c.it); tapeKey = key; }
	}
	var tapeKey = '';
	function swapTape(text, it) {
		var slot = $('[data-slot]'), cas = slot && slot.querySelector('[data-cas]');
		if (!cas) { return; }
		var h = 0, sd = it.type === 'dj' ? 'juontaja' : (it.title + it.artist);
		for (var i = 0; i < sd.length; i++) { h = (h * 31 + sd.charCodeAt(i)) >>> 0; }
		var cls = 'rcas c' + (1 + h % 6);
		if (tapeKey) {
			var old = cas.cloneNode(true); old.removeAttribute('data-cas'); old.className += ' rout';
			slot.appendChild(old);
			setTimeout(function () { if (old.parentNode) { old.parentNode.removeChild(old); } }, 800);
			cas.className = cls + ' rin';
			setTimeout(function () { cas.className = cls; }, 800);
		} else { cas.className = cls; }
		cas.querySelector('[data-tlabel]').textContent = text;
		cas.querySelector('em').textContent = it.type === 'dj' ? 'B' : (h % 2 ? 'B' : 'A');	}
	setInterval(needle, 1000);
	var ej = $('[data-eject]');
	if (ej) { ej.addEventListener('click', function () { if (playing) { elPlay.click(); } }); }
	root.querySelectorAll('[data-pre]').forEach(function (b) {
		b.addEventListener('click', function () {
			var k = b.getAttribute('data-pre'), t = null;
			if (k === 'chat') { t = root.querySelector('[data-cmsg]'); }
			if (k === 'vote') { t = $('[data-votebox]'); if (t && t.hidden) { elCap.textContent = 'Peukutus tulee näkyviin, kun kappale soi.'; t = null; } }
			if (k === 'greet' || k === 'song') {
				var ds = [].slice.call(root.querySelectorAll('details'));
				var d = ds.filter(function (x) { return x.querySelector('summary').textContent.indexOf(k === 'greet' ? 'terveiset' : 'oma kappaleesi') >= 0; })[0];
				if (d) { d.open = true; t = d; }
			}
			if (t) { try { t.scrollIntoView({ block: 'center', behavior: 'smooth' }); } catch (e) {} if (t.focus && k === 'chat') { t.focus(); } }
		});
	});
	function nextTrack(from) {
		for (var i = from; i < items.length; i++) { if (items[i] && items[i].type === 'track') { return items[i]; } }
		return null;
	}
	function render() {
		var c = current();
		if (!c) { return; }
		var it = c.it;
		var pe = function (x) { var d = document.createElement('div'); d.textContent = x == null ? '' : x; return d.innerHTML; };
		var ph = shift && shift.host && (!it.prog || it.prog === shift.name) ? ' · 🎙️ ' + pe(shift.host) : '';
		var pn = shift && shift.next ? '<span class="rnx">Seuraavaksi ' + (shift.next.day ? pe(shift.next.day) + ' ' : '') + 'klo ' + pe(shift.next.clock) + ': ' + pe(shift.next.name) + (shift.next.host ? ' · 🎙️ ' + pe(shift.next.host) : '') + '</span>' : '';
		elProg.innerHTML = '<span class="rl">LIVE</span>' + pe(it.prog ? it.prog : 'Suora lähetys') + ph + pn;
		if (it.type === 'dj') {
			elTitle.textContent = '🎙️ Juontaja';
			elArtist.textContent = '';
			if (!elCap.getAttribute('data-aux')) { elCap.textContent = it.text || ''; }
		} else if (it.type === 'ad') {
			elTitle.textContent = '📢 Mainos';
			elArtist.textContent = it.title || '';
			if (!elCap.getAttribute('data-aux')) { elCap.textContent = ''; }
		} else {
			elTitle.textContent = it.title;
			elArtist.textContent = it.artist;
			if (!elCap.getAttribute('data-aux')) { elCap.textContent = ''; }
		}
		drawComm(it.type === 'track' ? it.sub : 0, it.type === 'track' ? it.tid : 0);
		var idx = items.indexOf(it);
		var nt = nextTrack(idx + 1);
		elNext.textContent = nt ? 'Seuraavaksi: ' + nt.title : '';
	}
	var isApp = (window.matchMedia && window.matchMedia('(display-mode: standalone)').matches) || window.navigator.standalone || /[?&]suorni_app=1/.test(location.search);
	var autoTried = false, tapArmed = false;
	function blocked() {
		var hn = root.querySelector('[data-hint]');
		if (!hn) { hn = document.createElement('div'); hn.setAttribute('data-hint', ''); hn.className = 'rc'; hn.style.color = '#ffb347'; elCap.parentNode.insertBefore(hn, elCap); }
		hn.textContent = '👆 Napauta mihin tahansa aloittaaksesi radion (selain vaatii napautuksen).';
		elPlay.style.boxShadow = '0 0 0 8px rgba(255,179,71,.7)';
		if (tapArmed) { return; }
		tapArmed = true;
		var go = function (ev) { document.removeEventListener('pointerdown', go, true); tapArmed = false; elPlay.style.boxShadow = ''; if (ev && ev.target && elPlay.contains(ev.target)) { return; } if (!playing) { elPlay.click(); } };
		document.addEventListener('pointerdown', go, true);
	}
	function loadItem(it, pos, key) {
		if (shopUrl && shopAdGate(it, key)) { return; }
		/* Ensimmäinen kappale kuunneltu loppuun -> tervetuloa-tervehdys seuraavan kappaleen päälle */
		if (welArm && !welState && curKey && curKey !== key) {
			var pv = null;
			for (var wi = 0; wi < items.length; wi++) { if (items[wi].url + '@' + items[wi].start === curKey) { pv = items[wi]; break; } }
			if (pv && pv.type === 'track' && it.start >= pv.start + pv.dur - 2) {
				welState = 2;
				if (it.type === 'track') { welPlay(); } else { welMark(); }
			}
		}
		curKey = key;
		clearTimeout(endT);
		if (it.fade) {
			/* Tasalle leikattu kappale: vaihto seuraavaan tasan oikealla hetkellä (ei vasta seuraavalla tarkistuksella) */
			var endMs = (it.start + it.dur - srv()) * 1000 + 30;
			if (endMs > 0 && endMs < 3600000) { endT = setTimeout(function () { if (playing) { sync(); } }, endMs); }
		}
		fixes = 0;
		var tok = ++loadTok;
		au.src = it.url;
		var go = function () {
			au.removeEventListener('loadedmetadata', go);
			if (tok !== loadTok) { return; }
			try { if (pos > 1.5) { au.currentTime = Math.min(pos + 0.3, Math.max(0, (au.duration || 1e9) - 0.2)); } } catch (e) {}
			var pr = au.play();
			if (pr && pr.catch) {
				pr.catch(function (e) {
					if (tok !== loadTok) { return; }
					if (e && e.name === 'NotAllowedError') { setUi(false); blocked(); } else { curKey = ''; }
				});
			}
		};
		au.addEventListener('loadedmetadata', go);
		au.load();
		render();
	}
	/* Tervetuloa-tervehdys: soi kun uuden kuuntelijan ensimmäinen kappale on kuunneltu loppuun */
	var welUrl = shopUrl ? '' : (root.getAttribute('data-welcome') || '');
	var welState = 0, welAudio = null, welArm = false, volEl = $('[data-vol]');
	function welSeen() { try { return Date.now() - (+localStorage.getItem('suorni_wel') || 0) < 3 * 3600 * 1000; } catch (e) { return false; } }
	function welMark() { try { localStorage.setItem('suorni_wel', String(Date.now())); } catch (e) {} }
	function welPrepare() {
		if (!welUrl || welState || welArm || welSeen()) { return; }
		welArm = true;
		fetch(welUrl + (welUrl.indexOf('?') < 0 ? '?' : '&') + 'r=' + Date.now(), { cache: 'no-store' }).then(function (r) { return r.json(); }).then(function (j) {
			if (j && j.url) { welAudio = new Audio(); welAudio.preload = 'auto'; welAudio.referrerPolicy = 'no-referrer'; welAudio.src = j.url; }
		}).catch(function () {});
	}
	function welPlay() {
		var w = welAudio; welAudio = null;
		if (!w) { return; }
		var back = function () { try { au.volume = Math.max(0, Math.min(1, (volEl ? volEl.value : 100) / 100)); } catch (e) {} };
		try { au.volume = Math.max(0, Math.min(1, (volEl ? volEl.value : 100) / 100)) * 0.25; } catch (e) {}
		w.onended = back; w.onerror = back;
		setTimeout(back, 20000);
		var pr = w.play();
		if (pr && pr.catch) { pr.catch(back); }
		welMark();
	}
	/* Tasalle sovitetun kappaleen loppu häivytetään (it.fade = häivytyksen kesto sekunteina). iPhonessa selain ei salli äänenvoimakkuuden säätöä, jolloin kappale vain päättyy tasalla. */
	var endT = 0, fadeOn = false;
	setInterval(function () {
		if (!playing || shopAdHold) { return; }
		var c = current(), base = Math.max(0, Math.min(1, (volEl ? volEl.value : 100) / 100));
		if (c && c.it.type !== 'dj' && lag > 0 && c.next && c.next.type === 'dj') {
			/* Myymälän kuuntelija on jäljessä: juonto aloitetaan reaaliajassa tasan oikeaan aikaan, edellisen kappaleen loppu ohitetaan häivyttäen */
			var cutAt = c.next.start - lag, rm = cutAt - srv();
			if (rm <= 0) { lag = 0; fadeOn = false; try { au.volume = base; } catch (e) {} sync(); return; }
			if (rm < 3) { fadeOn = true; var m2 = rm / 3; try { au.volume = base * m2 * m2; } catch (e) {} return; }
		}
		if (c && c.it.fade && !au.paused) {
			var rem = c.it.start + c.it.dur - srv();
			if (rem < c.it.fade) { fadeOn = true; var m = Math.max(0, rem / c.it.fade); try { au.volume = base * m * m; } catch (e) {} return; }
		}
		if (fadeOn) { fadeOn = false; try { au.volume = base; } catch (e) {} }
	}, 100);
	/* Kun kappale päättyy, siirrytään heti seuraavaan ilman ajastinta (ajastimet hidastuvat, kun näyttö on sammutettu) */
	function advance() {
		if (!playing || shopAdHold) { return; }
		var idx = -1;
		for (var i = 0; i < items.length; i++) { if (items[i].url + '@' + items[i].start === curKey) { idx = i; break; } }
		var nx = idx >= 0 ? items[idx + 1] : null;
		if (!nx) { sync(); return; }
		var late = Math.max(0, srv() - nx.start);
		if (late > nx.dur) { sync(); return; }
		loadItem(nx, (nx.from || 0) + late, nx.url + '@' + nx.start);
	}
	function sync() {
		if (shopAdHold) { return; }
		var c = current();
		if (!c) { if (playing) { au.pause(); } fetchNow(playing); return; }
		var key = c.it.url + '@' + c.it.start;
		if (key !== curKey) {
			loadItem(c.it, c.pos + (c.it.from || 0), key);
		} else {
			var want = c.pos + (c.it.from || 0);
			if (!au.paused && !au.seeking && au.readyState > 2 && Math.abs(au.currentTime - want) > 7 && fixes < 1 && Date.now() - lastFix > 20000) {
				fixes++; lastFix = Date.now();
				try { au.currentTime = want; } catch (e) {}
			} else if (au.paused && playing && !au.ended && au.readyState >= 2) {
				var p2 = au.play();
				if (p2 && p2.catch) { p2.catch(function (e) { if (e && e.name === 'NotAllowedError') { setUi(false); } }); }
			}
		}
		if (c.next && c.next.type === 'dj' && c.next.url !== warmUrl && c.it.start + c.it.dur - srv() < 25) { warmUrl = c.next.url; warm.preload = 'auto'; warm.muted = true; warm.src = warmUrl; try { warm.load(); } catch (e) {} }
	}
	function setUi(on) {
		playing = on;
		root.classList.toggle('rplay', on);
		elPlay.textContent = on ? '⏸' : '▶';
		elPlay.setAttribute('aria-label', on ? 'Pysäytä' : 'Kuuntele');
		if (on) { var h2 = root.querySelector('[data-hint]'); if (h2) { h2.textContent = ''; } elPlay.style.boxShadow = ''; }
		if (!on) { elCap.textContent = ''; }
	}
	elPlay.addEventListener('click', function () {
		if (shopUrl && !playing && shopOpen === false) { return; }
		if (playing) { au.pause(); setUi(false); curKey = ''; if (shopUrl && !autoStop) { userStop = true; } return; }
		setUi(true);
		welPrepare();
		lag = 0;
		curKey = '';
		lastPing = 0;
		if (!items.length || !current()) { fetchNow(true); setTimeout(sync, 800); } else { sync(); fetchNow(true); }
	});
	$('[data-vol]').addEventListener('input', function (e) { au.volume = Math.max(0, Math.min(1, e.target.value / 100)); });
	var badSent = {};
	au.addEventListener('error', function () {
		var c = current();
		if (c && c.it.type === 'track' && c.it.id && !c.it.sub && !badSent[c.it.id]) { badSent[c.it.id] = 1; fetch(q(U.now, 'bad=' + c.it.id + '&r=' + Date.now()), { cache: 'no-store' }).then(function () { fetchNow(true); }).catch(function () {}); }
		curKey = '';
	});
	au.addEventListener('ended', advance);
	var lastTu = 0;
	au.addEventListener('timeupdate', function () { var n = Date.now(); if (playing && n - lastTu > 900) { lastTu = n; sync(); } });
	var mediaKey = '', mediaInit = false;
	function media(force) {
		if (!('mediaSession' in navigator)) { return; }
		try {
			var ms = navigator.mediaSession;
			var c = current();
			var it = c ? c.it : null;
			var t = it ? (it.type === 'dj' ? 'Juontaja' : (it.title || 'Suorni Radio')) : 'Suorni Radio';
			var ar = it && it.type !== 'dj' && it.artist ? it.artist : (it && it.type === 'dj' ? (it.prog || 'Suorni Radio') : 'Suorni Radio');
			var key = t + '|' + ar;
			if (force || key !== mediaKey || !ms.metadata) {
				mediaKey = key;
				var ic = root.getAttribute('data-icon'), md = { title: t, artist: ar, album: root.getAttribute('data-name') || 'Suorni Radio' };
				if (ic) { md.artwork = [{ src: ic, sizes: '512x512', type: 'image/png' }]; }
				ms.metadata = new MediaMetadata(md);
			}
			if (!mediaInit) {
				mediaInit = true;
				ms.setActionHandler('pause', function () { if (playing) { elPlay.click(); } });
				ms.setActionHandler('play', function () { if (!playing) { elPlay.click(); } });
				['previoustrack', 'nexttrack', 'seekbackward', 'seekforward', 'seekto'].forEach(function (a) { try { ms.setActionHandler(a, null); } catch (e) {} });
			}
			if (ms.setPositionState && it && it.dur > 0) { ms.setPositionState({ duration: it.dur, playbackRate: 1, position: Math.max(0, Math.min(it.dur, c.pos + (it.from || 0))) }); }
			ms.playbackState = playing ? 'playing' : 'paused';
		} catch (e) {}
	}
	au.addEventListener('playing', function () { media(true); });
	au.addEventListener('loadedmetadata', function () { media(true); });
	setInterval(function () { if (playing) { media(); } }, 3000);

	setInterval(function () {
		if (playing) { sync(); } else { var c = current(); if (c) { render(); } }
		var now = Date.now();
		if (playing && now - lastPing > 30000) { lastPing = now; fetchNow(true); }
		else if (now - lastFetch > (playing ? 8000 : 45000)) { fetchNow(false); }
		else { var tail = items.length ? items[items.length - 1] : null; if (tail && srv() > tail.start + tail.dur - 60 && now - lastFetch > 8000) { fetchNow(playing); } }
	}, 700);
	fetchNow(false);

	var gs = $('[data-gsend]');
	if (gs) {
		gs.addEventListener('click', function () {
			var st = $('[data-gst]');
			var body = JSON.stringify({ name: $('[data-gname]').value, msg: $('[data-gmsg]').value, voice: ($('[data-gvoice]') || {}).value || '', hp: $('[data-ghp]').value });
			gs.disabled = true;
			fetch(U.greet, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: body })
				.then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
				.then(function (x) {
					gs.disabled = false;
					if (x.ok) { st.textContent = 'Kiitos! Terveisesi odottavat hyväksyntää.'; $('[data-gmsg]').value = ''; }
					else { st.textContent = (x.j && x.j.message) || 'Lähetys epäonnistui.'; }
				})
				.catch(function () { gs.disabled = false; st.textContent = 'Lähetys epäonnistui.'; });
		});
	}

	/* Myymälätila: aukioloajat, kuulutukset ja mainokset */
	var shopOpen = null, shopData = null, userStop = false, autoStop = false, auxBusy = false, songN = 0, nextAt = 0, adIdx = 0, doneAnn = {};
	try { doneAnn = JSON.parse(localStorage.getItem('suorni_shop_ann') || '{}') || {}; } catch (e) {}
	function auxPlay(url, label) {
		if (auxBusy) { return; }
		auxBusy = true;
		var a = new Audio(), fin = false; try { a.referrerPolicy = 'no-referrer'; } catch (e) {} a.src = url;
		var end = function () { if (fin) { return; } fin = true; auxBusy = false; au.muted = false; if (elCap.getAttribute('data-aux')) { elCap.textContent = ''; elCap.removeAttribute('data-aux'); } };
		a.volume = Math.max(0, Math.min(1, ($('[data-vol]').value || 100) / 100));
		a.addEventListener('ended', end); a.addEventListener('error', end);
		setTimeout(end, 300000);
		au.muted = true;
		elCap.textContent = label; elCap.setAttribute('data-aux', '1');
		var p = a.play(); if (p && p.catch) { p.catch(end); }
	}
	function rollAd() {
		var lo = Math.max(1, (shopData && shopData.ads.min) | 0), hi = Math.max(lo, (shopData && shopData.ads.max) | 0);
		nextAt = lo + Math.floor(Math.random() * (hi - lo + 1)); songN = 0;
	}
	/* Myymälän mainos soitetaan kappaleen jälkeen: seuraavan kappaleen lataus odottaa, mainos soi ja musiikki jatkuu sen jälkeen siitä, mihin lähetys olisi jäänyt (lag) */
	function adPlay(url, label, done) {
		auxBusy = true;
		var a = new Audio(), fin = false, t0 = Date.now(); try { a.referrerPolicy = 'no-referrer'; } catch (e) {} a.src = url;
		var end = function () { if (fin) { return; } fin = true; auxBusy = false; if (elCap.getAttribute('data-aux')) { elCap.textContent = ''; elCap.removeAttribute('data-aux'); } done((Date.now() - t0) / 1000); };
		a.volume = Math.max(0, Math.min(1, ($('[data-vol]').value || 100) / 100));
		a.addEventListener('ended', end); a.addEventListener('error', end);
		setTimeout(end, 120000);
		try { au.pause(); } catch (e) {}
		elCap.textContent = label; elCap.setAttribute('data-aux', '1');
		var p = a.play(); if (p && p.catch) { p.catch(end); }
	}
	function shopAdGate(it, key) {
		if (it.type !== 'track' || shopSeen === key) { return false; }
		shopSeen = key;
		if (!shopData || !shopData.ads || !shopData.ads.on || !shopData.ads.list.length || auxBusy || !playing) { return false; }
		if (!nextAt) { rollAd(); }
		songN++;
		if (songN < nextAt) { return false; }
		/* vain luonnollisessa kappalevaihdossa kappaleesta kappaleeseen */
		var pv = null, idx = -1;
		for (var wi = 0; wi < items.length; wi++) { if (items[wi].url + '@' + items[wi].start === curKey) { pv = items[wi]; } if (items[wi] === it) { idx = wi; } }
		if (!pv || pv.type !== 'track' || it.start < pv.start + pv.dur - 2) { songN = nextAt; return false; }
		var ad = shopData.ads.list[adIdx % shopData.ads.list.length], ad_d = ad.dur || 15;
		/* viive ei saa kasvaa liikaa, eikä seuraavan juonnon edellä oleva kappale saa jäädä liian lyhyeksi */
		var nx = idx >= 0 ? items[idx + 1] : null;
		if (lag + ad_d > 240 || (nx && nx.type === 'dj' && it.dur < lag + ad_d + 30)) { songN = nextAt; return false; }
		adIdx++; rollAd();
		shopAdHold = true;
		adPlay(ad.url, '📢 Mainos: ' + ad.title, function (el) { lag += el; shopAdHold = false; if (playing) { sync(); } });
		return true;
	}
	var shopTimer = 0;
	function shopPoll() {
		fetch(q(shopUrl, 'r=' + Date.now()), { cache: 'no-store' }).then(function (r) { return r.json(); }).then(function (d) {
			if (!d || typeof d.open !== 'boolean') { return; }
			shopData = d;
			var was = shopOpen; shopOpen = d.open;
			var hint = root.querySelector('[data-shophint]');
			if (!hint) { hint = document.createElement('div'); hint.setAttribute('data-shophint', ''); hint.className = 'rc'; hint.style.cssText = 'color:#ffb347;font-weight:700'; elCap.parentNode.insertBefore(hint, elCap); }
			if (!d.open) {
				hint.textContent = '🔒 Myymälä on suljettu' + (d.next ? '. Musiikki alkaa ' + d.next + '.' : '.');
				if (playing) { autoStop = true; elPlay.click(); autoStop = false; }
				userStop = false;
			} else {
				hint.textContent = '';
				if (was === false) { userStop = false; }
				if (!playing && !userStop) { elPlay.click(); }
			}
			(d.an || []).forEach(function (x) {
				if (doneAnn[x.k]) { return; }
				doneAnn[x.k] = Date.now();
				var keys = Object.keys(doneAnn); if (keys.length > 60) { delete doneAnn[keys[0]]; }
				try { localStorage.setItem('suorni_shop_ann', JSON.stringify(doneAnn)); } catch (e) {}
				if (d.open) { auxPlay(x.url, '📣 Kuulutus: ' + x.title); }
			});
		}).catch(function () {});
	}
	if (shopUrl) {
		shopPoll(); shopTimer = setInterval(shopPoll, 15000);
		try { if ('wakeLock' in navigator) { var wlk = function () { if (!document.hidden) { navigator.wakeLock.request('screen').catch(function () {}); } }; wlk(); document.addEventListener('visibilitychange', wlk); } } catch (e) {}
	}

	/* PWA: huoltotyöntekijä ja asennusohje */
	try {
		var swu = root.getAttribute('data-sw');
		if (swu && 'serviceWorker' in navigator) { navigator.serviceWorker.register(swu, { scope: '/' }).catch(function () {}); }
		var inst = $('[data-inst]'), itx = $('[data-insttxt]'), ibt = $('[data-instbtn]');
		var standalone = (window.matchMedia && window.matchMedia('(display-mode: standalone)').matches) || window.navigator.standalone;
		var dp = null;
		if (inst && !standalone) {
			var ua = navigator.userAgent || '';
			if (/iPhone|iPad|iPod/.test(ua)) {
				itx.textContent = 'Avaa tämä sivu Safarissa, paina Jaa-kuvaketta ja valitse "Lisää Koti-valikkoon".';
				inst.hidden = false;
			}
			window.addEventListener('beforeinstallprompt', function (e) {
				e.preventDefault(); dp = e;
				itx.textContent = 'Saat radion omaksi sovelluskuvakkeeksi puhelimen näytölle.';
				ibt.hidden = false; inst.hidden = false;
			});
			ibt.addEventListener('click', function () { if (dp) { dp.prompt(); dp = null; ibt.hidden = true; inst.hidden = true; } });
		}
	} catch (e) {}
})();
(function () {
	'use strict';
	var root = document.querySelector('.sr');
	var hx = root && root.querySelector('[data-hx]');
	if (!hx) { return; }
	var body = hx.querySelector('[data-hxb]'), rec = hx.querySelector('[data-hxr]'), tabs = hx.querySelectorAll('[data-hxt]');
	var H = null, tab = 'top', reqL = null, selId = 0, reqMsg = '';
	function q(u, p) { return u + (u.indexOf('?') < 0 ? '?' : '&') + p; }
	function mk(t, c, tx) { var e = document.createElement(t); if (c) { e.className = c; } if (tx != null) { e.textContent = tx; } return e; }
	function hm(ts) { var d = new Date(ts * 1000); return ('0' + d.getHours()).slice(-2) + ':' + ('0' + d.getMinutes()).slice(-2); }
	function shareUrl(id) { try { var u = new URL(location.href); u.hash = ''; u.searchParams.set('biisi', id); return u.toString(); } catch (e) { return location.href; } }
	function share(x, b) {
		var url = shareUrl(x.id), txt = '🎵 ' + (x.a ? x.a + ' – ' : '') + x.t + ' – kuuntele Suorni Radiossa';
		if (navigator.share) { navigator.share({ title: 'Suorni Radio', text: txt, url: url }).catch(function () {}); return; }
		var done = function () { var o = b.textContent; b.textContent = '✓'; setTimeout(function () { b.textContent = o; }, 1500); };
		try { navigator.clipboard.writeText(txt + ' ' + url).then(done); } catch (e) { window.prompt('Kopioi linkki', url); }
	}
	function row(x, lead) {
		var r = mk('div', 'rhr'); r.appendChild(mk('span', 'tm', lead));
		r.appendChild(mk('span', 'tx', (x.a ? x.a + ' – ' : '') + x.t));
		var b = mk('button', 'sh', '📤'); b.type = 'button'; b.setAttribute('aria-label', 'Jaa kappale'); b.addEventListener('click', function () { share(x, b); }); r.appendChild(b);
		return r;
	}
	function drawRecent() {
		if (!rec) { return; }
		rec.innerHTML = '';
		if (!H) { rec.appendChild(mk('div', 'rs', 'Ladataan…')); return; }
		var L = H.recent.slice(1, 6);
		if (!L.length) { rec.appendChild(mk('div', 'rs', 'Lista täyttyy, kun radio on soinut hetken.')); return; }
		L.forEach(function (x) { rec.appendChild(row(x, hm(x.at))); });
	}
	function draw() {
		drawRecent();
		body.innerHTML = '';
		[].forEach.call(tabs, function (b) { b.classList.toggle('on', b.getAttribute('data-hxt') === tab); });
		if (tab === 'req') { drawReq(); return; }
		if (!H) { body.appendChild(mk('div', 'rs', 'Ladataan…')); return; }
		body.appendChild(mk('div', 'rs', H.toplabel + ' (peukkujen mukaan)'));
		if (!H.top.length) { body.appendChild(mk('div', 'rs', 'Ei vielä äänestettyjä kappaleita. Anna peukku soivalle kappaleelle!')); return; }
		H.top.forEach(function (x, i) { body.appendChild(row(x, '#' + (i + 1) + ' 👍' + x.n)); });
	}
	function drawReq() {
		var w = mk('div', 'rhq');
		if (!reqL) { body.appendChild(mk('div', 'rs', 'Ladataan kappaleita…')); fetch(q(hx.getAttribute('data-reqlist'), 'r=' + Date.now()), { cache: 'no-store' }).then(function (r) { return r.json(); }).then(function (j) { reqL = j; if (tab === 'req') { draw(); } }).catch(function () {}); return; }
		if (!reqL.on) { body.appendChild(mk('div', 'rs', 'Toiveet eivät ole nyt käytössä.')); return; }
		w.appendChild(mk('div', 'rs', 'Valitse kappale, jonka haluat kuulla. Toive soi pian' + (reqL.waiting ? ' (jonossa ' + reqL.waiting + ' toivetta)' : '') + '.'));
		var sr = mk('input', 'rt'); sr.type = 'text'; sr.placeholder = 'Hae esittäjää tai kappaletta'; sr.setAttribute('aria-label', 'Hae kappaletta');
		var lst = mk('div', 'rhl'), nm = mk('input', 'rt'), hp = mk('input', 'rh'), go = mk('button', 'rg', 'Lähetä toive'), st = mk('p', 'rs');
		nm.type = 'text'; nm.maxLength = 20; nm.placeholder = 'Nimimerkki (valinnainen)'; hp.tabIndex = -1; hp.setAttribute('autocomplete', 'off'); hp.setAttribute('aria-hidden', 'true'); go.type = 'button';
		function fill() {
			lst.innerHTML = ''; var f = sr.value.trim().toLowerCase(), n = 0;
			for (var i = 0; i < reqL.tracks.length && n < 60; i++) {
				var t = reqL.tracks[i], lab = (t.a ? t.a + ' – ' : '') + t.t;
				if (f && lab.toLowerCase().indexOf(f) < 0) { continue; }
				(function (t, lab) { var b = mk('button', t.id === selId ? 'sel' : '', lab); b.type = 'button'; b.addEventListener('click', function () { selId = t.id; fill(); }); lst.appendChild(b); })(t, lab); n++;
			}
			if (!n) { lst.appendChild(mk('div', 'rs', 'Ei hakutuloksia.')); }
		}
		sr.addEventListener('input', fill); fill();
		go.addEventListener('click', function () {
			if (!selId) { st.textContent = 'Valitse ensin kappale.'; return; }
			go.disabled = true; st.textContent = 'Lähetetään…';
			fetch(hx.getAttribute('data-req'), { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ id: selId, name: nm.value, hp: hp.value }) }).then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); }).then(function (x) {
				go.disabled = false; st.textContent = x.ok ? x.j.message : (x.j.message || 'Lähetys epäonnistui.');
				if (x.ok) { selId = 0; reqL = null; reqMsg = x.j.message; draw(); }
			}).catch(function () { go.disabled = false; st.textContent = 'Lähetys epäonnistui.'; });
		});
		st.textContent = reqMsg; reqMsg = '';
		[sr, lst, nm, hp, go, st].forEach(function (e) { w.appendChild(e); });
		body.appendChild(w);
	}
	[].forEach.call(tabs, function (b) { b.addEventListener('click', function () { tab = b.getAttribute('data-hxt'); draw(); }); });
	function load(first) {
		var one = '', m = /[?&]biisi=(c?\d{1,9})/.exec(location.search);
		if (first && m) { one = '&id=' + m[1]; }
		fetch(q(hx.getAttribute('data-hist'), 'r=' + Math.floor(Date.now() / 60000) + one), { cache: 'no-store' }).then(function (r) { return r.json(); }).then(function (j) {
			if (j.off) { return; }
			H = j; hx.hidden = false; if (tab !== 'req') { draw(); } else { drawRecent(); }
			if (j.one) { var n = mk('div', 'rbn', '🎧 Sinulle suositeltu: ' + (j.one.a ? j.one.a + ' – ' : '') + j.one.t + '. Paina play, niin kuulet radion tunnelman.'); root.insertBefore(n, root.firstChild); }
		}).catch(function () {});
	}
	load(true);
	setInterval(function () { if (!document.hidden && tab !== 'req') { load(false); } }, 60000);
	if (tab !== 'req') { draw(); }
})();

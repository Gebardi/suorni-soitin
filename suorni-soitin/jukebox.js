(function () {
	var btn = document.querySelector('[data-jbopen]'), win = document.querySelector('[data-jbwin]');
	if (!btn || !win) { return; }
	document.body.appendChild(win);
	var $ = function (s) { return win.querySelector(s); };
	var listEl = $('[data-jblist]'), chEl = $('[data-jbch]'), soEl = $('[data-jbsort]'), qEl = $('[data-jbq]'), bar = $('[data-jbbar]');
	var nowEl = $('[data-jbnow]'), ppEl = $('[data-jbpp]'), seek = $('[data-jbseek]'), t1 = $('[data-jbt1]'), t2 = $('[data-jbt2]');
	var au = new Audio(), data = null, chSel = '', sortK = 'pop', q = '', cur = 0, shown = 100, shuf = [], mine = {}, seeking = false;
	au.preload = 'none';
	try { mine = JSON.parse(localStorage.getItem('suorni_votes') || '{}') || {}; } catch (e) {}
	function esc(s) { var d = document.createElement('div'); d.textContent = s == null ? '' : s; return d.innerHTML; }
	function fmt(s) { s = Math.max(0, Math.floor(s || 0)); return Math.floor(s / 60) + ':' + ('0' + (s % 60)).slice(-2); }
	function norm(s) { return String(s || '').toLowerCase(); }
	function view() {
		var a = data.tracks.filter(function (t) {
			if (chSel && (t.c || []).indexOf(chSel) < 0) { return false; }
			if (q && norm(t.t + ' ' + t.a).indexOf(q) < 0) { return false; }
			return true;
		});
		if (sortK === 'new') { a.sort(function (x, y) { return y.at - x.at; }); }
		else if (sortK === 'az') { a.sort(function (x, y) { return x.t.localeCompare(y.t, 'fi'); }); }
		else if (sortK === 'rnd') { a.sort(function (x, y) { return (shuf[x.id] || 0) - (shuf[y.id] || 0); }); }
		else { a.sort(function (x, y) { return (y.p + y.v * 3) - (x.p + x.v * 3) || y.at - x.at; }); }
		return a;
	}
	function chips() {
		var h = '<button type="button" data-ch="" class="' + (chSel === '' ? 'on' : '') + '">Kaikki</button>';
		data.chs.forEach(function (c) { h += '<button type="button" data-ch="' + esc(c.id) + '" class="' + (chSel === c.id ? 'on' : '') + '">' + esc(c.name) + '</button>'; });
		chEl.innerHTML = h;
		var so = [['pop', 'Suosituimmat'], ['new', 'Uusimmat'], ['az', 'A–Ö'], ['rnd', 'Sekoita']];
		soEl.innerHTML = so.map(function (x) { return '<button type="button" data-so="' + x[0] + '" class="' + (sortK === x[0] ? 'on' : '') + '">' + x[1] + '</button>'; }).join('');
	}
	function draw() {
		var a = view();
		if (!a.length) { listEl.innerHTML = '<p class="rjn">Ei hakua vastaavia kappaleita.</p>'; return; }
		var h = a.slice(0, shown).map(function (t) {
			var m = mine['t' + t.id] > 0;
			return '<div class="rjr' + (t.id === cur ? ' cur' : '') + '" data-id="' + t.id + '"><button type="button" class="rjp" data-pl="' + t.id + '" aria-label="Soita ' + esc(t.t) + '">' + (t.id === cur && !au.paused ? '⏸' : '▶') + '</button><div class="rji"><b>' + esc(t.t) + '</b><span>' + esc(t.a) + ' · ' + fmt(t.l) + '</span></div><button type="button" class="rjv' + (m ? ' on' : '') + '" data-vt="' + t.id + '" aria-label="Pidän">👍 ' + Math.max(0, t.v) + '</button></div>';
		}).join('');
		if (a.length > shown) { h += '<p class="rjn"><button type="button" class="rjx" data-more>Näytä lisää (' + (a.length - shown) + ')</button></p>'; }
		listEl.innerHTML = h;
	}
	function find(id) { for (var i = 0; i < data.tracks.length; i++) { if (data.tracks[i].id === id) { return data.tracks[i]; } } return null; }
	function play(id) {
		var t = find(id); if (!t) { return; }
		if (cur === id && au.src) { if (au.paused) { au.play(); } else { au.pause(); } return; }
		cur = id;
		try { document.dispatchEvent(new CustomEvent('suorni-jb-on')); } catch (e) {}
		au.src = t.u;
		var pr = au.play(); if (pr && pr.catch) { pr.catch(function () { nowEl.textContent = 'Toisto ei onnistunut'; }); }
		nowEl.textContent = t.t + ' – ' + t.a;
		bar.classList.add('on');
		t2.textContent = fmt(t.l);
		try { fetch(win.getAttribute('data-play'), { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ id: id }) }).catch(function () {}); } catch (e) {}
		if ('mediaSession' in navigator) { try { navigator.mediaSession.metadata = new MediaMetadata({ title: t.t, artist: t.a, album: 'Suorni Jukeboksi' }); navigator.mediaSession.setActionHandler('nexttrack', next); navigator.mediaSession.setActionHandler('play', function () { au.play(); }); navigator.mediaSession.setActionHandler('pause', function () { au.pause(); }); } catch (e) {} }
		draw();
	}
	function next() {
		var a = view(), i = -1;
		for (var k = 0; k < a.length; k++) { if (a[k].id === cur) { i = k; break; } }
		if (!a.length) { return; }
		play(a[(i + 1) % a.length].id);
	}
	au.addEventListener('ended', next);
	au.addEventListener('error', function () { if (cur) { nowEl.textContent = 'Kappaleen toisto epäonnistui'; } });
	au.addEventListener('play', function () { ppEl.textContent = '⏸'; draw(); });
	au.addEventListener('pause', function () { ppEl.textContent = '▶'; draw(); });
	au.addEventListener('timeupdate', function () {
		var d = au.duration || (find(cur) || {}).l || 0;
		t1.textContent = fmt(au.currentTime); if (au.duration) { t2.textContent = fmt(au.duration); }
		if (!seeking && d) { seek.value = Math.round(au.currentTime / d * 1000); }
	});
	seek.addEventListener('input', function () { seeking = true; });
	seek.addEventListener('change', function () { var d = au.duration || 0; if (d) { au.currentTime = seek.value / 1000 * d; } seeking = false; });
	ppEl.addEventListener('click', function () { if (!au.src) { return; } if (au.paused) { au.play(); } else { au.pause(); } });
	$('[data-jbnext]').addEventListener('click', next);
	function stop() { try { au.pause(); } catch (e) {} au.removeAttribute('src'); cur = 0; bar.classList.remove('on'); }
	function close() { stop(); win.classList.remove('on'); document.documentElement.style.overflow = ''; btn.focus(); }
	$('[data-jbclose]').addEventListener('click', close);
	document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && win.classList.contains('on')) { close(); } });
	$('[data-jbradio]').addEventListener('click', function () {
		close();
		var rp = document.querySelector('.sr [data-play]');
		if (rp && !document.querySelector('.sr.rplay')) { rp.click(); }
	});
	document.addEventListener('suorni-radio-on', function () { try { au.pause(); } catch (e) {} });
	function vote(id, b) {
		var key = 't' + id;
		fetch(win.getAttribute('data-vote'), { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ id: id, v: 1, k: 't' }) })
			.then(function (r) { return r.json(); }).then(function (x) {
				if (!x || !x.ok) { return; }
				if (x.mine) { mine[key] = x.mine; } else { delete mine[key]; }
				try { localStorage.setItem('suorni_votes', JSON.stringify(mine)); } catch (e) {}
				var t = find(id); if (t) { t.v = x.u - x.d; }
				draw();
			}).catch(function () {});
	}
	listEl.addEventListener('click', function (e) {
		var p = e.target.closest('[data-pl]'), v = e.target.closest('[data-vt]');
		if (p) { play(+p.getAttribute('data-pl')); }
		else if (v) { vote(+v.getAttribute('data-vt')); }
		else if (e.target.closest('[data-more]')) { shown += 100; draw(); }
	});
	chEl.addEventListener('click', function (e) { var b = e.target.closest('[data-ch]'); if (!b) { return; } chSel = b.getAttribute('data-ch'); shown = 100; chips(); draw(); });
	soEl.addEventListener('click', function (e) {
		var b = e.target.closest('[data-so]'); if (!b) { return; }
		sortK = b.getAttribute('data-so');
		if (sortK === 'rnd') { shuf = {}; data.tracks.forEach(function (t) { shuf[t.id] = Math.random(); }); }
		shown = 100; chips(); draw();
	});
	var qt = 0;
	qEl.addEventListener('input', function () { clearTimeout(qt); qt = setTimeout(function () { q = norm(qEl.value.trim()); shown = 100; if (data) { draw(); } }, 150); });
	btn.addEventListener('click', function () {
		win.classList.add('on');
		document.documentElement.style.overflow = 'hidden';
		if (data) { return; }
		fetch(win.getAttribute('data-list'), { cache: 'no-store' }).then(function (r) { return r.json(); }).then(function (j) {
			if (!j || !j.tracks) { throw 0; }
			data = j; chips(); draw();
		}).catch(function () { listEl.innerHTML = '<p class="rjn">Kappaleiden lataus epäonnistui. Yritä hetken kuluttua uudelleen.</p>'; });
	});
})();

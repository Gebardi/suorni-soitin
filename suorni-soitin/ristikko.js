(function () {
'use strict';
var CFG = window.RK, app = document.getElementById('app');
var COL = ['#ffd23f', '#5ee1c9', '#ff8fa3', '#8ec5ff', '#c9a2ff', '#ffb26b', '#a3e635', '#f0abfc'];
function ls(k, v) { try { if (v === undefined) { return localStorage.getItem(k); } localStorage.setItem(k, v); } catch (e) {} return null; }
var pid = ls('suorni_rk_pid');
if (!pid || pid.length < 10) { pid = ''; var a = 'abcdefghijklmnopqrstuvwxyz0123456789'; for (var i = 0; i < 20; i++) { pid += a[Math.floor(Math.random() * a.length)]; } ls('suorni_rk_pid', pid); }
var name = ls('suorni_rk_name') || '';
var S = { room: CFG.room, v: 0, pz: null, cells: {}, pl: [], me: -1, done: 0, t0: 0, wd: {}, sel: -1, dir: 'r', err: '', skew: 0, busy: false, bad: -1 };
var timer = null;
function api(path, data) {
  var b = CFG.api + path; data = data || {}; data.pid = pid;
  return fetch(b + (b.indexOf('?') > -1 ? '' : ''), { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data) })
    .then(function (r) { return r.json().then(function (j) { if (!r.ok) { var e = new Error(j && j.message || 'Virhe'); e.status = r.status; throw e; } return j; }); });
}
function el(t, c, h) { var e = document.createElement(t); if (c) { e.className = c; } if (h !== undefined) { e.innerHTML = h; } return e; }
function esc(s) { return String(s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }
function shareUrl() { return CFG.home + (CFG.home.indexOf('?') > -1 ? '&' : '?') + 'ristikko=' + S.room; }

/* ---------- aloitus ---------- */
function start(msg) {
  app.innerHTML = '';
  var t = el('div', 'top', '<h1>🧩 Kuvaristikko</h1><a class="back" href="' + esc(CFG.home) + '">← Suorni</a>');
  var c = el('div', 'card');
  c.innerHTML = '<p>Arvaa kuvista sanat! Pelaa yksin tai kutsu kavereita samaan ristikkoon: täytätte ruudukkoa yhdessä, mutta jokainen kerää omia pisteitä. Ruutuun tulee kirjain vain, jos se on oikein.</p>'
    + '<input id="nm" maxlength="16" placeholder="Nimimerkkisi" value="' + esc(name) + '">'
    + (msg ? '<div class="err">' + esc(msg) + '</div>' : '');
  var r = el('div', 'row');
  var b1 = el('button', 'btn', 'Aloita uusi ristikko'); b1.onclick = function () { var n = document.getElementById('nm').value; saveName(n); create(); };
  r.appendChild(b1);
  c.appendChild(r);
  var j = el('div', 'row');
  j.innerHTML = '<input id="cd" maxlength="5" placeholder="Huonekoodi" style="width:140px;text-transform:uppercase">';
  var b2 = el('button', 'btn sec', 'Liity huoneeseen');
  b2.onclick = function () { var cd = (document.getElementById('cd').value || '').toUpperCase().replace(/[^A-Z0-9]/g, ''); if (cd.length < 5) { return; } saveName(document.getElementById('nm').value); S.room = cd; join(); };
  j.appendChild(b2); c.appendChild(j);
  app.appendChild(t); app.appendChild(c);
}
function saveName(n) { name = (n || '').trim().slice(0, 16); ls('suorni_rk_name', name); }
function create() {
  api('new', { name: name }).then(function (j) { S.room = j.room; try { history.replaceState(null, '', shareUrl()); } catch (e) {} join(); })
    .catch(function (e) { start(e.message); });
}
function join() {
  S.v = 0; S.pz = null; S.cells = {}; S.done = 0; S.sel = -1;
  poll(true);
}

/* ---------- tila ---------- */
function poll(first) {
  clearTimeout(timer);
  api('state', { room: S.room, name: first ? name : '', v: S.v }).then(function (j) { applyState(j); })
    .catch(function (e) { if (e.status === 404) { S.room = ''; start('Huonetta ei löytynyt tai se on vanhentunut.'); return; } })
    .then(function () { if (S.room) { timer = setTimeout(function () { poll(false); }, document.hidden ? 8000 : 2000); } });
}
function applyState(j) {
  if (j.now) { S.skew = j.now - Math.floor(Date.now() / 1000); }
  if (j.same) { return; }
  var full = !S.pz;
  S.v = j.v; S.cells = j.cells || {}; S.pl = j.pl || []; S.me = j.me; S.done = j.done; S.t0 = j.t0; S.wd = j.wd || {};
  if (j.pz) { S.pz = j.pz; }
  if (!S.pz) { return; }
  if (!S.room) { return; }
  try { history.replaceState(null, '', shareUrl()); } catch (e) {}
  if (full || !document.getElementById('board')) { build(); } else { paint(); }
  if (S.done) { showEnd(); }
}

/* ---------- ruudukko ---------- */
function layoutMaps() {
  var P = S.pz, C = P.c, map = {}, pic = {}, ents = P.ents, i, e, j;
  for (i = 0; i < ents.length; i++) {
    e = ents[i];
    var dr = e.d === 'r' ? 0 : 1, dc = e.d === 'r' ? 1 : 0;
    e.cells = [];
    for (j = 0; j < e.len; j++) { var k = (e.r + dr * j) * C + e.c + dc * j; e.cells.push(k); (map[k] = map[k] || {})[e.d] = i; }
    pic[(e.r - dr) * C + (e.c - dc)] = i;
  }
  S.map = map; S.pic = pic;
}
function build() {
  layoutMaps();
  var P = S.pz;
  app.innerHTML = '';
  var top = el('div', 'top', '<h1>🧩 Kuvaristikko</h1>');
  var sh = el('button', 'btn sm sec', '👥 Kutsu'); sh.onclick = share; top.appendChild(sh);
  var pls = el('div', 'pls'); pls.id = 'pls';
  var meta = el('div', 'meta'); meta.id = 'meta';
  var bd = el('div', 'board'); bd.id = 'board'; bd.style.gridTemplateColumns = 'repeat(' + P.c + ',1fr)';
  var hint = el('div', 'hint'); hint.id = 'hint';
  var kb = el('div', 'kb'); kb.id = 'kb';
  app.appendChild(top); app.appendChild(pls); app.appendChild(meta); app.appendChild(bd); app.appendChild(hint); app.appendChild(kb);
  var n = P.r * P.c;
  for (var i = 0; i < n; i++) {
    (function (i) {
      var c = el('div', 'cell');
      c.dataset.i = i;
      if (S.pic[i] !== undefined) {
        var e = P.ents[S.pic[i]];
        c.className = 'cell p'; c.innerHTML = '<span>' + e.e + '</span><span class="ar ' + e.d + '">' + (e.d === 'r' ? '▶' : '▼') + '</span>';
        c.onclick = function () { var en = S.pic[i]; selectEnt(en); };
      } else if (S.map[i]) {
        c.className = 'cell l'; c.onclick = function () { tapCell(i); };
      } else { c.className = 'cell x'; }
      bd.appendChild(c);
    })(i);
  }
  var rows = ['QWERTYUIOPÅ', 'ASDFGHJKLÖÄ', 'ZXCVBNM'];
  rows.forEach(function (r, ri) {
    var row = el('div', 'kr');
    r.split('').forEach(function (ch) { var b = el('button', 'k', ch); b.onclick = function () { typeCh(ch); }; row.appendChild(b); });
    if (ri === 2) { var d = el('button', 'k wide', '⌫'); d.onclick = function () { moveBack(); }; row.appendChild(d); }
    kb.appendChild(row);
  });
  document.onkeydown = function (ev) {
    if (ev.ctrlKey || ev.metaKey || ev.altKey) { return; }
    var k = ev.key;
    if (k.length === 1 && /\p{L}/u.test(k)) { typeCh(k.toUpperCase()); ev.preventDefault(); }
    else if (k === 'Backspace') { moveBack(); ev.preventDefault(); }
    else if (k === 'ArrowRight' || k === 'ArrowDown') { step(1); ev.preventDefault(); }
    else if (k === 'ArrowLeft' || k === 'ArrowUp') { step(-1); ev.preventDefault(); }
  };
  if (S.sel < 0) { firstEmpty(); }
  paint();
}
function curEnt() {
  var m = S.map[S.sel]; if (!m) { return -1; }
  if (m[S.dir] !== undefined) { return m[S.dir]; }
  return m.r !== undefined ? m.r : m.d;
}
function selectEnt(en) {
  var e = S.pz.ents[en]; S.dir = e.d;
  var t = -1; for (var j = 0; j < e.cells.length; j++) { if (!S.cells[e.cells[j]]) { t = e.cells[j]; break; } }
  S.sel = t >= 0 ? t : e.cells[0]; paint();
}
function tapCell(i) {
  var m = S.map[i];
  if (S.sel === i && m.r !== undefined && m.d !== undefined) { S.dir = S.dir === 'r' ? 'd' : 'r'; }
  else { S.sel = i; if (m[S.dir] === undefined) { S.dir = m.r !== undefined ? 'r' : 'd'; } }
  paint();
}
function firstEmpty() {
  for (var i = 0; i < S.pz.ents.length; i++) { var e = S.pz.ents[i]; for (var j = 0; j < e.cells.length; j++) { if (!S.cells[e.cells[j]]) { S.sel = e.cells[j]; S.dir = e.d; return; } } }
  S.sel = S.pz.ents[0].cells[0];
}
function step(d) {
  var en = curEnt(); if (en < 0) { return; }
  var e = S.pz.ents[en], k = e.cells.indexOf(S.sel) + d;
  if (k >= 0 && k < e.cells.length) { S.sel = e.cells[k]; paint(); }
}
function advance() {
  var en = curEnt(); if (en < 0) { return; }
  var e = S.pz.ents[en], k = e.cells.indexOf(S.sel);
  for (var j = 1; j <= e.cells.length; j++) { var t = e.cells[(k + j) % e.cells.length]; if (!S.cells[t]) { S.sel = t; return; } }
  /* sana valmis: siirry seuraavaan keskeneräiseen sanaan */
  for (var a = 0; a < S.pz.ents.length; a++) { var f = S.pz.ents[a]; for (var q = 0; q < f.cells.length; q++) { if (!S.cells[f.cells[q]]) { S.sel = f.cells[q]; S.dir = f.d; return; } } }
}
function moveBack() { step(-1); }
function typeCh(ch) {
  if (S.done || S.me < 0 || S.sel < 0 || S.busy) { return; }
  if (S.cells[S.sel]) { advance(); paint(); if (S.cells[S.sel]) { return; } }
  var at = S.sel; S.busy = true;
  api('put', { room: S.room, i: at, ch: ch }).then(function (j) {
    S.busy = false;
    var wasOk = j.right === true, wasBad = j.right === false;
    S.v = j.v; S.cells = j.cells; S.pl = j.pl; S.me = j.me; S.done = j.done; S.wd = j.wd || {};
    if (wasOk) { advance(); }
    if (wasBad) { S.bad = at; }
    paint();
    if (wasBad) { setTimeout(function () { S.bad = -1; paint(); }, 400); }
    if (S.done) { showEnd(); }
  }).catch(function () { S.busy = false; });
}
function paint() {
  var bd = document.getElementById('board'); if (!bd) { return; }
  var P = S.pz, en = curEnt(), hl = {};
  if (en >= 0) { P.ents[en].cells.forEach(function (k) { hl[k] = 1; }); }
  var cs = bd.children;
  for (var i = 0; i < cs.length; i++) {
    var c = cs[i];
    if (c.classList.contains('l')) {
      var f = S.cells[i], cls = 'cell l';
      if (f) { cls += ' f'; c.style.setProperty('--c', COL[f[1] % COL.length]); c.textContent = f[0]; }
      else { c.textContent = ''; c.style.removeProperty('--c'); }
      if (hl[i]) { cls += ' w'; }
      if (i === S.sel) { cls += ' sel'; }
      if (i === S.bad) { cls += ' bad'; }
      c.className = cls;
    } else if (c.classList.contains('p')) {
      c.classList.toggle('on', S.pic[i] === en);
    }
  }
  var h = document.getElementById('hint');
  if (en >= 0) { var e = P.ents[en]; h.innerHTML = '<span class="e">' + e.e + '</span><span>' + esc(e.h) + ' · ' + e.len + ' kirjainta</span>'; }
  var pls = document.getElementById('pls'); pls.innerHTML = '';
  S.pl.forEach(function (p, k) {
    var d = el('div', 'pl' + (k === S.me ? ' me' : ''), esc(p.n) + ' <b>' + p.s + '</b>'); d.style.setProperty('--c', COL[k % COL.length]); pls.appendChild(d);
  });
  var tot = 0, got = 0; for (var q = 0; q < P.r * P.c; q++) { if (S.map[q]) { tot++; if (S.cells[q]) { got++; } } }
  document.getElementById('meta').innerHTML = '<span>Huone <b>' + esc(S.room) + '</b></span><span>' + got + ' / ' + tot + ' ruutua</span>';
}
function share() {
  var u = shareUrl(), t = 'Tule ratkomaan kuvaristikkoa Suornissa! Huonekoodi ' + S.room;
  if (navigator.share) { navigator.share({ title: 'Kuvaristikko', text: t, url: u }).catch(function () {}); return; }
  try { navigator.clipboard.writeText(u).then(function () { alert('Linkki kopioitu: ' + u); }); } catch (e) { prompt('Kopioi linkki', u); }
}
function fmt(s) { var m = Math.floor(s / 60), r = s % 60; return m + ':' + (r < 10 ? '0' : '') + r; }
function showEnd() {
  if (document.getElementById('end')) { return; }
  var ov = el('div', 'ov'); ov.id = 'end';
  var c = el('div', 'card');
  var order = S.pl.map(function (p, k) { return { p: p, k: k }; }).sort(function (a, b) { return b.p.s - a.p.s; });
  var h = '<h2 style="margin:0">🎉 Ristikko valmis!</h2><p>Aika ' + fmt(Math.max(0, S.done - S.t0)) + '. Yhdessä selvittiin, ja pisteet ratkaisevat parhaan.</p><div class="rank">';
  order.forEach(function (o, n) { h += '<div style="--c:' + COL[o.k % COL.length] + '"><span>' + (n === 0 ? '👑 ' : '') + esc(o.p.n) + '</span><b>' + o.p.s + ' p</b></div>'; });
  c.innerHTML = h + '</div>';
  var r = el('div', 'row');
  var b1 = el('button', 'btn', 'Uusi ristikko'); b1.onclick = function () { clearTimeout(timer); S.room = ''; document.onkeydown = null; create(); };
  var b2 = el('button', 'btn sec', 'Sulje'); b2.onclick = function () { ov.remove(); };
  r.appendChild(b1); r.appendChild(b2); c.appendChild(r); ov.appendChild(c); document.body.appendChild(ov);
}

if (S.room) { join(); } else { start(); }
})();

/* Taajuus <-> Kuvaristikko -silta (lisää Koodaus-istunto 22.11.2026; Peli-istunto saa muokata vapaasti).
   Ristikko (suorni.fi/?ristikko=1) kirjoittaa ratkaisusta bonuksen selaimen tallennukseen (sama alkuperä kuin peli):
   localStorage 'suorni_rk_ledger' = [{id, t, mins, coin, left, pts}].
   Tämä tiedosto ottaa ne vastaan pelin testikoukun (window.__taajuus: state, save, render, fmt) kautta, joten pelin koodiin ei tarvitse koskea:
   +mins minuuttia nykyistä tuottoa (S.coins / S.total / S.earn) ja buffi 'Ristikko' (S.buffs: tuotto x coin, left s).
   Tuotto/s mitataan itse S.total:n muutoksesta (mediaani viimeisistä sekunneista), jotta poissaolon hyppy ei vääristä.
   Kuittaus: S.rkDone[id] (viimeiset 60), joten sama bonus ei tule kahdesti. Yli 3 vrk vanhoja ei oteta vastaan.
   Lisäksi otsikkoon tulee 🧩-painike, joka vie ristikkoon. Jos window.__taajuus poistetaan, silta jää hiljaa pois käytöstä. */
(function () {
  'use strict';
  var LK = 'suorni_rk_ledger', samples = [], lastTotal = null, lastT = 0;
  function T() { return window.__taajuus; }
  function S() { return T() && T().state ? T().state() : null; }
  function clamp(v, a, b) { v = +v; if (!(v === v)) { v = a; } return Math.max(a, Math.min(b, v)); }
  function sample() {
    var s = S(); if (!s) { return; }
    var now = Date.now();
    if (lastTotal !== null && now - lastT > 400 && now - lastT < 3000) {
      var d = (s.total - lastTotal) / ((now - lastT) / 1000);
      if (d >= 0) { samples.push(d); if (samples.length > 15) { samples.shift(); } }
    }
    lastTotal = s.total; lastT = now;
  }
  function rate() {
    if (samples.length < 4) { return 0; }
    var a = samples.slice().sort(function (x, y) { return x - y; });
    return a[Math.floor(a.length / 2)];
  }
  function toast(t) {
    var e = document.getElementById('toast'); if (!e) { return; }
    e.textContent = t; e.classList.add('on');
    clearTimeout(toast._t); toast._t = setTimeout(function () { e.classList.remove('on'); }, 5000);
  }
  function addBuff(s, n, left, coin) {
    if (!Array.isArray(s.buffs)) { s.buffs = []; }
    for (var i = 0; i < s.buffs.length; i++) { if (s.buffs[i].n === n) { s.buffs[i] = { n: n, left: left, lis: 1, coin: coin }; return; } }
    s.buffs.push({ n: n, left: left, lis: 1, coin: coin });
  }
  function apply() {
    var s = S(); if (!s) { return; }
    var raw = null; try { raw = localStorage.getItem(LK); } catch (e) { return; }
    if (!raw) { return; }
    if (samples.length < 4) { return; }   /* odotetaan, että tuotto on mitattu */
    var L; try { L = JSON.parse(raw); } catch (e) { try { localStorage.removeItem(LK); } catch (e2) {} return; }
    try { localStorage.removeItem(LK); } catch (e) {}   /* ensin poisto, ettei toinen välilehti ota samoja uudestaan */
    if (!Array.isArray(L)) { return; }
    if (!s.rkDone || typeof s.rkDone !== 'object') { s.rkDone = {}; }
    var got = 0, sum = 0, buffTxt = '';
    L.forEach(function (it) {
      if (!it || typeof it.id !== 'string' || s.rkDone[it.id] || !(Date.now() - (+it.t || 0) < 3 * 864e5)) { return; }
      s.rkDone[it.id] = Date.now();
      var inc = Math.max(20, rate() * 60 * clamp(it.mins, 0, 10));
      s.coins += inc; s.total += inc; s.earn += inc; sum += inc; got++;
      var cm = clamp(it.coin, 1, 1.5);
      if (cm > 1) { addBuff(s, 'Ristikko', clamp(it.left, 60, 900), cm); buffTxt = ' ja tuotto ×' + cm; }
    });
    var ks = Object.keys(s.rkDone);
    if (ks.length > 60) { ks.sort(function (a, b) { return s.rkDone[a] - s.rkDone[b]; }).slice(0, ks.length - 60).forEach(function (k) { delete s.rkDone[k]; }); }
    if (got) {
      lastTotal = s.total; lastT = Date.now();
      try { T().save(); T().render(true); } catch (e) {}
      toast('🧩 Ristikko-bonus: +$ ' + (T().fmt ? T().fmt(sum) : Math.round(sum)) + buffTxt);
    }
  }
  function mount() {
    var snd = document.getElementById('snd'); if (!snd || document.getElementById('rkLink')) { return; }
    var root = location.origin + location.pathname.split('/wp-content/')[0] + '/';
    var a = document.createElement('a');
    a.id = 'rkLink'; a.className = 'snd'; a.href = root + '?ristikko=1&src=peli'; a.target = '_blank'; a.rel = 'noopener';
    a.title = 'Kuvaristikko: ratkaise ja saat bonuksen peliin'; a.setAttribute('aria-label', 'Kuvaristikko'); a.style.textDecoration = 'none'; a.style.fontSize = '18px'; a.style.marginRight = '8px';
    a.textContent = '🧩';
    snd.parentNode.insertBefore(a, snd);
  }
  if (!T()) { return; }
  mount();
  setInterval(sample, 1000);
  setInterval(apply, 3000);
  document.addEventListener('visibilitychange', function () { if (!document.hidden) { lastTotal = null; setTimeout(apply, 1500); } });
})();

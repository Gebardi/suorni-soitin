/* Taajuus – palvelutyöntekijä: verkko ensin, välimuisti offline-tilaan */
var CACHE = 'taajuus-v18';
var SHELL = ['./', 'index.html', 'manifest.webmanifest', 'icon.svg', 'icon-192.png', 'icon-512.png'];
self.addEventListener('install', function(e){
  e.waitUntil(caches.open(CACHE).then(function(c){ return c.addAll(SHELL); }).then(function(){ return self.skipWaiting(); }));
});
self.addEventListener('activate', function(e){
  e.waitUntil(caches.keys().then(function(ks){
    return Promise.all(ks.filter(function(k){ return k !== CACHE; }).map(function(k){ return caches.delete(k); }));
  }).then(function(){ return self.clients.claim(); }));
});
self.addEventListener('fetch', function(e){
  var req = e.request;
  var u = new URL(req.url);
  /* vain pelin omat tiedostot; REST ja äänitiedostot menevät suoraan verkosta */
  if(req.method !== 'GET' || u.origin !== location.origin || u.pathname.indexOf(new URL(self.registration.scope).pathname) !== 0) return;
  if(u.search.indexOf('chk=') >= 0) return;   /* päivitystarkistus: aina verkosta */
  /* verkko ensin (aina tuorein versio), välimuisti vain offline-tilassa */
  e.respondWith(fetch(req, {cache:'no-cache'}).then(function(res){
    if(res && res.ok){ var cp = res.clone(); caches.open(CACHE).then(function(c){ c.put(req, cp); }); }
    return res;
  }).catch(function(){ return caches.match(req, {ignoreSearch:true}); }));
});

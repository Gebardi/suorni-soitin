(function(){
document.querySelectorAll('.sk:not([data-ok])').forEach(function(root){
	root.setAttribute('data-ok','1');
	function q(s){return root.querySelector(s)}
	function attr(n){return root.getAttribute(n)}
	function J(n,d){try{var v=JSON.parse(attr(n));return v==null?d:v}catch(e){return d}}
	var T=J('data-tracks',[]),LS=J('data-lists',[]),AD=J('data-ads',{on:false,min:3,max:3}),SC=J('data-sched',null),SH=false,nonce=attr('data-nonce'),ver=attr('data-ver')||'';
	var TTS=J('data-tts',{}),DJS=J('data-dj',{}),ANN=J('data-anns',[]),TG=J('data-targets',[]),CL=J('data-clients',[]),cur=TG.length?TG[0].id:'';
	try{var sv=localStorage.getItem('suorni_kt');if(sv&&TG.some(function(x){return x.id===sv}))cur=sv}catch(e){}
	var U={cmd:attr('data-url'),tracks:attr('data-tu'),order:attr('data-order-url'),lists:attr('data-lists-url'),state:attr('data-state-url'),stats:attr('data-stats-url'),reset:attr('data-reset-url'),addlink:attr('data-addlink-url'),dur:attr('data-dur-url'),sunodur:attr('data-sunodur-url'),del:attr('data-delete-url'),ads:attr('data-ads-url'),sched:attr('data-sched-url'),targets:attr('data-targets-url'),clients:attr('data-clients-url'),ann:attr('data-ann-url'),dj:attr('data-dj-url'),ttscfg:attr('data-tts-cfg-url'),ttstest:attr('data-tts-test-url')};
		function tq(u){return u+(u.indexOf('?')>-1?'&':'?')+'t='+encodeURIComponent(cur)}
		function curT(){for(var i=0;i<TG.length;i++)if(TG[i].id===cur)return TG[i];return TG[0]||{}}
	var st=q('[data-st]'),L=q('[data-l]'),tot=q('[data-tot]');
	var busy=false,lsBusy=false,open={};
	function say(m){st.textContent=m}
	function fmt(s){s=Math.max(0,Math.round(s));return Math.floor(s/60)+':'+('0'+s%60).slice(-2)}
	function fmtTot(s){var h=Math.floor(s/3600),m=Math.floor(s%3600/60);return h?h+' h '+m+' min':m+' min '+(s%60)+' s'}
	function api(m,u,b){
		var o={method:m,credentials:'same-origin',cache:'no-store',headers:{'X-WP-Nonce':nonce}};
		if(b!==undefined){o.headers['Content-Type']='application/json';o.body=JSON.stringify(b)}
		if(m==='GET')u+=(u.indexOf('?')>-1?'&':'?')+'_='+Date.now();
		return fetch(u,o).then(function(r){if(!r.ok)throw new Error(r.status);return r.json()});
	}
	function byId(id){for(var i=0;i<T.length;i++){if(T[i].id===id)return T[i]}return null}

	/* Komennot */
	function send(a,id,label){
		say('Lähetetään…');
		api('POST',U.cmd,{a:a,id:id||0,t:cur})
			.then(function(){say('Lähetetty: '+label+' (puhelin reagoi parissa sekunnissa)')})
			.catch(function(){say('Lähetys epäonnistui. Kirjaudu uudelleen ja lataa sivu.')});
	}
	root.querySelectorAll('.k button').forEach(function(b){
		b.onclick=function(){send(b.getAttribute('data-a'),0,b.textContent.trim())};
	});

	/* Äänenvoimakkuus */
	var vs=q('[data-vol]'),vv=q('[data-vv]'),vt=null,vlast=0;
	function sendVol(){
		api('POST',U.cmd,{a:'vol',v:+vs.value,t:cur})
			.then(function(){say('Äänenvoimakkuus '+vs.value+' % lähetetty')})
			.catch(function(){say('Äänenvoimakkuuden lähetys epäonnistui.')});
	}
	if(vs){
		vv.textContent=vs.value+' %';
		vs.oninput=function(){
			vv.textContent=vs.value+' %';
			clearTimeout(vt);
			var d=Date.now()-vlast;
			vt=setTimeout(function(){vlast=Date.now();sendVol()},d>400?0:400-d);
		};
	}

	/* Mainosten ja kuulutusten voimakkuus */
	var lvs=q('[data-lv]'),lvv=q('[data-lvv]'),lvt=null;
	if(lvs){
		lvv.textContent=lvs.value+' %';
		lvs.oninput=function(){
			lvv.textContent=lvs.value+' %';clearTimeout(lvt);
			lvt=setTimeout(function(){api('POST',U.cmd,{a:'lv',v:+lvs.value,t:cur}).then(function(){say('Mainosten ja kuulutusten voimakkuus '+lvs.value+' % tallennettu')}).catch(function(){say('Tallennus epäonnistui.')})},400);
		};
	}

	/* Nyt soi */
	var np={},npAt=0;
	function showNp(){
		var tt=q('[data-npt]'),ss=q('[data-nps]'),pg=q('[data-pgi]');
		var tr=np.id?byId(np.id):null;
		L.querySelectorAll('.rw').forEach(function(r){r.classList.toggle('on',np.id&&+r.getAttribute('data-id')===np.id)});
		if(!tr){tt.textContent='Ei tietoa soittimesta';ss.textContent='Yhdistä puhelin alta, niin näet täällä mitä siellä soi.';pg.style.width='0';return}
		var age=(np.now||0)-(np.t||0);
		if(np.playing&&age>30){tt.textContent=tr.title+' – '+tr.artist;ss.textContent='Puhelin ei ole raportoinut hetkeen.';pg.style.width='0';return}
		var pos=np.pos||0;
		if(np.playing)pos+=age+(Date.now()-npAt)/1000;
		if(np.dur)pos=Math.min(pos,np.dur);
		tt.textContent=tr.title+' – '+tr.artist;
		ss.textContent=(np.playing?'▶ ':'⏸ ')+fmt(pos)+(np.dur?' / '+fmt(np.dur):'')+(np.ver?' · puhelimen soitin v'+np.ver:'');
		pg.style.width=(np.dur?Math.min(100,pos/np.dur*100):0)+'%';
	}
	function pollState(){
		api('GET',tq(U.state)).then(function(s){np=s||{};npAt=Date.now();showNp()}).catch(function(){});
	}

	/* Kappalelista ja järjestys */
	var saveT=null;
	function saveOrder(){
		clearTimeout(saveT);busy=true;
		saveT=setTimeout(function(){
			api('POST',U.order,{ids:T.map(function(t){return t.id})})
				.then(function(){say('Järjestys tallennettu.')})
				.catch(function(){say('Järjestyksen tallennus epäonnistui.')})
				.then(function(){busy=false});
		},600);
	}
	function move(i,d){
		var j=i+d;if(j<0||j>=T.length)return;
		var x=T.splice(i,1)[0];T.splice(j,0,x);build();saveOrder();
	}
	function parseDur(s){
		s=String(s||'').trim();if(!s)return 0;
		var m=s.match(/^(\d+):(\d{1,2})$/);
		if(m)return (+m[1])*60+(+m[2]);
		return /^\d+$/.test(s)?+s:0;
	}
	function setDur(i){
		var t=T[i];if(!t)return;
		var v=prompt('Kappaleen "'+t.title+'" kesto (esim. 3:25):',t.dur>0?fmt(t.dur):'');
		if(v===null)return;
		var d=parseDur(v);
		if(d<5||d>3600){say('Anna kesto muodossa m:ss, esim. 3:25.');return}
		busy=true;
		api('POST',U.dur,{id:t.id,dur:d})
			.then(function(r){if(!r.ok)throw 0;t.dur=d;build();say('Kesto tallennettu: '+fmt(d))})
			.catch(function(){say('Keston tallennus epäonnistui.')})
			.then(function(){busy=false});
	}
	function removeTrack(i){
		var t=T[i];if(!t)return;
		var ext=String(t.url).indexOf(location.origin)!==0;
		var msg=ext?'Poistetaanko linkki "'+t.title+'" soittimesta? (Sunossa oleva kappale säilyy.)':'Poistetaanko "'+t.title+'" pysyvästi? Tiedosto poistuu myös WordPressin mediakirjastosta.';
		if(!confirm(msg))return;
		busy=true;say('Poistetaan…');
		api('POST',U.del,{id:t.id})
			.then(function(r){
				if(r.status!=='deleted'){say(r.message||'Poisto epäonnistui.');return}
				T.splice(i,1);LS.forEach(function(l){l.ids=l.ids.filter(function(x){return x!==t.id})});
				build();buildLists();say('Poistettu: '+t.title);
			})
			.catch(function(){say('Poisto epäonnistui. Kirjaudu uudelleen ja lataa sivu.')})
			.then(function(){busy=false});
	}
	function build(){
		L.innerHTML='';
		var s=0,miss=0,cnt=0;
		T.forEach(function(t,i){
			if(!t.ad){cnt++;if(t.dur>0)s+=t.dur;else miss++}
			var row=document.createElement('div');row.className='rw';row.setAttribute('data-id',t.id);
			var b=document.createElement('button');b.className='tr';
			b.innerHTML='<b></b><span></span>';
			b.querySelector('b').textContent=(t.ad?'📢 ':'')+t.title;
			var extT=String(t.url).indexOf(location.origin)!==0;
			b.querySelector('span').textContent=t.artist+(t.dur>0?' · '+fmt(t.dur):(extT?' · ei kestoa':''));
			b.onclick=function(){send('track',t.id,t.title)};
			var up=document.createElement('button');up.className='mv';up.textContent='↑';up.setAttribute('aria-label','Siirrä ylös');up.disabled=(i===0);up.onclick=function(){move(i,-1)};
			var dn=document.createElement('button');dn.className='mv';dn.textContent='↓';dn.setAttribute('aria-label','Siirrä alas');dn.disabled=(i===T.length-1);dn.onclick=function(){move(i,1)};
			var tm=null;
			if(extT){tm=document.createElement('button');tm.className='mv';tm.textContent='⏱';tm.setAttribute('aria-label','Aseta kesto');tm.title='Aseta kesto (tarvitaan automaattiseen siirtymiseen)';tm.onclick=function(){setDur(i)}}
			var rm=document.createElement('button');rm.className='mv rm';rm.textContent='🗑';rm.setAttribute('aria-label','Poista kappale');rm.onclick=function(){removeTrack(i)};
			row.appendChild(b);row.appendChild(up);row.appendChild(dn);if(tm)row.appendChild(tm);row.appendChild(rm);L.appendChild(row);
		});
		if(!T.length){L.textContent='Ei vielä kappaleita.'}
		tot.textContent=cnt+(cnt===1?' kappale':' kappaletta')+(cnt&&s>0?' · '+(miss?'vähintään ':'')+fmtTot(s)+(miss?' ('+miss+' ilman kestoa)':''):'');
		showNp();
	}

	/* Soittolistat */
	var lsT=null;
	function saveLists(){
		clearTimeout(lsT);lsBusy=true;
		lsT=setTimeout(function(){
			api('POST',U.lists,{lists:LS})
				.then(function(r){
					if(r&&r.lists){for(var i=0;i<r.lists.length&&i<LS.length;i++){LS[i].id=r.lists[i].id}}
					say('Soittolistat tallennettu.');
				})
				.catch(function(){say('Soittolistojen tallennus epäonnistui.')})
				.then(function(){lsBusy=false});
		},500);
	}
	/* Satunnaistoisto */
	var shb=q('[data-shuf]');
	function showSh(){if(shb)shb.textContent='🔀 Satunnaistoisto: '+(SH?'päällä':'pois')}
	if(shb){shb.onclick=function(){
		SH=!SH;showSh();
		api('POST',U.cmd,{a:'shuf',v:SH?1:0,t:cur})
			.then(function(){say('Satunnaistoisto '+(SH?'päällä':'pois')+' (puhelin päivittyy parissa sekunnissa)')})
			.catch(function(){SH=!SH;showSh();say('Asetuksen lähetys epäonnistui.')});
	}}
	showSh();

	/* Aikataulu */
	var scT=null,scBusy=false,scStat=null;
	function saveSched(){
		clearTimeout(scT);scBusy=true;
		scT=setTimeout(function(){
			api('POST',U.sched,{on:SC.on,days:{wd:SC.wd,sa:SC.sa,su:SC.su}})
				.then(function(r){SC=r;say('Aikataulu tallennettu.');showSched()})
				.catch(function(){say('Aikataulun tallennus epäonnistui.')})
				.then(function(){scBusy=false});
		},600);
	}
	function showSched(){
		if(!scStat||!SC)return;
		scStat.textContent=SC.on?('Nyt klo '+SC.now+': soitto '+(SC.open?'sallittu':'ei sallittu')+' aikataulun mukaan.'):'Aikataulu ei ole käytössä: musiikki soi aina.';
	}
	function buildSched(){
		var box=q('[data-sched-box]');if(!box||!SC)return;box.innerHTML='';
		var p=document.createElement('p');p.className='st';
		p.textContent='Aikataulu koskee yhdistettyä puhelinta. Soittoajan päättyessä musiikki pysähtyy, ja soittoajan alkaessa se käynnistyy itsestään. Kellonaika on sivuston aikavyöhykkeen (WordPressin asetus) mukainen. Soittoajan pitää alkaa ja päättyä samana päivänä.';
		box.appendChild(p);
		var on=document.createElement('label');on.style.display='block';
		var cb=document.createElement('input');cb.type='checkbox';cb.checked=!!SC.on;
		cb.onchange=function(){SC.on=cb.checked;saveSched()};
		on.appendChild(cb);on.appendChild(document.createTextNode(' Soita vain aikataulun mukaan'));box.appendChild(on);
		[['wd','Ma–Pe'],['sa','La'],['su','Su']].forEach(function(d){
			var r=document.createElement('div');r.className='sc-r';
			var lb=document.createElement('b');lb.textContent=d[1];
			var c=document.createElement('input');c.type='checkbox';c.checked=!!SC[d[0]].on;
			var f=document.createElement('input');f.type='time';f.value=SC[d[0]].from;f.className='sc-t';
			var t=document.createElement('input');t.type='time';t.value=SC[d[0]].to;t.className='sc-t';
			function upd(){
				if(f.value&&t.value&&f.value>=t.value){say('Alkamisajan pitää olla ennen päättymisaikaa.');return}
				SC[d[0]]={on:c.checked,from:f.value||SC[d[0]].from,to:t.value||SC[d[0]].to};saveSched();
			}
			c.onchange=upd;f.onchange=upd;t.onchange=upd;
			r.appendChild(c);r.appendChild(lb);r.appendChild(f);r.appendChild(document.createTextNode(' – '));r.appendChild(t);
			box.appendChild(r);
		});
		scStat=document.createElement('p');scStat.className='st';box.appendChild(scStat);showSched();
	}

	/* Mainokset */
	var adT=null,adBusy=false;
	function saveAds(){
		clearTimeout(adT);adBusy=true;
		adT=setTimeout(function(){
			var ids=T.filter(function(t){return t.ad}).map(function(t){return t.id});
			api('POST',U.ads,{on:AD.on,min:AD.min,max:AD.max,ids:ids})
				.then(function(){say('Mainosasetukset tallennettu.')})
				.catch(function(){say('Mainosasetusten tallennus epäonnistui.')})
				.then(function(){adBusy=false});
		},500);
	}
	function clampN(v){v=parseInt(v,10);if(!(v>=1))v=1;return Math.min(20,v)}
	function buildAds(){
		var box=q('[data-ads-box]');if(!box)return;box.innerHTML='';
		var p=document.createElement('p');p.className='st';
		p.textContent='Lisää mainos ensin tavallisena kappaleena (linkillä tai tiedostona) ja valitse se alta mainokseksi. Mainokset eivät näy tavallisessa soittolistassa. Ne soivat kappaleen jälkeen ja musiikki jatkuu sen jälkeen normaalisti.';
		box.appendChild(p);
		var on=document.createElement('label');on.style.display='block';
		var cb=document.createElement('input');cb.type='checkbox';cb.checked=!!AD.on;
		cb.onchange=function(){AD.on=cb.checked;saveAds()};
		on.appendChild(cb);on.appendChild(document.createTextNode(' Mainokset käytössä'));box.appendChild(on);
		var r=document.createElement('div');r.className='mk-r';
		var a=document.createElement('input');a.type='number';a.min=1;a.max=20;a.className='mk-n';a.value=AD.min;
		var b=document.createElement('input');b.type='number';b.min=1;b.max=20;b.className='mk-n';b.value=AD.max;
		function ch(){AD.min=clampN(a.value);AD.max=Math.max(AD.min,clampN(b.value));a.value=AD.min;b.value=AD.max;saveAds()}
		a.onchange=ch;b.onchange=ch;
		r.appendChild(document.createTextNode('Soita mainos '));r.appendChild(a);r.appendChild(document.createTextNode(' – '));r.appendChild(b);r.appendChild(document.createTextNode(' kappaleen välein'));
		box.appendChild(r);
		var h=document.createElement('p');h.className='st';h.textContent='Anna sama luku kummassakin, jos haluat mainoksen aina saman kappalemäärän välein. Eri luvuilla väli arvotaan jokaisella kerralla.';box.appendChild(h);
		var pn=document.createElement('div');pn.className='lp';
		T.forEach(function(t){
			var lb=document.createElement('label');
			var c=document.createElement('input');c.type='checkbox';c.checked=!!t.ad;
			c.onchange=function(){t.ad=c.checked;build();saveAds()};
			lb.appendChild(c);lb.appendChild(document.createTextNode(' '+t.title));pn.appendChild(lb);
		});
		box.appendChild(pn);
	}
	function buildLists(){
		var box=q('[data-lists-box]');box.innerHTML='';
		LS.forEach(function(l,li){
			var wrap=document.createElement('div');wrap.className='ls';
			var head=document.createElement('div');head.className='lh';
			var nm=document.createElement('b');nm.textContent=l.name+' ('+l.ids.length+')';
			var ed=document.createElement('button');ed.textContent=open[li]?'Sulje':'Muokkaa';
			var dl=document.createElement('button');dl.textContent='Poista';
			head.appendChild(nm);head.appendChild(ed);head.appendChild(dl);wrap.appendChild(head);
			ed.onclick=function(){open[li]=!open[li];buildLists()};
			dl.onclick=function(){
				if(!confirm('Poistetaanko lista '+l.name+'?'))return;
				LS.splice(li,1);open={};buildLists();saveLists();
			};
			if(open[li]){
				var pn=document.createElement('div');pn.className='lp';
				T.forEach(function(t){
					var lb=document.createElement('label');
					var cb=document.createElement('input');cb.type='checkbox';cb.checked=l.ids.indexOf(t.id)>-1;
					cb.onchange=function(){
						var p=l.ids.indexOf(t.id);
						if(cb.checked&&p<0)l.ids.push(t.id);
						if(!cb.checked&&p>-1)l.ids.splice(p,1);
						nm.textContent=l.name+' ('+l.ids.length+')';saveLists();
					};
					lb.appendChild(cb);lb.appendChild(document.createTextNode(' '+t.title));
					pn.appendChild(lb);
				});
				wrap.appendChild(pn);
			}
			box.appendChild(wrap);
		});
		var add=document.createElement('div');add.className='la';
		var inp=document.createElement('input');inp.placeholder='Uuden listan nimi';
		var bt=document.createElement('button');bt.textContent='Luo lista';
		bt.onclick=function(){
			var n=inp.value.trim();if(!n)return;
			LS.push({id:'',name:n,ids:[]});open[LS.length-1]=true;buildLists();saveLists();
		};
		add.appendChild(inp);add.appendChild(bt);box.appendChild(add);
	}

	/* Kuuntelukerrat */
	function loadStats(){
		api('GET',U.stats).then(function(r){
			var p=(r&&r.plays)||{},rows=[],sum=0;
			T.forEach(function(t){var n=+p[t.id]||0;sum+=n;if(n)rows.push({t:t,n:n})});
			rows.sort(function(a,b){return b.n-a.n});
			var box=q('[data-stats]');box.innerHTML='';
			var h=document.createElement('p');h.className='st';
			h.textContent=rows.length?'Kuuntelukertoja yhteensä: '+sum+' (yhdistämiesi laitteiden kuuntelua ei lasketa).':'Ei vielä kuuntelukertoja. Yhdistämiesi laitteiden kuuntelua ei lasketa.';
			box.appendChild(h);
			rows.slice(0,15).forEach(function(x,i){
				var d=document.createElement('div');d.className='sr';d.textContent=(i+1)+'. '+x.t.title+' – '+x.n;box.appendChild(d);
			});
		}).catch(function(){});
	}

	/* Päivitys: uudet kappaleet ja listat */
	function sig(){return T.map(function(t){return t.id+'|'+t.title+'|'+t.artist+'|'+(t.ad?1:0)}).join('\n')}
	function refresh(){
		if(busy||lsBusy||adBusy||scBusy)return;
		api('GET',U.tracks).then(function(r){
			if(r&&r.ver&&ver&&r.ver!==ver){
				try{
					var t=+sessionStorage.getItem('suorni_rl')||0;
					if(Date.now()-t>300000){sessionStorage.setItem('suorni_rl',String(Date.now()));location.reload();return}
				}catch(e){}
			}
			if(r&&r.sched&&!scBusy){SC.open=r.sched.open;SC.now=r.sched.now;showSched()}
			if(typeof r.sh==='boolean'){SH=r.sh;showSh()}
			if(busy||lsBusy||adBusy||!r||!Array.isArray(r.tracks))return;
			var oldSig=sig(),oldL=JSON.stringify(LS);
			var nT=r.tracks,nL=Array.isArray(r.lists)?r.lists:[];
			var chT=nT.map(function(t){return t.id+'|'+t.title+'|'+t.artist+'|'+(t.ad?1:0)}).join('\n')!==oldSig;
			var chL=JSON.stringify(nL)!==oldL;
			if(chT){T=nT;build();if(r.ads){AD=r.ads}buildAds()}
			if(chT||chL){LS=nL;buildLists()}
		}).catch(function(){});
	}

	/* Lisää kappaleita linkillä */
	var al=q('[data-addlinks]'),ta=q('[data-links]'),lg=q('[data-addlog]');
	function logLine(t){var d=document.createElement('div');d.textContent=t;lg.appendChild(d)}
	function measure(url){
		return new Promise(function(res){
			var a=new Audio(),fin=false;a.preload='metadata';
			try{a.referrerPolicy='no-referrer'}catch(e){}
			function end(v){if(fin)return;fin=true;a.onloadedmetadata=null;a.onerror=null;res(v)}
			a.onloadedmetadata=function(){end(isFinite(a.duration)?Math.round(a.duration):0)};
			a.onerror=function(){end(0)};
			setTimeout(function(){end(0)},15000);
			a.src=url;
		});
	}
	var ats=q('[data-add-theme]'),atn=q('[data-add-theme-new]');
	function buildAddTheme(keep){
		if(!ats)return;
		var v=keep?((ats.value==='__new'&&atn.value.trim())?atn.value.trim():ats.value):'';
		ats.innerHTML='';
		var o=document.createElement('option');o.value='';o.textContent='Teema: ei valittua';ats.appendChild(o);
		LS.forEach(function(l){var x=document.createElement('option');x.value=l.name;x.textContent='Teema: '+l.name;ats.appendChild(x)});
		var n=document.createElement('option');n.value='__new';n.textContent='+ Uusi teema…';ats.appendChild(n);
		ats.value=v;if(ats.value!==v)ats.value='';
		atn.hidden=ats.value!=='__new';
	}
	function defTheme(){
		if(!ats)return '';
		if(ats.value==='__new')return atn.value.trim();
		return ats.value;
	}
	if(ats){ats.onchange=function(){atn.hidden=ats.value!=='__new';if(!atn.hidden)atn.focus()};buildAddTheme()}
	if(al){al.onclick=function(){
		var lines=ta.value.split(/\n+/).map(function(s){return s.trim()}).filter(Boolean);
		if(!lines.length)return;
		al.disabled=true;lg.innerHTML='';
		var i=0,added=0;
		(function step(){
			if(i>=lines.length){
				al.disabled=false;ta.value='';say(added+' kappaletta lisätty.');
				refresh();return;
			}
			var parts=lines[i].split('|').map(function(s){return s.trim()});
			i++;logLine(i+'/'+lines.length+' lisätään…');
			api('POST',U.addlink,{url:parts[0],title:parts[1]||'',artist:parts[2]||'',teema:parts[4]||defTheme()})
				.then(function(r){
					if(r&&Array.isArray(r.lists)){LS=r.lists;buildLists();buildTheme();buildAddTheme(true)}
					if(r.status==='added'){
						added++;logLine('✓ '+r.track.title);
						var given=parseDur(parts[3]);
						if(given>=5)return api('POST',U.dur,{id:r.track.id,dur:given}).catch(function(){});
						return measure(r.track.url).then(function(d){
							if(d>0)return api('POST',U.dur,{id:r.track.id,dur:d}).catch(function(){});
						});
					}
					logLine((r.status==='exists'?'• Jo listalla: ':'✗ ')+(r.message||''));
				})
				.catch(function(){logLine('✗ Lisäys epäonnistui: '+parts[0])})
				.then(step);
		})();
	}}

	/* Kohteet: valinta, yhdistämislinkit, hallinta */
	function el(tag,txt,cls){var e=document.createElement(tag);if(txt!=null)e.textContent=txt;if(cls)e.className=cls;return e}
	function copyTxt(inp){
		(navigator.clipboard?navigator.clipboard.writeText(inp.value):Promise.reject())
			.then(function(){say('Linkki kopioitu.')})
			.catch(function(){inp.select();say('Valitse linkki ja kopioi se käsin.')});
	}
	function linkRow(box,label,url){
		box.appendChild(el('p',label,'st'));
		var i=el('input',null,'lk');i.readOnly=true;i.value=url;i.onclick=function(){i.select()};box.appendChild(i);
		var r=el('div',null,'k2');r.style.marginTop='10px';
		var c=el('button','Kopioi linkki');c.onclick=function(){copyTxt(i)};r.appendChild(c);
		if(navigator.share){var h=el('button','Jaa');h.onclick=function(){navigator.share({title:'Suorni',url:url}).catch(function(){})};r.appendChild(h)}
		box.appendChild(r);
	}
	function pairUrl(t){var b=attr('data-player-page')||'/';return b+(b.indexOf('?')>-1?'&':'?')+'suorni_pair='+encodeURIComponent(t.token)}
	function clientUrl(c){var b=attr('data-client-page')||'/';return b+(b.indexOf('?')>-1?'&':'?')+'avain='+encodeURIComponent(c.key)}
	var thsel=q('[data-theme-sel]');
	function buildTheme(){
		if(ats&&document.activeElement!==ats&&document.activeElement!==atn)buildAddTheme(true);
		if(!thsel)return;
		var v=curT().list||'';
		thsel.innerHTML='';
		var o=el('option','Teema: kaikki kappaleet');o.value='';thsel.appendChild(o);
		LS.forEach(function(l){var x=el('option','Teema: '+l.name);x.value=l.id;thsel.appendChild(x)});
		thsel.value=v;
		if(thsel.value!==v)thsel.value='';
	}
	if(thsel)thsel.onchange=function(){
		var v=thsel.value,t=curT();
		api('POST',U.cmd,{a:'list',v:v,t:cur}).then(function(){t.list=v;say(v?'Teema vaihdettu: puhelin aloittaa uuden teeman parissa sekunnissa.':'Kaikki kappaleet käytössä.')}).catch(function(){say('Teeman vaihto epäonnistui.');buildTheme()});
	};
	setInterval(function(){if(document.activeElement!==thsel)buildTheme()},10000);
	function applyTarget(){
		var t=curT();buildTheme();
		SH=!!t.shuf;showSh();
		if(vs){vs.value=t.vol==null?100:t.vol;vv.textContent=vs.value+' %'}
		np={};showNp();pollState();
	}
	var tsel=q('[data-tsel]'),tw=q('[data-tsel-wrap]');
	function buildSel(){
		tsel.innerHTML='';
		TG.forEach(function(t){var o=el('option',t.name);o.value=t.id;tsel.appendChild(o)});
		tsel.value=cur;tw.hidden=TG.length<2;
	}
	tsel.onchange=function(){cur=tsel.value;try{localStorage.setItem('suorni_kt',cur)}catch(e){}buildPair();applyTarget()};
	function buildPair(){
		var box=q('[data-pair-box]');box.innerHTML='';
		var t=curT();
		linkRow(box,'Avaa tämä osoite puhelimessa kohteelle "'+t.name+'". Tallenna se kirjanmerkiksi tai aloitusnäytölle ja avaa soitin aina sitä kautta:',pairUrl(t));
		var rs=el('button','Vaihda tämän kohteen koodi (katkaisee sen yhdistetyt laitteet)');rs.style.width='100%';
		rs.onclick=function(){
			if(!confirm('Vaihdetaanko koodi? Puhelin pitää yhdistää uudelleen.'))return;
			api('POST',U.reset,{t:cur}).then(function(){location.reload()}).catch(function(){say('Koodin vaihto epäonnistui.')});
		};
		box.appendChild(rs);
	}
	function saveTargets(list,msg){
		return api('POST',U.targets,{targets:list}).then(function(r){TG=r.targets.map(function(n){var o=TG.filter(function(x){return x.id===n.id})[0]||{};n.vol=o.vol==null?100:o.vol;n.shuf=o.shuf||0;return n});
			if(!TG.some(function(x){return x.id===cur}))cur=TG[0].id;
			buildSel();buildPair();buildTargets();buildClients();buildAnn();applyTarget();say(msg||'Tallennettu.')}).catch(function(){say('Tallennus epäonnistui.')});
	}
	function buildTargets(){
		var box=q('[data-targets-box]');box.innerHTML='';
		box.appendChild(el('p','Kohde on yksi soitin (esim. kaksi liikettä tai kaksi puhelinta). Jokaisella on oma yhdistämislinkki ja omat toisto-, ääni- ja satunnaisasetukset. Kappalelista, mainokset ja aikataulu ovat yhteiset.','st'));
		TG.forEach(function(t){
			var r=el('div',null,'la');
			var i=el('input');i.value=t.name;i.setAttribute('aria-label','Nimi');
			var sv=el('button','Tallenna');sv.onclick=function(){saveTargets(TG.map(function(x){return {id:x.id,name:x.id===t.id?i.value:x.name}}),'Nimi tallennettu.')};
			r.appendChild(i);r.appendChild(sv);
			if(TG.length>1){var d=el('button','Poista');d.className='rm';d.onclick=function(){if(confirm('Poistetaanko kohde "'+t.name+'"?'))saveTargets(TG.filter(function(x){return x.id!==t.id}).map(function(x){return {id:x.id,name:x.name}}),'Kohde poistettu.')};r.appendChild(d)}
			box.appendChild(r);
		});
		var r=el('div',null,'la');var i=el('input');i.placeholder='Uuden kohteen nimi';
		var a=el('button','Lisää kohde');a.onclick=function(){if(!i.value.trim())return;saveTargets(TG.map(function(x){return {id:x.id,name:x.name}}).concat([{name:i.value}]),'Kohde lisätty.')};
		r.appendChild(i);r.appendChild(a);box.appendChild(r);
	}


	/* Juontaja */
	function buildDj(){
		var box=q('[data-dj-box]');if(!box)return;box.innerHTML='';
		var d=DJS;
		box.appendChild(el('p','Juontaja puhuu kappaleiden välissä: kertoo kellonajan, sään ja seuraavan kappaleen nimen. Ääni tulee puhelimen omasta puhesyntetisaattorista (suomenkielinen ääni, yleensä naisääni), joten ääni vaihtelee puhelimen mukaan. Se ei ole generatiivinen tekoäly vaan valmiisiin lauseisiin perustuva juontaja. Sään haku tarvitsee puhelimen nettiyhteyden.','st'));
		function row(){var r=el('div',null,'mk-r');box.appendChild(r);return r}
		function chk(r,txt,key){var l=el('label');var c=el('input');c.type='checkbox';c.checked=!!d[key];c.onchange=function(){d[key]=c.checked};l.appendChild(c);l.appendChild(document.createTextNode(' '+txt));r.appendChild(l)}
		function num(r,txt,key,min,max,step){r.appendChild(el('span',txt));var i=el('input',null,'mk-n');i.type='number';i.min=min;i.max=max;i.step=step||1;i.value=d[key];i.onchange=function(){d[key]=parseFloat(i.value)};r.appendChild(i)}
		function txt(r,label,key,ph){r.appendChild(el('span',label));var i=el('input',null,'sc-t');i.value=d[key]==null?'':d[key];i.placeholder=ph||'';i.onchange=function(){d[key]=i.value};r.appendChild(i)}
		var r1=row();chk(r1,'Juontaja käytössä','on');
		var rC=row();chk(rC,'Käytä pilviääntä (Azure, luonnollinen ääni)','cloud');
		var cb=el('div',null,'ls');box.appendChild(cb);
		cb.appendChild(el('p','Luo maksuton Azure-tili ja "Speech"-resurssi (portal.azure.com → Luo resurssi → Speech). Kopioi sieltä Avain ja Alue (esim. westeurope). Avainta ei näytetä tallennuksen jälkeen. Ääntä tehdään vain uusille teksteille, ja valmiit puheet tallennetaan sivustolle. Hinta tarkistetaan Azuren hinnastosta.','st'));
		var ar=el('div',null,'mk-r');
		var ki=el('input',null,'sc-t');ki.type='password';ki.placeholder=TTS.haskey?'Avain tallennettu (kirjoita uusi vaihtaaksesi)':'Azure-avain';ki.style.flex='1';ar.appendChild(ki);
		var rg=el('input',null,'sc-t');rg.value=TTS.region||'westeurope';rg.placeholder='Alue';rg.style.width='130px';ar.appendChild(rg);cb.appendChild(ar);
		var vr=el('div',null,'mk-r');
		var vf=el('select',null,'sc-t');[['fi-FI-NooraNeural','Noora (nainen)'],['fi-FI-SelmaNeural','Selma (nainen)'],['fi-FI-HarriNeural','Harri (mies)']].forEach(function(o){var x=el('option',o[1]);x.value=o[0];vf.appendChild(x)});vf.value=TTS.vfi||'fi-FI-NooraNeural';
		var ve=el('select',null,'sc-t');[['en-US-JennyNeural','Jenny (englanti, nainen)'],['en-US-AriaNeural','Aria (englanti, nainen)'],['en-GB-SoniaNeural','Sonia (brittienglanti, nainen)'],['en-US-GuyNeural','Guy (englanti, mies)']].forEach(function(o){var x=el('option',o[1]);x.value=o[0];ve.appendChild(x)});ve.value=TTS.ven||'en-US-JennyNeural';
		vr.appendChild(vf);vr.appendChild(ve);cb.appendChild(vr);
		var cr=el('div',null,'k2');
		var cs=el('button','Tallenna avain ja äänet');
		cs.onclick=function(){api('POST',U.ttscfg,{key:ki.value,region:rg.value,vfi:vf.value,ven:ve.value}).then(function(r){TTS=r;ki.value='';ki.placeholder=r.haskey?'Avain tallennettu (kirjoita uusi vaihtaaksesi)':'Azure-avain';say('Pilviäänen asetukset tallennettu.')}).catch(function(){say('Tallennus epäonnistui.')})};
		var ct=el('button','Kokeile yhteyttä');
		ct.onclick=function(){say('Kokeillaan…');api('POST',U.ttstest,{}).then(function(r){if(r.ok){say('Yhteys toimii. Soitetaan testiääni.');var a=new Audio(r.url);a.play().catch(function(){say('Yhteys toimii, mutta selain esti toiston. Paina painiketta uudelleen.')})}else say('Virhe: '+r.message)}).catch(function(){say('Kokeilu epäonnistui.')})};
		cr.appendChild(cs);cr.appendChild(ct);cb.appendChild(cr);
		function sel(r,label,key,opts){r.appendChild(el('span',label));var x=el('select',null,'sc-t');opts.forEach(function(o){var op=el('option',o[1]);op.value=o[0];x.appendChild(op)});x.value=d[key]||opts[0][0];x.onchange=function(){d[key]=x.value;vis()};r.appendChild(x)}
		var rE=row();sel(rE,'Puhuu:','every',[['songs','tietyn kappalemäärän välein'],['hour','joka tasatunti'],['half','joka puolen tunnin välein']]);
		var r2=row();r2.appendChild(el('span','Kappalemäärä:'));num(r2,'','min',1,20);r2.appendChild(el('span','–'));num(r2,'','max',1,20);r2.appendChild(el('span','kappaleen välein'));
		function vis(){r2.style.display=(d.every==='hour'||d.every==='half')?'none':''}vis();
		var rT=row();sel(rT,'Kappaleiden nimet:','titles',[['auto','tunnista kieli (suomi/englanti)'],['fi','lue aina suomeksi'],['off','älä lue nimiä']]);
		var r3=row();chk(r3,'Kellonaika','time');chk(r3,'Sää','weather');
		var r3b=row();chk(r3b,'Tervehdys kellonajan mukaan (huomenta / päivää / iltaa)','tod');
		var r3c=row();txt(r3c,'Radion / liikkeen nimi:','station','Suorni Radio');
		var r3d=row();r3d.appendChild(el('span','Omat lauseet (yksi per rivi, juontaja valitsee jokaiseen puheeseen yhden satunnaisesti):'));
		var ph=el('textarea',null,'lk');ph.rows=4;ph.value=d.phrases||'';ph.placeholder='Kiitos kun olet Kamux Lohjan asiakkaana.\nTervetuloa tutustumaan autoihimme.';ph.onchange=function(){d.phrases=ph.value};box.appendChild(ph);
		var r4=row();r4.appendChild(el('span','Seuraavat kappaleet (0–3):'));var n=el('input',null,'mk-n');n.type='number';n.min=0;n.max=3;n.value=d.next;n.onchange=function(){d.next=parseInt(n.value,10)};r4.appendChild(n);
		var r5=row();txt(r5,'Paikka lauseessa:','where','Lohjalla');
		var r6=row();num(r6,'Leveysaste','lat',-90,90,0.0001);num(r6,'Pituusaste','lon',-180,180,0.0001);
		var r7=row();num(r7,'Nopeus','rate',0.6,1.4,0.05);num(r7,'Äänenkorkeus','pitch',0.6,1.6,0.05);
		var r8=row();txt(r8,'Äänen nimi (valinnainen):','voice','esim. Google, Satu');
		var k=el('div',null,'k2');
		var sv=el('button','Tallenna');sv.onclick=function(){api('POST',U.dj,d).then(function(r){DJS=r;say('Juontaja tallennettu.')}).catch(function(){say('Tallennus epäonnistui.')})};
		var ts=el('button','Testaa nyt');ts.onclick=function(){api('POST',U.dj,d).then(function(){send('dj',0,'juontaja')})};
		k.appendChild(sv);k.appendChild(ts);box.appendChild(k);
	}
	/* Kuulutukset */
	var DN=['Ma','Ti','Ke','To','Pe','La','Su'];
	function annSave(msg){
		return api('POST',U.ann,{anns:ANN}).then(function(r){ANN=r.anns;buildAnn();refresh();say(msg||'Kuulutukset tallennettu.')}).catch(function(){say('Kuulutusten tallennus epäonnistui.')});
	}
	function buildAnn(){
		var box=q('[data-ann-box]');if(!box)return;box.innerHTML='';
		box.appendChild(el('p','Ajasta oma ilmoituksesi soimaan tiettyyn kellonaikaan (esim. "Kauppa sulkeutuu 10 minuutin kuluttua"). Lisää ensin ilmoituksen äänitiedosto mediakirjastoon tai mp3-linkkinä (Lisää kappale linkillä). Valittu tiedosto ei enää soi tavallisena musiikkina. Musiikki keskeytyy ja jatkuu kuulutuksen jälkeen samasta kohdasta. Soittimen pitää olla auki puhelimessa.','st'));
		ANN.forEach(function(a,ai){
			var w=el('div',null,'ls');
			var r1=el('div',null,'la');
			var lb=el('input');lb.placeholder='Nimi (esim. Sulkemisilmoitus)';lb.value=a.label||'';lb.onchange=function(){a.label=lb.value};
			r1.appendChild(lb);w.appendChild(r1);
			var r2=el('div',null,'mk-r');
			var ts=el('select');ts.className='sc-t';ts.style.flex='1';
			var o0=el('option','– valitse äänitiedosto –');o0.value=0;ts.appendChild(o0);
			T.forEach(function(t){var o=el('option',t.title+' – '+t.artist);o.value=t.id;ts.appendChild(o)});
			ts.value=a.track;ts.onchange=function(){a.track=+ts.value};
			var tm=el('input');tm.type='time';tm.className='sc-t';tm.value=(a.time||'12:00').replace(/^(\d):/,'0$1:');tm.onchange=function(){a.time=tm.value};
			r2.appendChild(ts);r2.appendChild(tm);w.appendChild(r2);
			var r3=el('div',null,'mk-r');
			DN.forEach(function(d,di){
				var l=el('label');var cb=el('input');cb.type='checkbox';cb.checked=a.days.indexOf(di+1)>-1;
				cb.onchange=function(){a.days=a.days.filter(function(x){return x!==di+1});if(cb.checked)a.days.push(di+1)};
				l.appendChild(cb);l.appendChild(document.createTextNode(' '+d+' '));r3.appendChild(l);
			});
			w.appendChild(r3);
			if(TG.length>1){
				var r4=el('div',null,'mk-r');r4.appendChild(el('span','Soittimet: '));
				TG.forEach(function(t){
					var l=el('label');var cb=el('input');cb.type='checkbox';cb.checked=!a.targets||!a.targets.length||a.targets.indexOf(t.id)>-1;
					cb.onchange=function(){
						var cur2=(a.targets&&a.targets.length)?a.targets.slice():TG.map(function(x){return x.id});
						cur2=cur2.filter(function(x){return x!==t.id});if(cb.checked)cur2.push(t.id);
						a.targets=cur2.length===TG.length?[]:cur2;
					};
					l.appendChild(cb);l.appendChild(document.createTextNode(' '+t.name+' '));r4.appendChild(l);
				});
				w.appendChild(r4);
			}
			var r5=el('div',null,'k2');
			var on=el('label');var ocb=el('input');ocb.type='checkbox';ocb.checked=!!a.on;ocb.onchange=function(){a.on=ocb.checked};on.appendChild(ocb);on.appendChild(document.createTextNode(' Käytössä'));on.style.flex='1';
			var test=el('button','Testaa nyt');test.onclick=function(){if(!a.track){say('Valitse ensin äänitiedosto.');return}send('ann',a.track,'kuulutus')};
			var del=el('button','Poista');del.className='rm';del.onclick=function(){if(confirm('Poistetaanko kuulutus?')){ANN.splice(ai,1);annSave('Kuulutus poistettu.')}};
			r5.appendChild(on);r5.appendChild(test);r5.appendChild(del);w.appendChild(r5);
			box.appendChild(w);
		});
		var r=el('div',null,'k2');
		var add=el('button','+ Lisää kuulutus');add.onclick=function(){ANN.push({id:'',label:'',track:0,time:'12:00',days:[1,2,3,4,5,6,7],on:true,targets:[]});buildAnn()};
		var sv=el('button','Tallenna kuulutukset');sv.onclick=function(){annSave()};
		r.appendChild(add);r.appendChild(sv);box.appendChild(r);
	}
	/* Asiakkaat */
	function saveClients(list,msg){
		return api('POST',U.clients,{clients:list}).then(function(r){CL=r.clients;buildClients();say(msg||'Tallennettu.')}).catch(function(){say('Tallennus epäonnistui.')});
	}
	function clientsOut(){return CL.map(function(c){return {id:c.id,name:c.name,targets:c.targets}})}
	function buildClients(){
		var box=q('[data-clients-box]');box.innerHTML='';
		box.appendChild(el('p','Asiakas saa oman salaisen linkin, jolla hän ohjaa vain sinun valitsemiasi kohteita (toisto, tauko, seuraava, ääni, satunnaistoisto, kappaleen valinta). Hän ei tarvitse WordPress-tunnuksia eikä näe asetuksia, mainoksia tai aikataulua. Luo ensin sivu "Asiakas" ja liitä siihen koodi [suorni_asiakas].','st'));
		CL.forEach(function(c){
			var w=el('div',null,'ls');
			var h=el('div',null,'lh');h.appendChild(el('b',c.name));
			var rn=el('button','Uusi linkki');rn.onclick=function(){if(confirm('Vanha linkki lakkaa toimimasta. Jatketaanko?'))saveClients(CL.map(function(x){return {id:x.id,name:x.name,targets:x.targets,newkey:x.id===c.id}}),'Uusi linkki luotu.')};
			var dl=el('button','Poista');dl.className='rm';dl.onclick=function(){if(confirm('Poistetaanko asiakas "'+c.name+'"?'))saveClients(clientsOut().filter(function(x){return x.id!==c.id}),'Asiakas poistettu.')};
			h.appendChild(rn);h.appendChild(dl);w.appendChild(h);
			var lp=el('div',null,'lp');
			TG.forEach(function(t){
				var lb=el('label');var cb=el('input');cb.type='checkbox';cb.checked=c.targets.indexOf(t.id)>-1;
				cb.onchange=function(){var tg=c.targets.filter(function(x){return x!==t.id});if(cb.checked)tg.push(t.id);saveClients(clientsOut().map(function(x){if(x.id===c.id)x.targets=tg;return x}),'Oikeudet tallennettu.')};
				lb.appendChild(cb);lb.appendChild(document.createTextNode(' '+t.name));lp.appendChild(lb);
			});
			w.appendChild(lp);
			linkRow(w,'Asiakkaan linkki:',clientUrl(c));
			box.appendChild(w);
		});
		var r=el('div',null,'la');var i=el('input');i.placeholder='Uuden asiakkaan nimi';
		var a=el('button','Lisää asiakas');a.onclick=function(){if(!i.value.trim())return;saveClients(clientsOut().concat([{name:i.value,targets:TG.length?[TG[0].id]:[]}]),'Asiakas lisätty. Valitse hänelle kohteet.')};
		r.appendChild(i);r.appendChild(a);box.appendChild(r);
	}

	/* Suno-kappaleiden kestot: haetaan puuttuvat Sunon sivulta (kerran per kappale) */
	var sdBusy=false;
	function sunoDurs(){
		if(sdBusy)return;
		var todo=T.filter(function(t){return !(t.dur>0)&&/cdn1\.suno\.ai\//.test(t.url)});
		var tried={};try{tried=JSON.parse(sessionStorage.getItem('suorni_sd'))||{}}catch(e){}
		todo=todo.filter(function(t){return !tried[t.id]});
		if(!todo.length)return;
		sdBusy=true;
		(function step(i){
			if(i>=todo.length){sdBusy=false;refresh();return}
			tried[todo[i].id]=1;try{sessionStorage.setItem('suorni_sd',JSON.stringify(tried))}catch(e){}
			api('POST',U.sunodur,{id:todo[i].id}).catch(function(){}).then(function(){step(i+1)});
		})(0);
	}
	buildSel();buildPair();buildTargets();buildClients();buildAnn();buildDj();applyTarget();build();buildLists();buildAds();buildSched();loadStats();sunoDurs();
	setInterval(pollState,3000);
	setInterval(showNp,1000);
	setInterval(refresh,20000);
	setInterval(loadStats,60000);
	document.addEventListener('visibilitychange',function(){if(!document.hidden){refresh();pollState()}});
});
})();

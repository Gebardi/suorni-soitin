(function(){
document.querySelectorAll('.sp:not([data-ok])').forEach(function(root){
	root.setAttribute('data-ok','1');
	var au=root.querySelector('audio'),L=root.querySelector('[data-l]');
	try{au.referrerPolicy='no-referrer'}catch(e){}
	function q(s){return root.querySelector(s)}
	function attr(n){return root.getAttribute(n)}
	function J(n,d){try{var v=JSON.parse(attr(n));return v==null?d:v}catch(e){return d}}
	var T=J('data-tracks',[]),LS=J('data-lists',[]),only=attr('data-only')||'',AD=J('data-ads',{on:false,min:3,max:3});
	var ver=attr('data-ver')||'',needReload=false;
	var bad={},wd=null,keep=false,userPick=true,embedId=null;
	function tryReload(){
		if(!needReload||!au.paused)return;
		try{
			var t=+sessionStorage.getItem('suorni_rl')||0;
			if(Date.now()-t<300000)return;
			sessionStorage.setItem('suorni_rl',String(Date.now()));
		}catch(e){}
		location.reload();
	}
	var V=[],curId=null,shuffle=false,rep='all',hist=[],counted=false,sel='all';
	var prefs={};try{prefs=JSON.parse(localStorage.getItem('suorni_prefs'))||{}}catch(e){}
	shuffle=!!prefs.shuffle;if(prefs.rep==='one'||prefs.rep==='off')rep=prefs.rep;
	sel=only||prefs.sel||'all';
	function savePrefs(){try{localStorage.setItem('suorni_prefs',JSON.stringify({shuffle:shuffle,rep:rep,sel:only?'all':sel}))}catch(e){}}
	function fmt(s){if(!isFinite(s)||s<0)return'0:00';s=Math.round(s);return Math.floor(s/60)+':'+('0'+s%60).slice(-2)}
	function fmtTot(s){var h=Math.floor(s/3600),m=Math.floor(s%3600/60);return h?h+' h '+m+' min':m+' min '+(s%60)+' s'}
	function grad(x){var h=0;for(var i=0;i<x.length;i++)h=(h*31+x.charCodeAt(i))%360;return'linear-gradient(135deg,hsl('+h+',70%,55%),hsl('+((h+60)%360)+',70%,35%))'}
	function byId(id){for(var i=0;i<T.length;i++){if(T[i].id===id)return T[i]}return null}
	function findList(id){for(var i=0;i<LS.length;i++){if(LS[i].id===id)return LS[i]}return null}
	function view(){
		var A=T.filter(function(t){return !t.ad&&!t.ann});
		if(sel==='all')return A;
		var l=findList(sel);
		if(!l)return only?[]:A;
		var m={};l.ids.forEach(function(id){m[id]=1});
		return A.filter(function(t){return m[t.id]});
	}
	function idx(){for(var i=0;i<V.length;i++){if(V[i].id===curId)return i}return -1}

	/* Pariutus ja kauko-ohjaus (avain) */
	var cu=attr('data-cmd'),su=attr('data-state-url'),pu=attr('data-play-url'),tu=attr('data-tu');
	var k=null,last=null,lastV=null,lastSh=null,lastL=null,schedOpen=null,wantV=100,timer=null,rc=null,gn=null,ctx=null;
	function ck(set){
		try{
			if(set!==undefined){document.cookie='suorni_k='+(set||'')+'; max-age='+(set?31536000:0)+'; path=/; SameSite=Lax'+(location.protocol==='https:'?'; Secure':'')}
			else{var m=document.cookie.match(/(?:^|; )suorni_k=([^;]+)/);return m?m[1]:null}
		}catch(e){return null}
	}
	try{
		var u=new URL(location.href);
		if(u.searchParams.has('suorni_pair')){
			var nk=u.searchParams.get('suorni_pair');
			try{localStorage.setItem('suorni_k',nk)}catch(e){}
			ck(nk);
			u.searchParams.delete('suorni_pair');
			history.replaceState(null,'',u.pathname+u.search+u.hash);
		}
	}catch(e){}
	try{k=localStorage.getItem('suorni_k')}catch(e){}
	if(!k)k=ck();
	if(k){try{localStorage.setItem('suorni_k',k)}catch(e){}ck(k)}
	function devFlag(){return /(?:^|; )suorni_p=1/.test(document.cookie)}
	function paired(){return !!k||devFlag()}

	/* Kestot: haetaan puuttuvat selaimella */
	var durBusy=false;
	function loadDurs(){
		if(durBusy)return;
		var t=null;for(var i=0;i<T.length;i++){if(!(T[i].dur>0)&&!T[i].nd){t=T[i];break}}
		if(!t)return;
		durBusy=true;
		var a=new Audio();a.preload='metadata';
		try{a.referrerPolicy='no-referrer'}catch(e){}
		function done(d){
			a.onloadedmetadata=null;a.onerror=null;durBusy=false;
			if(d>0&&isFinite(d))t.dur=Math.round(d);else t.nd=true;
			render();loadDurs();
		}
		a.onloadedmetadata=function(){done(a.duration)};
		a.onerror=function(){done(0)};
		a.src=t.url;
	}

	/* Piirto */
	function tot(){
		var el=q('[data-tot]');if(!el)return;
		var s=0,miss=0;V.forEach(function(t){if(t.dur>0)s+=t.dur;else miss++});
		el.textContent=V.length+(V.length===1?' kappale':' kappaletta')+(V.length&&s>0?' · '+(miss?'vähintään ':'')+fmtTot(s)+(miss?' ('+miss+' ilman kestoa)':''):'');
	}
	function chips(){
		var el=q('[data-ch]');if(!el)return;
		el.innerHTML='';
		if(only||!LS.length){el.hidden=true;return}
		el.hidden=false;
		[{id:'all',name:'Kaikki'}].concat(LS).forEach(function(l){
			var b=document.createElement('button');b.textContent=l.name;b.className=(l.id===sel?'on':'');
			b.onclick=function(){sel=l.id;savePrefs();hist=[];render()};
			el.appendChild(b);
		});
	}
	function btns(){
		var s=q('[data-a="shuffle"]'),r=q('[data-a="repeat"]');
		if(s){s.style.opacity=shuffle?'1':'.45';s.setAttribute('aria-pressed',shuffle?'true':'false')}
		if(r){r.textContent=rep==='one'?'🔂':'🔁';r.style.opacity=rep==='off'?'.45':'1'}
	}
	function render(){
		if(sel!=='all'&&!only&&!findList(sel))sel='all';
		V=view();tot();chips();
		var f=((q('[data-q]')||{}).value||'').toLowerCase().trim();
		L.innerHTML='';
		if(!V.length){L.innerHTML='<p class="s" style="padding:20px 6px">Ei vielä kappaleita.</p>';return}
		var shown=0;
		V.forEach(function(t,i){
			if(f&&(t.title+' '+t.artist+' '+(t.genre||'')).toLowerCase().indexOf(f)<0)return;
			shown++;
			var r=document.createElement('div');r.className='r'+(t.id===curId?' on':'')+(bad[t.id]?' bad':'');
			r.innerHTML='<div class="n"></div><div class="c"></div><div class="m"><b></b><span></span></div><div class="d"></div>';
			r.children[0].textContent=i+1;
			r.children[1].style.background=grad(t.title+t.artist);
			r.querySelector('b').textContent=(bad[t.id]?'⚠ ':'')+t.title;
			r.querySelector('span').textContent=t.artist+(t.genre?' · '+t.genre:'');
			r.querySelector('.d').textContent=t.dur>0?fmt(t.dur):'';
			r.onclick=function(){
				if(bad[t.id]&&sunoId(t.url)){openEmbed(t);return}
				bad[t.id]=false;userPick=true;playId(t.id);
			};
			L.appendChild(r);
		});
		if(!shown)L.innerHTML='<p class="s" style="padding:20px 6px">Ei hakutuloksia.</p>';
	}

	/* Tilan raportointi kauko-ohjaimelle (vain yhdistetty laite) */
	var lastRep=0;
	var embStart=0,embTimer=null;
	function embPos(){var t=byId(embedId);var p=(Date.now()-embStart)/1000;return t&&t.dur>0?Math.min(p,t.dur):p}
	function stateNow(){
		if(embedId!==null){var t=byId(embedId);return {playing:true,pos:Math.round(embPos()),dur:t&&t.dur>0?t.dur:0}}
		return {playing:!au.paused,pos:Math.round(au.currentTime||0),dur:Math.round(au.duration||0)};
	}
	function embTick(){
		if(embedId===null)return;
		var t=byId(embedId),p=embPos(),raw=(Date.now()-embStart)/1000;
		if(t&&t.dur>0&&raw>=t.dur+2){
			if(rep==='one'){openEmbed(t);return}
			closeEmbed();warn('');userPick=true;advance();
			return;
		}
		q('[data-t]').textContent='~'+fmt(p)+(t&&t.dur>0?' / '+fmt(t.dur):' / ?');
		q('[data-s]').value=t&&t.dur>0?Math.round(p/t.dur*1000):0;
		report(false);
	}
	document.addEventListener('visibilitychange',function(){if(!document.hidden)embTick()});
	window.addEventListener('focus',function(){embTick()});
	function report(force){
		if(!paired()||!su)return;
		var now=Date.now();if(!force&&now-lastRep<5000)return;lastRep=now;
		try{fetch(su,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(Object.assign({k:k||'',ver:ver,id:curId||0},stateNow())),keepalive:true}).catch(function(){})}catch(e){}
	}
	function countPlay(id){
		if(!pu)return;
		try{fetch(pu,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({id:id})}).catch(function(){})}catch(e){}
	}

	/* Lukitusnäytön hallinta */
	function setMS(t){
		if(!('mediaSession' in navigator))return;
		try{navigator.mediaSession.metadata=new MediaMetadata({title:t.title,artist:t.artist,album:'Suorni Radio'})}catch(e){}
	}
	if('mediaSession' in navigator){
		var hd=function(n,f){try{navigator.mediaSession.setActionHandler(n,f)}catch(e){}};
		hd('play',function(){au.play().catch(function(){})});
		hd('pause',function(){au.pause()});
		hd('previoustrack',function(){prev()});
		hd('nexttrack',function(){next()});
		hd('seekto',function(d){if(d&&d.seekTime!=null)au.currentTime=d.seekTime});
	}
	var lastPS=0;
	function posState(){
		if(!('mediaSession' in navigator)||!navigator.mediaSession.setPositionState||!au.duration||!isFinite(au.duration))return;
		var n=Date.now();if(n-lastPS<1000)return;lastPS=n;
		try{navigator.mediaSession.setPositionState({duration:au.duration,position:Math.min(au.currentTime,au.duration),playbackRate:au.playbackRate||1})}catch(e){}
	}

	/* Soitto */
	function playId(id,back){
		var t=byId(id);if(!t)return;
		closeEmbed();
		if(bad[id]&&userPick&&sunoId(t.url)&&openEmbed(t))return;
		curId=id;counted=false;
		if(!back){hist.push(id);if(hist.length>50)hist.shift()}
		au.src=t.url;au.play().catch(function(e){if(e&&e.name==='NotSupportedError')failed(id)});
		q('[data-n]').textContent=(adMode&&t.ad?'📢 Mainos: ':'')+t.title+' – '+t.artist;
		if(!keep)warn('');
		keep=false;
		clearTimeout(wd);
		wd=setTimeout(function(){if(curId===id&&!au.paused&&au.readyState<2&&au.currentTime===0)failed(id)},20000);
		setMS(t);render();report(true);
	}
	function pick(dir){
		if(!V.length)return null;
		if(dir>0&&forced.length){var fid=forced.shift();if(byId(fid))return fid}
		var i=idx();
		if(shuffle){
			if(dir<0&&hist.length>1){hist.pop();return hist[hist.length-1]}
			if(V.length===1)return V[0].id;
			var j;do{j=Math.floor(Math.random()*V.length)}while(j===i);
			return V[j].id;
		}
		if(i<0)return V[0].id;
		var n=i+dir;
		if(n>=V.length){if(rep==='off')return null;n=0}
		if(n<0)n=V.length-1;
		return V[n].id;
	}

	/* Lisäkanava mainoksille ja kuulutuksille: oma äänielementti, jonka voimakkuutta voi nostaa (lv %) ja joka ei riipu taustamusiikin tasosta */
	var lv=150,auG=null,auP=null,ctxA=null,gnA=null,auxOn=false,auxCur=null,auxDone=null,auxWd=null;
	var SIL='data:audio/wav;base64,UklGRiQAAABXQVZFZm10IBAAAAABAAEAQB8AAIA+AAACABAAZGF0YQAAAAA=';
	function auxInit(){
		if(auP)return;
		auP=new Audio();auG=new Audio();auP.preload=auG.preload='auto';
		try{
			var AC=window.AudioContext||window.webkitAudioContext;
			if(AC){
				ctxA=new AC();var src=ctxA.createMediaElementSource(auG);gnA=ctxA.createGain();
				var cp=ctxA.createDynamicsCompressor();cp.threshold.value=-6;cp.knee.value=0;cp.ratio.value=20;cp.attack.value=.003;cp.release.value=.25;
				src.connect(gnA);gnA.connect(cp);cp.connect(ctxA.destination);ctxA.resume();
			}
		}catch(e){gnA=null}
		[auP,auG].forEach(function(e){try{e.src=SIL;var pr=e.play();if(pr&&pr.then)pr.then(function(){e.pause()}).catch(function(){})}catch(x){}});
	}
	function auxEl(url){
		auxInit();
		if(String(url).indexOf(location.origin)===0&&gnA){gnA.gain.value=Math.max(1,Math.min(4,lv/100));try{ctxA.resume()}catch(e){}return auG}
		return auP;
	}
	function playAux(t,done){
		if(auxOn)stopAux(true);
		var e=auxEl(t.url);auxCur=e;auxOn=true;auxDone=done;
		var fin=function(){
			if(!auxOn||auxCur!==e)return;
			auxOn=false;e.onended=e.onerror=null;clearTimeout(auxWd);
			var d=auxDone;auxDone=null;if(d)d();
		};
		e.onended=fin;e.onerror=fin;e.src=t.url;
		var pr=e.play();if(pr&&pr.catch)pr.catch(function(err){if(err&&err.name==='NotAllowedError')warn('Selain esti kuulutuksen tai mainoksen äänen. Napauta sivua kerran, niin se sallii äänet.');fin()});
		clearTimeout(auxWd);auxWd=setTimeout(function(){if(auxOn&&auxCur===e&&e.paused&&e.currentTime===0)fin()},15000);
	}
	function stopAux(silent){
		if(!auxOn)return;auxOn=false;clearTimeout(auxWd);
		try{auxCur.onended=auxCur.onerror=null;auxCur.pause()}catch(e){}
		if(!silent&&auxDone){var d=auxDone;auxDone=null;d()}else auxDone=null;
	}
	/* Kuulutukset: tauko musiikille, kuulutus kovempaa, sitten musiikki jatkuu */
	function annDone(){try{return JSON.parse(localStorage.getItem('suorni_annd'))||{}}catch(e){return {}}}
	function annMark(k){var d=annDone();d[k]=Date.now();var ks=Object.keys(d).sort(function(a,b){return d[b]-d[a]}).slice(0,60),o={};ks.forEach(function(x){o[x]=d[x]});try{localStorage.setItem('suorni_annd',JSON.stringify(o))}catch(e){}}
	/* Keskeytä musiikki hetkeksi (kuulutus, juontaja) ja jatka sen jälkeen */
	function withBreak(label,runner){
		if(auxOn||djOn)return;
		var wasPlaying=embedId===null&&!au.paused&&!au.ended&&!!au.src;
		var emb=embedId,nm=q('[data-n]').textContent;
		if(wasPlaying)au.pause();
		if(emb!==null)closeEmbed();
		q('[data-n]').textContent=label;
		runner(function(){
			q('[data-n]').textContent=nm;
			if(wasPlaying){au.play().catch(function(){})}
			else if(emb!==null){keep=true;userPick=true;playId(emb)}
		});
	}
	function playAnn(id){
		var t=byId(id);if(!t)return;
		withBreak('📣 Kuulutus: '+t.title,function(done){playAux(t,done)});
	}

	/* Juontaja: kertoo kellonajan, sään ja seuraavat kappaleet puhesyntetisaattorilla */
	var DJ={on:false},djOn=false,djCount=0,djNext=0,forced=[];
	function slotKeys(){var d=new Date(),m=d.getHours()*60+d.getMinutes(),day=d.toDateString();return {h:day+'#'+Math.floor(m/60),h2:day+'#'+Math.floor(m/30),mod:{h:m%60,h2:m%30}}}
	var djSlot=(function(){var c=slotKeys();return {h:c.h,h2:c.h2}})();
	function djRoll(){var lo=Math.max(1,DJ.min|0||3),hi=Math.max(lo,DJ.max|0||lo);djNext=lo+Math.floor(Math.random()*(hi-lo+1));djCount=0}
	var WC={0:'selkeää',1:'enimmäkseen selkeää',2:'puolipilvistä',3:'pilvistä',45:'sumuista',48:'huurteista sumua',51:'tihkusadetta',53:'tihkusadetta',55:'tihkusadetta',56:'jäätävää tihkua',57:'jäätävää tihkua',61:'heikkoa sadetta',63:'sadetta',65:'rankkaa sadetta',66:'jäätävää vesisadetta',67:'jäätävää vesisadetta',71:'heikkoa lumisadetta',73:'lumisadetta',75:'runsasta lumisadetta',77:'lumijyväsiä',80:'sadekuuroja',81:'sadekuuroja',82:'voimakkaita sadekuuroja',85:'lumikuuroja',86:'runsaita lumikuuroja',95:'ukkosta',96:'ukkosta ja rakeita',99:'ukkosta ja rakeita'};
	function degs(n){n=Math.round(n);return n<0?'pakkasta '+(-n)+' astetta':(n===0?'nolla astetta':n+(n===1?' aste':' astetta'))}
	function getWeather(cb){
		var lat=DJ.lat||60.2514,lon=DJ.lon||24.0667,key='suorni_wx_'+lat+'_'+lon;
		try{var c=JSON.parse(localStorage.getItem(key));if(c&&Date.now()-c.t<900000){cb(c.d);return}}catch(e){}
		var done=false,tm=setTimeout(function(){if(!done){done=true;cb(null)}},5000);
		fetch('https://api.open-meteo.com/v1/forecast?latitude='+lat+'&longitude='+lon+'&current=temperature_2m,weather_code&daily=temperature_2m_max,temperature_2m_min&timezone=Europe%2FHelsinki&forecast_days=1',{cache:'no-store'})
			.then(function(r){return r.json()})
			.then(function(j){
				if(done)return;done=true;clearTimeout(tm);
				var d={t:j.current.temperature_2m,c:j.current.weather_code,hi:j.daily.temperature_2m_max[0],lo:j.daily.temperature_2m_min[0]};
				try{localStorage.setItem(key,JSON.stringify({t:Date.now(),d:d}))}catch(e){}
				cb(d);
			}).catch(function(){if(!done){done=true;clearTimeout(tm);cb(null)}});
	}
	function finNum(n){
		var o=['nolla','yksi','kaksi','kolme','neljä','viisi','kuusi','seitsemän','kahdeksan','yhdeksän'];
		if(n<10)return o[n];
		if(n===10)return 'kymmenen';
		if(n<20)return o[n-10]+'toista';
		var t=Math.floor(n/10),u=n%10;
		return o[t]+'kymmentä'+(u?o[u]:'');
	}
	function finTime(h,m){return m===0?'Kello on tasan '+finNum(h)+'.':'Kello on '+finNum(h)+' '+finNum(m)+'.'}
	function pad2(n){return(n<10?'0':'')+n}
	/* Arvaa kappaleen nimen kieli: suomi vai englanti */
	function titleLang(t){
		var m=DJ.titles||'auto';
		if(m==='fi')return 'fi';
		if(/[äöåÄÖÅ]/.test(t))return 'fi';
		if(/\b(the|and|of|in|on|my|your|you|me|to|a|an|is|are|we|us|i|it|love|night|dream|dreams|time|heart|fire|sky|light|road|drive|city|summer|baby|girl|boy|day|world|like|feel|feeling|no|not|all|one|here|there|with|for|from|down|up|out|away|back|home|rain|sun|moon|star|stars|gold|blue|red|black|white|dance|song|music|life|dream|eyes|soul|run|fly|wild|free|forever|tonight|morning|midnight)\b/i.test(t))return 'en';
		if(/([aeiouyäö])\1/i.test(t)||/(nen|ssa|ssä|lla|llä|sta|stä|ksi|ton|tön|ut|yt)\b/i.test(t))return 'fi';
		return 'en';
	}
	function djSegs(ids,wx){
		var segs=[],now=new Date();
		function f(t){segs.push({t:t,l:'fi'})}
		var st=DJ.station||'Suorni Radio',h=now.getHours();
		var tod=h<4?'Hyvää yötä':(h<10?'Hyvää huomenta':(h<17?'Hyvää päivää':'Hyvää iltaa'));
		var greet=DJ.tod===false?['Hei, tässä '+st+'.',st+' kuuluu.','Ja taas jatketaan: '+st+'.']
			:[tod+'!',tod+', tässä '+st+'.',tod+' ja tervetuloa kuuntelemaan: '+st+'.'];
		if(DJ.every==='hour'||DJ.every==='half'||Math.random()<.7)f(greet[Math.floor(Math.random()*greet.length)]);
		if(DJ.time!==false)f(finTime(now.getHours(),now.getMinutes()));
		if(DJ.weather!==false&&wx){
			var w=WC[wx.c]||'';
			f((DJ.where||'Lohjalla')+' on tällä hetkellä '+degs(wx.t)+(w?' ja '+w:'')+'. Päivän ylin lämpötila on '+degs(wx.hi)+'.');
		}
		var ph=String(DJ.phrases||'').split(/\n+/).map(function(x){return x.trim()}).filter(Boolean);
		if(ph.length)f(ph[Math.floor(Math.random()*ph.length)]);
		var n=(DJ.titles==='off')?0:Math.max(0,Math.min(3,DJ.next==null?1:DJ.next|0));
		var names=ids.slice(0,n).map(function(id){var t=byId(id);return t?t.title:''}).filter(Boolean);
		if(names.length){
			f(names.length===1?'Seuraavaksi kuulet kappaleen':'Seuraavaksi kuulet kappaleet');
			names.forEach(function(nm,i){
				if(i>0)f(i===names.length-1?'ja':'sitten');
				segs.push({t:nm,l:titleLang(nm)});
			});
		}
		return segs;
	}
	function speakSeq(segs,cb){
		var i=0;
		(function nextSeg(){
			if(i>=segs.length){cb();return}
			var g=segs[i++];
			speak(g.t,nextSeg,g.l);
		})();
	}
	/* Suomenkieliset äänet parhaimmasta huonoimpaan: ensin puhelimessa oleva (local) ääni, vasta sitten verkkoääni */
	function djVoices(ss,lang){
		var vs=ss.getVoices?ss.getVoices():[],out=[];
		var re=lang==='en'?/^en/i:/^fi/i;
		if(DJ.voice&&lang!=='en'){vs.forEach(function(v){if(v.name.toLowerCase().indexOf(String(DJ.voice).toLowerCase())>-1)out.push(v)})}
		var fi=vs.filter(function(v){return re.test(v.lang)&&out.indexOf(v)<0});
		var sc=function(v){return (v.localService?0:4)+(/female|nainen|satu|heidi|afi|fic/i.test(v.name)?0:1)};
		fi.sort(function(a,b){return sc(a)-sc(b)});
		return out.concat(fi);
	}
	function speak(text,cb,lang){
		lang=lang||'fi';
		var ss=window.speechSynthesis;
		if(!ss||!window.SpeechSynthesisUtterance){warn('Juontaja: tämä selain ei tue puhetta. Käytä Chromea.');cb();return}
		if(!text){cb();return}
		var done=false,started=false,tm=setTimeout(function(){try{ss.cancel()}catch(e){}fin()},60000),st=null;
		function fin(){if(done)return;done=true;clearTimeout(tm);clearTimeout(st);cb()}
		function go(){
			var cands=djVoices(ss,lang).slice(0,3);cands.push(null); /* viimeinen yritys: puhelimen oletusääni */
			var info='ääniä '+(ss.getVoices?ss.getVoices().length:0)+', suomalaisia '+(cands.length-1);
			(function attempt(i){
				if(done)return;
				started=false;
				var v=cands[i];
				try{
					var u=new SpeechSynthesisUtterance(text);u.lang=v?v.lang:(lang==='en'?'en-US':'fi-FI');
					if(v)u.voice=v;
					u.rate=DJ.rate||1;u.pitch=DJ.pitch||1.15;u.volume=1;
					var failed=false;
					var retry=function(er){
						if(failed||done)return;failed=true;clearTimeout(st);
						try{ss.cancel()}catch(e){}
						if(i+1<cands.length){setTimeout(function(){attempt(i+1)},150);return}
						warn('Juontaja: puhe epäonnistui ('+(er||'tuntematon virhe')+'; '+info+'). Avaa puhelimen asetukset → Järjestelmä → Kielet → Tekstistä puheeksi ja asenna suomenkielinen ääni tai vaihda puhemoottori Googlen Puhepalveluihin.');
						fin();
					};
					u.onstart=function(){started=true;clearTimeout(st);warn('🎙 '+text)};
					u.onend=function(){if(failed)return;warn('');fin()};
					u.onerror=function(ev){retry(ev&&ev.error)};
					clearTimeout(st);st=setTimeout(function(){if(!started)retry('ei käynnistynyt')},5000);
					try{ss.resume()}catch(e){}
					ss.speak(u);
				}catch(e){warn('Juontaja: puhe epäonnistui.');fin()}
			})(0);
		}
		try{ss.cancel()}catch(e){}
		var vs=ss.getVoices?ss.getVoices():[];
		if(vs.length)setTimeout(go,80);
		else{
			var w=false,run=function(){if(w)return;w=true;go()};
			try{ss.onvoiceschanged=run}catch(e){}
			setTimeout(run,1000);
		}
	}
	/* Kerää seuraavat kappaleet (nx ensin) ja tee puhe */
	function djPlan(nx){
		var n=Math.max(0,Math.min(3,DJ.next==null?1:DJ.next|0)),ids=nx===null?[]:[nx];
		if(n>1&&nx!==null){
			if(shuffle){
				var pool=V.filter(function(t){return t.id!==nx}).map(function(t){return t.id});
				while(ids.length<n&&pool.length){ids.push(pool.splice(Math.floor(Math.random()*pool.length),1)[0])}
				forced=ids.slice(1);
			}else{
				var i=-1;for(var k2=0;k2<V.length;k2++){if(V[k2].id===nx)i=k2}
				for(var j=1;j<n&&i>=0;j++){var x=V[i+j]||(rep!=='off'?V[(i+j)%V.length]:null);if(x&&ids.indexOf(x.id)<0)ids.push(x.id)}
			}
		}
		return ids;
	}
	/* Pilviääni: palvelin tekee puheesta mp3:n (Azure), soitetaan lisäkanavalla; jos epäonnistuu, käytetään puhelimen ääntä */
	function cloudSpeak(segs,done,fallback){
		var url=attr('data-tts-url');
		if(!url){fallback();return}
		fetch(url+(url.indexOf('?')>-1?'&':'?')+'t='+Date.now(),{method:'POST',headers:{'Content-Type':'application/json'},credentials:'same-origin',body:JSON.stringify({k:k||'',segs:segs})})
			.then(function(r){return r.json().then(function(j){if(!r.ok)throw new Error((j&&j.message)||r.status);return j})})
			.then(function(j){
				if(!j||!j.url)throw new Error('ei osoitetta');
				warn('🎙 '+segs.map(function(g){return g.t}).join(' '));
				playAux({url:j.url},function(){warn('');done()});
			})
			.catch(function(e){warn('Pilviääni ei toiminut ('+(e&&e.message?e.message:e)+'), käytetään puhelimen ääntä.');fallback()});
	}
	function runDj(ids,done){
		djOn=true;
		var say=function(wx){
			var segs=djSegs(ids,wx),fin=function(){djOn=false;done()};
			if(DJ.cloud)cloudSpeak(segs,fin,function(){speakSeq(segs,fin)});
			else speakSeq(segs,fin);
		};
		if(DJ.weather!==false)getWeather(say);else say(null);
	}
	/* Seuraava kappale; juontaja puhuu ennen sitä, kun on sen vuoro */
	function startNext(nx){
		if(nx===null){au.pause();return}
		if(DJ.on&&window.speechSynthesis){
			var due=false;
			if(DJ.every==='hour'||DJ.every==='half'){
				var kk=DJ.every==='hour'?'h':'h2',cs=slotKeys();
				if(cs[kk]!==djSlot[kk]){
					djSlot[kk]=cs[kk];
					due=cs.mod[kk]<=15; /* puhutaan vain jos tasatunnista on kulunut enintään 15 min */
				}
			}else{
				if(!djNext)djRoll();
				djCount++;
				if(djCount>=djNext){djRoll();due=true}
			}
			if(due){
				var ids=djPlan(nx);
				q('[data-n]').textContent='🎙 Juontaja';
				runDj(ids,function(){userPick=true;playId(nx)});
				return;
			}
		}
		userPick=true;playId(nx);
	}
	function testDj(){
		if(!window.speechSynthesis){warn('Juontaja: tämä selain ei tue puhetta. Käytä Chromea.');return}
		var nx=pick(1),ids=djPlan(nx);forced=[];
		withBreak('🎙 Juontaja',function(done){runDj(ids,done)});
	}
	/* Mainokset: soitetaan mainos kappaleen jälkeen X–Y kappaleen välein */
	var adIdx=0,songCount=0,nextAt=0,afterAd=null,adMode=false;
	function adList(){return T.filter(function(t){return t.ad&&!t.ann})}
	function rollNext(){
		var lo=Math.max(1,AD.min|0),hi=Math.max(lo,AD.max|0);
		nextAt=lo+Math.floor(Math.random()*(hi-lo+1));songCount=0;
	}
	function advance(){
		userPick=true;
		if(adMode){
			adMode=false;var a=afterAd;afterAd=null;
			if(a===null){au.pause();return}
			playId(a);return;
		}
		if(!nextAt)rollNext();
		songCount++;
		var ads=adList();
		var nx;
		if(AD.on&&ads.length&&songCount>=nextAt){
			var ad=ads[adIdx%ads.length];adIdx++;
			nx=pick(1);rollNext();
			q('[data-n]').textContent='📢 Mainos: '+ad.title+' – '+ad.artist;
			playAux(ad,function(){startNext(nx)});
			return;
		}
		startNext(pick(1));
	}
	function next(){var id=pick(1);if(id===null){au.pause();return}playId(id)}
	function prev(){
		if(au.currentTime>3){au.currentTime=0;return}
		var id=pick(-1);if(id!==null)playId(id,shuffle)
	}
	/* Virhetilanteet: kappale jota ei voi soittaa ohitetaan ja musiikki jatkuu */
	function warn(msg,link){
		var w=q('[data-warn]');if(!w)return;
		w.textContent='';
		if(!msg){w.hidden=true;return}
		w.hidden=false;w.appendChild(document.createTextNode(msg));
		if(link){
			w.appendChild(document.createTextNode(' '));
			var a=document.createElement('a');a.href=link;a.target='_blank';a.rel='noopener';a.textContent='Avaa Sunossa';
			w.appendChild(a);
		}
	}
	function sunoId(url){var m=String(url).match(/cdn1\.suno\.ai\/([0-9a-f-]{36})\./i);return m?m[1]:null}
	function closeEmbed(){var e=q('[data-em]');if(e){e.hidden=true;e.innerHTML=''}embedId=null;clearInterval(embTimer);embTimer=null}
	function openEmbed(t){
		var sid=sunoId(t.url),e=q('[data-em]');if(!sid||!e)return false;
		au.pause();clearTimeout(wd);curId=t.id;embedId=t.id;embStart=Date.now();clearInterval(embTimer);embTimer=setInterval(embTick,1000);
		e.innerHTML='';
		var h=document.createElement('div');h.className='eh';
		var s=document.createElement('span');s.textContent='Sunon oma soitin: '+t.title+' · ';
		var ao=document.createElement('a');ao.href='https://suno.com/song/'+sid;ao.target='_blank';ao.rel='noopener';ao.textContent='Avaa Sunossa';ao.style.color='var(--sp-acc,#8b7bff)';s.appendChild(ao);
		var x=document.createElement('button');x.textContent='✕';x.setAttribute('aria-label','Sulje');x.onclick=closeEmbed;
		h.appendChild(s);h.appendChild(x);
		var f=document.createElement('iframe');
		f.src='https://suno.com/embed/'+sid+'?autoplay=1';f.allow='autoplay; encrypted-media';f.title=t.title;
		e.appendChild(h);e.appendChild(f);e.hidden=false;
		q('[data-n]').textContent=t.title+' – '+t.artist+' (Sunon soitin)';
		warn('Tätä kappaletta ei voi toistaa suoraan tässä soittimessa. Paina play Sunon soittimessa.'+(t.dur>0?'':' Kestoa ei tiedossa, joten seuraavaan ei siirrytä itsestään: aseta kesto kauko-ohjaimen ⏱-painikkeella.'));
		render();
		try{e.scrollIntoView({behavior:'smooth',block:'center'})}catch(x){}
		return true;
	}
	function sunoPage(url){var m=String(url).match(/cdn1\.suno\.ai\/([0-9a-f-]{36})\./i);return m?'https://suno.com/song/'+m[1]:null}
	function skipBad(){
		var cand=V.filter(function(t){return !bad[t.id]&&t.id!==curId});
		if(!cand.length){au.pause();return}
		if(shuffle){keep=true;playId(cand[Math.floor(Math.random()*cand.length)].id);return}
		var i=idx(),n=V.length;
		for(var s=1;s<=n;s++){
			var t=V[(i+s)%n];
			if(rep==='off'&&i+s>=n)break;
			if(!bad[t.id]&&t.id!==curId){keep=true;playId(t.id);return}
		}
		au.pause();
	}
	function failed(id){
		var ft=byId(id);
		if(adMode&&ft&&ft.ad&&curId===id){adMode=false;var an=afterAd;afterAd=null;if(an!==null){keep=true;playId(an)}else{au.pause()}return}
		if(curId!==id||bad[id])return;
		bad[id]=true;clearTimeout(wd);
		var t=byId(id),code=au.error?au.error.code:0;
		if(t&&userPick&&sunoId(t.url)&&openEmbed(t))return;
		warn('Kappaletta ei voi soittaa'+(t?': '+t.title:'')+(code?' (virhe '+code+')':'')+'. Ohitetaan.',t?sunoPage(t.url):null);
		render();skipBad();
	}
	root.addEventListener('click',function(e){
		var a=e.target.closest('[data-a]');if(!a)return;
		var c=a.getAttribute('data-a');
		if(c==='all'&&V.length)playId(shuffle?V[Math.floor(Math.random()*V.length)].id:V[0].id);
		if(c==='next'){userPick=true;next()}
		if(c==='prev'){userPick=true;prev()}
		if(c==='shuffle'){shuffle=!shuffle;hist=curId?[curId]:[];savePrefs();btns()}
		if(c==='repeat'){rep=rep==='all'?'one':(rep==='one'?'off':'all');savePrefs();btns()}
		if(c==='pp'){if(curId===null){if(V.length)playId(V[0].id)}else{au.paused?au.play().catch(function(){}):au.pause()}}
	});
	var qe=q('[data-q]');if(qe)qe.oninput=render;
	au.onplay=function(){q('.pp').textContent='⏸';report(true);try{navigator.mediaSession.playbackState='playing'}catch(e){}};
	au.onpause=function(){q('.pp').textContent='▶';report(true);tryReload();try{navigator.mediaSession.playbackState='paused'}catch(e){}};
	au.onended=function(){userPick=true;if(rep==='one'){au.currentTime=0;au.play().catch(function(){})}else{advance()}};
	au.onloadedmetadata=function(){report(true)};
	au.onplaying=function(){clearTimeout(wd)};
	au.onerror=function(){if(curId!==null&&au.error&&au.error.code!==1)failed(curId)};
	au.onseeked=function(){report(true)};
	au.ontimeupdate=function(){
		if(au.duration)q('[data-s]').value=Math.round(au.currentTime/au.duration*1000);
		q('[data-t]').textContent=fmt(au.currentTime);
		posState();report(false);
		if(!counted&&!paired()&&!adMode&&curId&&au.currentTime>=Math.min(30,(au.duration||60)/2)){counted=true;countPlay(curId)}
	};
	q('[data-s]').oninput=function(){if(au.duration)au.currentTime=this.value/1000*au.duration};
	btns();
	try{render();loadDurs()}catch(e){}

	/* Uudet kappaleet ja listat ilman sivun päivitystä */
	function sig(){return T.map(function(t){return t.id+'|'+t.title+'|'+t.artist+'|'+t.url+'|'+(t.dur>0?1:0)+'|'+(t.ad?1:0)+'|'+(t.ann?1:0)}).join('\n')+'#'+JSON.stringify(LS)+JSON.stringify(AD)}
	function refresh(){
		if(!tu)return;
		fetch(tu+(tu.indexOf('?')>-1?'&':'?')+'t='+Date.now(),{cache:'no-store'})
			.then(function(r){return r.json()})
			.then(function(d){
				if(!d||!Array.isArray(d.tracks))return;
				if(d.ver&&ver&&d.ver!==ver){needReload=true;tryReload()}
				var before=sig(),dm={};
				T.forEach(function(t){if(t.dur>0)dm[t.id]=t.dur});
				var oldT=T,oldL=LS,oldA=AD;
				T=d.tracks;LS=Array.isArray(d.lists)?d.lists:[];if(d.ads)AD=d.ads;
				T.forEach(function(t){if(!(t.dur>0)&&dm[t.id])t.dur=dm[t.id]});
				if(sig()===before){T=oldT;LS=oldL;AD=oldA;return}
				render();loadDurs();
			}).catch(function(){});
	}
	if(tu){
		setInterval(refresh,20000);
		document.addEventListener('visibilitychange',function(){if(!document.hidden)refresh()});
	}

	/* Äänenvoimakkuus: iPhonessa media.volume ei toimi, joten käytetään tarvittaessa GainNodea */
	var volOK=true;
	try{au.volume=0.5;volOK=(au.volume===0.5);au.volume=1}catch(e){volOK=false}
	function applyVol(v){
		wantV=v;var x=Math.max(0,Math.min(1,v/100));
		if(volOK){try{au.volume=x}catch(e){}}
		else if(gn){gn.gain.value=x}
	}
	function initGain(){
		if(volOK||gn||!paired())return;
		var AC=window.AudioContext||window.webkitAudioContext,t=byId(curId);
		if(!AC||!t)return;
		for(var i=0;i<T.length;i++){if(String(T[i].url).indexOf(location.origin)!==0)return}
		try{
			ctx=new AC();var src=ctx.createMediaElementSource(au);gn=ctx.createGain();
			src.connect(gn);gn.connect(ctx.destination);ctx.resume();applyVol(wantV);
		}catch(e){ctx=null;gn=null}
	}
	root.addEventListener('click',initGain);
	function unlockAudio(){
		if(!paired())return;
		auxInit();
		try{var ss=window.speechSynthesis;if(ss&&window.SpeechSynthesisUtterance){var u=new SpeechSynthesisUtterance(' ');u.volume=0;ss.speak(u)}}catch(e){}
	}
	document.addEventListener('pointerdown',unlockAudio,{passive:true});
	document.addEventListener('touchstart',unlockAudio,{passive:true});
	root.addEventListener('click',unlockAudio);

	/* Kauko-ohjauksen komennot */
	function run(c){
		if(c.a==='play'){if(curId===null){if(V.length)playId(V[0].id)}else{au.play().catch(function(){})}}
		else if(c.a==='pause'){wantStart=0;au.pause()}
		else if(c.a==='next'){next()}
		else if(c.a==='prev'){prev()}
		else if(c.a==='ann'){playAnn(c.id)}
		else if(c.a==='dj'){testDj()}
		else if(c.a==='track'&&byId(c.id)){
			var inV=false;V.forEach(function(t){if(t.id===c.id)inV=true});
			if(!inV&&!only){sel='all';savePrefs()}
			userPick=true;bad[c.id]=false;playId(c.id);
		}
	}
	var wantStart=0;
	function autoStart(){
		if(!V.length)return;
		if(curId===null||embedId!==null||au.ended||!au.src||au.error){userPick=true;playId(shuffle?V[Math.floor(Math.random()*V.length)].id:V[0].id)}
		else{au.play().catch(function(){})}
	}
	/* Jos selain esti automaattisen toiston, ensimmäinen napautus sivulle käynnistää soiton */
	document.addEventListener('pointerdown',function(){if(wantStart&&schedOpen===true&&au.paused&&embedId===null){autoStart()}},true);
	au.addEventListener('playing',function(){wantStart=0;warn('')});
	var wl=null;
	function keepAwake(){
		try{
			if(!('wakeLock' in navigator)||document.hidden||wl)return;
			navigator.wakeLock.request('screen').then(function(l){wl=l;l.addEventListener('release',function(){wl=null})}).catch(function(){});
		}catch(e){}
	}
	document.addEventListener('visibilitychange',function(){if(!document.hidden)keepAwake()});
	function lost(){
		clearInterval(timer);k=null;
		try{localStorage.removeItem('suorni_k')}catch(e){}
		ck('');
		try{document.cookie='suorni_p=; max-age=0; path=/'}catch(e){}
		if(rc){rc.textContent='📱 Kauko-ohjaus katkesi. Avaa yhdistämislinkki uudelleen.';rc.onclick=null}
	}
	function disconnect(){
		if(!confirm('Katkaistaanko kauko-ohjaus tästä laitteesta?'))return;
		lost();if(rc&&rc.parentNode)rc.parentNode.removeChild(rc);
	}
	function poll(){
		fetch(cu+(cu.indexOf('?')>-1?'&':'?')+(k?'k='+encodeURIComponent(k)+'&':'')+'t='+Date.now(),{cache:'no-store',credentials:'same-origin'})
			.then(function(r){return r.json()})
			.then(function(c){
				if(c&&(c.code==='rest_forbidden'||c.code==='rest_forbidden_context')){lost();return}
				if(!c||typeof c.seq!=='number')return;
				if(typeof c.v==='number'&&c.v!==lastV){lastV=c.v;applyVol(c.v)}
				if(typeof c.lv==='number'){lv=c.lv}
				if(c.dj&&typeof c.dj==='object'){DJ=c.dj}
				if(typeof c.sh==='boolean'&&c.sh!==lastSh){
					lastSh=c.sh;
					if(shuffle!==c.sh){shuffle=c.sh;hist=curId?[curId]:[];savePrefs();btns()}
				}
				if(typeof c.l==='string'&&c.l!==lastL&&!only){
						var firstL=lastL===null;lastL=c.l;
						var ns=c.l||'all';
						if(sel!==ns){
							sel=ns;savePrefs();hist=[];render();
							if(!firstL&&V.length&&schedOpen!==false){userPick=true;playId(shuffle?V[Math.floor(Math.random()*V.length)].id:V[0].id)}
						}
					}
					if(typeof c.open==='boolean'){
					var was=schedOpen;schedOpen=c.open;
					if(was===true&&c.open===false){
						wantStart=0;au.pause();closeEmbed();
						warn('Soittoaika päättyi. Musiikki jatkuu seuraavan soittoajan alkaessa.');
					}else if((was===false||(was===null&&c.sch===true))&&c.open===true){
						warn('');
						wantStart=Date.now();
						autoStart();
						setTimeout(function(){if(au.paused&&embedId===null)warn('Soittoaika alkoi, mutta selain esti automaattisen toiston. Paina play tai napauta sivua.')},3000);
					}else if(c.open===true&&wantStart&&Date.now()-wantStart<900000&&au.paused&&embedId===null){
						/* Aloitus ei onnistunut (kuormitus, selain esti tai kappale ei latautunut): yritetään uudelleen 15 min ajan */
						autoStart();
					}
				}
				if(Array.isArray(c.an)&&c.an.length){
					var dn=annDone();
					c.an.forEach(function(x){if(!dn[x.k]){annMark(x.k);playAnn(x.id)}});
				}
				if(last===null){last=c.seq;return}
				if(c.seq>last){last=c.seq;run(c)}
			}).catch(function(){});
	}
	if(cu&&paired()){
		rc=document.createElement('button');rc.className='b';
		rc.style.cssText='background:var(--sp-panel,#14141f);border:1px solid var(--sp-line,#262636)';
		rc.textContent='📱 Kauko-ohjaus päällä (katkaise)';
		rc.onclick=disconnect;
		q('.tb').appendChild(rc);
		poll();timer=setInterval(poll,2500);keepAwake();
	}
});
})();

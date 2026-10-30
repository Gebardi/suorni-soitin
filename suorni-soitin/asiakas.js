(function(){
document.querySelectorAll('.sa:not([data-ok])').forEach(function(root){
	root.setAttribute('data-ok','1');
	function q(s){return root.querySelector(s)}
	function attr(n){return root.getAttribute(n)}
	var st=q('[data-st]'),body=q('[data-body]'),L=q('[data-l]'),sel=q('[data-tsel]'),vs=q('[data-vol]'),vv=q('[data-vv]'),shb=q('[data-shuf]');
	var thsel=q('[data-theme-sel]'),LS=[],key='',TR=[],TG=[],cur='',np={},npAt=0,SH=false;
	try{var m=location.search.match(/[?&]avain=([^&]+)/);
		if(m){key=decodeURIComponent(m[1]);localStorage.setItem('suorni_ak',key);
			history.replaceState(null,'',location.pathname+location.search.replace(/([?&])avain=[^&]*&?/,'$1').replace(/[?&]$/,'')+location.hash)}
		else key=localStorage.getItem('suorni_ak')||'';
	}catch(e){}
	function say(t){st.textContent=t}
	function fmt(s){s=Math.max(0,Math.round(s));return Math.floor(s/60)+':'+('0'+s%60).slice(-2)}
	function api(m,u,b){
		var o={method:m,cache:'no-store',headers:{'X-Suorni-Key':key}};
		if(b!==undefined){o.headers['Content-Type']='application/json';o.body=JSON.stringify(b)}
		if(m==='GET')u+=(u.indexOf('?')>-1?'&':'?')+'t='+encodeURIComponent(cur)+'&_='+Date.now();
		return fetch(u,o).then(function(r){if(!r.ok)throw new Error(r.status);return r.json()});
	}
	function send(a,id,v){
		return api('POST',attr('data-cmd'),{a:a,id:id||0,v:v,t:cur}).then(function(r){say('Lähetetty.');return r}).catch(function(){say('Lähetys epäonnistui.')});
	}
	root.querySelectorAll('.k button').forEach(function(b){b.onclick=function(){send(b.getAttribute('data-a'))}});
	var vt=null;
	vs.oninput=function(){vv.textContent=vs.value+' %';clearTimeout(vt);vt=setTimeout(function(){send('vol',0,+vs.value)},350)};
	function showSh(){shb.textContent='🔀 Satunnaistoisto: '+(SH?'päällä':'pois')}
	shb.onclick=function(){SH=!SH;showSh();send('shuf',0,SH?1:0)};
	function byId(id){for(var i=0;i<TR.length;i++)if(TR[i].id===id)return TR[i];return null}
	function showNp(){
		var tt=q('[data-npt]'),ss=q('[data-nps]'),tr=np.id?byId(np.id):null;
		L.querySelectorAll('.rw').forEach(function(r){r.style.opacity=(np.id&&+r.getAttribute('data-id')===np.id)?'1':'.75'});
		if(!tr){tt.textContent='Ei tietoa soittimesta';ss.textContent='';return}
		var age=(np.now||0)-(np.t||0);
		tt.textContent=tr.title+' – '+tr.artist;
		if(np.playing&&age>30){ss.textContent='Soitin ei ole raportoinut hetkeen.';return}
		var pos=np.pos||0;if(np.playing)pos+=age+(Date.now()-npAt)/1000;if(np.dur)pos=Math.min(pos,np.dur);
		ss.textContent=(np.playing?'▶ ':'⏸ ')+fmt(pos)+(np.dur?' / '+fmt(np.dur):'');
	}
	function poll(){api('GET',attr('data-state')).then(function(s){np=s||{};npAt=Date.now();showNp()}).catch(function(){})}
	function buildList(){
		L.innerHTML='';
		TR.forEach(function(t){
			var w=document.createElement('div');w.className='rw';w.setAttribute('data-id',t.id);
			var b=document.createElement('button');b.textContent=t.title;
			var sp=document.createElement('span');sp.textContent=t.artist+(t.dur?' · '+fmt(t.dur):'');b.appendChild(sp);
			b.onclick=function(){send('track',t.id)};w.appendChild(b);L.appendChild(w);
		});
	}
	function curT(){for(var i=0;i<TG.length;i++)if(TG[i].id===cur)return TG[i];return {}}
	function buildTheme(){
		thsel.innerHTML='';
		if(!LS.length){thsel.hidden=true;return}
		var o=document.createElement('option');o.value='';o.textContent='Teema: kaikki kappaleet';thsel.appendChild(o);
		LS.forEach(function(l){var x=document.createElement('option');x.value=l.id;x.textContent='Teema: '+l.name;thsel.appendChild(x)});
		var v=curT().list||'';thsel.value=v;if(thsel.value!==v)thsel.value='';thsel.hidden=false;
	}
	thsel.onchange=function(){var v=thsel.value,t=curT();send('list',0,v).then(function(){t.list=v})};
	sel.onchange=function(){cur=sel.value;var t0=curT();vs.value=t0.vol==null?100:t0.vol;vv.textContent=vs.value+' %';SH=!!t0.shuf;showSh();np={};showNp();buildTheme();poll()};
	api('GET',attr('data-info')).then(function(r){
		TR=r.tracks||[];TG=r.targets||[];LS=r.lists||[];
		if(!TG.length){say('Tällä linkillä ei ole käytettäviä soittimia.');return}
		cur=TG[0].id;
		if(TG.length>1){TG.forEach(function(t){var o=document.createElement('option');o.value=t.id;o.textContent=t.name;sel.appendChild(o)});sel.hidden=false}
		q('h2').textContent=r.name||'Soitin';
		vs.value=TG[0].vol==null?100:TG[0].vol;SH=!!TG[0].shuf;vv.textContent=vs.value+' %';showSh();buildTheme();buildList();body.hidden=false;say('');poll();
		setInterval(poll,3000);setInterval(showNp,1000);
	}).catch(function(){say(key?'Linkki ei ole voimassa. Pyydä uusi linkki.':'Avaa sivu sinulle annetulla linkillä.')});
});
})();

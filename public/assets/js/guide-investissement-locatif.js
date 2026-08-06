if (new URLSearchParams(location.search).has('embedded')) document.documentElement.classList.add('embedded');

/* TABS */
function showTab(id){
  document.querySelectorAll('.tab-content').forEach(el=>el.classList.remove('active'));
  document.querySelectorAll('.tab-btn').forEach(el=>el.classList.remove('active'));
  document.getElementById(id).classList.add('active');
  event.currentTarget.classList.add('active');
  if(id==='bilan') setTimeout(initBilanSlider,50);
  if(id==='simulateur') setTimeout(calc,50);
}

/* BILAN SLIDER */
function initBilanSlider(){
  const sl = document.getElementById('loyer-slider');
  if(!sl||sl._init) return; sl._init=true;
  function upd(){
    const l=parseInt(sl.value);
    document.getElementById('loyer-label').textContent=l+' €';
    const coutTotal=230000, apport=80000, capital=150000;
    const mensCredit=capital*(0.034/12)*Math.pow(1+0.034/12,240)/(Math.pow(1+0.034/12,240)-1);
    const loyerAn=l*(12-1); // 1 mois vacance
    const chargesNR=2980;
    const rendBrut=(l*12/coutTotal*100);
    const rendNet=((loyerAn-chargesNR)/coutTotal*100);
    const cf=((loyerAn-chargesNR)/12-mensCredit);
    document.getElementById('lb-an').textContent=Math.round(l*12).toLocaleString('fr-FR')+' €';
    const rb=document.getElementById('r-brut');
    rb.textContent=rendBrut.toFixed(1)+' %';
    rb.style.color=rendBrut>=7?'var(--go)':rendBrut>=5?'var(--warn)':'var(--danger)';
    const rn=document.getElementById('r-net');
    rn.textContent=rendNet.toFixed(1)+' %';
    rn.style.color=rendNet>=4?'var(--go)':rendNet>=2.5?'var(--warn)':'var(--danger)';
    const cfEl=document.getElementById('cf-mois');
    cfEl.textContent=(cf>=0?'+':'')+Math.round(cf).toLocaleString('fr-FR')+' €/mois';
    cfEl.style.color=cf>=0?'var(--go)':cf>-200?'var(--warn)':'var(--danger)';
    const pct=Math.min(100,Math.max(2,rendBrut/12*100));
    const bar=document.getElementById('rendement-bar');
    bar.style.width=pct+'%';
    bar.style.background=rendBrut>=7?'var(--go)':rendBrut>=5?'var(--warn)':'var(--danger)';
    document.getElementById('rendement-label').textContent=rendBrut.toFixed(1)+' %';
  }
  sl.addEventListener('input',upd); upd();
}

/* SIMULATEUR */
function fmtE(n){return(n<0?'−':'')+Math.abs(Math.round(n)).toLocaleString('fr-FR')+' €';}
function fmtP(n){return(n>=0?'+':'')+n.toFixed(1)+' %';}
function gv(id){return parseFloat(document.getElementById(id).value)||0;}
function gs(id){return document.getElementById(id).value;}

function calcMens(cap,taux,ans){
  const tm=taux/100/12,n=ans*12;
  if(tm===0)return cap/n;
  return cap*(tm*Math.pow(1+tm,n))/(Math.pow(1+tm,n)-1);
}
function calcIS(base){
  if(base<=0)return 0;
  return Math.min(base,42500)*0.15+Math.max(0,base-42500)*0.25;
}

function calcImpot(revNet,regime,tmi,prix,travaux,notaire,loyer){
  const ps=0.172;
  const tmiD=tmi/100;
  if(regime==='micro'){
    const base=revNet*0.7;
    return base*(tmiD+ps);
  }else if(regime==='reel'){
    const base=Math.max(0,revNet);
    return base*(tmiD+ps);
  }else if(regime==='lmnp'){
    const amortBien=(prix+prix*notaire/100)/(27*12)*12;
    const amortMob=Math.min(travaux,5000)/6;
    const base=Math.max(0,revNet-amortBien-amortMob);
    return base*(tmiD+ps);
  }else{ // SCI IS
    const amort=(prix+prix*notaire/100+travaux)/27;
    const base=Math.max(0,revNet-amort);
    return calcIS(base);
  }
}

function calc(){
  const prix=gv('s-prix'), notPct=gv('s-notaire'), travaux=gv('s-travaux');
  const apport=gv('s-apport'), taux=gv('s-taux'), duree=gv('s-duree');
  const loyer=gv('s-loyer'), vacance=gv('s-vacance');
  const tf=gv('s-tf'), copro=gv('s-copro'), assur=gv('s-assur');
  const entretienPct=gv('s-entretien'), gestionPct=gv('s-gestion');
  const tmi=gv('s-tmi'), cible=gv('s-cible');
  const regime=gs('s-regime');

  const notaire=prix*notPct/100;
  const coutTotal=prix+notaire+travaux;
  const capital=Math.max(0,coutTotal-apport);
  const mens=calcMens(capital,taux,duree);

  const loyerAn=loyer*(12-vacance);
  const entretien=prix*entretienPct/100;
  const gestion=loyerAn*gestionPct/100;
  const chargesNR=tf+copro+assur+entretien+gestion;
  const revNet=loyerAn-chargesNR;
  const rendBrut=coutTotal>0?(loyer*12/coutTotal*100):0;
  const rendNet=coutTotal>0?(revNet/coutTotal*100):0;
  const impot=calcImpot(revNet,regime,tmi,prix,travaux,notPct,loyer);
  const revApresImpot=revNet-impot;
  const cfMens=revNet/12-mens;
  const cfMensApresImpot=revApresImpot/12-mens;
  const rfp=apport>0?(revApresImpot/apport*100):0;

  // Sensibilité
  function cfAvec(loyerMult,vacAdd,tauxAdd){
    const la=loyer*loyerMult*(12-(vacance+vacAdd));
    const cn=tf+copro+assur+prix*entretienPct/100+la*(gestionPct/100);
    const rn=la-cn;
    const m=calcMens(capital,taux+tauxAdd,duree);
    const imp=calcImpot(rn,regime,tmi,prix,travaux,notPct,loyer*loyerMult);
    return (rn-imp)/12-m;
  }
  const s1=cfAvec(0.95,0,0), s2=cfAvec(0.9,0,0);
  const s3=cfAvec(1,1,0), s4=cfAvec(1,2,0);
  const s5=cfAvec(1,0,0.5), s6=cfAvec(1,0,1);

  // Verdict
  let vClass,vTitle,vSub;
  if(rendNet>=cible&&cfMens>=-50){
    vClass='go';vTitle='GO';vSub=`Rendement net ${rendNet.toFixed(1)} % ≥ cible ${cible} % · cash-flow maîtrisé`;
  }else if(rendNet>=cible*0.7&&cfMens>=-500){
    vClass='cond';vTitle='GO conditionnel';vSub=`Rendement ${rendNet.toFixed(1)} % ou cash-flow ${fmtE(cfMens)}/mois à optimiser`;
  }else{
    vClass='nogo';vTitle='NO-GO';vSub=`Rendement insuffisant (${rendNet.toFixed(1)} %) ou effort mensuel trop élevé`;
  }

  const alertes=[];
  if(cfMens<-500) alertes.push({t:'d',m:`<b>Effort mensuel élevé ${fmtE(cfMens)}/mois</b> — augmenter l'apport ou chercher un loyer plus élevé.`});
  if(rendBrut<5) alertes.push({t:'d',m:'<b>Rendement brut < 5 %</b> — en dessous du seuil de viabilité locatif.'});
  if(capital>apport*4) alertes.push({t:'w',m:'<b>Levier fort :</b> capital emprunté &gt; 4× l\'apport — vérifier le taux d\'endettement.'});
  if(rfp>25) alertes.push({t:'g',m:`<b>Excellent rendement sur fonds propres : ${rfp.toFixed(1)} %</b>`});

  const panel=document.getElementById('res-panel');
  const cc=(n,seuil)=>n>=seuil?'g':n>=-200?'w':'r';

  panel.innerHTML=`
    <div class="verdict-bar ${vClass}">
      <div class="verdict-title">${vTitle}</div>
      <div class="verdict-sub">${vSub}</div>
    </div>
    <div class="kpi4">
      <div class="kpi4-cell"><div class="kpi4-lbl">Rendement brut</div><div class="kpi4-val ${rendBrut>=7?'g':rendBrut>=5?'w':'r'}">${rendBrut.toFixed(1)} %</div><div class="kpi4-sub">Loyers HC / coût total</div></div>
      <div class="kpi4-cell"><div class="kpi4-lbl">Rendement net</div><div class="kpi4-val ${rendNet>=cible?'g':rendNet>=cible*0.7?'w':'r'}">${rendNet.toFixed(1)} %</div><div class="kpi4-sub">Après charges / avant impôt</div></div>
      <div class="kpi4-cell"><div class="kpi4-lbl">Cash-flow mensuel</div><div class="kpi4-val ${cfMens>=0?'g':cfMens>=-300?'w':'r'}">${cfMens>=0?'+':''}${Math.round(cfMens).toLocaleString('fr-FR')} €</div><div class="kpi4-sub">Avant impôt sur les revenus</div></div>
      <div class="kpi4-cell"><div class="kpi4-lbl">RFP (rendement fonds propres)</div><div class="kpi4-val ${rfp>=15?'g':rfp>=8?'w':'r'}">${rfp.toFixed(1)} %</div><div class="kpi4-sub">Revenu net IS / apport</div></div>
    </div>
    <div class="bilan-res">
      <div class="br grp"><span>Acquisition</span><span></span></div>
      <div class="br"><span class="br-lbl">Prix d'achat</span><span class="br-val">${fmtE(prix)}</span></div>
      <div class="br"><span class="br-lbl">Frais de notaire (${notPct} %)</span><span class="br-val r">${fmtE(notaire)}</span></div>
      ${travaux>0?`<div class="br"><span class="br-lbl">Travaux</span><span class="br-val r">${fmtE(travaux)}</span></div>`:''}
      <div class="br"><span class="br-lbl">Coût total projet</span><span class="br-val">${fmtE(coutTotal)}</span></div>
      <div class="br grp"><span>Financement</span><span></span></div>
      <div class="br"><span class="br-lbl">Capital emprunté (${duree} ans · ${taux} %)</span><span class="br-val">${fmtE(capital)}</span></div>
      <div class="br"><span class="br-lbl">Mensualité crédit</span><span class="br-val r">${fmtE(mens)}/mois</span></div>
      <div class="br grp"><span>Compte d'exploitation annuel</span><span></span></div>
      <div class="br"><span class="br-lbl">Loyers HC (${12-vacance} mois)</span><span class="br-val g">${fmtE(loyerAn)}</span></div>
      <div class="br"><span class="br-lbl">Taxe foncière</span><span class="br-val r">${fmtE(-tf)}</span></div>
      <div class="br"><span class="br-lbl">Charges copropriété NR</span><span class="br-val r">${fmtE(-copro)}</span></div>
      <div class="br"><span class="br-lbl">Assurance PNO</span><span class="br-val r">${fmtE(-assur)}</span></div>
      <div class="br"><span class="br-lbl">Entretien (${entretienPct} %)</span><span class="br-val r">${fmtE(-entretien)}</span></div>
      ${gestion>0?`<div class="br"><span class="br-lbl">Gestion locative (${gestionPct} %)</span><span class="br-val r">${fmtE(-gestion)}</span></div>`:''}
      <div class="br"><span class="br-lbl">Revenu net exploitation</span><span class="br-val ${revNet>=0?'g':'r'}">${fmtE(revNet)}/an</span></div>
      <div class="br"><span class="br-lbl">Impôt estimé (${regime==='micro'?'Micro':regime==='reel'?'Foncier réel':regime==='lmnp'?'LMNP réel':'SCI IS'})</span><span class="br-val r">${fmtE(-impot)}</span></div>
      <div class="br total"><span class="br-lbl">Revenu net après impôt</span><span class="br-val">${fmtE(revApresImpot)}/an · ${fmtE(Math.round(revApresImpot/12))}/mois</span></div>
    </div>
    <div style="background:var(--surface);border:1px solid var(--border);border-radius:3px;padding:14px 16px">
      <div style="font-family:var(--font-m);font-size:9px;letter-spacing:.12em;text-transform:uppercase;color:var(--ink-3);margin-bottom:10px">Analyse de sensibilité — impact sur le cash-flow mensuel</div>
      <div class="sensi-grid">
        <div class="sensi-card"><div class="sensi-scenario">Loyer −5 %</div><div class="sensi-val" style="color:${s1>=0?'var(--go)':'var(--danger)'}">${fmtE(s1)}</div><div class="sensi-delta" style="color:${s1-cfMensApresImpot<0?'var(--danger)':'var(--go)'}">${fmtP((s1-cfMensApresImpot))} €</div></div>
        <div class="sensi-card"><div class="sensi-scenario">Loyer −10 %</div><div class="sensi-val" style="color:${s2>=0?'var(--go)':'var(--danger)'}">${fmtE(s2)}</div><div class="sensi-delta" style="color:${s2-cfMensApresImpot<0?'var(--danger)':'var(--go)'}">${fmtP((s2-cfMensApresImpot))} €</div></div>
        <div class="sensi-card"><div class="sensi-scenario">Vacance +1 mois</div><div class="sensi-val" style="color:${s3>=0?'var(--go)':'var(--danger)'}">${fmtE(s3)}</div><div class="sensi-delta" style="color:${s3-cfMensApresImpot<0?'var(--danger)':'var(--go)'}">${fmtP((s3-cfMensApresImpot))} €</div></div>
        <div class="sensi-card"><div class="sensi-scenario">Vacance +2 mois</div><div class="sensi-val" style="color:${s4>=0?'var(--go)':'var(--danger)'}">${fmtE(s4)}</div><div class="sensi-delta" style="color:${s4-cfMensApresImpot<0?'var(--danger)':'var(--go)'}">${fmtP((s4-cfMensApresImpot))} €</div></div>
        <div class="sensi-card"><div class="sensi-scenario">Taux crédit +0,5 %</div><div class="sensi-val" style="color:${s5>=0?'var(--go)':'var(--danger)'}">${fmtE(s5)}</div><div class="sensi-delta" style="color:${s5-cfMensApresImpot<0?'var(--danger)':'var(--go)'}">${fmtP((s5-cfMensApresImpot))} €</div></div>
        <div class="sensi-card"><div class="sensi-scenario">Taux crédit +1 %</div><div class="sensi-val" style="color:${s6>=0?'var(--go)':'var(--danger)'}">${fmtE(s6)}</div><div class="sensi-delta" style="color:${s6-cfMensApresImpot<0?'var(--danger)':'var(--go)'}">${fmtP((s6-cfMensApresImpot))} €</div></div>
      </div>
      <div style="margin-top:10px;padding:8px 10px;background:var(--card);border-radius:3px;font-family:var(--font-m);font-size:11px;color:var(--ink-2)">
        Mensualité crédit : <b>${fmtE(mens)}/mois</b> · Capital restant dû après 5 ans : <b>${fmtE(capital-Array.from({length:60},(_,i)=>calcMens(capital,taux,duree)-capital*(taux/100/12)*Math.pow(1+taux/100/12,i)).reduce((a,b)=>a+b,0))}</b> · Réserve après achat : <b style="color:var(--go)">${fmtE(125000-apport)}</b>
      </div>
    </div>
    ${alertes.map(a=>`<div class="res-alert ${a.t}"><span>●</span><span>${a.m}</span></div>`).join('')}
  `;
}

document.addEventListener('DOMContentLoaded',()=>{ initBilanSlider(); });

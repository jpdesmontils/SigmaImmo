if (new URLSearchParams(location.search).has('embedded')) document.documentElement.classList.add('embedded');

/* ── TABS ── */
function showTab(id) {
  document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
  document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));
  document.getElementById(id).classList.add('active');
  event.currentTarget.classList.add('active');
  if (id === 'bilan') setTimeout(initSlider, 50);
}

/* ── BILAN SLIDER ── */
function fmt(n) { return n.toLocaleString('fr-FR') + ' €'; }
function calcBilan(prixM2) {
  const surface = 800;
  const achat = 180000;
  const droits = Math.round(achat * 0.00715);
  const geometre = 3500;
  const notaire = 4200;
  const portage = Math.round(achat * 0.7 * 0.05 / 2);
  const charges = droits + geometre + notaire + portage;
  const vsbTerrain = surface * prixM2;
  const vsbMaison = 162000;
  const vsbTotal = vsbTerrain + vsbMaison;
  const margeBrute = vsbTotal - achat - charges;
  const is = margeBrute > 0 ? Math.round(Math.min(margeBrute, 42500) * 0.15 + Math.max(0, margeBrute - 42500) * 0.25) : 0;
  const margeNette = margeBrute - is;
  const pctNette = ((margeNette / vsbTotal) * 100);
  return { vsbTerrain, vsbTotal, margeBrute, margeNette, pctNette };
}

function initSlider() {
  const slider = document.getElementById('terrain-slider');
  if (!slider || slider._inited) return;
  slider._inited = true;
  function update() {
    const v = parseInt(slider.value);
    document.getElementById('terrain-price-label').textContent = v + ' €/m²';
    const r = calcBilan(v);
    document.getElementById('vsb-terrain').textContent = fmt(r.vsbTerrain);
    document.getElementById('vsb-total').textContent = fmt(r.vsbTotal);
    document.getElementById('marge-brute').textContent = fmt(r.margeBrute);
    const mn = document.getElementById('marge-nette');
    mn.textContent = fmt(r.margeNette) + ' · ' + r.pctNette.toFixed(1) + '%';
    mn.style.color = r.margeNette >= 0 ? 'var(--go)' : 'var(--danger)';
    const pct = Math.min(100, Math.max(0, r.pctNette));
    const bar = document.getElementById('marge-bar');
    bar.style.width = pct * 2.5 + '%';
    bar.style.background = r.pctNette < 10 ? 'var(--danger)' : r.pctNette < 18 ? 'var(--warn)' : 'var(--go)';
    document.getElementById('marge-bar-pct').textContent = r.pctNette.toFixed(1) + '%';
  }
  slider.addEventListener('input', update);
  update();
}

/* Init premier onglet visible */
document.addEventListener('DOMContentLoaded', function() {
  calc();
});

/* ── SIMULATEUR MDB ── */
function v(id){ return parseFloat(document.getElementById(id).value) || 0; }
function vs(id){ return document.getElementById(id).value; }
function fmtE(n){ return (n<0?'−':'')+Math.abs(Math.round(n)).toLocaleString('fr-FR')+' €'; }
function fmtP(n){ return (n>=0?'+':'')+n.toFixed(1)+' %'; }
function fmtPabs(n){ return Math.abs(n).toFixed(1)+' %'; }
function colorClass(n, seuil){ return n>=seuil?'g':n>=seuil*0.6?'w':'r'; }

function calcIS(base){
  if(base<=0) return 0;
  const t1 = Math.min(base, 42500)*0.15;
  const t2 = Math.max(0, base-42500)*0.25;
  return t1+t2;
}

function calc(){
  const achat = v('s-achat');
  const tauxDroits = v('s-droits');
  const honoAchat = v('s-hono-achat');
  const apport = v('s-apport');
  const tauxCredit = v('s-taux')/100;
  const portage = v('s-portage');
  const travaux = v('s-travaux');
  const moe = v('s-moe')/100;
  const doAssur = v('s-do')/100;
  const geometre = v('s-geometre');
  const divers = v('s-divers');
  const nbLots = v('s-lots');
  const pvente = v('s-pvente');
  const tauxCommerc = v('s-commerc')/100;
  const tvaTaux = v('s-tva-taux')/100;
  const tvaModeVal = vs('s-tva');
  const cible = v('s-cible');

  // CHARGES
  const droits = achat * tauxDroits;
  const fraisAnnexes = travaux*(moe+doAssur) + geometre + divers;
  const totalHorsFin = achat + droits + honoAchat + travaux + fraisAnnexes;
  const capitalEmprunte = Math.max(0, totalHorsFin - apport);
  const portageEuros = capitalEmprunte * tauxCredit * (portage/12);

  // RECETTES
  const vsb = nbLots * pvente;
  const commerc = vsb * tauxCommerc;

  // TVA
  let tva = 0;
  if(tvaModeVal === 'marge'){
    const achatVentile = achat / Math.max(1, nbLots);
    const margeParLot = pvente - achatVentile;
    tva = Math.max(0, margeParLot * tvaTaux) * nbLots;
  } else if(tvaModeVal === 'total'){
    tva = vsb * tvaTaux / (1+tvaTaux);
  }

  const totalCharges = achat + droits + honoAchat + travaux + fraisAnnexes + portageEuros + commerc + tva;
  const margeBrute = vsb - totalCharges;
  const is = calcIS(margeBrute);
  const margeNette = margeBrute - is;
  const margeNettePct = vsb > 0 ? (margeNette/vsb)*100 : 0;
  const margeBrutePct = vsb > 0 ? (margeBrute/vsb)*100 : 0;
  const rfp = apport > 0 ? (margeNette/apport)*100 : 0;
  const coutRevient = totalCharges / Math.max(1, nbLots);
  const pointMort = totalCharges;

  // PMA (prix max achat pour atteindre la cible)
  // VSB - fraisVente - travaux - annexes - portage approximatif - IS approx - cible*VSB = PMA + droits*(PMA)
  // On résout itérativement (3 passes suffisent)
  let pma = vsb * (1 - cible/100) - travaux - fraisAnnexes - geometre - divers - commerc;
  for(let i=0;i<5;i++){
    const portageApprox = Math.max(0,pma+travaux+fraisAnnexes-apport)*tauxCredit*(portage/12);
    const mbApprox = vsb - pma*(1+tauxDroits) - travaux - fraisAnnexes - portageApprox - commerc;
    const isApprox = calcIS(mbApprox);
    pma = (vsb*(1-cible/100) - travaux - fraisAnnexes - portageApprox - commerc - isApprox) / (1+tauxDroits);
  }
  const ecartPma = pma - achat;
  const ecartPmaPct = achat > 0 ? (ecartPma/achat)*100 : 0;

  // SENSIBILITÉ
  function margeNetteAvec(vsbMult, travauxMult, portageAdd){
    const v2 = vsb*vsbMult;
    const t2 = travaux*travauxMult;
    const fa2 = t2*(moe+doAssur)+geometre+divers;
    const p2 = portage+portageAdd;
    const ce2 = Math.max(0, achat+droits+honoAchat+t2+fa2-apport);
    const po2 = ce2*tauxCredit*(p2/12);
    const c2 = v2*tauxCommerc;
    const tc2 = achat+droits+honoAchat+t2+fa2+po2+c2+tva;
    const mb2 = v2-tc2;
    return mb2 > 0 ? mb2-calcIS(mb2) : mb2;
  }
  const sensiVSBm5 = margeNetteAvec(0.95,1,0);
  const sensiVSBm10 = margeNetteAvec(0.90,1,0);
  const sensiTravp10 = margeNetteAvec(1,1.10,0);
  const sensiTravp20 = margeNetteAvec(1,1.20,0);
  const sensiPort3 = margeNetteAvec(1,1,3);
  const sensiPort6 = margeNetteAvec(1,1,6);
  const pctOf = (n) => vsb>0?(n/vsb)*100:0;

  // VERDICT
  let verdictClass, verdictLabel, verdictSub;
  if(margeNettePct >= cible && ecartPma >= 0){
    verdictClass='go'; verdictLabel='GO';
    verdictSub=`Marge nette ${fmtPabs(margeNettePct)} ≥ cible ${fmtPabs(cible)} · PMA > prix demandé de ${fmtE(ecartPma)}`;
  } else if(margeNettePct >= cible*0.65 || (margeNettePct >= 10 && ecartPma < 0 && ecartPma > -achat*0.08)){
    verdictClass='cond'; verdictLabel='GO conditionnel';
    verdictSub=`Marge nette ${fmtPabs(margeNettePct)} — négocier le prix ou optimiser les frais`;
  } else {
    verdictClass='nogo'; verdictLabel='NO-GO';
    verdictSub=`Marge nette insuffisante (${fmtPabs(margeNettePct)}) ou prix trop élevé vs PMA`;
  }

  // ALERTES
  const alertes = [];
  if(margeNettePct < 10) alertes.push({t:'d', msg:'<b>Marge nette < 10 %</b> — en dessous du seuil de viabilité minimum MDB.'});
  if(rfp < 20) alertes.push({t:'w', msg:`<b>Rendement fonds propres ${rfp.toFixed(1)} %</b> — inférieur au seuil recommandé de 20 %.`});
  if(ecartPma < 0) alertes.push({t:'w', msg:`<b>PMA ${fmtE(pma)} < prix demandé ${fmtE(achat)}</b> — négocier ${fmtE(-ecartPma)} ou ${fmtPabs(-ecartPmaPct)} de décote.`});
  if(capitalEmprunte > apport*3) alertes.push({t:'w', msg:'<b>Levier élevé :</b> le capital emprunté dépasse 3× l\'apport — risque de refus bancaire.'});
  if(margeNette > 0 && sensiVSBm10 < 0) alertes.push({t:'w', msg:'<b>Fragilité VSB :</b> une baisse de prix de sortie de 10 % rend l\'opération déficitaire.'});

  // RENDER
  const panel = document.getElementById('res-panel');
  panel.innerHTML = `
    <div class="verdict-bar ${verdictClass}">
      <div class="verdict-title">${verdictLabel}</div>
      <div class="verdict-sub">${verdictSub}</div>
    </div>

    <div class="kpi4">
      <div class="kpi4-cell">
        <div class="kpi4-lbl">Marge nette après IS</div>
        <div class="kpi4-val ${colorClass(margeNettePct, cible)}">${fmtE(margeNette)}</div>
        <div class="kpi4-sub">${margeNettePct.toFixed(1)} % de la VSB</div>
      </div>
      <div class="kpi4-cell">
        <div class="kpi4-lbl">VSB totale</div>
        <div class="kpi4-val">${fmtE(vsb)}</div>
        <div class="kpi4-sub">${nbLots} lot${nbLots>1?'s':''} × ${fmtE(pvente)}</div>
      </div>
      <div class="kpi4-cell">
        <div class="kpi4-lbl">Rendement fonds propres</div>
        <div class="kpi4-val ${colorClass(rfp,20)}">${rfp.toFixed(1)} %</div>
        <div class="kpi4-sub">Marge nette / apport ${fmtE(apport)}</div>
      </div>
      <div class="kpi4-cell">
        <div class="kpi4-lbl">IS estimé</div>
        <div class="kpi4-val">${fmtE(is)}</div>
        <div class="kpi4-sub">Taux effectif ${margeBrute>0?((is/margeBrute)*100).toFixed(1):0} %</div>
      </div>
    </div>

    <div class="pma-gauge">
      <div class="pma-gauge-title">Prix maximum d'acquisition (PMA) vs prix demandé</div>
      <div class="pma-nums">
        <span style="font-size:12px;color:var(--ink-2)">Prix demandé : <b style="color:var(--ink);font-family:var(--font-m)">${fmtE(achat)}</b></span>
        <span style="font-size:12px;color:var(--ink-2)">PMA : <b style="font-family:var(--font-m);color:${pma>=achat?'var(--go)':'var(--danger)'}">${fmtE(pma)}</b></span>
      </div>
      <div class="pma-track">
        <div class="pma-fill" style="width:${Math.min(100,Math.max(5,(achat/Math.max(pma,achat))*100))}%;background:${pma>=achat?'var(--go)':'var(--danger)'}"></div>
      </div>
      <div class="pma-legend">
        <span>0 €</span>
        <span class="${pma>=achat?'pma-ok':'pma-ko'}">${ecartPma>=0?'PMA > prix ✓ +'+fmtE(ecartPma):'Prix > PMA ✗ −'+fmtE(-ecartPma)}</span>
        <span>${fmtE(Math.max(pma,achat)*1.05)}</span>
      </div>
    </div>

    <div class="bilan-res">
      <div class="br grp"><span>Charges</span><span></span></div>
      <div class="br"><span class="br-lbl">Prix d'achat</span><span class="br-val">${fmtE(achat)}</span></div>
      <div class="br sub"><span class="br-lbl">Droits de mutation (${(tauxDroits*100).toFixed(3)} %)</span><span class="br-val r">${fmtE(-droits)}</span></div>
      ${honoAchat>0?`<div class="br sub"><span class="br-lbl">Honoraires agence achat</span><span class="br-val r">${fmtE(-honoAchat)}</span></div>`:''}
      ${travaux>0?`<div class="br sub"><span class="br-lbl">Travaux</span><span class="br-val r">${fmtE(-travaux)}</span></div>`:''}
      ${fraisAnnexes>0?`<div class="br sub"><span class="br-lbl">Frais annexes (MOE, DO, géomètre, divers)</span><span class="br-val r">${fmtE(-fraisAnnexes)}</span></div>`:''}
      <div class="br sub"><span class="br-lbl">Frais de portage (${portage} mois · ${(tauxCredit*100).toFixed(2)} %)</span><span class="br-val r">${fmtE(-portageEuros)}</span></div>
      <div class="br sub"><span class="br-lbl">Commercialisation sortie (${(tauxCommerc*100).toFixed(1)} %)</span><span class="br-val r">${fmtE(-commerc)}</span></div>
      ${tva>0?`<div class="br sub"><span class="br-lbl">TVA (${tvaModeVal==='marge'?'sur marge':'sur prix total'})</span><span class="br-val r">${fmtE(-tva)}</span></div>`:''}
      <div class="br"><span class="br-lbl" style="font-weight:500;color:var(--ink)">Total charges</span><span class="br-val r">${fmtE(-totalCharges)}</span></div>
      <div class="br grp"><span>Résultats</span><span></span></div>
      <div class="br"><span class="br-lbl">Marge brute avant IS</span><span class="br-val ${margeBrute>=0?'g':'r'}">${fmtE(margeBrute)} · ${margeBrutePct.toFixed(1)} %</span></div>
      <div class="br sub"><span class="br-lbl">IS estimé (PME : 15 % → 25 %)</span><span class="br-val r">${fmtE(-is)}</span></div>
      <div class="br total"><span class="br-lbl">Marge nette après IS</span><span class="br-val">${fmtE(margeNette)} · ${margeNettePct.toFixed(1)} %</span></div>
    </div>

    <div style="background:var(--surface);border:1px solid var(--border);border-radius:4px;padding:14px 16px">
      <div style="font-family:var(--font-m);font-size:9px;letter-spacing:.12em;text-transform:uppercase;color:var(--ink-3);margin-bottom:10px">Analyse de sensibilité — impact sur la marge nette</div>
      <div class="sensi-grid">
        <div class="sensi-card">
          <div class="sensi-scenario">VSB −5 %</div>
          <div class="sensi-val" style="color:${sensiVSBm5>=0?'var(--go)':'var(--danger)'}">${fmtE(sensiVSBm5)}</div>
          <div class="sensi-delta" style="color:${sensiVSBm5-margeNette<0?'var(--danger)':'var(--go)'}">${fmtP(pctOf(sensiVSBm5)-margeNettePct)} pts</div>
        </div>
        <div class="sensi-card">
          <div class="sensi-scenario">VSB −10 %</div>
          <div class="sensi-val" style="color:${sensiVSBm10>=0?'var(--go)':'var(--danger)'}">${fmtE(sensiVSBm10)}</div>
          <div class="sensi-delta" style="color:${sensiVSBm10-margeNette<0?'var(--danger)':'var(--go)'}">${fmtP(pctOf(sensiVSBm10)-margeNettePct)} pts</div>
        </div>
        <div class="sensi-card">
          <div class="sensi-scenario">Travaux +10 %</div>
          <div class="sensi-val" style="color:${sensiTravp10>=0?'var(--go)':'var(--danger)'}">${fmtE(sensiTravp10)}</div>
          <div class="sensi-delta" style="color:${sensiTravp10-margeNette<0?'var(--danger)':'var(--go)'}">${fmtP(pctOf(sensiTravp10)-margeNettePct)} pts</div>
        </div>
        <div class="sensi-card">
          <div class="sensi-scenario">Travaux +20 %</div>
          <div class="sensi-val" style="color:${sensiTravp20>=0?'var(--go)':'var(--danger)'}">${fmtE(sensiTravp20)}</div>
          <div class="sensi-delta" style="color:${sensiTravp20-margeNette<0?'var(--danger)':'var(--go)'}">${fmtP(pctOf(sensiTravp20)-margeNettePct)} pts</div>
        </div>
        <div class="sensi-card">
          <div class="sensi-scenario">Portage +3 mois</div>
          <div class="sensi-val" style="color:${sensiPort3>=0?'var(--go)':'var(--danger)'}">${fmtE(sensiPort3)}</div>
          <div class="sensi-delta" style="color:${sensiPort3-margeNette<0?'var(--danger)':'var(--go)'}">${fmtP(pctOf(sensiPort3)-margeNettePct)} pts</div>
        </div>
        <div class="sensi-card">
          <div class="sensi-scenario">Portage +6 mois</div>
          <div class="sensi-val" style="color:${sensiPort6>=0?'var(--go)':'var(--danger)'}">${fmtE(sensiPort6)}</div>
          <div class="sensi-delta" style="color:${sensiPort6-margeNette<0?'var(--danger)':'var(--go)'}">${fmtP(pctOf(sensiPort6)-margeNettePct)} pts</div>
        </div>
      </div>
      <div style="margin-top:8px;padding:8px 10px;background:var(--card);border-radius:3px;font-family:var(--font-m);font-size:11px;color:var(--ink-2)">
        Point mort VSB : <b style="color:var(--ink)">${fmtE(pointMort)}</b> · Coût de revient par lot : <b style="color:var(--ink)">${fmtE(coutRevient)}</b> · Capital emprunté : <b style="color:var(--ink)">${fmtE(capitalEmprunte)}</b>
      </div>
    </div>

    ${alertes.map(a=>`<div class="res-alert ${a.t}"><span>●</span><span>${a.msg}</span></div>`).join('')}
  `;
}

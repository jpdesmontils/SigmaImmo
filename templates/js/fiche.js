(() => {
  const scriptUrl = document.currentScript?.src || location.href;
  const apiUrl = new URL('../../api/', scriptUrl);
  const config = document.documentElement.dataset;
  const type = config.analysisType;
  const analysisId = new URLSearchParams(location.search).get('id') || new URLSearchParams(location.search).get('listing') || window.__immoAnalysisId;
  const euro = value => typeof value === 'number' ? value.toLocaleString('fr-FR', { style: 'currency', currency: 'EUR', maximumFractionDigits: 0 }) : '—';
  const percent = value => typeof value === 'number' ? `${value.toLocaleString('fr-FR')} %` : '—';
  const date = value => value ? new Intl.DateTimeFormat('fr-FR').format(new Date(`${value}T00:00:00`)) : '—';
  const label = value => String(value || '—').replaceAll('_', ' ').replace(/\b\w/g, char => char.toUpperCase());
  const scoreClass = score => score >= 70 ? 'sh' : score >= 50 ? 'sm' : 'sl';
  const escapeHtml = value => String(value ?? '').replace(/[&<>"']/g, char => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[char]);
  const valueFor = (context, name) => name === '.' ? context['.'] : name.split('.').reduce((value, key) => value?.[key], context);

  function renderTemplate(template, context) {
    const root = { children: [] }, stack = [root];
    for (const part of template.split(/(\{\{\s*[#/^]?\s*[\w.]+\s*\}\})/)) {
      const match = part.match(/^\{\{\s*([#/^]?)\s*([\w.]+)\s*\}\}$/);
      if (!match) { if (part) stack.at(-1).children.push(part); continue; }
      const [, marker, name] = match;
      if (marker === '#' || marker === '^') { const node = { marker, name, children: [] }; stack.at(-1).children.push(node); stack.push(node); }
      else if (marker === '/') { if (stack.length === 1 || stack.at(-1).name !== name) throw new Error(`Balise de modèle non fermée : ${name}`); stack.pop(); }
      else stack.at(-1).children.push({ marker: 'value', name });
    }
    if (stack.length !== 1) throw new Error(`Balise de modèle non fermée : ${stack.at(-1).name}`);
    const renderNodes = (nodes, scope) => nodes.map(node => {
      if (typeof node === 'string') return node;
      const value = valueFor(scope, node.name);
      if (node.marker === 'value') return escapeHtml(value);
      const present = Array.isArray(value) ? value.length > 0 : Boolean(value);
      if ((node.marker === '#' && !present) || (node.marker === '^' && present)) return '';
      if (node.marker === '^') return renderNodes(node.children, scope);
      return Array.isArray(value) ? value.map(item => renderNodes(node.children, { ...scope, ...(item && typeof item === 'object' ? item : { '.': item }) })).join('') : renderNodes(node.children, scope);
    }).join('');
    return renderNodes(root.children, context);
  }

  function locatifContext(a) {
    const announcement = a.annonce || {}, address = announcement.adresse || {}, features = announcement.caracteristiques || {}, price = announcement.prix || {}, revenue = announcement.revenus || {}, finance = a.financement || {}, summary = a.exec_summary || {}, global = a.note_globale || {};
    const decisionClass = global.decision === 'go' ? 'go' : global.decision === 'no_go' ? 'nogo' : 'cond';
    const decisionColor = decisionClass === 'go' ? '#145a2e' : decisionClass === 'nogo' ? '#831515' : '#7a4108';
    return { source: announcement.agence_nom || announcement.source_plateforme || '—', reference: announcement.reference_annonce || '—', city: address.ville || '—', postalCode: address.code_postal || '—', neighborhood: address.quartier || '—', propertyType: label(announcement.type_bien), surface: features.surface_totale_m2 ?? '—', lotsCount: features.nb_lots ?? '—', yearBuilt: features.annee_construction ?? '—', dpeBuilding: (announcement.dpe || {}).lettre_bien || '—', dpeHomes: (announcement.dpe || {}).lettre_logements || '—', fees: price.honoraires_charge || '—', analysisDate: date(a.meta?.date_analyse), promptVersion: a.meta?.version_prompt || '—', schemaVersion: a.meta?.version_schema || '—', score: global.score ?? summary.note ?? '—', scoreOffset: Math.max(0, 201.1 * (1 - Number(global.score ?? summary.note ?? 0) / 100)).toFixed(1), decision: summary.decision_label || label(global.decision), decisionClass, decisionColor, askingPrice: euro(price.affiche), pricePerSqm: euro(price.m2_affiche), totalCost: euro(finance.cout_total), notaryFees: euro(finance.frais_notaire), annualRevenue: euro(revenue.total_annonce_an), annualRevenueRebuilt: euro(revenue.total_reconstitue_an), netYield: percent(summary.rendement_net_min_pct), cashflowRange: `${euro(summary.cashflow_min_mois)} à ${euro(summary.cashflow_max_mois)}`, summary: summary.resume_narratif || 'Résumé indisponible.', strength: summary.point_fort || '—', watchout: summary.point_vigilance || '—', creditTerm: finance.duree_credit_ans ? `${finance.duree_credit_ans} ans` : '—', creditRate: percent(finance.taux_credit_pct), conditions: (global.conditions_go || []).map((text, index) => ({ number: String(index + 1).padStart(2, '0'), text })), axes: Object.entries(a.axes || {}).map(([key, axis]) => ({ name: label(key), weight: axis.poids_pct ?? '—', score: axis.score ?? '—', verdict: label(axis.verdict), comment: axis.commentaire_synthetique || '—', scoreClass: scoreClass(axis.score), kpis: (axis.kpis || []).map(kpi => ({ label: kpi.label || kpi.nom || '—', value: kpi.valeur || kpi.value || '—', source: kpi.source || '—', comment: kpi.commentaire || '', valueClass: scoreClass(kpi.score) })) })), lots: (announcement.lots || []).map(lot => ({ level: lot.niveau || '—', type: label(lot.type), surface: lot.surface_m2 ? `${lot.surface_m2} m²` : '—', tenant: lot.locataire || '—', rent: euro(lot.loyer_hc_mois), charges: euro(lot.charges_mois), annualTotal: euro((lot.loyer_total_mois || 0) * 12), note: lot.note || '—', lotClass: lot.type === 'local_commercial' ? 'lb-comm' : 'lb-resi' })), scenarios: (finance.scenarios || []).map(scenario => ({ label: scenario.label || 'Scénario', deposit: euro(scenario.apport_eur), loan: euro(scenario.capital_emprunte), payment: euro(scenario.mensualite_credit), income: euro(scenario.revenus_hc_mois), expenses: euro(scenario.charges_nr_mois), cashflow: euro(scenario.cashflow_mois), grossYield: percent(scenario.rendement_brut_pct), netYield: percent(scenario.rendement_net_pct) })), risks: (a.risques || []).map(risk => ({ title: risk.titre || '—', criticality: label(risk.criticite), action: risk.action || '—', impact: euro(risk.impact_cashflow_si_realise ?? risk.budget_estime_max ?? risk.budget_estime_min), criticalityClass: risk.criticite === 'critique' ? 'rc' : risk.criticite === 'eleve' ? 're' : 'rm', impactClass: risk.criticite === 'critique' ? 'tr' : 'tw' })), upsides: (a.upsides || []).map(upside => ({ title: upside.titre || '—', description: upside.description || '—', impact: `${euro(upside.impact_revenu_an_min)} à ${euro(upside.impact_revenu_an_max)}`, horizon: label(upside.horizon) })), sources: (a.sources || []).map(source => ({ label: source.label || source })) };
  }

  function mdbContext(a) {
    const summary = a.executive_summary || {};
    return { ...a, date_analyse: date(a.date_analyse), decisionLabel: label(summary.decision), decisionClass: summary.decision === 'go' ? 'go' : summary.decision === 'no_go' ? 'nogo' : 'cond', executive_summary: { ...summary, marge_nette_base_eur: euro(summary.marge_nette_base_eur), prix_demande_eur: euro(summary.prix_demande_eur), prix_max_acquisition_eur: euro(summary.prix_max_acquisition_eur), ecart_prix_demande_pma_eur: euro(summary.ecart_prix_demande_pma_eur) }, bilan_promoteur: { ...(a.bilan_promoteur || {}), scenarios: (a.bilan_promoteur?.scenarios || []).map(s => Object.fromEntries(Object.entries(s).map(([key, value]) => [key, key.endsWith('_eur') ? euro(value) : value]))) }, planning: { ...(a.planning || {}), cout_portage_eur: euro(a.planning?.cout_portage_eur) }, risques: (a.risques || []).map(r => ({ ...r, criticite: label(r.criticite), impact_marge_eur: euro(r.impact_marge_eur) })) };
  }

  function setupGallery(listing) {
    const gallery = document.querySelector('[data-property-gallery]');
    if (!gallery) return;
    const sourceLink = document.querySelector('[data-source-link]');
    if (sourceLink && listing?.url) sourceLink.href = listing.url;
    const images = [...new Set((listing?.images || []).filter(Boolean))];
    if (!images.length) { gallery.hidden = true; return; }
    const main = gallery.querySelector('[data-gallery-main]');
    const thumbs = gallery.querySelector('[data-gallery-thumbs]');
    const select = index => { main.src = images[index]; main.alt = `Photo ${index + 1} du bien`; [...thumbs.children].forEach((button, i) => button.classList.toggle('active', i === index)); };
    images.forEach((src, index) => { const button = document.createElement('button'); button.type = 'button'; button.className = 'property-thumb'; button.style.backgroundImage = `url("${src.replace(/"/g, '%22')}")`; button.setAttribute('aria-label', `Afficher la photo ${index + 1}`); button.addEventListener('click', () => select(index)); thumbs.append(button); });
    select(0);
  }

  function setupTabs(app) { app.querySelectorAll('.tab-btn').forEach(button => button.addEventListener('click', () => { app.querySelectorAll('.tab-content').forEach(panel => panel.classList.toggle('active', panel.id === button.dataset.tab)); app.querySelectorAll('.tab-btn').forEach(tab => tab.classList.toggle('active', tab === button)); })); }

  function addRecalculateButton() {
    const button = document.createElement('button'); button.className = 'recalculate-button'; button.type = 'button'; button.title = 'Recalculer l’analyse'; button.setAttribute('aria-label', 'Recalculer l’analyse'); button.textContent = '↻';
    button.addEventListener('click', () => openRecalculationModal(button)); document.body.append(button);
  }

  function openRecalculationModal(button) {
    const modal = document.createElement('div'); modal.className = 'recalculate-modal'; modal.innerHTML = '<div class="recalculate-modal-box" role="dialog" aria-modal="true" aria-labelledby="recalculate-title"><h2 id="recalculate-title">Recalculer l’analyse</h2><p>Choisissez le type de calcul à relancer pour cette annonce.</p><div class="recalculate-actions"><button type="button" data-action="cancel">Annuler</button><button type="button" data-type="locatif">Locatif</button><button type="button" data-type="mdb">Marchand de biens</button></div></div>';
    const close = () => modal.remove(); modal.addEventListener('click', event => { if (event.target === modal) close(); }); modal.querySelector('[data-action="cancel"]').addEventListener('click', close);
    modal.querySelectorAll('[data-type]').forEach(choice => choice.addEventListener('click', () => { close(); startRecalculation(button, choice.dataset.type); })); document.body.append(modal); modal.querySelector('[data-type]')?.focus();
  }

  async function startRecalculation(button, selectedType) {
    button.disabled = true; button.textContent = '⌛'; button.title = 'Recalcul en cours';
    try {
      const response = await fetch(new URL('analyze.php', apiUrl), { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ id: analysisId, type: selectedType }) });
      const payload = await response.json(); if (!response.ok) throw new Error(payload.error || 'Démarrage du recalcul impossible');
      const poll = setInterval(async () => { try { const statusResponse = await fetch(`${new URL('analyze.php', apiUrl)}?id=${encodeURIComponent(analysisId)}`); const statusPayload = await statusResponse.json(); if (statusPayload.job?.status === 'completed') { clearInterval(poll); location.reload(); } if (statusPayload.job?.status === 'failed') { clearInterval(poll); resetButton(button); alert(`Recalcul impossible : ${statusPayload.job.error || 'erreur inconnue'}`); } } catch (_) {} }, 2500);
    } catch (error) { resetButton(button); alert(`Recalcul impossible : ${error.message}`); }
  }
  function resetButton(button) { button.disabled = false; button.textContent = '↻'; button.title = 'Recalculer l’analyse'; }

  async function render() {
    const app = document.getElementById('app');
    if (!analysisId || !['locatif', 'mdb'].includes(type)) { app.className = 'error'; app.textContent = 'Identifiant ou type d’analyse manquant.'; return; }
    try {
      const response = await fetch(`${new URL('analysis.php', apiUrl)}?id=${encodeURIComponent(analysisId)}&type=${type}`); const payload = await response.json();
      if (!response.ok || !payload.analysis) throw new Error(payload.error || 'Analyse indisponible');
      const context = type === 'locatif' ? locatifContext(payload.analysis) : mdbContext(payload.analysis);
      document.title = `Fiche ${type === 'locatif' ? 'investissement locatif' : 'MDB'} — ${context.reference || context.annonce_id || analysisId}`;
      app.className = ''; app.innerHTML = renderTemplate(document.getElementById('fiche-template').innerHTML, context); setupTabs(app); setupGallery(payload.listing); addRecalculateButton();
    } catch (error) { app.className = 'error'; app.textContent = `Analyse indisponible : ${error.message}`; }
  }
  render();
})();

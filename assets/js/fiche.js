(() => {
  const currentScript = document.currentScript;
  const scriptUrl = currentScript?.src || location.href;
  const apiUrl = new URL('../../api/', scriptUrl);
  const runtimeContext = window.__immoAnalysisContext || {};
  const defaultType = runtimeContext.type || currentScript?.dataset.analysisType || document.documentElement.dataset.analysisType;
  const query = new URLSearchParams(location.search);
  const defaultAnalysisId = runtimeContext.id || query.get('id') || query.get('listing');
  let activeAnalysisId = defaultAnalysisId;
  const euro = value => typeof value === 'number' ? value.toLocaleString('fr-FR', { style: 'currency', currency: 'EUR', maximumFractionDigits: 0 }) : '—';
  const percent = value => typeof value === 'number' ? `${value.toLocaleString('fr-FR')} %` : '—';
  const signedPercent = value => typeof value === 'number' ? `${value > 0 ? '+' : ''}${value.toLocaleString('fr-FR')} %` : '—';
  const date = value => value ? new Intl.DateTimeFormat('fr-FR').format(new Date(`${value}T00:00:00`)) : '—';
  const duration = value => typeof value === 'number' ? `${Math.floor(value / 60) ? `${Math.floor(value / 60)} h ` : ''}${value % 60 ? `${value % 60} min` : ''}`.trim() : '—';
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

  function negotiationContext(negotiation) {
    const scale = negotiation?.echelle_valeur || {};
    const coherence = negotiation?.coherence_prix || {};
    const commune = coherence.reference_commune || {};
    const neighborhood = coherence.reference_quartier || {};
    const analyzed = coherence.bien_analyse || {};
    const references = Array.isArray(coherence.references_marche) ? coherence.references_marche : [];
    const scaleValues = [scale.valeur_prudente_min_eur, scale.valeur_prudente_max_eur, scale.offre_recommandee_min_eur, scale.offre_recommandee_max_eur, scale.prix_demande_eur, scale.cout_total_eur];
    const scaleAvailable = scaleValues.every(Number.isFinite);
    const values = scaleAvailable ? scaleValues : [];
    const minimum = Math.min(...values), maximum = Math.max(...values), padding = Math.max((maximum - minimum) * 0.05, 1);
    const position = value => `${((value - minimum + padding) / (maximum - minimum + padding * 2) * 100).toFixed(2)}%`;
    const rangeStyle = (from, to) => `left:${position(from)};width:${((to - from) / (maximum - minimum + padding * 2) * 100).toFixed(2)}%`;
    const unavailable = 'Non déterminé';
    const range = (from, to) => Number.isFinite(from) && Number.isFinite(to) ? `${euro(from)} – ${euro(to)}` : unavailable;
    const optionalEuro = value => Number.isFinite(value) ? euro(value) : unavailable;
    const optionalPercent = value => Number.isFinite(value) ? signedPercent(value) : unavailable;
    const referenceSummary = reference => [Number.isFinite(reference.prix_m2_eur) ? `${euro(reference.prix_m2_eur)}/m²` : unavailable, reference.source && reference.source !== 'non_determinable' ? reference.source : unavailable, reference.date || unavailable].join(' · ');
    const marketRows = references.map(item => ({ source: item.source || unavailable, propertyType: item.type_bien || unavailable, pricePerSqm: optionalEuro(item.prix_m2_eur), range: Number.isFinite(item.fourchette_min_eur_m2) && Number.isFinite(item.fourchette_max_eur_m2) ? range(item.fourchette_min_eur_m2, item.fourchette_max_eur_m2) : unavailable, precision: item.precision || '', referenceDate: item.date || unavailable }));
    marketRows.push({ source: 'Bien analysé', propertyType: analyzed.type_bien || unavailable, pricePerSqm: optionalEuro(analyzed.prix_m2_eur), range: analyzed.fourchette_ou_commentaire || unavailable, precision: '', referenceDate: analyzed.date || unavailable, analyzed: true });
    return { negotiationAvailable: Boolean(negotiation?.echelle_valeur || negotiation?.coherence_prix), scaleAvailable, prudentRange: range(scale.valeur_prudente_min_eur, scale.valeur_prudente_max_eur), prudentEstimate: scale.valeur_prudente_estimee, offerRange: range(scale.offre_recommandee_min_eur, scale.offre_recommandee_max_eur), askingPriceNegotiation: optionalEuro(scale.prix_demande_eur), totalCostNegotiation: optionalEuro(scale.cout_total_eur), prudentStyle: scaleAvailable ? rangeStyle(scale.valeur_prudente_min_eur, scale.valeur_prudente_max_eur) : '', offerStyle: scaleAvailable ? rangeStyle(scale.offre_recommandee_min_eur, scale.offre_recommandee_max_eur) : '', askingStyle: scaleAvailable ? `left:${position(scale.prix_demande_eur)}` : '', totalStyle: scaleAvailable ? `left:${position(scale.cout_total_eur)}` : '', negotiationArgument: scale.argumentaire || unavailable, askingPricePerSqm: optionalEuro(coherence.prix_demande_m2_eur), surfaceReference: Number.isFinite(coherence.surface_reference_m2) ? `${coherence.surface_reference_m2} m² habitables` : unavailable, communeGap: optionalPercent(commune.ecart_pct), communeReference: referenceSummary(commune), neighborhoodGap: optionalPercent(neighborhood.ecart_pct), neighborhoodReference: referenceSummary(neighborhood), marketRows, marketLimits: coherence.perimetre_et_limites || unavailable };
  }

  function patrimonialContext(a) {
    const property = a.bien || {}, address = property.adresse || {}, features = property.caracteristiques || {}, acquisition = a.acquisition || {}, quality = a.qualite_patrimoniale || {}, access = a.accessibilite_depuis_rp || a.accessibilite_depuis_paris || {}, route = access.itineraire_recommande || {}, arrival = route.gare_arrivee || {}, frequencies = access.frequences || {}, lastMile = access.dernier_kilometre || {}, roundTrip = access.aller_retour_journee || {}, decision = a.decision || {}, finance = a.financement || {}, leversData = a.leviers_optimisation || {};
    const decisionClass = decision.verdict === 'go' ? 'go' : decision.verdict === 'no_go' ? 'nogo' : 'cond';
    const decisionColor = decisionClass === 'go' ? '#145a2e' : decisionClass === 'nogo' ? '#831515' : '#7a4108';
    const scenario = (finance.scenarios || []).find(item => item.code === finance.scenario_retenu) || {};
    const safeUrl = value => { try { const parsed = new URL(value); return ['http:', 'https:'].includes(parsed.protocol) ? parsed.href : ''; } catch (_) { return ''; } };
    const sourceContext = source => typeof source === 'string' ? { label: source, url: '', date: '—' } : { label: source?.label || '—', url: safeUrl(source?.url), date: source?.date_consultation || '—' };
    const scoringAxes = Object.entries(a.notation?.axes || {}).map(([key, axis]) => ({ name: label(key), score: axis.score ?? '—', weight: axis.poids_pct ?? '—', contribution: axis.contribution_points ?? '—', confidence: label(axis.confiance), justification: axis.justification || '—', arguments: axis.arguments || [], scoreClass: scoreClass(axis.score) }));
    const criteria = Object.entries(access.evaluation?.criteres || {}).map(([key, criterion]) => ({ name: label(key), score: `${criterion.points ?? '—'}/${criterion.maximum ?? '—'}`, justification: criterion.justification || '—' }));
    const levers = Object.entries(leversData).filter(([, value]) => value && typeof value === 'object' && 'faisabilite' in value).map(([key, value]) => ({ name: label(key), feasibility: label(value.faisabilite), applicable: value.applicable ? 'Applicable' : 'Non applicable', works: euro(value.travaux_eur), delay: Number.isFinite(value.delai_estime_mois) ? `${value.delai_estime_mois} mois` : '—', central: euro(value.resultat_net_central_eur ?? value.revenu_net_annuel_central_eur), degraded: euro(value.resultat_net_degrade_eur ?? value.revenu_net_annuel_degrade_eur), conditions: (value.conditions || []).join(' · ') || '—', sources: (value.sources || []).map(sourceContext) }));
    const scenarios = (finance.scenarios || []).map(item => ({ name: label(item.code), applicable: item.applicable ? 'Oui' : 'Non', deposit: `${item.apport_pct ?? '—'} % · ${euro(item.apport_eur)}`, loan: euro(item.capital_emprunte_eur), rate: percent(item.taux_credit_pct), term: Number.isFinite(item.duree_credit_ans) ? `${item.duree_credit_ans} ans` : '—', payment: euro(item.mensualite_credit_eur), optimizationIncome: euro(item.revenus_optimisation_annuels_eur), resale: euro(item.produit_revente_net_eur), effort: euro(item.effort_mensuel_eur), returnOnCapital: percent(item.rendement_net_sur_capital_pct), netCost: euro(item.cout_net_apres_optimisation_eur), activationCondition: item.condition_activation || 'Aucune' }));
    return {
      ...negotiationContext(a.negociation || {}), source: property.source || '—', reference: property.reference || '—', listingUrl: safeUrl(property.url), city: address.ville || '—', postalCode: address.code_postal || '—', propertyType: label(property.type), surface: features.surface_habitable_m2 ?? '—', landSurface: features.surface_terrain_m2 ?? '—', dpe: features.dpe || '—', analysisDate: date(a.meta?.date_analyse), promptVersion: a.meta?.version_prompt || '—', schemaVersion: a.meta?.version_schema || '—', score: decision.score_global ?? '—', scoreOffset: Math.max(0, 201.1 * (1 - Number(decision.score_global || 0) / 100)).toFixed(1), decision: label(decision.verdict), decisionClass, decisionColor,
      askingPrice: euro(acquisition.prix_affiche_eur), askingPriceRaw: acquisition.prix_affiche_eur ?? 0, notaryFeesRaw: acquisition.frais_notaire_eur ?? 0, immediateWorks: euro(acquisition.travaux_immediats_eur), heritageWorks: euro(acquisition.travaux_patrimoniaux_eur), worksRaw: Number(acquisition.travaux_immediats_eur || 0) + Number(acquisition.travaux_patrimoniaux_eur || 0), annexFees: euro(acquisition.frais_annexes_eur), annexFeesRaw: acquisition.frais_annexes_eur ?? 0, totalCost: euro(acquisition.cout_total_projet_eur),
      scenarioName: label(decision.scenario_recommande), contribution: euro(scenario.apport_eur), netCost: euro(scenario.cout_net_apres_optimisation_eur), financeJustification: finance.justification_scenario || '—', summary: decision.synthese_argumentee || decision.synthese || 'Résumé indisponible.', strength: decision.point_fort || '—', watchout: decision.point_vigilance || '—', conditions: (decision.conditions_go || []).map((text, index) => ({ number: String(index + 1).padStart(2, '0'), text })),
      retainedLever: label(leversData.levier_retenu || decision.levier_recommande), retainedLeverJustification: leversData.justification_levier_retenu || '—', heritageUse: label(quality.adequation_usage), heritageComment: quality.commentaire_argumente || '—',
      primaryResidenceCity: access.origine?.ville || '—', departureStation: access.origine?.gare_depart || access.origine?.ville || '—', arrivalStation: arrival.nom || '—', trainTypes: (route.trains || []).map(train => typeof train === 'string' ? train : train.type).filter(Boolean).join(' · ') || '—', trainMinimum: duration(route.duree_train_min), trainTypical: duration(route.duree_train_typique), totalMinimum: duration(route.duree_totale_min), totalTypical: duration(route.duree_totale_typique), directLabel: route.direct ? 'Direct' : `${route.nombre_correspondances ?? '—'} correspondance(s)`, robustness: label(route.robustesse),
      observationPeriod: access.periode_observation?.date_debut && access.periode_observation?.date_fin ? `Du ${date(access.periode_observation.date_debut)} au ${date(access.periode_observation.date_fin)}` : '—', observedDays: (access.periode_observation?.jours_observes || []).map(date).join(' · ') || '—', reliability: label(access.fiabilite), weekdayFrequency: frequencies.jour_ouvre?.liaisons_exploitables ?? '—', saturdayFrequency: frequencies.samedi?.liaisons_exploitables ?? '—', sundayFrequency: frequencies.dimanche?.liaisons_exploitables ?? '—', fridayComment: frequencies.vendredi_soir_commentaire || '—', sundayComment: frequencies.dimanche_soir_retour_commentaire || '—', frequencyComment: frequencies.commentaire || '—', travelComment: access.evaluation?.commentaire_argumente || '—',
      lastMileMode: label(lastMile.mode_recommande), lastMileDistance: typeof lastMile.distance_km === 'number' ? `${lastMile.distance_km.toLocaleString('fr-FR')} km` : '—', lastMileDuration: duration(lastMile.duree_min), publicTransport: lastMile.transport_public?.description || '—', carRequired: lastMile.voiture_indispensable ? 'Oui' : 'Non', roundTrip: roundTrip.possible ? 'Possible' : 'Non recommandé', onSiteTime: Number.isFinite(roundTrip.temps_sur_place_estime_heures) ? `${roundTrip.temps_sur_place_estime_heures} h` : '—', roundTripComment: roundTrip.commentaire || '—', accessScore: access.evaluation?.score ?? '—', accessVerdict: label(access.evaluation?.verdict), accessStrength: access.evaluation?.point_fort || '—', accessWatchout: access.evaluation?.point_vigilance || '—',
      alternatives: (access.alternatives || []).map(item => ({ station: item.gare_arrivee?.nom || item.gare_arrivee || '—', directLabel: item.direct ? 'Direct' : `${item.nombre_correspondances ?? '—'} correspondance(s)`, duration: duration(item.duree_train_typique ?? item.duree_totale_min), comment: item.commentaire || '—' })), criteria,
      quality: [{ label: 'Emplacement', value: label(quality.qualite_emplacement) }, { label: 'Rareté', value: label(quality.rarete) }, { label: 'Liquidité', value: label(quality.liquidite_revente) }, { label: 'Bâti', value: label(quality.qualite_bati) }, { label: 'Valorisation', value: label(quality.potentiel_valorisation) }, { label: 'Horizon recommandé', value: quality.horizon_recommande_ans ? `${quality.horizon_recommande_ans} ans` : '—' }], scoringAxes, levers, scenarios,
      risks: (a.risques || []).map(item => ({ ...item, criticality: label(item.criticite), criticalityClass: item.criticite === 'critique' ? 'rc' : item.criticite === 'elevee' || item.criticite === 'eleve' ? 're' : 'rm', impactClass: item.criticite === 'critique' ? 'tr' : 'tw' })), beforeOffer: a.diligences_avant_offre || [], beforeContract: a.diligences_avant_compromis || [], warnings: a.avertissements || [], accessWarnings: access.avertissements || [], sources: (a.sources || []).map(sourceContext), accessSources: (access.sources || []).map(sourceContext)
    };
  }

  function setupGallery(root, listing, fallbackUrl) {
    const gallery = root.querySelector('[data-property-gallery]');
    if (!gallery) return;
    const sourceLink = root.querySelector('[data-source-link]');
    if (sourceLink && (listing?.url || fallbackUrl)) sourceLink.href = listing?.url || fallbackUrl;
    const images = [...new Set((listing?.images || []).filter(Boolean))];
    if (!images.length) {
      gallery.hidden = true;
      gallery.closest('.hero-top')?.classList.add('hero-top-no-gallery');
      return;
    }
    const main = gallery.querySelector('[data-gallery-main]');
    const thumbs = gallery.querySelector('[data-gallery-thumbs]');
    const select = index => { main.src = images[index]; main.alt = `Photo ${index + 1} du bien`; [...thumbs.children].forEach((button, i) => button.classList.toggle('active', i === index)); };
    images.forEach((src, index) => { const button = document.createElement('button'); button.type = 'button'; button.className = 'property-thumb'; button.style.backgroundImage = `url("${src.replace(/"/g, '%22')}")`; button.setAttribute('aria-label', `Afficher la photo ${index + 1}`); button.addEventListener('click', () => select(index)); thumbs.append(button); });
    select(0);
  }

  function setupTabs(app) { app.querySelectorAll('.tab-btn').forEach(button => button.addEventListener('click', () => { app.querySelectorAll('.tab-content').forEach(panel => panel.classList.toggle('active', panel.id === button.dataset.tab)); app.querySelectorAll('.tab-btn').forEach(tab => tab.classList.toggle('active', tab === button)); })); }

  function setupFinanceEditor(app) {
    const editor = app.querySelector('[data-finance-editor]');
    if (!editor) return;
    const update = () => {
      const price = Number(editor.dataset.price) || 0;
      const annex = Number(editor.dataset.annex) || 0;
      const notary = Math.max(0, Number(editor.querySelector('[data-finance-input="notary"]').value) || 0);
      const works = Math.max(0, Number(editor.querySelector('[data-finance-input="works"]').value) || 0);
      app.querySelectorAll('[data-finance-total]').forEach(output => { output.textContent = euro(price + annex + notary + works); });
    };
    editor.querySelectorAll('input').forEach(input => input.addEventListener('input', update));
    update();
  }

  function addRecalculateButton() {
    const button = document.createElement('button'); button.className = 'recalculate-button'; button.type = 'button'; button.title = 'Recalculer l’analyse'; button.setAttribute('aria-label', 'Recalculer l’analyse'); button.textContent = '↻';
    button.addEventListener('click', () => openRecalculationModal(button)); document.body.append(button);
  }

  function openModal({ title, message, actions }) {
    const modal = document.createElement('div');
    modal.className = 'recalculate-modal';
    const box = document.createElement('div'); box.className = 'recalculate-modal-box'; box.setAttribute('role', 'dialog'); box.setAttribute('aria-modal', 'true');
    const heading = document.createElement('h2'); heading.textContent = title; heading.id = `modal-title-${Date.now()}`; box.setAttribute('aria-labelledby', heading.id);
    const description = document.createElement('p'); description.textContent = message;
    const actionList = document.createElement('div'); actionList.className = 'recalculate-actions';
    const close = () => { modal.remove(); document.removeEventListener('keydown', handleKeydown); };
    const handleKeydown = event => { if (event.key === 'Escape') close(); };
    actions.forEach(action => {
      const actionButton = document.createElement('button'); actionButton.type = 'button'; actionButton.textContent = action.label;
      if (action.type) actionButton.dataset.type = action.type;
      actionButton.addEventListener('click', () => { close(); action.onClick?.(); }); actionList.append(actionButton);
    });
    box.append(heading, description, actionList); modal.append(box);
    modal.addEventListener('click', event => { if (event.target === modal) close(); }); document.addEventListener('keydown', handleKeydown);
    document.body.append(modal); actionList.querySelector('button')?.focus();
  }

  function openRecalculationModal(button) {
    openModal({ title: 'Recalculer l’analyse', message: 'Choisissez le type de calcul à relancer pour cette annonce.', actions: [
      { label: 'Annuler' },
      { label: 'Locatif', type: 'locatif', onClick: () => startRecalculation(button, 'locatif') },
      { label: 'Marchand de biens', type: 'mdb', onClick: () => startRecalculation(button, 'mdb') },
      { label: 'Patrimonial optimisé', type: 'patrimonial', onClick: () => startRecalculation(button, 'patrimonial') }
    ] });
  }

  function showRecalculationError(message) {
    openModal({ title: 'Recalcul impossible', message, actions: [{ label: 'Fermer', type: 'close' }] });
  }

  async function startRecalculation(button, selectedType) {
    button.disabled = true; button.textContent = '⌛'; button.title = 'Recalcul en cours';
    try {
      const response = await fetch(new URL('analyze.php', apiUrl), { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ id: activeAnalysisId, type: selectedType }) });
      const payload = await response.json(); if (!response.ok) throw new Error(payload.error || 'Démarrage du recalcul impossible');
      const poll = setInterval(async () => { try { const statusResponse = await fetch(`${new URL('analyze.php', apiUrl)}?id=${encodeURIComponent(activeAnalysisId)}`); const statusPayload = await statusResponse.json(); if (statusPayload.job?.status === 'completed') { clearInterval(poll); location.reload(); } if (statusPayload.job?.status === 'failed') { clearInterval(poll); resetButton(button); showRecalculationError(statusPayload.job.error || 'Une erreur inconnue est survenue.'); } } catch (_) {} }, 2500);
    } catch (error) { resetButton(button); showRecalculationError(error.message); }
  }
  function resetButton(button) { button.disabled = false; button.textContent = '↻'; button.title = 'Recalculer l’analyse'; }

  async function render(options = {}) {
    const app = options.target || (typeof document.getElementById === 'function' ? document.getElementById('app') : null);
    if (!app) return;
    const type = options.type || defaultType;
    const analysisId = options.id || defaultAnalysisId;
    activeAnalysisId = analysisId;
    const embedded = Boolean(options.target);
    const contexts = { locatif: locatifContext, mdb: mdbContext, patrimonial: patrimonialContext };
    if (!analysisId || !contexts[type]) { app.className = 'error'; app.textContent = 'Identifiant ou type d’analyse manquant.'; return; }
    try {
      const response = await fetch(`${new URL('analysis.php', apiUrl)}?id=${encodeURIComponent(analysisId)}&type=${type}`); const payload = await response.json();
      if (!response.ok || !payload.analysis) throw new Error(payload.error || 'Analyse indisponible');
      const context = contexts[type](payload.analysis);
      const titles = { locatif: 'investissement locatif', mdb: 'MDB', patrimonial: 'Patrimonial optimisé' };
      document.title = `Fiche ${titles[type]} — ${context.reference || context.annonce_id || analysisId}`;
      const template = options.template || document.getElementById('fiche-template')?.innerHTML;
      if (!template) throw new Error('Modèle de fiche introuvable');
      if (!embedded) app.className = '';
      if (embedded) app.dataset.ui = `fiche-${type}`;
      app.innerHTML = renderTemplate(template, context); if (embedded) app.querySelector('.site-header')?.remove(); setupTabs(app); setupFinanceEditor(app); setupGallery(app, payload.listing, context.listingUrl); if (!embedded) addRecalculateButton();
    } catch (error) { if (!embedded) app.className = 'error'; app.textContent = `Analyse indisponible : ${error.message}`; }
  }
  window.ImmoModal = { open: openModal };
  window.ImmoAnalysisRenderer = { render };
  render();
})();

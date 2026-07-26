(() => {
  const API_ROOT = new URL('api/', document.baseURI);
  const id = new URLSearchParams(location.search).get('id');
  const types = { patrimonial: 'Patrimoine', locatif: 'Locatif', mdb: 'Marchand de biens' };
  const app = document.getElementById('property-app');
  let data, pollTimer;
  const esc = value => String(value ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  const money = value => Number.isFinite(Number(value)) ? Number(value).toLocaleString('fr-FR',{style:'currency',currency:'EUR',maximumFractionDigits:0}) : 'Prix non renseigné';
  const lastTabKey = 'immoagg.property.lastTab';

  async function request(url, options) {
    const response = await fetch(url, options), payload = await response.json();
    if (!response.ok) throw new Error(payload.error || `Erreur HTTP ${response.status}`);
    return payload;
  }
  async function load() {
    if (!id) return fail('Identifiant du bien manquant.');
    try { data = await request(new URL(`property.php?id=${encodeURIComponent(id)}`, API_ROOT)); renderShell(); selectTab(validTab(localStorage.getItem(lastTabKey)) || 'annonce'); }
    catch (error) { fail(error.message); }
  }
  function validTab(value) { return ['annonce', ...Object.keys(types)].includes(value) ? value : null; }
  function renderShell() {
    const listing = data.listing, locationText = listing.location || listing.address || 'Localisation non renseignée';
    document.title = `${listing.title || 'Fiche du bien'} — ImmoAggregator`;
    app.className = '';
    app.innerHTML = `<header class="property-header"><div class="property-top"><a class="property-back" href="index.html">← Retour à la galerie</a><span class="property-brand">ImmoAggregator</span><span class="property-identity">${esc(listing.title || 'Annonce')} · ${esc(money(listing.price))}</span></div><nav class="property-tabs" role="tablist" aria-label="Fiche du bien">${tabButton('annonce','Annonce')}${tabButton('patrimonial','Patrimoine')}${tabButton('locatif','Locatif')}${tabButton('mdb','<span class="mdb-long">Marchand de biens</span><span class="mdb-short">MDB</span>')}<div class="property-actions"><button class="property-action" data-selection="shortlist" title="Favori">☆</button><button class="property-action" data-selection="ecartee" title="Écarter">×</button><button class="property-action" data-map title="Voir sur la carte">⌖</button><button class="property-action" data-delete-listing title="Supprimer l’annonce">•••</button></div></nav></header><section class="property-content" id="property-panel" role="tabpanel"></section>`;
    app.querySelectorAll('[data-tab]').forEach(button => button.addEventListener('click', () => selectTab(button.dataset.tab)));
    app.querySelectorAll('[data-selection]').forEach(button => button.addEventListener('click', () => updateSelection(button.dataset.selection)));
    app.querySelector('[data-map]').addEventListener('click', () => location.href = `index.html?view=map&listing=${encodeURIComponent(id)}`);
    app.querySelector('[data-delete-listing]').addEventListener('click', deleteListing);
    app.dataset.location = locationText;
  }
  function tabButton(key,label) { return `<button class="property-tab" role="tab" data-tab="${key}" aria-selected="false">${label}</button>`; }
  function selectTab(tab) {
    clearTimeout(pollTimer); localStorage.setItem(lastTabKey, tab);
    app.querySelectorAll('[data-tab]').forEach(button => button.setAttribute('aria-selected', String(button.dataset.tab === tab)));
    if (tab === 'annonce') renderListing(); else renderAnalysis(tab);
  }
  function renderListing() {
    const item = data.listing, images = [...new Set((item.images?.length ? item.images : [item.imageUrl]).filter(Boolean))];
    const image = images[0] ? `<img class="property-gallery-main" data-main-image src="${esc(images[0])}" alt="${esc(item.title || 'Photo du bien')}">` : '<div class="property-gallery-main"></div>';
    const thumbs = images.length > 1 ? `<div class="property-thumbnails">${images.map((src,i)=>`<button class="${i?'':'active'}" data-image="${esc(src)}" style="background-image:url('${esc(src)}')" aria-label="Afficher la photo ${i+1}"></button>`).join('')}</div>` : '';
    panel().innerHTML = `<div class="listing-heading"><small>Annonce sauvegardée</small><h1>${esc(item.title || 'Annonce immobilière')}</h1><p>${esc(item.location || 'Localisation à compléter')}</p></div><div class="listing-layout"><div>${image}${thumbs}</div><aside class="listing-summary"><div class="listing-price">${esc(money(item.price))}</div><div class="listing-kpis"><div><small>Surface</small><strong>${item.surface ? esc(item.surface)+' m²':'—'}</strong></div><div><small>Pièces</small><strong>${esc(item.rooms || '—')}</strong></div><div><small>Terrain</small><strong>${item.terrain ? esc(item.terrain)+' m²':'—'}</strong></div><div><small>DPE</small><strong>${esc(item.dpe || '—')}</strong></div></div><form class="property-panel property-field" id="address-form"><label for="exact-address">Adresse exacte</label><input id="exact-address" name="address" value="${esc(item.address || item.location || '')}"><small>Facultative — améliore la précision géographique, mais n’est pas nécessaire pour lancer une analyse.</small><div class="analysis-form-actions"><button class="property-secondary">Enregistrer l’adresse</button></div></form>${item.url?`<a class="property-primary" href="${esc(item.url)}" target="_blank" rel="noopener">Voir l’annonce d’origine ↗</a>`:''}</aside></div><section class="property-panel listing-description"><h2>Description</h2><p>${esc(item.description || 'Aucune description disponible.')}</p></section>`;
    panel().querySelectorAll('[data-image]').forEach(button => button.addEventListener('click',()=>{panel().querySelector('[data-main-image]').src=button.dataset.image;panel().querySelectorAll('[data-image]').forEach(x=>x.classList.toggle('active',x===button))}));
    panel().querySelector('#address-form').addEventListener('submit', saveAddress);
  }
  function renderAnalysis(type) {
    const summary = data.analyses[type], job = data.job;
    if (job && job.type === type && ['queued','running'].includes(job.status)) return renderRunning(type, job);
    if (job && job.type === type && job.status === 'failed' && !summary) return renderFailed(type, job);
    if (summary && summary.available !== false) return renderAvailable(type);
    const missing = data.requirements[type]?.missing || [];
    if (missing.length) return renderForm(type, missing);
    panel().innerHTML = state(type, `Lancer l’analyse ${types[type].toLowerCase()}`, 'Les données indispensables sont présentes. L’analyse est calculée indépendamment des autres opportunités.', `<button class="property-primary" data-start>Lancer l’analyse</button>`);
    panel().querySelector('[data-start]').addEventListener('click',()=>startAnalysis(type));
  }
  function renderForm(type, missing) {
    panel().innerHTML = `<div class="analysis-state"><form class="analysis-state-card analysis-form" id="requirements-form"><div class="analysis-eyebrow">${esc(types[type])} · données indispensables</div><h2>Compléter avant l’analyse</h2><p>Seuls les champs absents et réellement nécessaires au prompt sont demandés.</p><div class="analysis-form-grid">${missing.map(field=>`<div class="property-field"><label for="req-${field.field}">${esc(field.label)} *</label><input id="req-${field.field}" name="${field.field}" type="${field.type}" min="0" required></div>`).join('')}</div><div class="property-panel property-field"><label for="req-address">Adresse exacte — facultatif</label><input id="req-address" name="address" value="${esc(data.listing.address || data.listing.location || '')}"><small>Son absence ne bloque pas le calcul.</small></div><div class="analysis-form-actions"><button type="button" class="property-secondary" data-cancel>Annuler</button><button class="property-primary">Enregistrer et lancer</button></div></form></div>`;
    panel().querySelector('[data-cancel]').addEventListener('click',()=>selectTab('annonce'));
    panel().querySelector('form').addEventListener('submit', async event => { event.preventDefault(); const values=Object.fromEntries(new FormData(event.currentTarget)); await saveFields(values); await startAnalysis(type); });
  }
  function renderRunning(type, job) { panel().innerHTML = state(type,'Analyse en cours',`Lancée ${job.started_at ? new Date(job.started_at).toLocaleString('fr-FR') : 'il y a quelques instants'}. Vous pouvez fermer cet onglet et revenir plus tard.`, '<div class="analysis-spinner" aria-hidden="true"></div>'); pollTimer=setTimeout(()=>refresh(type),2500); }
  function renderFailed(type, job) { panel().innerHTML=state(type,"L’analyse n’a pas abouti",job.error||'Une erreur technique est survenue.',`<button class="property-primary" data-retry>Réessayer</button> <button class="property-danger" data-delete>Supprimer l’analyse</button>`,'analysis-error'); panel().querySelector('[data-retry]').onclick=()=>startAnalysis(type);panel().querySelector('[data-delete]').onclick=()=>deleteAnalysis(type); }
  function renderAvailable(type) { panel().innerHTML=`<div class="analysis-toolbar"><button class="property-secondary" data-recalculate>Recalculer</button><button class="property-danger" data-delete>Supprimer l’analyse</button></div><iframe class="analysis-frame" title="Analyse ${esc(types[type])}" src="templates/fiche-investissement-${type}.html?id=${encodeURIComponent(id)}&embedded=1"></iframe>`;panel().querySelector('[data-recalculate]').onclick=()=>startAnalysis(type);panel().querySelector('[data-delete]').onclick=()=>deleteAnalysis(type); }
  function state(type,title,message,actions,extra=''){return `<div class="analysis-state"><div class="analysis-state-card ${extra}"><div class="analysis-eyebrow">Analyse ${esc(types[type])}</div><h2>${esc(title)}</h2><p>${esc(message)}</p>${actions}</div></div>`}
  async function saveAddress(event){event.preventDefault();await saveFields({address:new FormData(event.currentTarget).get('address')});toast('Adresse enregistrée.');}
  async function saveFields(fields){data=await request(new URL(`property.php?id=${encodeURIComponent(id)}`,API_ROOT),{method:'PATCH',headers:{'Content-Type':'application/json'},body:JSON.stringify(fields)});}
  async function startAnalysis(type){try{await request(new URL('analyze.php',API_ROOT),{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({id,type})});await refresh(type)}catch(error){toast(error.message)}}
  async function refresh(type){try{data=await request(new URL(`property.php?id=${encodeURIComponent(id)}`,API_ROOT));renderAnalysis(type)}catch(error){toast(error.message)}}
  async function deleteAnalysis(type){if(!confirm(`Supprimer définitivement l’analyse ${types[type]} ?`))return;await request(new URL(`property.php?id=${encodeURIComponent(id)}&type=${type}`,API_ROOT),{method:'DELETE'});await refresh(type)}
  async function updateSelection(selection){const current=data.listing.selection===selection?null:selection;await request(new URL('tag.php',API_ROOT),{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({id,selection:current})});data.listing.selection=current;toast(current?'Classement enregistré.':'Classement retiré.');}
  async function deleteListing(){if(!confirm('Supprimer définitivement cette annonce et toutes ses analyses ?'))return;await request(new URL('delete.php',API_ROOT),{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({id})});location.href='index.html'}
  function panel(){return document.getElementById('property-panel')}function toast(message){const node=document.createElement('div');node.className='property-toast';node.textContent=message;document.body.append(node);setTimeout(()=>node.remove(),4000)}function fail(message){app.className='property-error';app.textContent=message}
  load();
})();

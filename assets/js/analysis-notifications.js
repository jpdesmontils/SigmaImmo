(() => {
  if (window.ImmoAnalysisNotifications) return;
  const STORAGE_KEY = 'immoagg.analysis.jobs';
  const API_ROOT = new URL('api/', document.baseURI);
  const types = { patrimonial: 'Patrimoine', locatif: 'Locatif', mdb: 'Marchand de biens' };
  let timer;
  function pendingJobs() { try { return JSON.parse(localStorage.getItem(STORAGE_KEY) || '{}'); } catch (_) { return {}; } }
  function save(jobs) { try { localStorage.setItem(STORAGE_KEY, JSON.stringify(jobs)); } catch (_) {} }
  function track(job) { const jobs = pendingJobs(); jobs[job.id] = { id: job.id, type: job.type, title: job.title || 'Fiche du bien' }; save(jobs); schedule(500); }
  function schedule(delay = 2500) { clearTimeout(timer); timer = setTimeout(check, delay); }
  async function check() {
    const jobs = pendingJobs(), entries = Object.values(jobs);
    if (!entries.length) return;
    await Promise.all(entries.map(async tracked => {
      try {
        const response = await fetch(new URL(`property.php?id=${encodeURIComponent(tracked.id)}`, API_ROOT));
        if (!response.ok) return;
        const payload = await response.json(), job = payload.job;
        if (!job || job.type !== tracked.type || !['completed', 'failed'].includes(job.status)) return;
        delete jobs[tracked.id];
        notify(tracked, job.status === 'completed', payload.listing?.title);
      } catch (_) {}
    }));
    save(jobs);
    if (Object.keys(jobs).length) schedule();
  }
  function notify(tracked, succeeded, currentTitle) {
    toast(succeeded ? 'Analyse terminée' : 'Échec de l’analyse', {
      detail: `${types[tracked.type] || tracked.type} · ${currentTitle || tracked.title}`,
      variant: succeeded ? 'success' : 'error',
      action: { label: 'Voir la fiche', onClick: () => openProperty(tracked) }, duration: 30000
    });
  }
  function openProperty(tracked) {
    const event = new CustomEvent('immoagg:open-analysis', { cancelable: true, detail: tracked });
    if (document.dispatchEvent(event)) location.href = `fiche-bien.html?id=${encodeURIComponent(tracked.id)}`;
  }
  function toast(message, options = {}) {
    const esc = value => String(value).replace(/[&<>"']/g, char => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[char]));
    const node = document.createElement('div'), close = () => node.remove();
    node.className = `property-toast ${options.variant ? `property-toast-${options.variant}` : ''}`;
    node.setAttribute('role', options.variant === 'error' ? 'alert' : 'status');
    node.innerHTML = `<button type="button" class="property-toast-close" aria-label="Fermer la notification">×</button><strong>${esc(message)}</strong>${options.detail ? `<span>${esc(options.detail)}</span>` : ''}${options.action ? `<button type="button" class="property-toast-action">${esc(options.action.label)}</button>` : ''}`;
    node.querySelector('.property-toast-close').addEventListener('click', close);
    if (options.action) node.querySelector('.property-toast-action').addEventListener('click', () => { close(); options.action.onClick(); });
    document.body.append(node); setTimeout(close, options.duration || 4000);
  }
  window.ImmoAnalysisNotifications = { track, toast };
  window.addEventListener?.('storage', event => { if (event.key === STORAGE_KEY) schedule(0); });
  schedule(0);
})();

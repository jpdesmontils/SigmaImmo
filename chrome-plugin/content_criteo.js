// ============================================================
// ImmoAggregator — Capteur Criteo
// Injecté sur toutes les pages, observe les pubs Green Acres
// ============================================================

(function () {
  // Éviter la double initialisation
  if (window.__immoAggCriteoRunning) return;
  window.__immoAggCriteoRunning = true;

  const GA_PATTERNS = [
    'green-acres',
    'greenacres',
    'green_acres'
  ];

  const CRITEO_IMG_PATTERNS = [
    'static.criteo.net',
    'dis.us.criteo.com',
    'dis.eu.criteo.com',
    'rdi.us.criteo.com',
    'rdi.eu.criteo.com',
    'bidder.criteo.com',
    'ad.doubleclick.net'  // Criteo passe parfois par DFP
  ];

  const captured = new Map(); // imageUrl → ad data

  // ── 1. Observer les iframes Criteo injectées ─────────────────
  const iframeObserver = new MutationObserver(mutations => {
    for (const m of mutations) {
      for (const node of m.addedNodes) {
        if (node.nodeType !== 1) continue;
        checkElement(node);
        node.querySelectorAll?.('iframe, img, div[data-creative]')
          .forEach(checkElement);
      }
    }
  });

  iframeObserver.observe(document.body || document.documentElement, {
    childList: true,
    subtree: true
  });

  // ── 2. Intercepter les images déjà dans le DOM ────────────────
  function scanExistingImages() {
    document.querySelectorAll('img').forEach(checkImageEl);
    document.querySelectorAll('iframe').forEach(checkIframeEl);
  }

  // ── 3. Écouter les messages des iframes Criteo ────────────────
  window.addEventListener('message', e => {
    try {
      const data = typeof e.data === 'string' ? JSON.parse(e.data) : e.data;
      if (data && data.type && (
        data.type.toLowerCase().includes('criteo') ||
        data.type.toLowerCase().includes('cdb') ||
        data.type.toLowerCase().includes('prebid')
      )) {
        extractFromCriteoMessage(data);
      }
    } catch {}
  });

  // ── 4. Intercepter fetch/XHR vers Criteo ─────────────────────
  interceptFetch();
  interceptXHR();

  // ── Vérification d'un élément ────────────────────────────────

  function checkElement(el) {
    if (el.tagName === 'IMG') checkImageEl(el);
    if (el.tagName === 'IFRAME') checkIframeEl(el);

    // Divs avec background-image
    const style = el.style?.backgroundImage || '';
    if (style.includes('url(')) {
      const match = style.match(/url\(['"]?([^'")\s]+)['"]?\)/);
      if (match) checkUrl(match[1], el);
    }
  }

  function checkImageEl(img) {
    const src = img.src || img.dataset.src || img.dataset.lazy || '';
    if (src) checkUrl(src, img);

    // Observer le chargement futur
    img.addEventListener('load', () => {
      if (img.src) checkUrl(img.src, img);
    }, { once: true });
  }

  function checkIframeEl(iframe) {
    const src = iframe.src || '';
    if (isCriteoUrl(src)) {
      // Iframe Criteo identifiée, essayer de lire son contenu
      setTimeout(() => scanIframeContent(iframe), 500);
    }
  }

  function scanIframeContent(iframe) {
    try {
      const doc = iframe.contentDocument || iframe.contentWindow?.document;
      if (!doc) return;
      doc.querySelectorAll('img').forEach(img => {
        const src = img.src || '';
        if (src) checkUrl(src, img, doc.location.href);
      });
      // Liens de destination
      doc.querySelectorAll('a[href]').forEach(a => {
        if (isGreenAcresUrl(a.href)) {
          // Cette iframe contient une pub Green Acres
          doc.querySelectorAll('img').forEach(img => {
            if (img.src && isCriteoImageUrl(img.src)) {
              captureAd(img.src, a.href, 'iframe_link');
            }
          });
        }
      });
    } catch {}
  }

  function checkUrl(url, el, context = location.href) {
    if (!url || url.startsWith('data:')) return;

    // Image provenant de Criteo ?
    if (!isCriteoImageUrl(url) && !isCriteoUrl(url)) return;

    // Chercher un lien GA dans les parents ou attributs
    const destUrl = findDestinationUrl(el) || '';
    const isGA = isGreenAcresUrl(destUrl) || isGreenAcresUrl(context);

    // Même sans confirmer GA, on capture les images Criteo
    // et on filtre côté serveur (Criteo fait du retargeting GA)
    captureAd(url, destUrl, 'dom_observation');
  }

  function captureAd(imageUrl, destinationUrl, method) {
    if (captured.has(imageUrl)) return;

    const ad = {
      imageUrl,
      destinationUrl,
      method,
      pageUrl: location.href,
      timestamp: Date.now(),
      isGreenAcres: isGreenAcresUrl(destinationUrl) || isGreenAcresUrl(imageUrl)
    };

    captured.set(imageUrl, ad);

    // Envoi immédiat au background
    chrome.runtime.sendMessage({ type: 'CRITEO_ADS', data: [ad] })
      .catch(() => {}); // Ignorer si le background est inactif

    console.log('[ImmoAgg] Pub Criteo capturée :', imageUrl.slice(0, 80) + '…');
  }

  // ── Interception réseau ──────────────────────────────────────

  function interceptFetch() {
    const originalFetch = window.fetch;
    window.fetch = async function (...args) {
      const url = typeof args[0] === 'string' ? args[0] : args[0]?.url || '';
      if (isCriteoUrl(url)) {
        const response = await originalFetch.apply(this, args);
        response.clone().json().then(data => {
          extractFromCriteoResponse(url, data);
        }).catch(() => {});
        return response;
      }
      return originalFetch.apply(this, args);
    };
  }

  function interceptXHR() {
    const OrigXHR = window.XMLHttpRequest;
    function PatchedXHR() {
      const xhr = new OrigXHR();
      const originalOpen = xhr.open.bind(xhr);
      let reqUrl = '';

      xhr.open = function (method, url, ...rest) {
        reqUrl = url;
        return originalOpen(method, url, ...rest);
      };

      xhr.addEventListener('load', () => {
        if (isCriteoUrl(reqUrl)) {
          try {
            const data = JSON.parse(xhr.responseText);
            extractFromCriteoResponse(reqUrl, data);
          } catch {}
        }
      });

      return xhr;
    }
    PatchedXHR.prototype = OrigXHR.prototype;
    window.XMLHttpRequest = PatchedXHR;
  }

  function extractFromCriteoResponse(url, data) {
    // Parcourir récursivement la réponse JSON pour trouver des URLs d'images
    const urls = [];
    findUrlsInObject(data, urls);
    const imgUrls = urls.filter(u => /\.(jpg|jpeg|png|webp|gif)/i.test(u));
    const gaUrls  = urls.filter(u => isGreenAcresUrl(u));

    imgUrls.forEach(imgUrl => {
      const dest = gaUrls[0] || '';
      captureAd(imgUrl, dest, 'xhr_intercept');
    });
  }

  function extractFromCriteoMessage(data) {
    const urls = [];
    findUrlsInObject(data, urls);
    const imgUrls = urls.filter(u => /\.(jpg|jpeg|png|webp|gif)/i.test(u) && isCriteoImageUrl(u));
    const gaUrls  = urls.filter(u => isGreenAcresUrl(u));
    imgUrls.forEach(imgUrl => captureAd(imgUrl, gaUrls[0] || '', 'postmessage'));
  }

  function findUrlsInObject(obj, results, depth = 0) {
    if (depth > 8 || !obj) return;
    if (typeof obj === 'string') {
      if (obj.startsWith('http')) results.push(obj);
      return;
    }
    if (typeof obj === 'object') {
      Object.values(obj).forEach(v => findUrlsInObject(v, results, depth + 1));
    }
  }

  // ── Helpers ──────────────────────────────────────────────────

  function isCriteoUrl(url) {
    return CRITEO_IMG_PATTERNS.some(p => url.includes(p));
  }

  function isCriteoImageUrl(url) {
    return CRITEO_IMG_PATTERNS.some(p => url.includes(p))
      && /\.(jpg|jpeg|png|webp|gif)/i.test(url);
  }

  function isGreenAcresUrl(url) {
    return GA_PATTERNS.some(p => (url || '').toLowerCase().includes(p));
  }

  function findDestinationUrl(el) {
    // Chercher href dans les parents
    let node = el;
    for (let i = 0; i < 6; i++) {
      if (!node) break;
      if (node.href) return node.href;
      if (node.dataset?.href) return node.dataset.href;
      node = node.parentElement;
    }
    return '';
  }

  // Scan initial après chargement complet
  if (document.readyState === 'complete') {
    scanExistingImages();
  } else {
    window.addEventListener('load', scanExistingImages);
  }

  // Re-scan périodique (lazy load, SPA)
  setTimeout(scanExistingImages, 2000);
  setTimeout(scanExistingImages, 5000);

})();

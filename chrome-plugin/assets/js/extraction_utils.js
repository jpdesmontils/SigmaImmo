// Algorithmes d'extraction communs aux différents portails immobiliers.
(function (root, factory) {
  const api = factory();
  if (typeof module !== 'undefined' && module.exports) module.exports = api;
  if (root) root.ImmoExtraction = api;
})(typeof globalThis !== 'undefined' ? globalThis : this, function () {
  const BOILERPLATE_LINES = [
    /^(?:afficher|voir|lire) (?:la )?suite$/i,
    /^(?:réduire|masquer|voir moins)$/i,
    /^(?:contacter|appeler) (?:le vendeur|l'agence|l'annonceur)$/i,
    /^(?:ajouter|retirer) (?:aux|des) favoris$/i,
    /^(?:partager|imprimer|signaler)(?: l'annonce)?$/i
  ];

  function cleanListingText(value) {
    if (!value) return '';
    const seen = new Set();
    return String(value)
      .replace(/\u00ad/g, '')
      .replace(/\r/g, '')
      .split('\n')
      .map(line => line.replace(/[\t\u00a0 ]+/g, ' ').trim())
      .filter(line => {
        if (!line || BOILERPLATE_LINES.some(pattern => pattern.test(line))) return false;
        const key = line.toLocaleLowerCase('fr-FR');
        if (seen.has(key)) return false;
        seen.add(key);
        return true;
      })
      .join('\n')
      .replace(/\n{3,}/g, '\n\n')
      .trim();
  }

  function normalizeRating(value) {
    const rating = String(value || '').trim().toUpperCase();
    return /^[A-G]$/.test(rating) ? rating : '';
  }

  function ratingAfterLabels(text, labels) {
    for (const label of labels) {
      const match = text.match(new RegExp('(?:' + label + ')\\s*(?:class(?:e|é)?\\s*)?(?:[:\\-–|]\\s*)?([A-G])(?:\\b|\\s*\\()', 'i'));
      if (match) return normalizeRating(match[1]);
    }
    return '';
  }

  function extractEnergyRatings(value) {
    const text = cleanListingText(value);
    return {
      dpe: ratingAfterLabels(text, [
        'DPE',
        'diagnostic de performance [ée]nerg[ée]tique',
        'classe [ée]nerg(?:ie|[ée]tique)',
        '[ée]tiquette [ée]nergie',
        'performance [ée]nerg[ée]tique',
        'energy (?:rating|efficiency|performance)'
      ]),
      ges: ratingAfterLabels(text, [
        'GES',
        'gaz à effet de serre',
        'classe climat',
        '[ée]tiquette climat',
        '[ée]missions? de gaz à effet de serre',
        'greenhouse gas(?: emissions?)?',
        'climate rating'
      ])
    };
  }

  function parseSquareMeters(value) {
    const match = String(value || '').match(/([\d\s]+(?:[,.]\d+)?)\s*(?:m²|m2|sqm)(?!\w)/i);
    if (!match) return null;
    const number = parseFloat(match[1].replace(/\s/g, '').replace(',', '.'));
    return Number.isFinite(number) ? number : null;
  }

  function extractTerrainSurface(value) {
    const text = cleanListingText(value);
    const patterns = [
      /(?:surface\s+(?:du|de)\s+)?terrain(?:\s+constructible)?\s*(?:de|:|-)?\s*([\d\s]+(?:[,.]\d+)?)\s*(m²|m2|ha)(?!\w)/i,
      /([\d\s]+(?:[,.]\d+)?)\s*(m²|m2|ha)(?!\w)\s+(?:de\s+)?terrain\b/i
    ];
    for (const pattern of patterns) {
      const match = text.match(pattern);
      if (!match) continue;
      const number = parseFloat(match[1].replace(/\s/g, '').replace(',', '.'));
      if (Number.isFinite(number)) return /ha/i.test(match[2]) ? number * 10000 : number;
    }
    return null;
  }

  function descriptionsFromJsonLd(doc) {
    const descriptions = [];
    const visit = value => {
      if (!value || typeof value !== 'object') return;
      if (typeof value.description === 'string') descriptions.push(value.description);
      Object.values(value).forEach(child => {
        if (child && typeof child === 'object') visit(child);
      });
    };
    doc.querySelectorAll('script[type="application/ld+json"]').forEach(script => {
      try { visit(JSON.parse(script.textContent)); } catch (error) {
        // Un JSON-LD tiers invalide ne doit pas empêcher l'extraction DOM.
      }
    });
    return descriptions;
  }

  function extractDescription(doc) {
    const candidates = descriptionsFromJsonLd(doc);
    const selectors = [
      '[itemprop="description"]', '[data-testid*="description"]',
      '[id*="description"]', '[class*="description"]',
      '[id*="descriptif"]', '[class*="descriptif"]',
      '[id*="presentation"]', '[class*="presentation"]'
    ];
    doc.querySelectorAll(selectors.join(',')).forEach(element => {
      // textContent inclut aussi le texte déjà chargé mais encore replié derrière
      // un bouton « voir plus », contrairement à innerText.
      if (element.textContent) candidates.push(element.textContent);
    });

    const explicit = candidates.map(cleanListingText).filter(text => text.length >= 40);
    if (explicit.length) return explicit.reduce((longest, text) => text.length > longest.length ? text : longest, '');

    // Repli multiportail : réunir les paragraphes du contenu principal plutôt
    // que de sélectionner un parent qui inclurait menus, boutons et pied de page.
    const main = doc.querySelector('main, article, [role="main"]') || doc.body;
    const paragraphs = [...main.querySelectorAll('p')]
      .map(element => cleanListingText(element.innerText || element.textContent))
      .filter(text => text.length >= 40);
    if (paragraphs.length) return cleanListingText(paragraphs.join('\n\n'));

    const meta = doc.querySelector('meta[property="og:description"], meta[name="description"]');
    return cleanListingText(meta ? meta.content : '');
  }

  function extractEnergyRatingsFromDocument(doc) {
    const targeted = [...doc.querySelectorAll(
      '[class*="dpe"], [id*="dpe"], [class*="ges"], [id*="ges"], ' +
      '[class*="energy"], [id*="energy"], [class*="climat"], [id*="climat"]'
    )].map(element => [
      element.innerText || element.textContent || '',
      element.id || '',
      element.className || '',
      element.getAttribute('aria-label') || '',
      element.getAttribute('title') || '',
      element.getAttribute('alt') || ''
    ].join(' ')).join('\n');
    const targetedRatings = extractEnergyRatings(targeted);
    const pageRatings = extractEnergyRatings(doc.body ? doc.body.innerText : '');
    return {
      dpe: targetedRatings.dpe || pageRatings.dpe,
      ges: targetedRatings.ges || pageRatings.ges
    };
  }

  function waitForElement(doc, selector, timeout = 3000) {
    return new Promise(resolve => {
      if (doc.querySelector(selector)) return resolve(true);
      const start = Date.now();
      const check = () => {
        if (doc.querySelector(selector)) return resolve(true);
        if (Date.now() - start > timeout) return resolve(false);
        requestAnimationFrame(check);
      };
      check();
    });
  }

  return {
    cleanListingText,
    extractDescription,
    extractEnergyRatings,
    extractEnergyRatingsFromDocument,
    extractTerrainSurface,
    normalizeRating,
    parseSquareMeters,
    waitForElement
  };
});

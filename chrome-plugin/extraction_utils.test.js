const assert = require('node:assert/strict');
const {
  cleanListingText,
  extractEnergyRatings,
  extractTerrainSurface,
  normalizeRating,
  parseSquareMeters
} = require('./extraction_utils.js');

assert.equal(
  cleanListingText('Belle maison\u00a0 familiale\nVoir la suite\nBelle maison\u00a0 familiale\nJardin arboré'),
  'Belle maison familiale\nJardin arboré'
);

assert.deepEqual(
  extractEnergyRatings('Diagnostic de performance énergétique : D\nClasse climat : B'),
  { dpe: 'D', ges: 'B' }
);
assert.deepEqual(
  extractEnergyRatings('DPE E (278 kWh/m²/an) — GES C (17 kg CO₂/m²/an)'),
  { dpe: 'E', ges: 'C' }
);
assert.deepEqual(
  extractEnergyRatings('Energy efficiency: F\nGreenhouse gas emissions: D'),
  { dpe: 'F', ges: 'D' }
);
assert.deepEqual(extractEnergyRatings('DPE vierge — GES non renseigné'), { dpe: '', ges: '' });

assert.equal(extractTerrainSurface('Surface du terrain : 1 250 m²'), 1250);
assert.equal(extractTerrainSurface('Maison sur 0,45 ha de terrain'), 4500);
assert.equal(extractTerrainSurface('Terrain inconnu'), null);
assert.equal(parseSquareMeters('Surface habitable 87,5 m²'), 87.5);
assert.equal(normalizeRating(' g '), 'G');
assert.equal(normalizeRating('H'), '');

console.log('Tests extraction_utils: OK');

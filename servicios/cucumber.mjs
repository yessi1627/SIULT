// Configuracion de Cucumber (BDD). Ejecutar: npm run bdd
export default {
  paths: ['features/**/*.feature'],
  import: ['features/pasos/**/*.mjs'],
  language: 'es',
  format: ['progress-bar', 'html:reportes/bdd.html'],
};

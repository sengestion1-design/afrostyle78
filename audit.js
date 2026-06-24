const { chromium } = require('playwright');

const BASE = 'https://afrostyle78.com';

const PAGES = [
  { url: '/',                     name: 'Accueil' },
  { url: '/boutique',             name: 'Boutique' },
  { url: '/panier',               name: 'Panier' },
  { url: '/login',                name: 'Connexion' },
  { url: '/register',             name: 'Inscription' },
  { url: '/compte',               name: 'Mon compte' },
  { url: '/suivi',                name: 'Suivi commande' },
  { url: '/guide-des-tailles',    name: 'Guide des tailles' },
  { url: '/sur-mesure',           name: 'Sur-mesure' },
  { url: '/mot-de-passe-oublie',  name: 'Mot de passe oublié' },
  { url: '/politique-confidentialite', name: 'Politique' },
];

const VIEWPORTS = [
  { name: 'Mobile',  width: 390,  height: 844 },
  { name: 'Tablet',  width: 768,  height: 1024 },
  { name: 'Desktop', width: 1440, height: 900 },
];

(async () => {
  const browser = await chromium.launch({ headless: true });
  const results = [];

  for (const vp of VIEWPORTS) {
    const context = await browser.newContext({
      viewport: { width: vp.width, height: vp.height },
      userAgent: 'Mozilla/5.0 (compatible; AuditBot/1.0)',
    });
    const page = await context.newPage();

    const jsErrors = [];
    const networkErrors = [];

    page.on('console', msg => {
      if (msg.type() === 'error') jsErrors.push(msg.text());
    });
    page.on('pageerror', err => jsErrors.push('PAGE ERROR: ' + err.message));
    page.on('response', res => {
      if (res.status() >= 400) {
        networkErrors.push(`${res.status()} ${res.url()}`);
      }
    });

    for (const p of PAGES) {
      jsErrors.length = 0;
      networkErrors.length = 0;

      let status = 0;
      let loadTime = 0;
      let issues = [];

      try {
        const t0 = Date.now();
        const response = await page.goto(BASE + p.url, {
          waitUntil: 'networkidle',
          timeout: 15000,
        });
        loadTime = Date.now() - t0;
        status = response?.status() ?? 0;

        // Débordements horizontaux
        const overflows = await page.evaluate(() => {
          const docWidth = document.documentElement.scrollWidth;
          const winWidth = window.innerWidth;
          const overflowing = [];
          if (docWidth > winWidth) {
            document.querySelectorAll('*').forEach(el => {
              const rect = el.getBoundingClientRect();
              if (rect.right > winWidth + 5) {
                overflowing.push(`<${el.tagName.toLowerCase()}> ${el.className?.toString().slice(0,40)}`);
              }
            });
          }
          return overflowing.slice(0, 5);
        });

        // Scroll pour déclencher le lazy-load
        await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight));
        await page.waitForTimeout(1500);

        // Images cassées (seulement celles avec src défini et naturalWidth=0)
        const brokenImages = await page.evaluate(() => {
          return Array.from(document.querySelectorAll('img[src]'))
            .filter(img => img.complete && !img.naturalWidth && !img.src.includes('data:'))
            .map(img => img.src?.split('/').pop() || img.src)
            .slice(0, 5);
        });

        // Liens cassés (href vides ou #)
        const badLinks = await page.evaluate(() => {
          return Array.from(document.querySelectorAll('a[href=""], a[href="#"], a:not([href])'))
            .map(a => a.textContent?.trim().slice(0, 30) || '(sans texte)')
            .slice(0, 5);
        });

        if (overflows.length) issues.push(`🔴 Débordement horizontal: ${overflows.join(' | ')}`);
        if (brokenImages.length) issues.push(`🖼️ Images cassées: ${brokenImages.join(', ')}`);
        if (badLinks.length) issues.push(`🔗 Liens vides: ${badLinks.join(', ')}`);
        if (jsErrors.length) issues.push(`⚠️ JS Errors: ${jsErrors.slice(0,3).join(' | ')}`);
        if (networkErrors.length) issues.push(`🌐 Ressources 4xx/5xx: ${networkErrors.slice(0,3).join(' | ')}`);
        if (loadTime > 5000) issues.push(`🐌 Lent: ${(loadTime/1000).toFixed(1)}s`);

      } catch (e) {
        issues.push(`💥 ERREUR: ${e.message}`);
        status = 0;
      }

      results.push({
        viewport: vp.name,
        page: p.name,
        url: p.url,
        status,
        loadTime: `${(loadTime/1000).toFixed(1)}s`,
        issues,
      });
    }

    await context.close();
  }

  await browser.close();

  // Affichage rapport
  console.log('\n' + '='.repeat(70));
  console.log('  RAPPORT AUDIT AFROSTYLE78.COM');
  console.log('='.repeat(70));

  let currentVp = '';
  for (const r of results) {
    if (r.viewport !== currentVp) {
      currentVp = r.viewport;
      console.log(`\n📱 ${currentVp.toUpperCase()}\n` + '-'.repeat(50));
    }
    const statusIcon = r.status === 200 ? '✅' : r.status >= 400 ? '❌' : '⚠️';
    const issueCount = r.issues.length;
    console.log(`${statusIcon} [${r.status}] ${r.page} (${r.url}) — ${r.loadTime} — ${issueCount === 0 ? '✓ Aucun problème' : issueCount + ' problème(s)'}`);
    for (const issue of r.issues) {
      console.log(`     ${issue}`);
    }
  }

  console.log('\n' + '='.repeat(70));
  const totalIssues = results.reduce((n, r) => n + r.issues.length, 0);
  const pagesOk = results.filter(r => r.issues.length === 0).length;
  console.log(`  RÉSUMÉ: ${pagesOk}/${results.length} pages sans problème — ${totalIssues} problème(s) total`);
  console.log('='.repeat(70) + '\n');
})();

const { chromium } = require('playwright-core');
(async () => {
  const browser = await chromium.launch({ headless: true, executablePath: '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome' });
  const ctx = await browser.newContext({ viewport: { width: 570, height: 1200 }, locale: 'ru-RU' });
  const page = await ctx.newPage();
  const errors=[]; page.on('pageerror', e=>errors.push(e.message));
  await page.goto('https://piz.itop7.ru/login', { waitUntil: 'networkidle' });
  await page.fill('input[name=login]', 'icontest');
  await page.fill('input[name=password]', 'Icon2026!');
  await Promise.all([page.waitForLoadState('networkidle'), page.click('button[type=submit]')]);
  await page.goto('https://piz.itop7.ru/', { waitUntil: 'networkidle' });
  await page.click('[data-sidebar-toggle]');
  await page.waitForTimeout(250);
  await page.click('[data-nav-group-toggle]');
  const items = await page.locator('.nav__item').evaluateAll(nodes => nodes.map(n => {
    const label = n.querySelector('span:not(.nav__kbd)')?.textContent.trim() || '';
    const use = n.querySelector('use')?.getAttribute('href') || '';
    return {label, icon: use};
  }));
  console.log(items);
  console.log('unique icons', new Set(items.map(i=>i.icon)).size, 'of', items.length);
  await page.screenshot({ path: 'temp/icons-fixed.png', clip: { x: 0, y: 0, width: 300, height: 1100 } });
  console.log('errors', errors);
  await browser.close();
})().catch(e=>{console.error('FATAL',e);process.exit(1);});

const { chromium } = require('playwright-core');
const base = 'https://piz.itop7.ru';
const out = 'assets/knowledge/';
const pages = [
  ['dashboard', '/?period=3m'],
  ['pos', '/pos'],
  ['products', '/products'],
  ['stock-in', '/stock/in'],
  ['stock-out', '/stock/out'],
  ['prices', '/prices'],
  ['sales', '/sales'],
  ['shifts', '/shifts'],
  ['suppliers', '/suppliers'],
  ['invoices', '/suppliers/invoices'],
  ['inventory', '/inventory'],
  ['employees', '/employees'],
  ['settings', '/settings'],
  ['activity', '/activity'],
];
(async () => {
  const browser = await chromium.launch({ headless: true, executablePath: '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome' });
  const clean = async (page) => {
    await page.addStyleTag({ content: '.install-banner,.pos-add-toast{display:none!important}' }).catch(()=>{});
    await page.waitForTimeout(250);
  };
  // login page without auth
  const loginCtx = await browser.newContext({ viewport: { width: 1440, height: 900 }, locale: 'ru-RU' });
  const loginPage = await loginCtx.newPage();
  await loginPage.goto(base + '/login', { waitUntil: 'networkidle' });
  await clean(loginPage);
  await loginPage.screenshot({ path: out + 'login.jpg', type: 'jpeg', quality: 84 });
  await loginCtx.close();

  const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 }, locale: 'ru-RU' });
  await ctx.addInitScript(() => {
    localStorage.setItem('piz-report-view', 'chart');
    localStorage.setItem('piz-pos-grid-cols', '2');
  });
  const page = await ctx.newPage();
  await page.goto(base + '/login', { waitUntil: 'networkidle' });
  await page.fill('input[name=login]', 'kbadmin');
  await page.fill('input[name=password]', 'Kb2026!!');
  await Promise.all([page.waitForLoadState('networkidle'), page.click('button[type=submit]')]);
  for (const [name, path] of pages) {
    await page.goto(base + path, { waitUntil: 'networkidle' });
    await clean(page);
    await page.screenshot({ path: out + name + '.jpg', type: 'jpeg', quality: 84 });
    console.log('desktop', name);
  }
  await page.goto(base + '/knowledge', { waitUntil: 'networkidle' });
  await clean(page);
  await page.screenshot({ path: 'temp/knowledge-page.jpg', type: 'jpeg', quality: 84 });
  await ctx.close();

  const mobileCtx = await browser.newContext({ viewport: { width: 390, height: 844 }, locale: 'ru-RU', isMobile: true, hasTouch: true });
  await mobileCtx.addInitScript(() => localStorage.setItem('piz-pos-grid-cols', '2'));
  const mobile = await mobileCtx.newPage();
  await mobile.goto(base + '/login', { waitUntil: 'networkidle' });
  await mobile.fill('input[name=login]', 'kbadmin');
  await mobile.fill('input[name=password]', 'Kb2026!!');
  await Promise.all([mobile.waitForLoadState('networkidle'), mobile.click('button[type=submit]')]);
  await mobile.goto(base + '/pos', { waitUntil: 'networkidle' });
  await clean(mobile);
  await mobile.screenshot({ path: out + 'pos-mobile.jpg', type: 'jpeg', quality: 84 });
  console.log('mobile pos');
  await mobile.goto(base + '/', { waitUntil: 'networkidle' });
  await clean(mobile);
  await mobile.screenshot({ path: out + 'dashboard-mobile.jpg', type: 'jpeg', quality: 84 });
  console.log('mobile dashboard');
  await mobileCtx.close();
  await browser.close();
})().catch(e => { console.error(e); process.exit(1); });

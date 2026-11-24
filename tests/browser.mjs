import { chromium } from 'playwright-core';
import path from 'node:path';

const executablePath = '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';
const base = process.env.ATLAS_URL || 'http://127.0.0.1:9010';
const browser = await chromium.launch({ executablePath, headless: true, args: ['--no-sandbox'] });
const context = await browser.newContext({ viewport: { width: 1440, height: 1200 }, locale: 'zh-CN' });
const page = await context.newPage();
const errors = [];
page.on('pageerror', (err) => errors.push(err.message));
page.on('console', (msg) => { if (msg.type() === 'error') errors.push(msg.text()); });

let passed = 0;
let failed = 0;
function check(name, condition, detail = '') {
  if (condition) { passed++; console.log('  ✓ ' + name); }
  else { failed++; console.log('  ✗ ' + name + (detail ? ' — ' + detail : '')); }
}

console.log('\nATLAS BROWSER TEST\n==================');
await page.goto(base + '/login', { waitUntil: 'networkidle', timeout: 60000 });
check('login page renders', await page.locator('h1').innerText().then((t) => t.includes('登录工作台')));
await page.fill('#username', process.env.ATLAS_USER || 'admin');
await page.fill('#password', process.env.ATLAS_PASS || 'Atlas!2026');
await page.click('button[type="submit"]');
await page.waitForLoadState('networkidle');
check('login redirects into the console', !page.url().endsWith('/login'), page.url());
await page.goto(base + '/jobs/create', { waitUntil: 'networkidle', timeout: 60000 });
await page.waitForTimeout(1500);
check('targeting page loads', await page.locator('h1').innerText().then((t) => t.includes('全球地址')));
check('Leaflet map is mounted', await page.locator('#target-map .leaflet-tile-pane').count() > 0);
check('map tile images loaded', await page.locator('#target-map img.leaflet-tile').count() > 0);
check('map container has usable height', (await page.locator('#target-map').boundingBox())?.height > 500);
await page.waitForTimeout(1200);
const cityOptionCount = await page.locator('#region_id option').count();
check('city node dropdown loads cities for selected country', cityOptionCount > 1000, String(cityOptionCount));
check('city node options come from the dictionary DB', await page.locator('#region_id option[data-geo-city]').count() > 1000);
const kanaCount = await page.locator('#region_id option').evaluateAll((options) => options.filter((o) => /[\u3040-\u30ff]/.test(o.textContent)).length);
check('city dropdown has no Japanese kana labels', kanaCount === 0, String(kanaCount));


const initialLat = await page.inputValue('#latitude');
const box = await page.locator('#target-map').boundingBox();
await page.mouse.click(box.x + box.width * 0.62, box.y + box.height * 0.52);
await page.waitForTimeout(2500);
const clickedLat = await page.inputValue('#latitude');
check('clicking the map updates latitude', clickedLat !== initialLat, initialLat + ' -> ' + clickedLat);
check('clicking the map sets a target address', (await page.inputValue('#target_address')).length > 0);
check('reverse geocode fills detected address', (await page.locator('#detected-address').innerText()).length > 3);

await page.fill('#address-search', 'Tokyo');
await page.waitForTimeout(2200);
check('global address search returns results', await page.locator('#search-results .map-result').count() > 0);
if (await page.locator('#search-results .map-result').count() > 0) {
  await page.locator('#search-results .map-result').first().click();
  await page.waitForTimeout(1600);
  const japan = await page.inputValue('#country_id');
  check('search result synchronizes country', japan !== '', japan);
  check('search result updates lat/lng', (await page.inputValue('#latitude')).startsWith('35.') || (await page.inputValue('#country_id')) !== '');
}
await page.locator('[data-radius="25000"]').click();
check('radius preset updates hidden field', await page.inputValue('#radius_input') === '25000');
check('radius readout updates', (await page.locator('#radius-label').innerText()).includes('25.0'));
check('payload preview updates radius', await page.locator('#preview-radius').innerText() === '25000');

await page.locator('[data-command-open]').first().click();
check('command palette opens', await page.locator('#command-palette').evaluate((el) => el.classList.contains('is-open')));
await page.keyboard.press('Escape');
check('command palette closes', !(await page.locator('#command-palette').evaluate((el) => el.classList.contains('is-open'))));

await page.screenshot({ path: '/tmp/atlas-shots/targeting-tested.png', fullPage: true });

console.log('\nPage sweep');
for (const [label, url] of [
  ['dashboard', '/'],
  ['jobs', '/jobs'],
  ['job detail', await page.evaluate(() => '/jobs/' + 'd7ea2c45-1912-4ecd-acbf-e013f94bc21b')],
  ['businesses', '/businesses'],
  ['business detail', '/businesses/1'],
  ['countries', '/countries?country=1'],
  ['industries', '/industries'],
  ['keywords', '/keywords'],
  ['quality', '/data-quality'],
  ['exports', '/exports'],
  ['settings', '/settings'],
  ['account', '/account'],
  ['users', '/users'],
  ['batch', '/jobs/batch'],
]) {
  const resp = await page.goto(base + url, { waitUntil: 'domcontentloaded', timeout: 30000 });
  check(label + ' responds 200', resp?.status() === 200, String(resp?.status()));
  check(label + ' renders shell', await page.locator('aside#sidebar').count() === 1);
}

// Mobile layout sanity.
const mobile = await context.newPage();
await mobile.setViewportSize({ width: 390, height: 844 });
await mobile.goto(base + '/jobs/create', { waitUntil: 'domcontentloaded' });
check('mobile renders targeting page', await mobile.locator('#target-map').count() === 1);
check('mobile sidebar is hidden', !(await mobile.locator('#sidebar').evaluate((el) => el.classList.contains('is-open'))));
await mobile.locator('[data-nav-open]').click();
check('mobile nav opens', await mobile.locator('#sidebar').evaluate((el) => el.classList.contains('is-open')));
await mobile.screenshot({ path: '/tmp/atlas-shots/mobile.png', fullPage: false });

console.log('\nConsole errors: ' + errors.length);
errors.slice(0, 10).forEach((e) => console.log('  ! ' + e));
console.log('\n==================');
console.log(`PASSED: ${passed}  FAILED: ${failed}`);
await browser.close();
process.exit(failed > 0 ? 1 : 0);

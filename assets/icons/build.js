// Turns the icon drawings into the PNG files phones use for the home screen.
// Run: node assets/icons/build.js   (needs Playwright; writes assets/img/apps/<app>-<size>.png)
const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');

const APPS = ['fractions', 'square', 'crown', 'spacing', 'board-feet', 'stairs', 'dxf'];
const SIZES = [180, 192, 512];
const SET = 'dark'; // the set chosen for home-screen icons
const src = __dirname;
const out = path.join(__dirname, '..', 'img', 'apps');

(async () => {
  fs.mkdirSync(out, { recursive: true });
  const browser = await chromium.launch();
  const page = await browser.newPage();
  for (const app of APPS) {
    const svg = fs.readFileSync(path.join(src, `${app}-${SET}.svg`), 'utf8');
    for (const size of SIZES) {
      await page.setViewportSize({ width: size, height: size });
      await page.setContent(`<html><body style="margin:0">${svg.replace('<svg ', `<svg width="${size}" height="${size}" `)}</body></html>`);
      await page.screenshot({ path: path.join(out, `${app}-${size}.png`), clip: { x: 0, y: 0, width: size, height: size } });
    }
  }
  await browser.close();
  console.log('icons written to', out);
})();

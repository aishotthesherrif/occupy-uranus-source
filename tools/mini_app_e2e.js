/**
 * End-to-end test of the mini-app in a real browser.
 *
 * Starts PHP's built-in server, opens mini-app/app-v1.html in headless Chrome signed in as a
 * throwaway Telegram user, and plays through the real buttons: start a journey, shop, launch,
 * play days, answer events, dock, rest, read status, turn back, reach both endings, abandon.
 * Then a plain browser (no Telegram): sign in with a captain name, play, reload, switch captain, sign back in.
 * Fails on any browser error or on stray markup / "undefined" / "NaN" on screen.
 *
 * Setup (once, anywhere outside the repo):  npm i puppeteer-core
 * Run:  NODE_PATH=<that node_modules> node tools/mini_app_e2e.js [screenshot-dir]
 * Uses logs/sessions of the repo it runs from for one throwaway user; run it from a copy.
 */
const { spawn, execFileSync } = require('child_process');
const crypto = require('crypto');
const path = require('path');
const fs = require('fs');
const puppeteer = require('puppeteer-core');

const ROOT = path.resolve(__dirname, '..');
const SHOTS = process.argv[2] || null;
const CHROME = process.env.CHROME || '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';
const PORT = 8800 + Math.floor(Math.random() * 100);
const USER_ID = 987650003;

const php = (code) => execFileSync('php', ['-r', code], { cwd: ROOT, encoding: 'utf8' });
const BOT_TOKEN = php("require 'config.php'; echo BOT_TOKEN;");

function signedInitData(userId) {
    const fields = { auth_date: String(Math.floor(Date.now() / 1000)), query_id: 'e2e', user: JSON.stringify({ id: userId, first_name: 'E2E', last_name: 'Pilot', username: 'e2epilot' }) };
    const check = Object.keys(fields).sort().map((k) => `${k}=${fields[k]}`).join('\n');
    const secret = crypto.createHmac('sha256', 'WebAppData').update(BOT_TOKEN).digest();
    const hash = crypto.createHmac('sha256', secret).update(check).digest('hex');
    return new URLSearchParams({ ...fields, hash }).toString();
}

let checks = 0, fails = 0;
const check = (ok, what) => { checks++; if (!ok) { fails++; console.log('FAIL:', what); } };
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

async function main() {
    php(`require 'config.php'; require 'game/GameSessionManager.php'; (new GameSessionManager())->deleteSession(${USER_ID});`);
    const server = spawn('php', ['-S', `127.0.0.1:${PORT}`, '-t', ROOT], { stdio: 'ignore' });
    for (let i = 0; i < 50; i++) { try { await fetch(`http://127.0.0.1:${PORT}/`); break; } catch { await sleep(100); } }

    const browser = await puppeteer.launch({ executablePath: CHROME, headless: 'new', args: ['--no-sandbox'] });
    const page = await browser.newPage();
    await page.setViewport({ width: 390, height: 844, deviceScaleFactor: 2 });

    const errors = [];
    page.on('pageerror', (e) => errors.push('pageerror: ' + e.message));
    page.on('console', (m) => { if (m.type() === 'error' && !/Failed to load resource|ERR_/.test(m.text())) errors.push('console: ' + m.text()); });
    await page.setRequestInterception(true);
    page.on('request', (req) => (req.url().startsWith(`http://127.0.0.1:${PORT}`) ? req.continue() : req.abort()));

    const initData = signedInitData(USER_ID);
    await page.evaluateOnNewDocument((data) => {
        window.Telegram = { WebApp: {
            initData: data, initDataUnsafe: {}, platform: 'test', version: '8', ready() {}, expand() {}, setHeaderColor() {},
            close() { window.__closed = true; }, HapticFeedback: { impactOccurred() {}, notificationOccurred() {} },
        } };
    }, initData);

    const url = `http://127.0.0.1:${PORT}/mini-app/app-v1.html`;
    const visible = (sel) => page.$eval(sel, (n) => getComputedStyle(n).display !== 'none').catch(() => false);
    const text = (sel) => page.$eval(sel, (n) => n.innerText).catch(() => '');
    // The cockpit uses 3D transforms, which confuse coordinate clicks; clicking the element is what a tap does
    const tap = (sel) => page.$eval(sel, (n) => n.click());
    const has = async (sel, needle) => (await text(sel)).toLowerCase().includes(needle.toLowerCase());
    const titleIs = async (want) => (await text('#panelTitle')).trim().toLowerCase() === want.toLowerCase();
    const shot = async (name) => { if (SHOTS) { await sleep(900); // let modal animations finish
        fs.mkdirSync(SHOTS, { recursive: true }); await page.screenshot({ path: path.join(SHOTS, name + '.png') }); } };
    const waitFor = async (fn, ms = 6000) => { const t = Date.now(); while (Date.now() - t < ms) { if (await fn()) return true; await sleep(80); } return false; };
    const clickPanelButton = async (label) => {
        const ok = await page.evaluate((l) => { const b = [...document.querySelectorAll('#panelActions .panel-btn')].find((x) => x.innerText.includes(l)); if (b && !b.disabled) { b.click(); return true; } return false; }, label);
        return ok;
    };
    const quiet = async () => waitFor(async () => !(await page.evaluate(() => document.body.classList.contains('busy'))), 5000);
    const sane = async (where) => {
        const body = await page.evaluate(() => document.body.innerText);
        const bad = body.match(/.{0,40}(\bundefined\b|\bNaN\b|\[object|<\/?[a-zA-Z]+>).{0,40}/);
        check(!bad, `${where}: no undefined/NaN/markup on screen${bad ? ' -> ' + JSON.stringify(bad[0]) : ''}`);
    };
    const apiState = () => page.evaluate(async (d) => (await fetch(`../api/game/state.php?init_data=${encodeURIComponent(d)}`)).json(), initData);

    // ---- no journey yet -> start page ---------------------------------------------------------------
    await page.goto(url, { waitUntil: 'load' });
    check(await waitFor(() => visible('#startPage')), 'no game: start page shows');
    check(await has('#startPage', 'New Journey'), 'start page offers New Journey');
    await shot('01-start'); await sane('start page');

    // ---- new journey -> launch pad with shop -----------------------------------------------------------------
    await tap('#startPageNewBtn');
    check(await waitFor(() => titleIs('Launch Pad')), 'New Journey opens the launch pad');
    const rows = await page.$$('#panelBody .shop-row');
    check(rows.length === 3, 'launch pad lists three shop items');
    check((await text('#panelBody')).includes('15.000 ETH'), 'ETH shown to 3 decimals (15.000, not cents)');
    const crew = await page.$$eval('.crew-roster .crew-card', (cards) => cards.map((c) => c.innerText));
    check(crew.length === 5 && crew.every((c) => /\w+ \w+/.test(c) && !c.includes('Unknown')), 'crew panel shows the five real crew members');
    await shot('02-launch-pad'); await sane('launch pad');

    await page.evaluate(() => document.querySelector('#panelBody .shop-buy').click());
    check(await waitFor(async () => (await text('#flashMessage')).includes('Bought Fuel')), 'buying fuel confirms in the flash message');
    await quiet();
    check((await text('#panelBody')).includes('14.000 ETH'), 'the launch pad shows the ETH left after buying');

    await clickPanelButton('Launch Ship'); await quiet();
    check(await waitFor(async () => !(await visible('#panelModal'))), 'launch closes the panel');
    check((await text('#flashMessage')).toLowerCase().includes('launched'), 'launch is announced');
    check((await text('.day-display')).trim() === '1', 'dashboard shows day 1');
    check((await apiState()).session.status === 'in_space', 'the server agrees: in space');
    await shot('03-dashboard'); await sane('dashboard');

    // ---- status ------------------------------------------------------------------------------------------------
    await tap('#btn-status');
    check(await waitFor(() => titleIs('Status')), 'Status opens');
    const statusText = (await text('#panelBody')).toLowerCase();
    check(statusText.includes('crew (5/5)') && statusText.includes('ship & supplies') && statusText.includes('to uranus'), 'status shows mission, crew and supplies');
    await shot('04-status'); await sane('status');
    await clickPanelButton('Close');

    // ---- play: days, events, waypoints, stations -----------------------------------------------------------------
    const seen = new Set();
    let turnedBack = false;
    for (let i = 0; i < 160; i++) {
        await quiet();
        if (await visible('#gameOverScreen') || await visible('#victoryScreen')) break;

        if (await visible('#eventModal')) {
            seen.add('event');
            if (!seen.has('event-shot')) { seen.add('event-shot'); await shot('05-event'); await sane('event'); }
            await page.evaluate(() => document.querySelector('#eventChoices .event-choice-btn').click());
            await waitFor(async () => (await titleIs('Result')) || await visible('#gameOverScreen') || await visible('#eventModal'));
            if (await visible('#panelModal')) { if (!seen.has('result-shot')) { seen.add('result-shot'); await shot('06-event-result'); await sane('event result'); } await clickPanelButton('Back to Journey'); }
            continue;
        }
        if (await visible('#waypointModal')) {
            seen.add('waypoint');
            await shot('07-waypoint');
            await tap(i % 2 ? '#btnStopAtWaypoint' : '#btnSkipWaypoint');
            await waitFor(async () => !(await visible('#waypointModal')));
            continue;
        }
        if (await visible('#panelModal')) {
            if (!(await titleIs('Result')) && !(await titleIs('Status'))) {
                seen.add('station');
                if (!seen.has('station-shot')) { seen.add('station-shot'); await shot('08-station'); await sane('station'); }
                await page.evaluate(() => document.querySelector('#panelBody .shop-buy:not(:disabled)')?.click());
                await quiet();
                await clickPanelButton('Rest'); await quiet();
                seen.add('rested');
                await clickPanelButton('Depart'); await quiet();
                continue;
            }
            await clickPanelButton('Back to Journey') || await clickPanelButton('Close');
            continue;
        }
        if (!turnedBack && i === 5 && await visible('#btn-return')) {
            turnedBack = true;
            await tap('#btn-return');
            check(await waitFor(() => titleIs('Turn back?')), 'Turn Back asks first');
            await shot('09-turn-back');
            await clickPanelButton('Stay on course');
            check(!(await visible('#panelModal')), 'staying on course closes the prompt');
            continue;
        }
        if (i % 9 === 4 && await visible('#btn-hunt')) { await tap('#btn-hunt'); seen.add('hunt'); continue; }
        await tap('#btn-continue');
        seen.add('continue');

        if (i % 10 === 0) {
            await quiet(); await sleep(150);
            const ui = await page.$eval('.day-display', (n) => n.innerText.trim());
            const st = await apiState();
            if (st.has_session) check(String(st.session.progress.day) === ui, `UI day ${ui} equals the server day ${st.session.progress.day}`);
        }
    }
    check(seen.has('continue') && seen.has('event'), `played days and events (saw: ${[...seen].join(', ')})`);
    check(errors.length === 0, 'no browser errors: ' + errors.slice(0, 3).join(' | '));

    // ---- endings (crafted games) --------------------------------------------------------------------------------------
    const craft = (mutate) => php(`require 'config.php'; require 'bot/FormatHelpers.php'; require 'game/GameEngine.php'; require 'game/GameSessionManager.php';
        $m = new GameSessionManager(); $e = new GameEngine(); $s = $e->createNewGame(${USER_ID}); $s['status'] = 'in_space'; ${mutate} $m->deleteSession(${USER_ID}); $m->saveSession(${USER_ID}, $s);`);

    craft(`$s['resources']['fuel'] = 0; $s['progress']['day'] = 33;`);
    await page.goto(url, { waitUntil: 'load' });
    check(await waitFor(() => visible('#gameOverScreen')), 'a dead game opens on the game over screen');
    check((await text('#gameOverReason')).includes('ran out of fuel'), 'game over states the real reason');
    await shot('10-game-over'); await sane('game over');
    await tap('#gameOverNewBtn');
    check(await waitFor(() => titleIs('Launch Pad')), 'New Game from game over leads to a fresh launch pad');

    craft(`$s['progress']['distance'] = TOTAL_DISTANCE; $s['progress']['day'] = 120;`);
    await page.goto(url, { waitUntil: 'load' });
    check(await waitFor(() => visible('#victoryScreen')), 'a finished journey opens on the victory screen');
    await shot('11-victory'); await sane('victory');

    // ---- abandon -----------------------------------------------------------------------------------------------------------
    craft(`$s['progress']['day'] = 12;`);
    await page.goto(url, { waitUntil: 'load' });
    check(await waitFor(async () => !(await visible('#startPage')) && (await text('.day-display')).trim() === '12'), 'reopening shows the saved game (day 12)');
    await quiet();
    await tap('#btn-abandon');
    const asked = await waitFor(() => titleIs('Abandon mission?'));
    check(asked, 'Abandon asks first' + (asked ? '' : ` (title=${JSON.stringify(await text('#panelTitle'))}, panel=${await visible('#panelModal')}, busy=${await page.evaluate(() => document.body.className)}, errors=${errors.join('|')}, hit=${await page.evaluate(() => { const b = document.getElementById('btn-abandon'); b.scrollIntoView({block:'center'}); const r = b.getBoundingClientRect(); const e = document.elementFromPoint(r.x + r.width / 2, r.y + r.height / 2); return e ? (e.id || e.className || e.tagName) + ' vs btn display=' + getComputedStyle(b).display : 'none'; })})`));
    await clickPanelButton('Yes, abandon it');
    check(await waitFor(() => visible('#startPage')), 'abandoning returns to the start page');
    check((await apiState()).has_session === false, 'the server no longer has the game');

    check(errors.length === 0, 'no browser errors at the end: ' + errors.slice(0, 3).join(' | '));

    // ---- web player: plain browser, captain name (honor system) ----------------------------------------------------
    // A fresh profile (empty localStorage). Like a real browser, telegram-web-app.js is "loaded" but has no initData.
    const webCtx = await browser.createBrowserContext();
    const web = await webCtx.newPage();
    await web.setViewport({ width: 390, height: 844, deviceScaleFactor: 2 });
    web.on('pageerror', (e) => errors.push('web pageerror: ' + e.message));
    web.on('console', (m) => { if (m.type() === 'error' && !/Failed to load resource|ERR_/.test(m.text())) errors.push('web console: ' + m.text()); });
    await web.setRequestInterception(true);
    web.on('request', (req) => (req.url().startsWith(`http://127.0.0.1:${PORT}`) ? req.continue() : req.abort()));
    await web.evaluateOnNewDocument(() => {
        window.Telegram = { WebApp: { initData: '', initDataUnsafe: {}, ready() {}, expand() {}, setHeaderColor() {}, close() {} } };
    });
    const wVisible = (sel) => web.$eval(sel, (n) => getComputedStyle(n).display !== 'none').catch(() => false);
    const wText = (sel) => web.$eval(sel, (n) => n.innerText).catch(() => '');
    const wTap = (sel) => web.$eval(sel, (n) => n.click());
    const wTitleIs = async (want) => (await wText('#panelTitle')).trim().toLowerCase() === want.toLowerCase();
    const wPanelButton = (label) => web.evaluate((l) => { const b = [...document.querySelectorAll('#panelActions .panel-btn')].find((x) => x.innerText.includes(l)); if (b && !b.disabled) { b.click(); return true; } return false; }, label);
    const wQuiet = () => waitFor(async () => !(await web.evaluate(() => document.body.classList.contains('busy'))), 5000);
    const signIn = async (name) => { await web.$eval('#signInName', (n, v) => { n.value = v; }, name); await wTap('#signInBtn'); };
    const captain = `E2E Captain ${Math.floor(Math.random() * 9000) + 1000}`;
    const webShot = async (name) => { if (SHOTS) { await sleep(900); await web.screenshot({ path: path.join(SHOTS, name + '.png') }); } };

    await web.goto(url, { waitUntil: 'load' });
    check(await waitFor(() => wVisible('#signInPage')), 'web: a plain browser asks for a captain name');
    check(!(await wVisible('#startPage')) && !(await wVisible('.dashboard')), 'web: nothing else shows before signing in');
    await webShot('20-web-sign-in');
    await signIn('x');
    check(await waitFor(async () => (await wText('#signInError')).includes('2–20')), 'web: a one-letter name is refused with the rules');
    await signIn(captain);
    check(await waitFor(() => wVisible('#startPage')), 'web: signing in with a new name opens the start page');
    check((await wText('#startCaptain')).includes(captain), 'web: the start page shows the captain name');
    check((await wText('#startPageCloseBtn')).toLowerCase().includes('switch captain'), 'web: the start page offers Switch captain instead of Close');
    await webShot('21-web-start');
    await wTap('#startPageNewBtn');
    check(await waitFor(() => wTitleIs('Launch Pad')), 'web: New Journey opens the launch pad');
    await wPanelButton('Launch');
    check(await waitFor(async () => (await wText('.day-display')).trim() === '1'), 'web: launched, day 1');
    await wQuiet();
    await wTap('#btn-continue');
    await wQuiet();
    const webDay = await waitFor(async () => (await wText('.day-display')).trim() !== '1' || (await wVisible('#eventModal')) || (await wVisible('#waypointModal')));
    check(webDay, 'web: Continue plays a day');

    await web.reload({ waitUntil: 'load' });
    check(await waitFor(async () => !(await wVisible('#signInPage')) && (await wVisible('.dashboard'))), 'web: reloading remembers the captain');

    await web.evaluate(() => { document.querySelectorAll('#eventModal, #waypointModal').forEach((m) => { m.style.display = 'none'; }); });
    await wTap('#btn-status');
    check(await waitFor(() => wTitleIs('Status')), 'web: Status opens');
    check((await wText('#panelBody')).toLowerCase().includes(captain.toLowerCase()), 'web: Status shows who is flying');
    check(await wPanelButton('Switch captain'), 'web: Status offers Switch captain');
    check(await waitFor(() => wVisible('#signInPage')), 'web: Switch captain returns to sign-in');
    check((await web.evaluate(() => localStorage.getItem('uranus.captain'))) === null, 'web: switching forgets the stored name');

    await signIn(captain.toUpperCase());
    check(await waitFor(async () => !(await wVisible('#startPage')) && (await wVisible('.dashboard'))), 'web: the same name (any case) brings back the same ship, not a new one');
    check(errors.length === 0, 'no browser errors in web mode: ' + errors.slice(0, 3).join(' | '));
    await webCtx.close();

    await browser.close();
    server.kill();
    php(`require 'config.php'; require 'game/GameSessionManager.php'; (new GameSessionManager())->deleteSession(${USER_ID});`);
    php(`require 'config.php'; require 'game/GameSessionManager.php'; require 'api/game/auth.php'; (new GameSessionManager())->deleteSession(webPlayerFromName(${JSON.stringify(captain)})['user_id']);`);
    console.log(`${checks} checks, ${fails} failed.`);
    process.exit(fails ? 1 : 0);
}

main().catch((e) => { console.error(e); process.exit(2); });

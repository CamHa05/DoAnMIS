import { writeFileSync } from 'node:fs';

const [cookieName, cookieValue, screenshotPath] = process.argv.slice(2);
const targetUrl = 'http://127.0.0.1:8012/admin';
const target = await fetch(`http://127.0.0.1:9224/json/new?${encodeURIComponent('about:blank')}`, {
    method: 'PUT',
}).then((response) => response.json());
const socket = new WebSocket(target.webSocketDebuggerUrl);
const pending = new Map();
let messageId = 0;

const send = (method, params = {}) => new Promise((resolve, reject) => {
    const id = ++messageId;
    pending.set(id, { resolve, reject });
    socket.send(JSON.stringify({ id, method, params }));
});

socket.addEventListener('message', (event) => {
    const message = JSON.parse(event.data);
    if (!message.id || !pending.has(message.id)) return;

    const promise = pending.get(message.id);
    pending.delete(message.id);

    if (message.error) promise.reject(new Error(message.error.message));
    else promise.resolve(message.result);
});

await new Promise((resolve, reject) => {
    socket.addEventListener('open', resolve, { once: true });
    socket.addEventListener('error', reject, { once: true });
});

await send('Network.enable');
await send('Page.enable');
await send('Emulation.setDeviceMetricsOverride', {
    width: 1440,
    height: 900,
    deviceScaleFactor: 1,
    mobile: false,
});
await send('Network.setCookie', {
    name: cookieName,
    value: cookieValue,
    url: targetUrl,
    httpOnly: true,
    secure: false,
    sameSite: 'Lax',
});
await send('Page.navigate', { url: targetUrl });
await new Promise((resolve) => setTimeout(resolve, 1800));

const evaluation = await send('Runtime.evaluate', {
    expression: `(async () => {
        await document.fonts.ready;
        const dashboard = document.querySelector('.admin-dashboard');
        const stats = document.querySelector('.admin-stats');
        const analytics = document.querySelector('.admin-dashboard__analytics');
        const chart = document.querySelector('[data-admin-activity-chart] svg');
        const firstPoint = document.querySelector('[data-admin-chart-point]');
        const tooltip = document.querySelector('[data-admin-chart-tooltip]');
        firstPoint?.focus();
        await new Promise((resolve) => setTimeout(resolve, 50));
        const tooltipOnFocus = Boolean(tooltip && !tooltip.hidden && tooltip.textContent.trim());
        document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }));
        await new Promise((resolve) => setTimeout(resolve, 150));
        return {
            url: location.href,
            title: document.title,
            h1: document.querySelector('h1')?.textContent.trim(),
            dashboardPresent: Boolean(dashboard),
            viewportWidth: document.documentElement.clientWidth,
            pageScrollWidth: document.documentElement.scrollWidth,
            bodyScrollWidth: document.body.scrollWidth,
            statCards: document.querySelectorAll('.admin-stat-card').length,
            statColumns: stats ? getComputedStyle(stats).gridTemplateColumns : null,
            analyticsColumns: analytics ? getComputedStyle(analytics).gridTemplateColumns : null,
            chartPresent: Boolean(chart),
            chartLines: document.querySelectorAll('.admin-chart__line').length,
            chartPoints: document.querySelectorAll('[data-admin-chart-point]').length,
            chartTooltipOnFocus: tooltipOnFocus,
            donutSegments: document.querySelectorAll('.admin-donut__segment').length,
            tableCount: document.querySelectorAll('.admin-table').length,
            fontStatus: document.fonts.status,
        };
    })()`,
    awaitPromise: true,
    returnByValue: true,
});

const screenshot = await send('Page.captureScreenshot', { format: 'png' });
writeFileSync(screenshotPath, Buffer.from(screenshot.data, 'base64'));
console.log(JSON.stringify(evaluation.result.value));
socket.close();

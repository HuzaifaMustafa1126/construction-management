const cdp = "http://127.0.0.1:9225";
const app = "http://127.0.0.1:5187";
const target = await (
  await fetch(`${cdp}/json/new?${encodeURIComponent(`${app}/login`)}`, {
    method: "PUT",
  })
).json();
const ws = new WebSocket(target.webSocketDebuggerUrl);
await new Promise((resolve, reject) => {
  ws.onopen = resolve;
  ws.onerror = reject;
});
let sequence = 0;
const pending = new Map();
const events = { console: [], exceptions: [], failed: [], http: [] };
ws.onmessage = ({ data }) => {
  const message = JSON.parse(data);
  if (message.id) {
    const request = pending.get(message.id);
    if (!request) return;
    pending.delete(message.id);
    message.error
      ? request.reject(new Error(JSON.stringify(message.error)))
      : request.resolve(message.result);
    return;
  }
  if (
    message.method === "Runtime.consoleAPICalled" &&
    ["error", "warning"].includes(message.params.type)
  )
    events.console.push(
      message.params.args.map((arg) => arg.value || arg.description).join(" "),
    );
  if (message.method === "Runtime.exceptionThrown")
    events.exceptions.push(message.params.exceptionDetails.text);
  if (message.method === "Network.loadingFailed")
    events.failed.push(message.params.errorText);
  if (
    message.method === "Network.responseReceived" &&
    message.params.response.status >= 400
  )
    events.http.push({
      status: message.params.response.status,
      url: message.params.response.url,
    });
};
const send = (method, params = {}) =>
  new Promise((resolve, reject) => {
    const id = ++sequence;
    pending.set(id, { resolve, reject });
    ws.send(JSON.stringify({ id, method, params }));
  });
const evaluate = async (expression) =>
  (
    await send("Runtime.evaluate", {
      expression,
      awaitPromise: true,
      returnByValue: true,
    })
  ).result.value;
const wait = async (expression, label, timeout = 12000) => {
  const end = Date.now() + timeout;
  while (Date.now() < end) {
    try {
      if (await evaluate(expression)) return;
    } catch {}
    await new Promise((resolve) => setTimeout(resolve, 150));
  }
  throw new Error(`Timeout: ${label}`);
};
const clickText = (text) =>
  evaluate(
    `(() => { const element = [...document.querySelectorAll("button,a")].find((candidate) => candidate.textContent.trim() === ${JSON.stringify(text)}); if (!element) return false; element.click(); return true; })()`,
  );
const setInput = (selector, value) =>
  evaluate(
    `(() => { const element = document.querySelector(${JSON.stringify(selector)}); if (!element) return false; const prototype = element.tagName === "SELECT" ? HTMLSelectElement.prototype : HTMLInputElement.prototype; Object.getOwnPropertyDescriptor(prototype, "value").set.call(element, ${JSON.stringify(value)}); element.dispatchEvent(new Event("input", { bubbles: true })); element.dispatchEvent(new Event("change", { bubbles: true })); return true; })()`,
  );
const navigate = async (path) => {
  await send("Page.navigate", { url: `${app}${path}` });
  await wait("document.readyState === 'complete'", `navigate ${path}`);
};
const login = async (email) => {
  await navigate("/login");
  await wait("!!document.querySelector('input[name=email]')", "login form");
  await setInput("input[name=email]", email);
  await setInput("input[name=password]", "S3-Disposable-Only-2026!");
  await clickText("Sign in");
  await wait("location.pathname === '/dashboard'", `login ${email}`);
};

await send("Runtime.enable");
await send("Page.enable");
await send("Network.enable");
await send("Log.enable");
await send("Emulation.setDeviceMetricsOverride", {
  width: 1440,
  height: 900,
  deviceScaleFactor: 1,
  mobile: false,
});
const results = {};

await login("admin-a@s3.invalid");
await navigate("/projects");
await wait("document.body.innerText.includes('Alpha Tower')", "project cards");
results.cardsLoaded = await evaluate(
  "({ cards: document.querySelectorAll('.project-card').length, hasAlpha: document.body.innerText.includes('Alpha Tower') })",
);
results.alphaCard = await evaluate(
  "(() => { const card = [...document.querySelectorAll('.project-card')].find((element) => element.innerText.includes('Alpha Tower')); return card?.innerText || null; })()",
);

await setInput("input[placeholder*='Search name']", "Alpha");
await setInput("label.app-field select", "planning");
await setInput("label.app-field:nth-of-type(3) select", "4");
await clickText("Search");
await wait(
  "document.querySelectorAll('.project-card').length === 1 && document.body.innerText.includes('Alpha Tower')",
  "filtered Alpha",
);
await clickText("List");
await wait("!!document.querySelector('.app-table')", "list view");
results.filterPersistenceList = await evaluate(
  "({ search: document.querySelector(\"input[placeholder*='Search name']\").value, status: document.querySelectorAll('label.app-field select')[0].value, manager: document.querySelectorAll('label.app-field select')[1].value, hasTable: !!document.querySelector('.app-table'), hasAlpha: document.body.innerText.includes('Alpha Tower') })",
);
await clickText("Cards");
await wait("!!document.querySelector('.project-card')", "cards after list");
results.filterPersistenceCards = await evaluate(
  "({ search: document.querySelector(\"input[placeholder*='Search name']\").value, status: document.querySelectorAll('label.app-field select')[0].value, manager: document.querySelectorAll('label.app-field select')[1].value })",
);

await setInput("input[placeholder*='Search name']", "");
await setInput("label.app-field select", "");
await setInput("label.app-field:nth-of-type(3) select", "");
await clickText("Search");
await wait("document.body.innerText.includes('16 projects')", "all projects");
await clickText("Next");
await wait("document.body.innerText.includes('Page 2 of 2')", "page 2");
await clickText("List");
await wait(
  "document.querySelector('.app-table') && document.body.innerText.includes('Page 2 of 2')",
  "page state in list",
);
results.paginationPersistence = await evaluate(
  "({ pageText: document.querySelector('.server-pagination span')?.innerText, hasTable: !!document.querySelector('.app-table') })",
);

await clickText("Archived projects");
await wait(
  "document.querySelector('h1')?.innerText === 'Archived projects'",
  "archive mode",
);
await clickText("Cards");
await wait("!!document.querySelector('.project-card')", "archive cards");
await clickText("List");
await wait(
  "document.querySelector('h1')?.innerText === 'Archived projects' && document.querySelector('.app-table')",
  "archive list persistence",
);
results.archivePersistence = await evaluate(
  "({ title: document.querySelector('h1')?.innerText, hasTable: !!document.querySelector('.app-table'), page: document.querySelector('.server-pagination span')?.innerText })",
);

await clickText("Active projects");
await wait(
  "document.querySelector('h1')?.innerText === 'Projects'",
  "active mode",
);
await setInput("input[placeholder*='Search name']", "Alpha");
await clickText("Search");
await wait("document.body.innerText.includes('Alpha Tower')", "Alpha active");
await clickText("Cards");
await wait("!!document.querySelector('.project-card')", "Alpha card");
await evaluate(
  "document.querySelector('[aria-label=\"View Alpha Tower\"]').click()",
);
await wait(
  "location.pathname.match(/^\\/projects\\/\\d+$/) && document.body.innerText.includes('Overview')",
  "Alpha detail",
);
results.overview = await evaluate(
  "({ url: location.pathname, text: document.body.innerText, hasEdit: document.body.innerText.includes('Edit project'), hasStatusControls: document.body.innerText.includes('Status and progress') })",
);
await clickText("Team");
await wait("document.body.innerText.includes('Disposable Member')", "team tab");
results.team = await evaluate(
  "({ text: document.body.innerText, hasMember: document.body.innerText.includes('Disposable Member'), hasManager: document.body.innerText.includes('Disposable Manager'), hasSave: /Save|Update/.test(document.body.innerText) })",
);

await evaluate("sessionStorage.clear()");
await login("nonmember@s3.invalid");
await navigate("/projects/2");
await wait(
  "document.body.innerText.includes('Project not found or unavailable')",
  "nonmember denied",
);
results.nonmember = await evaluate(
  "({ url: location.pathname, message: document.body.innerText })",
);

await evaluate("sessionStorage.clear()");
await login("admin-a@s3.invalid");
await navigate("/projects");
await wait(
  "document.body.innerText.includes('Archived projects')",
  "admin projects",
);
await clickText("Archived projects");
await wait(
  "document.querySelector('h1')?.innerText === 'Archived projects' && document.body.innerText.includes('Active Conflict')",
  "archived project",
);
results.archiveBeforeRestore = await evaluate("document.body.innerText");
await evaluate(
  "document.querySelector('[aria-label=\"Restore Active Conflict\"]').click()",
);
await wait(
  "document.body.innerText.includes('Restore project')",
  "restore dialog",
);
await clickText("Confirm");
await wait(
  "!document.body.innerText.includes('Active Conflict') || document.body.innerText.includes('No archived projects')",
  "restore complete",
);
results.restore = await evaluate(
  "({ title: document.querySelector('h1')?.innerText, body: document.body.innerText })",
);
await clickText("Active projects");
await wait(
  "document.querySelector('h1')?.innerText === 'Projects'",
  "active after restore",
);
await setInput("input[placeholder*='Search name']", "Active Conflict");
await clickText("Search");
await wait(
  "document.body.innerText.includes('Active Conflict')",
  "restored visible",
);
results.restoreVisible = await evaluate("document.body.innerText");

await send("Emulation.setDeviceMetricsOverride", {
  width: 360,
  height: 800,
  deviceScaleFactor: 1,
  mobile: true,
});
await navigate("/projects");
await wait("!!document.querySelector('.project-card')", "mobile projects");
results.mobile = await evaluate(
  "({ innerWidth, documentScrollWidth: document.documentElement.scrollWidth, bodyScrollWidth: document.body.scrollWidth, cards: document.querySelectorAll('.project-card').length, header: document.querySelector('h1')?.innerText, controls: [...document.querySelectorAll('button,a')].filter((element) => { const box = element.getBoundingClientRect(); return box.width > 0 && box.height > 0; }).slice(0, 12).map((element) => { const box = element.getBoundingClientRect(); return { text: element.innerText || element.getAttribute('aria-label'), left: box.left, right: box.right }; }) })",
);
results.events = events;
console.log(JSON.stringify(results));
ws.close();

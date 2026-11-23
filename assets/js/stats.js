// Counts taps on buttons and Amazon links, and calculator opens, for the private Stats screen.
// No cookies, nothing personal: just "this thing was tapped".
(function () {
  if (!navigator.sendBeacon) return;
  function send(k, r) {
    try { navigator.sendBeacon('/t', new URLSearchParams({ k: k, r: r })); } catch (e) {}
  }
  var AMAZON = /(^|\.)(amazon\.[a-z.]+|amzn\.to|a\.co)$/i;
  document.addEventListener('click', function (e) {
    var a = e.target.closest && e.target.closest('a[href]');
    if (!a) return;
    var t = a.getAttribute('data-t');
    if (t) { var i = t.indexOf(':'); send(t.slice(0, i), t.slice(i + 1)); return; }
    if (AMAZON.test(a.hostname)) send('amazon', a.href);
  }, true);
  var app = document.body && document.body.getAttribute('data-app');
  if (app) send('app', app);
})();

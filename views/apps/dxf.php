<main class="form-app">
  <label class="file-btn">
    <span>Choose a DXF file</span>
    <input type="file" id="dxf-file" accept=".dxf,application/dxf,image/vnd.dxf">
  </label>
  <p class="note" id="dxf-name">Your file stays on your phone. Nothing is uploaded.</p>
  <section class="fields">
    <?= app_field('tol', 'Join tolerance', 'num', '0.001') ?>
    <?= app_field('gap', 'Close gaps up to', 'num', '0.03') ?>
  </section>
  <p class="note" id="dxf-units">Same units as your drawing.</p>

  <div id="dxf-result" hidden>
    <section class="outs" aria-live="polite">
      <?= app_out('closed', 'Closed shapes', true) ?>
      <?= app_out('open', 'Still open') ?>
      <?= app_out('dupes', 'Duplicates removed') ?>
      <?= app_out('bridged', 'Gaps closed') ?>
      <?= app_out('zero', 'Tiny bits removed') ?>
      <?= app_out('circles', 'Circles') ?>
      <p class="note" data-out="notes"></p>
    </section>
    <div class="dxf-preview" id="dxf-preview"></div>
    <p class="note"><span class="lg"></span> closed &nbsp; <span class="lg bad"></span> open (red dots = loose ends)</p>
    <a class="file-btn dl" id="dxf-download" download>Download fixed DXF</a>
  </div>

  <details class="help" open><summary>How it works</summary>
    <p>Breaks every line, arc and polyline into pieces, removes duplicates and tiny bits, then joins pieces that touch into closed polylines. Ends closer than the <b>join tolerance</b> snap together; gaps up to <b>close gaps</b> get a short line. Shapes only join on the same layer.</p>
    <p>Reads LINE, ARC, CIRCLE, LWPOLYLINE, POLYLINE, SPLINE, ELLIPSE. Text and blocks are left out (explode blocks first). Saves a plain R12 DXF that VCarve, Aspire, Fusion, Carbide Create and LightBurn read.</p>
    <p>Inches: 0.001 and 0.03. Millimeters: 0.02 and 0.8. Set automatically when the file says its units.</p>
  </details>
</main>

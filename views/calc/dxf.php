<section class="page calc">
  <?= calc_head('dxf') ?>
  <form class="calc-form" id="dxf-form" autocomplete="off">
    <label class="calc-file">
      <span class="btn">Choose a DXF file</span>
      <input type="file" name="file" accept=".dxf,application/dxf,image/vnd.dxf">
    </label>
    <p class="calc-filename" data-out="filename">Your file stays on your device. Nothing is uploaded.</p>
    <div class="calc-row">
      <label class="calc-field">Join tolerance <input name="tol" inputmode="decimal" value="0.001"></label>
      <label class="calc-field">Close gaps up to <input name="gap" inputmode="decimal" value="0.03"></label>
    </div>
    <p class="calc-units" data-out="units">Same units as your drawing.</p>
    <button class="btn" type="submit" disabled>Fix it</button>
  </form>

  <div class="dxf-result" data-out="result" hidden>
    <div class="calc-out" aria-live="polite">
      <div class="calc-big"><span>Closed shapes</span><b data-out="closed"></b></div>
      <div><span>Still open</span><b data-out="open"></b></div>
      <div><span>Duplicates removed</span><b data-out="dupes"></b></div>
      <div><span>Gaps closed</span><b data-out="bridged"></b></div>
      <div><span>Tiny bits removed</span><b data-out="zero"></b></div>
      <div><span>Circles</span><b data-out="circles"></b></div>
    </div>
    <p class="calc-check" data-out="notes"></p>
    <div class="dxf-preview" data-out="preview"></div>
    <p class="calc-legend"><span class="lg ok"></span> closed &nbsp; <span class="lg bad"></span> open (red dots = loose ends)</p>
    <a class="btn btn-wide" data-out="download" download>Download fixed DXF</a>
  </div>

  <div class="prose calc-help">
    <p><b>What it does:</b> breaks every line, arc and polyline into pieces, throws away duplicates and zero-length bits, then joins pieces that touch into polylines. Ends closer than the <b>join tolerance</b> are snapped together. Gaps up to <b>close gaps</b> get a short straight line so the shape closes. Shapes only join to shapes on the same layer.</p>
    <p><b>Reads:</b> LINE, ARC, CIRCLE, LWPOLYLINE, POLYLINE, SPLINE and ELLIPSE (splines and ellipses become fine polylines). Text, blocks and dimensions are left out and listed, so explode blocks first. <b>Writes:</b> a plain R12 DXF that VCarve, Aspire, Fusion, Carbide Create, LightBurn and others read. Coordinates and layers stay the same.</p>
    <p><b>Tolerance tips:</b> drawing in inches: 0.001 and 0.03. In mm: 0.02 and 0.8. If a shape you expect closed stays red, raise "close gaps" a little.</p>
  </div>
</section>
<script src="<?= e(asset('js/dxf.js')) ?>" defer></script>
<script src="<?= e(asset('js/dxf-ui.js')) ?>" defer></script>

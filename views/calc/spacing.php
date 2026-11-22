<section class="page calc">
  <?= calc_head('spacing') ?>
  <form class="calc-form" data-calc="spacing" autocomplete="off">
    <label class="calc-field">Total space (inside to inside) <input name="length" inputmode="text" value="30 1/2"></label>
    <div class="calc-row">
      <label class="calc-field">How many items <input name="count" inputmode="numeric" value="3"></label>
      <label class="calc-field">Item thickness <input name="thickness" inputmode="text" value="3/4"></label>
    </div>
    <label class="calc-field">Ends
      <select name="ends">
        <option value="gap">Equal space at both ends (shelves, dividers)</option>
        <option value="flush">First &amp; last item at the ends (posts)</option>
      </select>
    </label>
    <?= calc_precision() ?>
  </form>
  <div class="calc-out" aria-live="polite">
    <div class="calc-big"><span>Space between</span><b data-out="gap"></b></div>
    <div><span>On center</span><b data-out="oc"></b></div>
  </div>
  <h2 class="rule-title">Marks from the left / bottom</h2>
  <ol class="calc-marks" data-out="marks"></ol>
  <div class="prose calc-help"><p>Item thickness 0 gives you evenly spaced points (holes, screws). "Edge" is where the item starts; "center" is its middle.</p></div>
</section>
<?= calc_script() ?>

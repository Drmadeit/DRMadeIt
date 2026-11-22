<section class="page calc">
  <?= calc_head('crown') ?>
  <form class="calc-form" data-calc="crown" autocomplete="off">
    <label class="calc-field">Wall corner angle (°)
      <input name="wall" inputmode="decimal" value="90">
    </label>
    <label class="calc-field">Spring angle
      <select name="spring">
        <option value="38">38° (most common)</option>
        <option value="45">45°</option>
        <option value="52">52°</option>
        <option value="custom">Other…</option>
      </select>
    </label>
    <label class="calc-field" data-custom hidden>Spring angle (°) <input name="spring_custom" inputmode="decimal" placeholder="e.g. 40"></label>
  </form>
  <div class="calc-out" aria-live="polite">
    <div class="calc-big"><span>Nested (upside down) — miter</span><b data-out="nested"></b><small>Bevel 0°</small></div>
    <div><span>Flat — miter</span><b data-out="miter"></b></div>
    <div><span>Flat — bevel</span><b data-out="bevel"></b></div>
  </div>
  <div class="prose calc-help">
    <p><b>Wall corner angle</b> is the angle between the two walls: 90° for a normal corner, 135° for a 45° bay or octagon corner, 120° for a hexagon. Use a protractor or angle finder.</p>
    <p><b>Nested:</b> put the crown upside down on the saw, ceiling edge on the table and wall edge against the fence, just like it sits on the wall (upside down). Set the miter to the number above, bevel at 0°.</p>
    <p><b>Flat:</b> lay the crown face up on the table. Set both the miter and the bevel. Good for wide crown that won't fit nested.</p>
    <p><b>Spring angle:</b> the angle between the back of the crown and the wall. If you don't know it, it's probably 38°.</p>
  </div>
</section>
<?= calc_script() ?>

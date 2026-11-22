<section class="page calc">
  <?= calc_head('board-feet') ?>
  <form class="calc-form" data-calc="board-feet" autocomplete="off">
    <div class="calc-row">
      <label class="calc-field">Thickness (in) <input name="thick" inputmode="text" value="1"></label>
      <label class="calc-field">Width (in) <input name="width" inputmode="text" value="6"></label>
    </div>
    <div class="calc-row">
      <label class="calc-field">Length <input name="length" inputmode="text" value="8"></label>
      <label class="calc-field">Length is in
        <select name="len_unit"><option value="ft">feet</option><option value="in">inches</option></select>
      </label>
    </div>
    <div class="calc-row">
      <label class="calc-field">Quantity <input name="qty" inputmode="numeric" value="1"></label>
      <label class="calc-field">Waste % <input name="waste" inputmode="decimal" value="15"></label>
    </div>
    <label class="calc-field">Price per board foot $ <small>(optional)</small> <input name="price" inputmode="decimal" placeholder="e.g. 8.50"></label>
  </form>
  <div class="calc-out" aria-live="polite">
    <div class="calc-big"><span>Board feet</span><b data-out="bf"></b></div>
    <div><span>With waste</span><b data-out="bf-waste"></b></div>
    <div><span>Cost</span><b data-out="cost"></b></div>
  </div>
  <div class="prose calc-help"><p>Board feet = thickness × width × length (all inches) ÷ 144. Rough lumber is sold by its rough thickness: 4/4 = 1", 5/4 = 1 1/4", 8/4 = 2".</p></div>
</section>
<?= calc_script() ?>

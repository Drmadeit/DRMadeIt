<section class="page calc">
  <?= calc_head('stairs') ?>
  <form class="calc-form" data-calc="stairs" autocomplete="off">
    <label class="calc-field">Total rise (finished floor to finished floor) <input name="rise" inputmode="text" value="108"></label>
    <div class="calc-row">
      <label class="calc-field">Max riser <input name="max_riser" inputmode="text" value="7 3/4"></label>
      <label class="calc-field">Tread run <input name="run" inputmode="text" value="10"></label>
    </div>
    <?= calc_precision() ?>
  </form>
  <div class="calc-out" aria-live="polite">
    <div class="calc-big"><span>Riser height</span><b data-out="riser"></b></div>
    <div><span>Risers</span><b data-out="risers"></b></div>
    <div><span>Treads</span><b data-out="treads"></b></div>
    <div><span>Total run</span><b data-out="total-run"></b></div>
    <div><span>Stringer length</span><b data-out="stringer"></b></div>
    <div><span>Angle</span><b data-out="angle"></b></div>
    <p class="calc-check" data-out="comfort"></p>
  </div>
  <div class="prose calc-help"><p>Defaults follow common US residential code (max riser 7 3/4", min tread 10"). Check your local code. Tread run is measured without the nosing. Stringer length is the minimum board length; buy longer.</p></div>
</section>
<?= calc_script() ?>

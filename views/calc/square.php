<section class="page calc">
  <?= calc_head('square') ?>
  <form class="calc-form" data-calc="square" autocomplete="off">
    <div class="calc-row">
      <label class="calc-field">Side A <input name="a" inputmode="text" value="36"></label>
      <label class="calc-field">Side B <input name="b" inputmode="text" value="48"></label>
    </div>
    <label class="calc-field">Your measured diagonal <small>(optional)</small> <input name="measured" inputmode="text" placeholder="e.g. 60 1/8"></label>
    <?= calc_precision() ?>
  </form>
  <div class="calc-out" aria-live="polite">
    <div class="calc-big"><span>Diagonal C should be</span><b data-out="c"></b></div>
    <div><span>Decimal</span><b data-out="c-dec"></b></div>
    <p class="calc-check" data-out="check"></p>
  </div>
  <svg class="calc-diagram" data-out="diagram" viewBox="0 0 320 220" role="img" aria-label="Triangle diagram"></svg>
  <div class="prose calc-help">
    <p>For a square corner, measure along one side (A) and the other side (B) and mark both. The distance between the marks should equal C. The classic version is the 3-4-5 rule: 3 ft and 4 ft gives exactly 5 ft. Bigger multiples (6-8-10, 9-12-15) are more accurate.</p>
    <p>For a box or cabinet, both diagonals should match each other, and match C.</p>
  </div>
</section>
<?= calc_script() ?>

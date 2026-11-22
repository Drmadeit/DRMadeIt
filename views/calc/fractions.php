<section class="page calc">
  <?= calc_head('fractions') ?>
  <form class="calc-form" data-calc="fractions" autocomplete="off">
    <label class="calc-field">Measurement or math
      <input name="expr" inputmode="text" value="23 5/8 + 1 3/16 - 3/4" autocapitalize="none" spellcheck="false">
    </label>
    <?= calc_precision() ?>
  </form>
  <div class="calc-out" aria-live="polite">
    <div class="calc-big"><span>Inches</span><b data-out="inches"></b></div>
    <div><span>Feet &amp; inches</span><b data-out="ftin"></b></div>
    <div><span>Decimal</span><b data-out="decimal"></b></div>
    <div><span>Metric</span><b data-out="mm"></b></div>
  </div>
  <div class="prose calc-help">
    <p>Use <b>+</b> and <b>−</b> to add and subtract lengths, <b>x</b> or <b>÷</b> with a plain number to multiply or divide (e.g. <code>96 ÷ 3</code>). It works left to right like a pocket calculator.</p>
    <p><?= e(CALC_INPUT_HINT) ?>. A <b>+</b> or <b>−</b> after a result means the real number is a hair over or under the rounded fraction.</p>
  </div>
</section>
<?= calc_script() ?>

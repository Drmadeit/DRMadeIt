<main class="form-app">
  <section class="outs" aria-live="polite">
    <?= app_out('bf', 'Board feet', true) ?>
    <?= app_out('bf-waste', 'With waste') ?>
    <?= app_out('cost', 'Cost') ?>
  </section>
  <section class="fields">
    <?= app_field('thick', 'Thickness', 'len', '1', 'in') ?>
    <?= app_field('width', 'Width', 'len', '6', 'in') ?>
    <?= app_field('length', 'Length', 'num', '8') ?>
    <?= app_choice('len_unit', 'Length is in', ['ft' => 'Feet', 'in' => 'Inches'], 'ft') ?>
    <?= app_field('qty', 'Quantity', 'num', '1') ?>
    <?= app_field('waste', 'Waste', 'num', '15', '%') ?>
    <?= app_field('price', 'Price per board foot', 'num', '', '$') ?>
  </section>
  <details class="help"><summary>How to use</summary>
    <p>Board feet = thickness × width × length (all in inches) ÷ 144. Rough lumber goes by rough thickness: 4/4 = 1", 5/4 = 1 1/4", 8/4 = 2".</p>
  </details>
</main>

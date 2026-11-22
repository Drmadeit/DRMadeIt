<main class="form-app">
  <section class="outs" aria-live="polite">
    <?= app_out('nested', 'Nested (upside down): miter · bevel 0°', true) ?>
    <?= app_out('miter', 'Flat: miter') ?>
    <?= app_out('bevel', 'Flat: bevel') ?>
  </section>
  <section class="fields">
    <?= app_field('wall', 'Wall corner angle', 'num', '90', '°') ?>
    <?= app_choice('spring', 'Spring angle', ['38' => '38°', '45' => '45°', '52' => '52°', 'other' => 'Other'], '38') ?>
    <div data-show-when="spring=other"><?= app_field('spring_custom', 'Spring angle', 'num', '40', '°') ?></div>
  </section>
  <details class="help"><summary>How to use</summary>
    <p><b>Wall corner angle:</b> 90° for a normal corner, 135° for a 45° bay corner, 120° for a hexagon.</p>
    <p><b>Nested:</b> crown upside down on the saw, ceiling edge on the table, wall edge on the fence. Set the miter, bevel at 0°.</p>
    <p><b>Flat:</b> crown face up on the table. Set both miter and bevel. Good for wide crown.</p>
    <p><b>Spring angle:</b> the angle between the back of the crown and the wall. Don't know it? It's probably 38°.</p>
  </details>
</main>

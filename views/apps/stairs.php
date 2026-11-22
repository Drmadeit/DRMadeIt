<main class="form-app">
  <section class="outs" aria-live="polite">
    <?= app_out('riser', 'Riser height', true) ?>
    <?= app_out('risers', 'Risers') ?>
    <?= app_out('treads', 'Treads') ?>
    <?= app_out('total-run', 'Total run') ?>
    <?= app_out('stringer', 'Stringer (min.)') ?>
    <?= app_out('angle', 'Angle') ?>
    <?= app_out('rule', '2 × riser + tread') ?>
    <p class="note" data-out="comfort"></p>
  </section>
  <section class="fields">
    <?= app_field('rise', 'Total rise (floor to floor)', 'len', '108') ?>
    <?= app_field('max_riser', 'Max riser', 'len', '7 3/4') ?>
    <?= app_field('run', 'Tread run', 'len', '10') ?>
  </section>
  <details class="help" open><summary>How to use</summary>
    <p>Defaults follow common US residential code: max riser 7 3/4", min tread 10" (not counting the nosing). Check your local code. Buy stringers longer than the minimum shown.</p>
  </details>
</main>

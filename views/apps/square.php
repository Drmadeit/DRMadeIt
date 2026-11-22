<main class="form-app">
  <section class="outs" aria-live="polite">
    <?= app_out('c', 'Diagonal should be', true) ?>
    <?= app_out('c-dec', 'Decimal') ?>
    <?= app_out('c-mm', 'Metric') ?>
    <p class="note" data-out="check"></p>
  </section>
  <svg class="diagram" data-out="diagram" viewBox="0 0 320 210" role="img" aria-label="Triangle diagram"></svg>
  <section class="fields">
    <?= app_field('a', 'Side A', 'len', '36') ?>
    <?= app_field('b', 'Side B', 'len', '48') ?>
    <?= app_field('measured', 'Measured diagonal', 'len', '', 'optional') ?>
  </section>
  <details class="help" open><summary>How to use</summary>
    <p>Mark Side A on one edge and Side B on the other, measuring from the corner. The distance between the marks should be the diagonal. 3-4-5 is the classic: 3 ft and 4 ft gives exactly 5 ft. Bigger is more accurate (6-8-10, 9-12-15).</p>
    <p>Type your measured diagonal to see how far off you are. For a box, both diagonals should match.</p>
  </details>
</main>

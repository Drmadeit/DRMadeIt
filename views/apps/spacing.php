<main class="form-app">
  <section class="outs" aria-live="polite">
    <?= app_out('gap', 'Space between', true) ?>
    <?= app_out('oc', 'On center') ?>
    <?= app_out('total', 'Items take up') ?>
  </section>
  <section class="fields">
    <?= app_field('length', 'Total space (inside to inside)', 'len', '30 1/2') ?>
    <?= app_field('count', 'How many items', 'num', '3') ?>
    <?= app_field('thickness', 'Item thickness', 'len', '3/4') ?>
    <?= app_choice('ends', 'Ends', ['gap' => 'Space at ends', 'flush' => 'Item at ends'], 'gap') ?>
  </section>
  <h2 class="list-title">Marks from the left / bottom</h2>
  <ol class="marks" data-out="marks"></ol>
  <details class="help"><summary>How to use</summary>
    <p><b>Space at ends:</b> shelves or dividers, with equal space above the first and below the last. <b>Item at ends:</b> posts, with the first and last item flush to the ends.</p>
    <p>Thickness 0 spaces points evenly (holes, screws). "Edge" is where the item starts, "center" is its middle.</p>
  </details>
</main>

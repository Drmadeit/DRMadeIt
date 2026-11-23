<form method="post" action="/admin/files/import" class="a-card a-form" data-busy="Importing… this can take a minute">
  <?= csrf_field() ?>
  <label>MakerWorld model links <small>(one per line)</small>
    <textarea name="links" rows="8" autocapitalize="none" spellcheck="false" placeholder="https://makerworld.com/en/models/123456-spice-rack&#10;https://makerworld.com/en/models/234567-…"></textarea></label>
  <p class="a-hint">In the MakerWorld app: open a model → Share → Copy link, then paste here. Repeat for each one. Models already in your shop are skipped.</p>
  <div class="a-seg" role="radiogroup" aria-label="Type">
    <label><input type="radio" name="type" value="3d" checked><span>3D print</span></label>
    <label><input type="radio" name="type" value="cnc"><span>CNC</span></label>
  </div>
  <button class="a-btn">Import as drafts</button>
  <p class="a-hint">Each one becomes a draft with its "Get it on MakerWorld" button. The site tries to grab the title and cover photo from MakerWorld; if MakerWorld won't share them, the title comes from the link and you add a photo.</p>
</form>

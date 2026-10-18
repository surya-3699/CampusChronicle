<?php
require_once 'app/Models/CertificateSettings.php';
$settingsModel=new CertificateSettings();$settings=$settingsModel->get($eventId);$templates=$settingsModel->templates();
?>
<section class="cc-profile-card mb-4" aria-labelledby="certificateSettingsHeading">
 <h2 id="certificateSettingsHeading">Event certificates</h2>
 <p>Mark an approved student as attended to issue their participation certificate automatically. Award first, second or third prize below to issue a separate winner certificate. Each prize is available once per event.</p>
 <form method="post" action="index.php?page=save-certificate-settings" class="cc-profile-form">
  <?= cc_csrf_field() ?><input type="hidden" name="event_id" value="<?= $eventId ?>">
  <label>College / organization<input name="organization" maxlength="150" required value="<?= htmlspecialchars($settings['organization']) ?>"></label>
  <div class="two"><label>Signatory name<input name="signatory" maxlength="150" required value="<?= htmlspecialchars($settings['signatory']) ?>"></label><label>Signatory title<input name="signatory_title" maxlength="150" required value="<?= htmlspecialchars($settings['signatory_title']) ?>"></label></div>
  <div class="two">
  <?php foreach (['participation_template_id'=>'Participation design','winner_template_id'=>'Winner design'] as $key=>$label): ?>
   <label><?= $label ?><select name="<?= $key ?>"><option value="0">Campus Chronicle — professional default</option><?php foreach ($templates as $template): ?><option value="<?= (int)$template['id'] ?>" <?= (int)$settings[$key]===(int)$template['id']?'selected':'' ?>><?= htmlspecialchars($template['name']) ?></option><?php endforeach; ?></select></label>
  <?php endforeach; ?>
  </div>
  <p class="cc-profile-help">Upload a college border or letterhead as a published certificate JPG, PNG or WEBP in Templates. Keep the centre blank for names and award details. PDF files are reference downloads and cannot be used as backgrounds. Save these settings before previewing. Issued certificates retain the design selected when they were issued.</p>
  <button type="submit" class="admin-action-btn">Save certificate settings</button>
 </form>
 <div class="cc-profile-actions mt-3">
  <a target="_blank" rel="noopener" href="index.php?page=preview-certificate&event_id=<?= $eventId ?>">Preview participation</a>
  <a target="_blank" rel="noopener" href="index.php?page=preview-certificate&event_id=<?= $eventId ?>&type=Winner">Preview winner</a>
  <a href="index.php?page=admin-templates">Upload college template</a>
 </div>
 <form method="post" action="index.php?page=backfill-certificates" class="mt-3"><?= cc_csrf_field() ?><input type="hidden" name="event_id" value="<?= $eventId ?>"><button class="action edit" type="submit">Issue missing participation certificates</button><p class="cc-profile-help">For attendance recorded before this upgrade. Existing certificates are not duplicated.</p></form>
</section>

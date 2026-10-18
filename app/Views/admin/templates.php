<?php
require_once "app/Models/Template.php";
$templates = (new Template())->all();
?>

<section class="admin-panel admin-form-panel cc-template-center">
    <div class="admin-panel__header cc-template-header">
        <div>
            <span class="admin-panel__eyebrow">Design system</span>
            <h3>Template Center</h3>
            <p>Upload and manage approved event, gallery and certificate templates from one admin-only library.</p>
        </div>
        <div class="cc-template-count" aria-label="<?= count($templates); ?> saved templates">
            <strong><?= count($templates); ?></strong>
            <span>Saved templates</span>
        </div>
    </div>

    <?php if (!empty($_SESSION['success'])): ?>
        <div class="cc-template-alert cc-template-alert--success" role="status">
            <i class="bi bi-check-circle"></i>
            <span><?= htmlspecialchars($_SESSION['success']); ?></span>
        </div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <?php if (!empty($_SESSION['error'])): ?>
        <div class="cc-template-alert cc-template-alert--error" role="alert">
            <i class="bi bi-exclamation-triangle"></i>
            <span><?= htmlspecialchars($_SESSION['error']); ?></span>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <section class="cc-template-upload" aria-labelledby="uploadTemplateTitle">
        <div class="cc-template-upload__intro">
            <span class="cc-template-upload__icon"><i class="bi bi-cloud-arrow-up"></i></span>
            <div>
                <span class="admin-panel__eyebrow">Upload template</span>
                <h4 id="uploadTemplateTitle">Add a new approved template</h4>
                <p>Choose the content type, attach the source asset, and publish it when it is ready for use.</p>
            </div>
        </div>

        <form method="post" action="index.php?page=save-template" class="admin-form-grid cc-template-form" enctype="multipart/form-data"><?= cc_csrf_field(); ?>
            <input type="hidden" name="MAX_FILE_SIZE" value="10485760">

            <label>
                Template Type
                <select name="template_type" required>
                    <option value="event">Event</option>
                    <option value="gallery">Gallery</option>
                    <option value="certificate">Certificate</option>
                </select>
            </label>

            <label>
                Template Name
                <input name="name" maxlength="180" required placeholder="e.g. College Certificate 2026">
            </label>

            <label class="cc-template-file-field">
                Template File
                <input type="file" name="template_file" required accept="image/jpeg,image/png,image/webp,application/pdf,.jpg,.jpeg,.png,.webp,.pdf">
                <small class="admin-template-help">JPG, PNG, WEBP or PDF · maximum 10 MB</small>
            </label>

            <label>
                Status
                <select name="status">
                    <option value="Draft">Draft</option>
                    <option value="Published">Published</option>
                </select>
            </label>

            <label class="cc-template-description">
                Description
                <textarea name="description" rows="3" placeholder="Usage notes, issuing department, version, print size..."></textarea>
            </label>

            <div class="cc-template-submit-wrap">
                <button class="admin-action-btn" type="submit">
                    <i class="bi bi-cloud-arrow-up"></i>
                    Upload Template
                </button>
            </div>
        </form>
    </section>

    <section class="cc-template-library" aria-labelledby="templateLibraryTitle">
        <div class="cc-template-library__head">
            <div>
                <span class="admin-panel__eyebrow">Asset library</span>
                <h4 id="templateLibraryTitle">Uploaded Templates</h4>
            </div>
        </div>

        <?php if (empty($templates)): ?>
            <div class="cc-template-empty">
                <i class="bi bi-files"></i>
                <h4>No templates uploaded yet</h4>
                <p>Use the upload section above to add your first approved template.</p>
            </div>
        <?php else: ?>
            <div class="admin-template-grid">
                <?php foreach ($templates as $t): ?>
                    <?php
                        $filePath = trim((string)($t['file_path'] ?? ''));
                        $isImage = str_starts_with((string)($t['mime_type'] ?? ''), 'image/');
                        $previewPath = $isImage
                            ? cc_asset_url($filePath, 'public/images/gallery/default-cover.svg')
                            : $filePath;
                    ?>
                    <article class="admin-template-card">
                        <div class="cc-template-card-topline">
                            <span class="admin-panel__eyebrow"><?= htmlspecialchars(strtoupper($t['template_type'])); ?></span>
                            <span class="cc-template-status cc-template-status--<?= strtolower(htmlspecialchars($t['status'])); ?>">
                                <?= htmlspecialchars($t['status']); ?>
                            </span>
                        </div>

                        <div class="admin-template-preview">
                            <?php if ($filePath !== '' && $isImage): ?>
                                <img src="<?= htmlspecialchars($previewPath); ?>" alt="<?= htmlspecialchars($t['name']); ?> preview" loading="lazy" data-fallback="public/images/gallery/default-cover.svg">
                            <?php elseif ($filePath !== ''): ?>
                                <div class="admin-template-pdf">
                                    <i class="bi bi-file-earmark-pdf"></i>
                                    <span>PDF template</span>
                                </div>
                            <?php else: ?>
                                <div class="admin-template-pdf">
                                    <i class="bi bi-file-earmark-x"></i>
                                    <span>No asset attached</span>
                                </div>
                            <?php endif; ?>
                        </div>

                        <h4><?= htmlspecialchars($t['name']); ?></h4>
                        <p><?= htmlspecialchars($t['description'] ?: 'No description provided.'); ?></p>

                        <div class="admin-template-actions">
                            <?php if ($filePath !== '' && cc_asset_exists($filePath)): ?>
                                <a class="admin-panel-link" target="_blank" rel="noopener" href="<?= htmlspecialchars($filePath); ?>">
                                    <i class="bi bi-box-arrow-up-right"></i> Open asset
                                </a>
                            <?php elseif ($filePath !== ''): ?>
                                <span class="cc-template-missing"><i class="bi bi-exclamation-circle"></i> File missing</span>
                            <?php endif; ?>

                            <form method="post" action="index.php?page=delete-template" class="cc-inline-delete" data-confirm="Delete this template and its uploaded asset?"><?= cc_csrf_field(); ?>
                                <input type="hidden" name="id" value="<?= (int)$t['id']; ?>">
                                <button class="admin-panel-link danger cc-link-button" type="submit">
                                    <i class="bi bi-trash3"></i> Delete
                                </button>
                            </form>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</section>

<style>
.cc-template-center{display:grid;gap:22px}.cc-template-header{align-items:center}.cc-template-count{min-width:140px;padding:14px 18px;border:1px solid rgba(255,255,255,.1);border-radius:16px;background:rgba(255,255,255,.035);display:flex;flex-direction:column;gap:2px}.cc-template-count strong{font-size:24px;line-height:1;color:#fff}.cc-template-count span{font-size:11px;color:rgba(255,255,255,.55)}.cc-template-alert{display:flex;align-items:center;gap:10px;padding:12px 14px;border-radius:14px;border:1px solid rgba(255,255,255,.1);font-size:13px}.cc-template-alert--success{background:rgba(34,197,94,.1)}.cc-template-alert--error{background:rgba(239,68,68,.1)}.cc-template-upload,.cc-template-library{padding:20px;border:1px solid rgba(255,255,255,.09);border-radius:20px;background:rgba(5,12,27,.46)}.cc-template-upload__intro{display:flex;gap:14px;align-items:flex-start;margin-bottom:18px}.cc-template-upload__icon{width:46px;height:46px;border-radius:14px;display:grid;place-items:center;background:rgba(255,255,255,.08);font-size:20px;flex:0 0 auto}.cc-template-upload h4,.cc-template-library h4{margin:4px 0 6px;color:#fff}.cc-template-upload p{margin:0;color:rgba(255,255,255,.55);font-size:13px}.cc-template-form{align-items:end}.cc-template-description{grid-column:span 2}.cc-template-submit-wrap{display:flex;align-items:end}.admin-template-help{display:block;margin-top:6px;color:rgba(255,255,255,.48);font-size:11px;line-height:1.4}.cc-template-library__head{display:flex;align-items:center;justify-content:space-between;margin-bottom:16px}.admin-template-preview{height:150px;margin:12px 0 16px;border-radius:14px;overflow:hidden;background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.08)}.admin-template-preview img{width:100%;height:100%;object-fit:cover;display:block}.admin-template-pdf{height:100%;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:8px;color:rgba(255,255,255,.72)}.admin-template-pdf i{font-size:36px}.cc-template-card-topline{display:flex;align-items:center;justify-content:space-between;gap:12px}.cc-template-status{font-size:10px;text-transform:uppercase;letter-spacing:.08em;padding:5px 8px;border-radius:999px;border:1px solid rgba(255,255,255,.1);color:rgba(255,255,255,.65)}.cc-template-status--published{background:rgba(34,197,94,.1)}.cc-template-status--draft{background:rgba(148,163,184,.08)}.admin-template-actions{display:flex;gap:12px;align-items:center;flex-wrap:wrap;margin-top:12px}.admin-template-actions span{font-size:11px;color:rgba(255,255,255,.5)}.admin-panel-link.danger{color:#ff9c9c}.cc-template-missing{color:#f6c36d!important}.cc-template-empty{text-align:center;padding:34px 16px;color:rgba(255,255,255,.55)}.cc-template-empty i{font-size:32px}.cc-template-empty h4{margin:10px 0 4px}.cc-template-empty p{margin:0;font-size:13px}.cc-inline-delete{display:inline;margin:0}.cc-link-button{border:0;background:none;padding:0;cursor:pointer;font:inherit}.cc-link-button:focus-visible{outline:2px solid currentColor;outline-offset:4px}@media(max-width:800px){.cc-template-header{align-items:flex-start}.cc-template-count{width:100%}.cc-template-description{grid-column:auto}.cc-template-submit-wrap{width:100%}.cc-template-submit-wrap .admin-action-btn{width:100%;justify-content:center}}
</style>

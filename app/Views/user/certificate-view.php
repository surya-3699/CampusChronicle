<?php
$isPreview=$isPreview ?? false;
$presentation=json_decode($certificate['presentation_json'] ?? '{}',true) ?: [];
$organization=$presentation['organization'] ?? 'Campus Chronicle';
$signatory=$presentation['signatory'] ?? ($certificate['faculty_name'] ?: 'Event Coordinator');
$signatoryTitle=$presentation['signatory_title'] ?? 'Organizing Committee';
$background=$presentation['background'] ?? '';
if (!$background || !cc_asset_exists($background)) $background='';
$winner=($certificate['certificate_type'] ?? '')==='Winner';
$rank=[1=>'First Prize',2=>'Second Prize',3=>'Third Prize'][(int)($certificate['prize_rank'] ?? 0)] ?? '';
$issuedOn=date('d F Y',strtotime($certificate['generated_at']));
$eventDate=!empty($certificate['event_date'])?date('d F Y',strtotime($certificate['event_date'])):'';
$verifyPath='index.php?page=verify-certificate&code='.rawurlencode($certificate['certificate_code']);
$esc=fn($s)=>htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8');
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= $winner?'Winner':'Participation' ?> Certificate — <?= $esc($certificate['event_title']) ?></title>
<style>
*{box-sizing:border-box}body{margin:0;background:#e8edef;color:#173c43;font-family:Arial,sans-serif}.toolbar{max-width:1120px;margin:24px auto;padding:0 20px;display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap}.toolbar p{margin:0;font-size:14px;line-height:1.5}.toolbar button,.toolbar a{background:#173c43;color:white;padding:12px 18px;border:0;border-radius:6px;text-decoration:none;font:600 14px Arial;cursor:pointer}.document-wrap{max-width:1160px;margin:0 auto 36px;padding:0 20px;overflow-x:auto}.certificate{position:relative;width:1120px;height:792px;background:#fffdf8;border:1px solid #cab683;overflow:hidden;isolation:isolate}.certificate:before{content:'';position:absolute;inset:22px;border:2px solid #b59651;z-index:-1}.certificate:after{content:'';position:absolute;inset:30px;border:1px solid #ddcca6;z-index:-1}.corner{position:absolute;width:160px;height:160px;background:#173c43;transform:rotate(45deg);z-index:-1}.top{left:-98px;top:-98px}.bottom{right:-98px;bottom:-98px}.template-image{position:absolute;inset:0;width:100%;height:100%;object-fit:fill;z-index:-1}.custom:before,.custom:after,.custom .corner{display:none}.certificate-inner{height:100%;padding:62px 86px 42px;display:flex;flex-direction:column;align-items:center;text-align:center}.institution{font-size:15px;letter-spacing:3px;text-transform:uppercase;font-weight:700;max-width:900px;overflow-wrap:anywhere;line-height:1.4}.rule{width:56px;height:3px;background:#b59651;margin:22px 0 18px}.eyebrow{font-size:12px;letter-spacing:4px;text-transform:uppercase;color:#917238}h1{font-family:Georgia,serif;font-size:50px;font-weight:400;line-height:1.12;margin:10px 0 20px}.intro{font-size:14px;color:#536668;margin:0 0 14px}.recipient{font-family:Georgia,serif;font-size:42px;line-height:1.12;border-bottom:1px solid #d5c59e;padding:0 24px 14px;max-width:100%;overflow-wrap:anywhere}.recipient.long{font-size:29px}.recognition{font-size:16px;line-height:1.65;margin:17px 0 9px;max-width:840px}.event{font-weight:bold;font-size:24px;line-height:1.25;max-width:100%;overflow-wrap:anywhere}.event.long{font-size:18px}.event-date{font-size:13px;color:#536668;margin:12px 0}.award{display:inline-block;color:#917238;font-size:18px;font-weight:bold;letter-spacing:1px}.signatures{display:flex;align-items:end;justify-content:space-between;width:100%;gap:30px;margin-top:auto;padding-top:18px}.signature{text-align:center;width:30%;overflow-wrap:anywhere}.signature strong{display:block;font-size:15px;border-bottom:1px solid #c5b07b;padding-bottom:9px;line-height:1.4}.signature span{display:block;font-size:11px;text-transform:uppercase;letter-spacing:1px;padding-top:8px}.seal{width:90px;height:90px;border:2px solid #b59651;outline:1px solid #ddcca6;outline-offset:5px;border-radius:50%;display:flex;align-items:center;justify-content:center;flex-direction:column;gap:5px;font-size:9px;letter-spacing:1px;color:#917238}.seal b{font:27px Georgia;color:#173c43}.reference{margin-top:27px;font-size:10px;color:#657376;letter-spacing:.5px}.reference a{color:inherit;text-decoration:none}.preview{position:absolute;inset:46% 0 auto;color:#173c4322;font:bold 90px Arial;transform:rotate(-20deg);pointer-events:none}.custom .certificate-inner{background:rgba(255,253,248,.12)}
@media(max-width:700px){.toolbar{margin:16px auto}.document-wrap{padding:0 10px}.toolbar p{font-size:13px}}
@page{size:A4 landscape;margin:0}
@media print{html,body{margin:0!important;background:white}.toolbar{display:none}.document-wrap{margin:0;padding:0;overflow:visible;max-width:none}.certificate{width:297mm;height:210mm;border:0;break-inside:avoid;print-color-adjust:exact;-webkit-print-color-adjust:exact}.certificate-inner{padding:16mm 23mm 11mm}h1{font-size:37pt}.recipient{font-size:31pt}.recipient.long{font-size:22pt}.event{font-size:18pt}.event.long{font-size:14pt}.reference{margin-top:7mm}}
</style><script src="public/js/csp-helpers.js"></script>
</head><body>
<div class="toolbar"><p><?= $isPreview?'Preview only — no certificate has been issued.':'Your certificate is ready.' ?><br>Print or save as PDF: A4 · Landscape · Scale 100% · Headers and footers off. On a phone, scroll across the certificate.</p><div><a href="<?= $isPreview?'index.php?page=admin-registrations&event_id='.(int)$certificate['event_id']:'index.php?page=certificates' ?>">Back</a> <button type="button" data-print>Print / Save PDF</button></div></div>
<main class="document-wrap"><article class="certificate<?= $background?' custom':'' ?>" aria-label="<?= $winner?'Winner':'Participation' ?> certificate">
<?php if($background): ?><img class="template-image" src="<?= $esc($background) ?>" alt="College certificate background"><?php endif; ?>
<i class="corner top"></i><i class="corner bottom"></i>
<?php if($isPreview): ?><div class="preview">PREVIEW</div><?php endif; ?>
<div class="certificate-inner">
 <div class="institution"><?= $esc($organization) ?></div><div class="rule"></div>
 <div class="eyebrow">Recognition of <?= $winner?'Excellence':'Participation' ?></div>
 <h1>Certificate of <?= $winner?'Achievement':'Participation' ?></h1>
 <p class="intro">This certificate is proudly presented to</p>
 <div class="recipient <?= mb_strlen($certificate['fullname'])>45?'long':'' ?>"><?= $esc($certificate['fullname']) ?></div>
 <p class="recognition"><?= $winner?'In recognition of outstanding performance and securing':'For active participation and confirmed attendance in' ?><?php if($winner): ?><br><span class="award"><?= $esc($rank) ?></span> in<?php endif; ?></p>
 <div class="event <?= mb_strlen($certificate['event_title'])>70?'long':'' ?>"><?= $esc($certificate['event_title']) ?></div>
 <p class="event-date"><?= $eventDate?'Held on '.$esc($eventDate):'' ?><?= !empty($certificate['venue'])?' · '.$esc($certificate['venue']):'' ?></p>
 <div class="signatures"><div class="signature"><strong><?= $esc($issuedOn) ?></strong><span>Date of issue</span></div><div class="seal"><span>CAMPUS</span><b>CC</b><span>CHRONICLE</span></div><div class="signature"><strong><?= $esc($signatory) ?></strong><span><?= $esc($signatoryTitle) ?></span></div></div>
 <div class="reference"><?= $esc($certificate['certificate_code']) ?><?php if(!$isPreview): ?> · <a href="<?= $esc($verifyPath) ?>">Verify at Campus Chronicle → Verify a Certificate</a><?php endif; ?></div>
</div></article></main></body></html>

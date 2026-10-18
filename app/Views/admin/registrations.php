<?php
require_once "app/Controllers/EventController.php";
require_once "app/Controllers/RegistrationController.php";
require_once "app/Controllers/CertificateController.php";
require_once "app/Models/Certificate.php";

$eventId = isset($_GET['event_id']) ? (int)$_GET['event_id'] : 0;

$eventController = new EventController();
$event = $eventController->getEvent($eventId);

if (!$event) {
    die("Event not found.");
}

$regController = new RegistrationController();
$statusFilter = $_GET['status'] ?? null;
$registrations = $regController->getEventRegistrations($eventId, $statusFilter ?: null);
$stats = $regController->getStats($eventId);

$certificateModel = new Certificate();
// Build a quick lookup of which users already have a certificate for this event.
$existingCerts = [];
foreach ($certificateModel->getEventCertificates($eventId) as $c) {
    $existingCerts[$c['user_id']][$c['certificate_type']] = $c;
}
?>

<div class="admin-content">

    <div class="events-header">
        <div>
            <h1>Registrations — <?= htmlspecialchars($event['title']); ?></h1>
            <p>
                <?= (int)$stats['total']; ?> total ·
                <?= (int)$stats['pending']; ?> pending ·
                <?= (int)$stats['approved']; ?> approved ·
                <?= (int)$stats['attended']; ?> attended
            </p>
        </div>

        <a href="index.php?page=admin-events" class="add-event-btn">← Back to Events</a>
    </div>

    <?php if(isset($_SESSION['success'])): ?><div class="alert alert-success"><?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div><?php endif; ?>
    <?php if(isset($_SESSION['error'])): ?><div class="alert alert-danger"><?= htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div><?php endif; ?>
    <?php include 'app/Views/admin/certificate-settings.php'; ?>
    <div class="table-toolbar reg-filter-tabs">
        <a href="index.php?page=admin-registrations&event_id=<?= $eventId ?>" class="<?= !$statusFilter ? 'active' : '' ?>">All</a>
        <a href="index.php?page=admin-registrations&event_id=<?= $eventId ?>&status=Pending" class="<?= $statusFilter === 'Pending' ? 'active' : '' ?>">Pending</a>
        <a href="index.php?page=admin-registrations&event_id=<?= $eventId ?>&status=Approved" class="<?= $statusFilter === 'Approved' ? 'active' : '' ?>">Approved</a>
        <a href="index.php?page=admin-registrations&event_id=<?= $eventId ?>&status=Attended" class="<?= $statusFilter === 'Attended' ? 'active' : '' ?>">Attended</a>
        <a href="index.php?page=admin-registrations&event_id=<?= $eventId ?>&status=Rejected" class="<?= $statusFilter === 'Rejected' ? 'active' : '' ?>">Rejected</a>
    </div>

    <div class="table-wrapper">

        <?php if (empty($registrations)): ?>

            <div class="empty-state-block">
                <h3>No registrations here yet</h3>
                <p>Once students register for this event, they'll show up in this list.</p>
            </div>

        <?php else: ?>

            <table class="premium-table">
                <thead>
                    <tr>
                        <th>Student</th>
                        <th>Email</th>
                        <th>Registered On</th>
                        <th>Status</th>
                        <th>Certificate</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($registrations as $reg): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($reg['fullname']); ?></strong></td>
                            <td><?= htmlspecialchars($reg['email']); ?></td>
                            <td><?= date('d M Y, h:i A', strtotime($reg['registration_date'])); ?></td>
                            <td>
                                <span class="status-badge status-<?= strtolower($reg['status']); ?>">
                                    <?= htmlspecialchars($reg['status']); ?>
                                </span>
                            </td>
                            <td>
                                <?php if (isset($existingCerts[$reg['user_id']]['Participation'])): ?>
                                    <a href="index.php?page=download-certificate&id=<?= (int)$existingCerts[$reg['user_id']]['Participation']['id']; ?>"
                                       target="_blank" rel="noopener" class="action view">Participation</a>
                                <?php elseif ($reg['status'] === 'Attended'): ?>
                                    <form method="POST" action="index.php?page=generate-certificate" class="inline-form"><?= cc_csrf_field(); ?>
                                        <input type="hidden" name="registration_id" value="<?= $reg['id']; ?>">
                                        <button type="submit" class="action edit">Generate</button>
                                    </form>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                                <?php if(isset($existingCerts[$reg['user_id']]['Winner'])): $winner=$existingCerts[$reg['user_id']]['Winner']; ?>
                                  <a target="_blank" rel="noopener" class="action view" href="index.php?page=download-certificate&id=<?= (int)$winner['id'] ?>"><?= [1=>'1st',2=>'2nd',3=>'3rd'][(int)$winner['prize_rank']] ?> prize</a>
                                <?php elseif($reg['status']==='Attended'): ?>
                                  <form method="post" action="index.php?page=award-certificate" class="mt-2"><?= cc_csrf_field() ?><input type="hidden" name="registration_id" value="<?= (int)$reg['id'] ?>"><label>Prize for <?= htmlspecialchars($reg['fullname']) ?><select required name="prize_rank"><option value="">Choose prize</option><option value="1">1st prize</option><option value="2">2nd prize</option><option value="3">3rd prize</option></select></label><button type="submit" class="action edit">Award winner certificate</button></form>
                                <?php endif; ?>
                            </td>
                            <td class="reg-actions">
                                <?php if ($reg['status'] === 'Pending'): ?>
                                    <a href="index.php?page=approve-registration&id=<?= $reg['id']; ?>&event_id=<?= $eventId ?>" class="action edit">Approve</a>
                                    <a href="index.php?page=reject-registration&id=<?= $reg['id']; ?>&event_id=<?= $eventId ?>"
                                       class="action delete"
                                       data-confirm="Reject this registration?">Reject</a>
                                <?php elseif ($reg['status'] === 'Approved'): ?>
                                    <a href="index.php?page=mark-attendance&id=<?= $reg['id']; ?>&event_id=<?= $eventId ?>" class="action view">Mark Attended</a>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

        <?php endif; ?>

    </div>

</div>

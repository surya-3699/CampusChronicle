<?php
if (!isLoggedIn()) {
    redirect("index.php?page=login");
}

require_once "app/Controllers/NotificationController.php";
$notificationController = new NotificationController();
$userId = $_SESSION['user_id'];
$notifications = $notificationController->getNotifications($userId);
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card shadow border-0 bg-dark text-white mb-4">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center py-3">
                    <h4 class="mb-0 fw-bold">
                        <i class="bi bi-bell-fill me-2"></i> Notifications
                    </h4>
                    <span class="badge bg-light text-primary fw-bold px-3 py-2 rounded-pill">
                        <?= count($notifications) ?> Total
                    </span>
                </div>
                <div class="card-body p-0">
                    <?php if (!empty($notifications)): ?>
                        <div class="list-group list-group-flush bg-transparent">
                            <?php foreach ($notifications as $notification): ?>
                                <div class="list-group-item bg-transparent text-white border-secondary p-4 d-flex justify-content-between align-items-start transition-hover" 
                                     style="border-bottom: 1px solid rgba(255,255,255,0.1); background: <?= $notification['is_read'] ? 'transparent' : 'rgba(13, 110, 253, 0.08)' ?>;">
                                    <div class="me-auto">
                                        <div class="d-flex align-items-center mb-1">
                                            <h5 class="fw-bold mb-0 text-info">
                                                <?= htmlspecialchars($notification['title']) ?>
                                            </h5>
                                            <?php if (!$notification['is_read']): ?>
                                                <span class="badge bg-danger ms-2 rounded-pill small" style="font-size: 0.7rem;">New</span>
                                            <?php endif; ?>
                                        </div>
                                        <p class="mb-2 text-light" style="opacity: 0.9;">
                                            <?= htmlspecialchars($notification['message']) ?>
                                        </p>
                                        <div class="text-muted small">
                                            <i class="bi bi-clock me-1"></i>
                                            <?= date("d M Y • h:i A", strtotime($notification['created_at'])) ?>
                                        </div>
                                    </div>
                                    
                                    <?php if (!empty($notification['redirect_url'])): ?>
                                        <a href="<?= htmlspecialchars($notification['redirect_url']) ?>" class="btn btn-outline-info btn-sm ms-3 mt-1">
                                            View
                                        </a>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <div class="display-1 text-muted mb-3">
                                <i class="bi bi-bell-slash"></i>
                            </div>
                            <h4 class="fw-bold text-muted">No Notifications Yet</h4>
                            <p class="text-muted">We will notify you when events or registration statuses change.</p>
                            <a href="index.php?page=events" class="btn btn-primary mt-3 px-4">
                                Browse Events
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="d-flex justify-content-between align-items-center">
                <a href="index.php?page=profile" class="btn btn-outline-light btn-sm">
                    <i class="bi bi-arrow-left me-1"></i> Back to Profile
                </a>
            </div>
        </div>
    </div>
</div>

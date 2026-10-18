<div class="admin-content">

    <div class="events-header">
        <div>
            <h1>Contact Messages</h1>
            <p>Total Messages : <?= count($contactMessages); ?></p>
        </div>
    </div>

    <?php if (empty($contactMessages)): ?>

        <div class="table-wrapper">
            <div class="empty-state-block">
                <h3>No messages yet</h3>
                <p>Messages sent through the Contact page will show up here.</p>
            </div>
        </div>

    <?php else: ?>

        <div class="contacts-layout">

            <div class="table-wrapper contacts-list">

                <?php foreach ($contactMessages as $msg): ?>

                    <a href="index.php?page=admin-contacts&id=<?= $msg['id']; ?>"
                       class="contact-row <?= ($openContact && $openContact['id'] == $msg['id']) ? 'active' : ''; ?> <?= $msg['status'] === 'New' ? 'unread' : ''; ?>">

                        <div class="contact-row-top">
                            <strong><?= htmlspecialchars($msg['name']); ?></strong>
                            <span class="status-badge status-<?= strtolower($msg['status']); ?>"><?= htmlspecialchars($msg['status']); ?></span>
                        </div>

                        <div class="contact-row-subject"><?= htmlspecialchars($msg['subject']); ?></div>

                        <div class="contact-row-time"><?= date('d M Y, h:i A', strtotime($msg['created_at'])); ?></div>

                    </a>

                <?php endforeach; ?>

            </div>

            <div class="table-wrapper contacts-detail">

                <?php if (!$openContact): ?>

                    <div class="empty-state-block">
                        <h3>Select a message</h3>
                        <p>Choose a message from the list to read the full details.</p>
                    </div>

                <?php else: ?>

                    <div class="contact-detail-header">
                        <h2><?= htmlspecialchars($openContact['subject']); ?></h2>
                        <span class="status-badge status-<?= strtolower($openContact['status']); ?>"><?= htmlspecialchars($openContact['status']); ?></span>
                    </div>

                    <p class="contact-detail-meta">
                        From <strong><?= htmlspecialchars($openContact['name']); ?></strong>
                        &lt;<a href="mailto:<?= htmlspecialchars($openContact['email']); ?>"><?= htmlspecialchars($openContact['email']); ?></a>&gt;
                        <?php if (!empty($openContact['phone'])): ?>
                            &middot; <?= htmlspecialchars($openContact['phone']); ?>
                        <?php endif; ?>
                        &middot; <?= date('d M Y, h:i A', strtotime($openContact['created_at'])); ?>
                    </p>

                    <div class="contact-detail-message">
                        <?= nl2br(htmlspecialchars($openContact['message'])); ?>
                    </div>

                    <a href="mailto:<?= htmlspecialchars($openContact['email']); ?>?subject=Re: <?= rawurlencode($openContact['subject']); ?>" class="btn">
                        Reply by Email
                    </a>

                <?php endif; ?>

            </div>

        </div>

    <?php endif; ?>

</div>

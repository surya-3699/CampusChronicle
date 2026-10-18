<div class="admin-content">

    <div class="events-header">
        <div>
            <h1>Manage Users</h1>
            <p>Total Users : <?= count($allUsers); ?></p>
        </div>
    </div>

    <div class="table-toolbar">
        <input type="text" id="userSearch" placeholder="Search by name, email or username..." class="search-box">
    </div>

    <div class="table-wrapper">

        <table class="premium-table" id="userTable">

            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Username</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Joined</th>
                    <th>Actions</th>
                </tr>
            </thead>

            <tbody>

                <?php if (!empty($allUsers)): ?>

                    <?php foreach ($allUsers as $u): ?>

                        <tr>
                            <td>#<?= (int)$u['id']; ?></td>
                            <td><strong><?= htmlspecialchars($u['fullname']); ?></strong></td>
                            <td><?= htmlspecialchars($u['username'] ?? $u['email'] ?? 'Not added'); ?></td>
                            <td><?= htmlspecialchars($u['email']); ?></td>
                            <td>
                                <span class="publish <?= $u['role'] === 'admin' ? 'yes' : 'no'; ?>">
                                    <?= ucfirst(htmlspecialchars($u['role'])); ?>
                                </span>
                            </td>
                            <td><?= date('d M Y', strtotime($u['created_at'])); ?></td>
                            <td>

                                <?php if ((int)$u['id'] === (int)$_SESSION['user_id']): ?>

                                    <span style="color:#8fa4b3;font-size:13px;">This is you</span>

                                <?php else: ?>

                                    <?php if ($u['role'] === 'admin'): ?>
                                        <a href="index.php?page=demote-user&id=<?= $u['id']; ?>" class="action edit"
                                           data-confirm="Remove admin access for this user?">Demote</a>
                                    <?php else: ?>
                                        <a href="index.php?page=promote-user&id=<?= $u['id']; ?>" class="action view"
                                           data-confirm="Make this user an admin?">Promote</a>
                                    <?php endif; ?>

                                    <a href="index.php?page=delete-user&id=<?= $u['id']; ?>" class="action delete"
                                       data-confirm="Permanently delete this user? This cannot be undone.">Delete</a>

                                <?php endif; ?>

                            </td>
                        </tr>

                    <?php endforeach; ?>

                <?php else: ?>

                    <tr>
                        <td colspan="7" style="text-align:center">No Users Found</td>
                    </tr>

                <?php endif; ?>

            </tbody>

        </table>

    </div>

</div>

<script nonce="<?= cc_nonce(); ?>">
(function () {
    var search = document.getElementById('userSearch');
    var table = document.getElementById('userTable');
    if (!search || !table) return;

    search.addEventListener('input', function () {
        var q = search.value.trim().toLowerCase();
        table.querySelectorAll('tbody tr').forEach(function (row) {
            row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
        });
    });
})();
</script>

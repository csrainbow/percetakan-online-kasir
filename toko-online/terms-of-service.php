<?php
require_once __DIR__ . '/config.php';

$pageTitle = 'Syarat & Ketentuan';
$stmt = $db->prepare("SELECT * FROM content_pages WHERE slug = ?");
$stmt->execute(['terms-of-service']);
$page = $stmt->fetch();

if (!$page) {
    $page = ['title' => 'Syarat & Ketentuan', 'content' => '<p>Halaman belum tersedia.</p>'];
}

include __DIR__ . '/includes/header.php';
?>
<div class="page-content">
    <h1><?= htmlspecialchars($page['title']) ?></h1>
    <div class="page-body">
        <?= $page['content'] ?>
    </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>

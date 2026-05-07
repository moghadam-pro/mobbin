<?php
require_once __DIR__ . '/config.php';

$projects = read_projects();
$category = $_GET['category'] ?? 'all';
$category = in_array($category, ['apps', 'sites'], true) ? $category : 'all';

$filtered = [];
foreach ($projects as $project) {
    if (!($project['is_active'] ?? false)) {
        continue;
    }
    if ($category !== 'all' && ($project['category'] ?? '') !== $category) {
        continue;
    }
    $filtered[] = update_project_media_fields($project);
}

usort($filtered, function ($a, $b) {
    return strtotime($b['created_at'] ?? '') <=> strtotime($a['created_at'] ?? '');
});
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Private Mobbin Mirror</title>
    <link rel="stylesheet" href="asset/bootstrap.css">
    <link rel="stylesheet" href="asset/style.css">
    <?php
    require_once __DIR__ . '/meta.php';
    render_meta([
        'title'       => 'Private Mobbin Mirror',
        'description' => 'A curated personal library of...',
        'url'         => BASE_URL,
    ]);
    ?>
</head>
<body>
    <div class="container">
        <nav class="navbar">
            <a class="navbar-brand" href="index.php">Private Mobbin Mirror</a>
            <div class="d-flex gap-2">
                <a class="nav-link <?php echo $category === 'apps' ? 'active' : ''; ?>" href="index.php?category=apps">Apps</a>
                <a class="nav-link <?php echo $category === 'sites' ? 'active' : ''; ?>" href="index.php?category=sites">Sites</a>
                <a class="nav-link" href="login.php">Admin</a>
            </div>
        </nav>

        <section class="hero">
            <h1>Private Mobbin Mirror</h1>
            <p>Curated internal library of app and site screenshots.</p>
        </section>

        <?php if (empty($filtered)) : ?>
            <div class="empty-state">No active projects found.</div>
        <?php else : ?>
            <div class="row" style="--bs-gutter-y: 1.5rem;">
                <?php foreach ($filtered as $project) : ?>
                    <?php
                    $folder = $project['project_folder'] ?? '';
                    $cover = $project['cover_image'] ?? null;
                    $icon = $project['icon_image'] ?? null;
                    $folderUrl = build_project_base_url($folder);
                    $coverUrl = $cover ? $folderUrl . rawurlencode($cover) : '';
                    $iconUrl = $icon ? $folderUrl . rawurlencode($icon) : '';
                    ?>
                    <div class="col-12 col-sm-6 col-lg-4">
                        <a class="card" href="view.php?id=<?php echo e($project['id'] ?? ''); ?>">
                            <div class="card-cover">
                                <?php if ($cover && file_exists(PROJECTS_DIR . '/' . $folder . '/' . $cover)) : ?>
                                    <img src="<?php echo e($coverUrl); ?>" alt="<?php echo e($project['project_name'] ?? ''); ?> cover">
                                <?php else : ?>
                                    <div class="empty-state" style="height: 100%; border: none;">No cover</div>
                                <?php endif; ?>
                            </div>
                            <div class="card-body">
                                <div class="d-flex align-items-center gap-3 mb-3">
                                    <div class="icon-chip">
                                        <?php if ($icon && file_exists(PROJECTS_DIR . '/' . $folder . '/' . $icon)) : ?>
                                            <img src="<?php echo e($iconUrl); ?>" alt="<?php echo e($project['project_name'] ?? ''); ?> icon">
                                        <?php else : ?>
                                            <span class="meta-line">—</span>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <div style="font-weight: 600;"><?php echo e($project['project_name'] ?? 'Untitled'); ?></div>
                                        <div class="meta-line"><?php echo format_date($project['created_at'] ?? null); ?></div>
                                    </div>
                                </div>
                                <div class="d-flex align-items-center justify-content-between">
                                    <span class="badge badge-<?php echo e($project['category'] ?? 'apps'); ?>">
                                        <?php echo e($project['category'] ?? 'apps'); ?>
                                    </span>
                                    <span class="meta-line"><?php echo (int)($project['files_count'] ?? 0); ?> files</span>
                                </div>
                            </div>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <script src="asset/jquery.js"></script>
    <script src="asset/script.js"></script>
</body>
</html>

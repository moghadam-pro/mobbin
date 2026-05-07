<?php
require_once __DIR__ . '/config.php';

$id = $_GET['id'] ?? '';
$projects = read_projects();
$project = find_project_by_id($projects, $id);

if (!$project || !($project['is_active'] ?? false)) {
    http_response_code(404);
    $project = null;
} else {
    $project = update_project_media_fields($project);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $project ? e($project['project_name']) : 'Not Found'; ?> - Private Mobbin Mirror</title>
    <link rel="stylesheet" href="asset/bootstrap.css">
    <link rel="stylesheet" href="asset/style.css">
    <?php
    require_once __DIR__ . '/meta.php';
    render_meta([
        'title'       => $project ? $project['project_name'] : 'Not Found',
        'description' => $project ? $project['description'] : 'Project not found or inactive.',
        'url'         => BASE_URL,
    ]);
    ?>
</head>
<body>
    <div class="container">
        <nav class="navbar">
            <a class="navbar-brand" href="index.php">Private Mobbin Mirror</a>
            <div class="d-flex gap-2">
                <a class="nav-link" href="index.php?category=apps">Apps</a>
                <a class="nav-link" href="index.php?category=sites">Sites</a>
                <a class="nav-link" href="login.php">Admin</a>
            </div>
        </nav>

        <?php if (!$project) : ?>
            <div class="empty-state" style="margin-top: 3rem;">Project not found or inactive.</div>
        <?php else : ?>
            <?php
            $folder = $project['project_folder'] ?? '';
            $folderUrl = build_project_base_url($folder);
            $cover = $project['cover_image'] ?? null;
            $icon = $project['icon_image'] ?? null;
            $coverUrl = $cover ? $folderUrl . rawurlencode($cover) : '';
            $iconUrl = $icon ? $folderUrl . rawurlencode($icon) : '';
            $screenshots = $project['_screenshots'] ?? [];
            ?>
            <section class="mt-4">
                <div class="detail-hero">
                    <div class="icon-chip" style="width: 96px; height: 96px;">
                        <?php if ($icon && file_exists(PROJECTS_DIR . '/' . $folder . '/' . $icon)) : ?>
                            <img src="<?php echo e($iconUrl); ?>" alt="<?php echo e($project['project_name']); ?> icon">
                        <?php else : ?>
                            <span class="meta-line">No icon</span>
                        <?php endif; ?>
                    </div>
                    <div>
                        <h2 style="margin: 0 0 0.4rem;"><?php echo e($project['project_name']); ?></h2>
                        <div class="d-flex gap-2 align-items-center mb-2">
                            <span class="badge badge-<?php echo e($project['category']); ?>"><?php echo e($project['category']); ?></span>
                            <span class="meta-line">Created <?php echo format_date($project['created_at'] ?? null); ?></span>
                            <span class="meta-line">Updated <?php echo format_date($project['updated_at'] ?? null); ?></span>
                            <span class="meta-line"><?php echo (int)($project['files_count'] ?? 0); ?> screenshots</span>
                        </div>
                        <div class="meta-line">Platform: <?php echo e($project['platform'] ?? '—'); ?></div>
                        <p style="margin-top: 0.7rem; color: var(--muted);">
                            <?php echo e($project['description'] ?? ''); ?>
                        </p>
                        <?php if (!empty($project['tags'])) : ?>
                            <div class="d-flex gap-2 flex-wrap mt-2">
                                <?php foreach ($project['tags'] as $tag) : ?>
                                    <span class="tag">#<?php echo e($tag); ?></span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($cover && file_exists(PROJECTS_DIR . '/' . $folder . '/' . $cover)) : ?>
                    <img class="detail-cover" src="<?php echo e($coverUrl); ?>" alt="<?php echo e($project['project_name']); ?> cover">
                <?php endif; ?>
            </section>

            <section class="mt-5">
                <h3 class="mb-3">Screenshots</h3>
                <?php if (empty($screenshots)) : ?>
                    <div class="empty-state">No screenshots available.</div>
                <?php else : ?>
                    <div class="gallery-grid">
                        <?php foreach ($screenshots as $shot) : ?>
                            <?php $shotUrl = $folderUrl . rawurlencode($shot); ?>
                            <div class="gallery-item" data-full="<?php echo e($shotUrl); ?>">
                                <img src="<?php echo e($shotUrl); ?>" alt="Screenshot">
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        <?php endif; ?>
    </div>

    <div class="lightbox" id="lightbox" aria-hidden="true">
        <div class="lightbox-content">
            <button class="lightbox-close" id="lightboxClose" aria-label="Close">×</button>
            <button class="lightbox-btn prev" id="lightboxPrev" aria-label="Previous">‹</button>
            <img id="lightboxImage" alt="Screenshot preview">
            <button class="lightbox-btn next" id="lightboxNext" aria-label="Next">›</button>
        </div>
    </div>

    <script src="asset/jquery.js"></script>
    <script src="asset/script.js"></script>
</body>
</html>

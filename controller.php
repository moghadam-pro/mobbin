<?php
require_once __DIR__ . '/config.php';
require_admin();

$projects = read_projects();
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

// Recalculate media metadata for listing
$changed = false;
foreach ($projects as &$project) {
    $media = detect_project_media($project['project_folder'] ?? '');
    $cover = $media['cover_image'];
    $icon = $media['icon_image'];
    $files = $media['files_count'];

    if (($project['cover_image'] ?? null) !== $cover) {
        $project['cover_image'] = $cover;
        $changed = true;
    }
    if (($project['icon_image'] ?? null) !== $icon) {
        $project['icon_image'] = $icon;
        $changed = true;
    }
    if (($project['files_count'] ?? 0) !== $files) {
        $project['files_count'] = $files;
        $changed = true;
    }
}
unset($project);

if ($changed) {
    write_projects($projects);
}

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';
    $id = $_POST['id'] ?? '';

    if ($action && $id) {
        foreach ($projects as $index => $project) {
            if (($project['id'] ?? '') === $id) {
                if ($action === 'toggle') {
                    $isActive = !($project['is_active'] ?? false);
                    $projects[$index]['is_active'] = $isActive;
                    $projects[$index]['status'] = $isActive ? 'active' : 'inactive';
                    $projects[$index]['updated_at'] = now_string();
                    write_projects($projects);
                    $_SESSION['flash'] = ['type' => 'success', 'message' => 'Status updated.'];
                }

                if ($action === 'delete') {
                    $folder = $project['project_folder'] ?? '';
                    $dir = safe_project_path($folder);
                    if ($dir) {
                        delete_directory($dir);
                    }
                    array_splice($projects, $index, 1);
                    write_projects($projects);
                    $_SESSION['flash'] = ['type' => 'success', 'message' => 'Project deleted.'];
                }
                break;
            }
        }
    }

    header('Location: controller.php');
    exit;
}

$search = trim($_GET['search'] ?? '');
$filterCategory = $_GET['filter_category'] ?? 'all';
$filterCategory = in_array($filterCategory, ['apps', 'sites'], true) ? $filterCategory : 'all';
$filterStatus = $_GET['filter_status'] ?? 'all';
$filterStatus = in_array($filterStatus, ['active', 'inactive'], true) ? $filterStatus : 'all';
$sort = $_GET['sort'] ?? 'newest';
$sort = in_array($sort, ['newest', 'oldest', 'alpha', 'manual'], true) ? $sort : 'newest';

$filtered = [];
foreach ($projects as $project) {
    if ($filterCategory !== 'all' && ($project['category'] ?? '') !== $filterCategory) {
        continue;
    }
    if ($filterStatus === 'active' && !($project['is_active'] ?? false)) {
        continue;
    }
    if ($filterStatus === 'inactive' && ($project['is_active'] ?? false)) {
        continue;
    }
    if ($search) {
        $haystack = strtolower(implode(' ', [
            $project['project_name'] ?? '',
            $project['project_folder'] ?? '',
            $project['description'] ?? '',
            $project['platform'] ?? '',
            is_array($project['tags'] ?? null) ? implode(' ', $project['tags']) : '',
        ]));
        if (strpos($haystack, strtolower($search)) === false) {
            continue;
        }
    }
    $filtered[] = $project;
}

if ($sort === 'newest') {
    usort($filtered, fn($a, $b) => strtotime($b['created_at'] ?? '') <=> strtotime($a['created_at'] ?? ''));
} elseif ($sort === 'oldest') {
    usort($filtered, fn($a, $b) => strtotime($a['created_at'] ?? '') <=> strtotime($b['created_at'] ?? ''));
} elseif ($sort === 'alpha') {
    usort($filtered, fn($a, $b) => strcasecmp($a['project_name'] ?? '', $b['project_name'] ?? ''));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - App Screenshot Showcase</title>
    <link rel="stylesheet" href="asset/bootstrap.css">
    <link rel="stylesheet" href="asset/style.css">
</head>
<body>
    <div class="container">
        <nav class="navbar">
            <a class="navbar-brand" href="index.php">App Screenshot Showcase</a>
            <div class="d-flex gap-2">
                <a class="nav-link" href="index.php">Home</a>
                <a class="nav-link active" href="controller.php">Admin</a>
            </div>
        </nav>

        <div class="d-flex justify-content-between align-items-center mt-4">
            <div>
                <h2 class="mb-2">Projects</h2>
                <div class="meta-line">Manage screenshots and metadata.</div>
            </div>
            <a class="btn btn-primary" href="add.php">Add Project</a>
        </div>

        <?php if ($flash) : ?>
            <div class="alert alert-<?php echo e($flash['type']); ?> mt-3">
                <?php echo e($flash['message']); ?>
            </div>
        <?php endif; ?>

        <form class="toolbar mt-4" method="GET">
            <div class="row" style="--bs-gutter-y: 0.75rem;">
                <div class="col-12">
                    <input class="form-control" type="text" name="search" placeholder="Search projects" value="<?php echo e($search); ?>">
                </div>
                <div class="col-6 col-md-3">
                    <select class="form-select" name="filter_category">
                        <option value="all">All Categories</option>
                        <option value="apps" <?php echo $filterCategory === 'apps' ? 'selected' : ''; ?>>Apps</option>
                        <option value="sites" <?php echo $filterCategory === 'sites' ? 'selected' : ''; ?>>Sites</option>
                    </select>
                </div>
                <div class="col-6 col-md-3">
                    <select class="form-select" name="filter_status">
                        <option value="all">All Status</option>
                        <option value="active" <?php echo $filterStatus === 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?php echo $filterStatus === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                    </select>
                </div>
                <div class="col-6 col-md-3">
                    <select class="form-select" name="sort">
                        <option value="newest" <?php echo $sort === 'newest' ? 'selected' : ''; ?>>Newest First</option>
                        <option value="oldest" <?php echo $sort === 'oldest' ? 'selected' : ''; ?>>Oldest First</option>
                        <option value="alpha" <?php echo $sort === 'alpha' ? 'selected' : ''; ?>>Alphabetical</option>
                        <option value="manual" <?php echo $sort === 'manual' ? 'selected' : ''; ?>>Manual Order</option>
                    </select>
                </div>
                <div class="col-6 col-md-3">
                    <button class="btn btn-outline-light w-100" type="submit">Apply</button>
                </div>
            </div>
        </form>

        <div class="table-wrap mt-4">
            <?php if (empty($filtered)) : ?>
                <div class="empty-state" style="border: none;">No projects found.</div>
            <?php else : ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Project Name</th>
                            <th>Project Files URL</th>
                            <th>Files</th>
                            <th>Category</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($filtered as $project) : ?>
                            <?php
                            $folder = $project['project_folder'] ?? '';
                            $folderUrl = build_project_base_url($folder);
                            $icon = $project['icon_image'] ?? null;
                            $iconUrl = $icon ? $folderUrl . rawurlencode($icon) : '';
                            ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="icon-chip" style="width: 32px; height: 32px;">
                                            <?php if ($icon && file_exists(PROJECTS_DIR . '/' . $folder . '/' . $icon)) : ?>
                                                <img src="<?php echo e($iconUrl); ?>" alt="Icon">
                                            <?php else : ?>
                                                <span class="meta-line">—</span>
                                            <?php endif; ?>
                                        </div>
                                        <div>
                                            <div><?php echo e($project['project_name'] ?? 'Untitled'); ?></div>
                                            <div class="meta-line"><?php echo e($project['project_folder'] ?? ''); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="folder-cell d-flex align-items-center gap-2">
                                        <span><?php echo e($project['project_folder'] ?? ''); ?></span>
                                        <button class="icon-btn copy-btn" type="button" data-url="<?php echo e($folderUrl); ?>" aria-label="Copy URL">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                                        </button>
                                    </div>
                                </td>
                                <td><?php echo (int)($project['files_count'] ?? 0); ?></td>
                                <td><?php echo e($project['category'] ?? ''); ?></td>
                                <td>
                                    <?php if (!empty($project['is_active'])) : ?>
                                        <span class="badge badge-active">active</span>
                                    <?php else : ?>
                                        <span class="badge badge-inactive">inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <div class="d-flex gap-2 justify-content-end">
                                        <form method="POST">
                                            <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">
                                            <input type="hidden" name="action" value="toggle">
                                            <input type="hidden" name="id" value="<?php echo e($project['id'] ?? ''); ?>">
                                            <button class="icon-btn success" type="submit" title="Toggle Status">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
                                            </button>
                                        </form>
                                        <a class="icon-btn" href="add.php?id=<?php echo e($project['id'] ?? ''); ?>" title="Edit">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5Z"/></svg>
                                        </a>
                                        <form method="POST" onsubmit="return confirm('Delete this project and its files?');">
                                            <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?php echo e($project['id'] ?? ''); ?>">
                                            <button class="icon-btn danger" type="submit" title="Delete">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M8 6V4h8v2"/><path d="M6 6l1 14h10l1-14"/><path d="M10 11v6"/><path d="M14 11v6"/></svg>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <script src="asset/jquery.js"></script>
    <script src="asset/script.js"></script>
</body>
</html>

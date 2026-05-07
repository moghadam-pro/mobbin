<?php
require_once __DIR__ . '/config.php';
require_admin();

// AJAX: return media preview for a folder
if (isset($_GET['preview_folder'])) {
    header('Content-Type: application/json');
    $f = sanitize_folder_name($_GET['preview_folder'] ?? '');
    if ($f && is_dir(PROJECTS_DIR . '/' . $f)) {
        $media = detect_project_media($f);
        $base  = build_project_base_url($f);
        echo json_encode([
            'cover_url'   => $media['cover_image'] ? $base . rawurlencode($media['cover_image']) : null,
            'icon_url'    => $media['icon_image']  ? $base . rawurlencode($media['icon_image'])  : null,
            'files_count' => $media['files_count'],
        ]);
    } else {
        echo json_encode(['cover_url' => null, 'icon_url' => null, 'files_count' => 0]);
    }
    exit;
}

// Folders inside storage/ for autocomplete
$storageFolders = [];
if (is_dir(PROJECTS_DIR)) {
    foreach (scandir(PROJECTS_DIR) as $item) {
        if ($item[0] === '.' || !is_dir(PROJECTS_DIR . '/' . $item)) {
            continue;
        }
        $storageFolders[] = $item;
    }
}

$projects   = read_projects();
$editId     = $_GET['id'] ?? '';
$editProject = $editId ? find_project_by_id($projects, $editId) : null;
$mode       = $editProject ? 'edit' : 'add';

$errors  = [];

$values = [
    'project_name'   => $editProject['project_name']   ?? '',
    'project_folder' => $editProject['project_folder'] ?? '',
    'category'       => $editProject['category']       ?? '',
    'description'    => $editProject['description']    ?? '',
    'tags'           => is_array($editProject['tags'] ?? null) ? implode(', ', $editProject['tags']) : '',
    'platform'       => $editProject['platform']       ?? '',
    'is_active'      => $editProject ? ($editProject['is_active'] ?? true) : true,
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $values['project_name']   = trim($_POST['project_name']   ?? '');
    $values['project_folder'] = trim($_POST['project_folder'] ?? '');
    $values['category']       = $_POST['category']            ?? '';
    $values['description']    = trim($_POST['description']    ?? '');
    $values['tags']           = trim($_POST['tags']           ?? '');
    $values['platform']       = trim($_POST['platform']       ?? '');
    $values['is_active']      = isset($_POST['is_active']);

    if ($values['project_name'] === '') {
        $errors[] = 'Project name is required.';
    }
    if ($values['project_folder'] === '') {
        $errors[] = 'Project folder name is required.';
    }

    $sanitizedFolder = sanitize_folder_name($values['project_folder']);
    if ($sanitizedFolder === '' || $sanitizedFolder !== $values['project_folder']) {
        $errors[] = 'Folder name can only contain letters, numbers, dash, and underscore.';
    }

    if (!in_array($values['category'], ['apps', 'sites'], true)) {
        $errors[] = 'Category must be apps or sites.';
    }

    $dir = safe_project_path($sanitizedFolder);
    if ($dir === null) {
        $errors[] = 'Project folder not found in /storage/. Create it and add images first.';
    }

    foreach ($projects as $project) {
        if (($project['project_folder'] ?? '') === $sanitizedFolder) {
            if ($mode === 'add' || ($editProject && ($editProject['id'] ?? '') !== ($project['id'] ?? ''))) {
                $errors[] = 'Project folder name already exists.';
                break;
            }
        }
    }

    if (empty($errors)) {
        $media = detect_project_media($sanitizedFolder);
        $tags  = normalize_tags($values['tags']);

        if ($mode === 'edit' && $editProject) {
            $record = $editProject;
            $record['project_name']   = $values['project_name'];
            $record['project_folder'] = $sanitizedFolder;
            $record['category']       = $values['category'];
            $record['description']    = $values['description'];
            $record['tags']           = $tags;
            $record['platform']       = $values['platform'];
            $record['is_active']      = $values['is_active'];
            $record['status']         = $values['is_active'] ? 'active' : 'inactive';
            $record['cover_image']    = $media['cover_image'];
            $record['icon_image']     = $media['icon_image'];
            $record['files_count']    = $media['files_count'];
            $record['updated_at']     = now_string();

            foreach ($projects as $index => $project) {
                if (($project['id'] ?? '') === ($editProject['id'] ?? '')) {
                    $projects[$index] = $record;
                    break;
                }
            }
        } else {
            $record = [
                'id'             => 'proj_' . date('YmdHis') . '_' . substr(bin2hex(random_bytes(3)), 0, 6),
                'project_name'   => $values['project_name'],
                'project_folder' => $sanitizedFolder,
                'category'       => $values['category'],
                'files_count'    => $media['files_count'],
                'cover_image'    => $media['cover_image'],
                'icon_image'     => $media['icon_image'],
                'description'    => $values['description'],
                'tags'           => $tags,
                'platform'       => $values['platform'],
                'status'         => $values['is_active'] ? 'active' : 'inactive',
                'is_active'      => $values['is_active'],
                'created_at'     => now_string(),
                'updated_at'     => now_string(),
            ];
            array_unshift($projects, $record);
        }

        if (write_projects($projects)) {
            $_SESSION['flash'] = ['type' => 'success', 'message' => $mode === 'edit' ? 'Project updated.' : 'Project added.'];
            header('Location: controller.php');
            exit;
        }

        $errors[] = 'Failed to write data. Please check list.json permissions.';
    }
}

// Initial media preview (server-side for edit mode or after validation error)
$mediaPreview = null;
if ($values['project_folder'] !== '') {
    $f = sanitize_folder_name($values['project_folder']);
    if ($f && is_dir(PROJECTS_DIR . '/' . $f)) {
        $media = detect_project_media($f);
        $base  = build_project_base_url($f);
        $mediaPreview = [
            'cover_url'   => $media['cover_image'] ? $base . rawurlencode($media['cover_image']) : null,
            'icon_url'    => $media['icon_image']  ? $base . rawurlencode($media['icon_image'])  : null,
            'files_count' => $media['files_count'],
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $mode === 'edit' ? 'Edit Project' : 'Add Project'; ?> - App Screenshot Showcase</title>
    <link rel="stylesheet" href="asset/bootstrap.css">
    <link rel="stylesheet" href="asset/style.css">
</head>
<body>
    <div class="container">
        <nav class="navbar">
            <a class="navbar-brand" href="index.php">App Screenshot Showcase</a>
            <div class="d-flex gap-2">
                <a class="nav-link" href="controller.php">Admin</a>
                <a class="nav-link active" href="#"><?php echo $mode === 'edit' ? 'Edit' : 'Add'; ?></a>
            </div>
        </nav>

        <div class="d-flex justify-content-between align-items-center mt-4">
            <div>
                <h2 class="mb-1"><?php echo $mode === 'edit' ? 'Edit Project' : 'Add Project'; ?></h2>
                <div class="meta-line">Folder images must exist in /storage/ before saving.</div>
            </div>
            <a class="btn btn-outline-light" href="controller.php">Back</a>
        </div>

        <?php if (!empty($errors)) : ?>
            <div class="alert alert-danger mt-3">
                <?php foreach ($errors as $error) : ?>
                    <div><?php echo e($error); ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form class="mt-4" method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">
            <div class="row" style="--bs-gutter-y: 1rem;">

                <!-- Row 1: Name + Folder -->
                <div class="col-12 col-md-6">
                    <label class="form-label" for="project_name">Project Name *</label>
                    <input class="form-control" type="text" id="project_name" name="project_name"
                           value="<?php echo e($values['project_name']); ?>" required autocomplete="off">
                </div>
                <div class="col-12 col-md-6">
                    <label class="form-label" for="project_folder">Project Folder *</label>
                    <div class="autocomplete-wrap">
                        <input class="form-control" type="text" id="project_folder" name="project_folder"
                               value="<?php echo e($values['project_folder']); ?>" required autocomplete="off">
                        <ul class="autocomplete-list" id="folderAutocomplete" hidden></ul>
                    </div>
                </div>

                <!-- Row 2: Category + Platform + Tags + Status -->
                <div class="col-6 col-md-3">
                    <label class="form-label" for="category">Category *</label>
                    <select class="form-select" id="category" name="category" required>
                        <option value="">Select</option>
                        <option value="apps"  <?php echo $values['category'] === 'apps'  ? 'selected' : ''; ?>>Apps</option>
                        <option value="sites" <?php echo $values['category'] === 'sites' ? 'selected' : ''; ?>>Sites</option>
                    </select>
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label" for="platform">Platform</label>
                    <input class="form-control" type="text" id="platform" name="platform"
                           value="<?php echo e($values['platform']); ?>" placeholder="iOS, Android, Web…">
                </div>
                <div class="col-12 col-md-3">
                    <label class="form-label" for="tags">Tags <span class="meta-line">(comma separated)</span></label>
                    <input class="form-control" type="text" id="tags" name="tags"
                           value="<?php echo e($values['tags']); ?>" placeholder="finance, onboarding…">
                </div>
                <div class="col-12 col-md-3 d-flex align-items-end">
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" id="is_active" name="is_active"
                               <?php echo $values['is_active'] ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="is_active">Active (visible on gallery)</label>
                    </div>
                </div>

                <!-- Row 3: Description -->
                <div class="col-12">
                    <label class="form-label" for="description">Description</label>
                    <textarea class="form-control" id="description" name="description"
                              rows="4"><?php echo e($values['description']); ?></textarea>
                </div>
            </div>

            <!-- Media preview panel -->
            <div class="toolbar mt-4" id="mediaPreviewSection" <?php echo $mediaPreview ? '' : 'style="display:none;"'; ?>>
                <div class="d-flex align-items-center gap-4 flex-wrap">
                    <div class="d-flex align-items-center gap-3">
                        <div class="icon-chip" id="previewIconChip" style="width:56px;height:56px;">
                            <?php if ($mediaPreview && $mediaPreview['icon_url']) : ?>
                                <img src="<?php echo e($mediaPreview['icon_url']); ?>" alt="icon">
                            <?php else : ?>
                                <span class="meta-line">—</span>
                            <?php endif; ?>
                        </div>
                        <div>
                            <div class="meta-line" style="font-size:0.75rem;">Icon</div>
                        </div>
                    </div>
                    <div class="d-flex align-items-center gap-3">
                        <div class="preview-cover-thumb" id="previewCoverThumb">
                            <?php if ($mediaPreview && $mediaPreview['cover_url']) : ?>
                                <img src="<?php echo e($mediaPreview['cover_url']); ?>" alt="cover">
                            <?php else : ?>
                                <span class="meta-line" style="font-size:0.7rem;">No cover</span>
                            <?php endif; ?>
                        </div>
                        <div>
                            <div class="meta-line" style="font-size:0.75rem;">Cover</div>
                        </div>
                    </div>
                    <div>
                        <div style="font-weight:600;" id="previewCount"><?php echo $mediaPreview ? $mediaPreview['files_count'] : 0; ?></div>
                        <div class="meta-line" style="font-size:0.75rem;">Screenshots</div>
                    </div>
                </div>
            </div>

            <button class="btn btn-primary mt-4" type="submit">Save Project</button>
        </form>
    </div>

    <script src="asset/jquery.js"></script>
    <script src="asset/script.js"></script>
    <script>
    (function () {
        var folders = <?php echo json_encode($storageFolders); ?>;
        var input   = document.getElementById('project_folder');
        var list    = document.getElementById('folderAutocomplete');
        var focusedIndex = -1;

        function renderList(items) {
            list.innerHTML = '';
            focusedIndex = -1;
            if (!items.length) { list.hidden = true; return; }
            items.forEach(function (f) {
                var li = document.createElement('li');
                li.className = 'autocomplete-item';
                li.textContent = f;
                li.addEventListener('mousedown', function (e) {
                    e.preventDefault();
                    input.value = f;
                    list.hidden = true;
                    fetchPreview(f);
                });
                list.appendChild(li);
            });
            list.hidden = false;
        }

        function getFiltered() {
            var val = input.value.trim().toLowerCase();
            return val
                ? folders.filter(function (f) { return f.toLowerCase().includes(val); })
                : folders.slice();
        }

        input.addEventListener('focus', function () { renderList(getFiltered()); });
        input.addEventListener('input', function () { renderList(getFiltered()); });
        input.addEventListener('blur',  function () { setTimeout(function () { list.hidden = true; }, 150); });

        input.addEventListener('keydown', function (e) {
            var items = list.querySelectorAll('.autocomplete-item');
            if (!items.length) return;
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                focusedIndex = Math.min(focusedIndex + 1, items.length - 1);
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                focusedIndex = Math.max(focusedIndex - 1, 0);
            } else if (e.key === 'Enter' && focusedIndex >= 0) {
                e.preventDefault();
                var chosen = items[focusedIndex].textContent;
                input.value = chosen;
                list.hidden = true;
                fetchPreview(chosen);
                return;
            } else if (e.key === 'Escape') {
                list.hidden = true;
                return;
            }
            items.forEach(function (li, i) {
                li.classList.toggle('focused', i === focusedIndex);
            });
        });

        function setPreviewImage(container, url, alt) {
            container.innerHTML = '';
            if (url) {
                var img = document.createElement('img');
                img.src = url;
                img.alt = alt;
                container.appendChild(img);
            } else {
                var span = document.createElement('span');
                span.className = 'meta-line';
                span.textContent = '—';
                container.appendChild(span);
            }
        }

        function fetchPreview(folder) {
            $.getJSON('add.php', { preview_folder: folder }, function (data) {
                document.getElementById('mediaPreviewSection').style.display = '';
                setPreviewImage(document.getElementById('previewIconChip'), data.icon_url, 'icon');
                setPreviewImage(document.getElementById('previewCoverThumb'), data.cover_url, 'cover');
                document.getElementById('previewCount').textContent = data.files_count;
            });
        }
    })();
    </script>
</body>
</html>

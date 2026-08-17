<?php
// =============================================
// Smart Classroom — Teacher: My Classes
// =============================================
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/layout.php';
requireLogin();
if (userRole() !== 'teacher') redirect(BASE_URL . '/index.php');

$user    = currentUser();
$teacher = $user['id'];

// All classes for this teacher
$classes = $pdo->prepare("
    SELECT c.*, (SELECT COUNT(*) FROM class_members WHERE class_id = c.id) AS member_count
    FROM classes c
    WHERE c.teacher_id = ?
    ORDER BY c.created_at DESC
");
$classes->execute([$teacher]);
$classList = $classes->fetchAll();

renderHead('My Classes');
?>
<body>
<div class="app-wrapper">
<?php renderSidebar($user, 'my_classes.php'); ?>
<div class="main-content">
<?php renderTopbar('My Classes', $user, [['icon'=>'fa-plus','label'=>'New Class','onclick'=>"openModal('create-class-modal')"]]);?>

<div class="page-content animate-up">

  <!-- Page Header -->
  <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.75rem;flex-wrap:wrap;gap:1rem">
    <div>
      <h2 style="font-size:1.4rem;font-weight:800;color:var(--text-primary);letter-spacing:-0.02em;margin:0">
        <i class="fas fa-chalkboard" style="color:var(--primary);margin-right:0.5rem"></i>My Classes
      </h2>
      <p style="font-size:0.85rem;color:var(--text-muted);margin:0.25rem 0 0">
        <?= count($classList) ?> class<?= count($classList) !== 1 ? 'es' : '' ?> found
      </p>
    </div>
    <button class="btn btn-primary" onclick="openModal('create-class-modal')" style="gap:0.5rem">
      <i class="fas fa-plus"></i> New Class
    </button>
  </div>

  <!-- Search / Filter Bar -->
  <div style="display:flex;align-items:center;gap:0.75rem;margin-bottom:1.5rem;flex-wrap:wrap">
    <div style="position:relative;flex:1;min-width:200px;max-width:340px">
      <i class="fas fa-search" style="position:absolute;left:0.85rem;top:50%;transform:translateY(-50%);color:var(--text-muted);font-size:0.8rem;pointer-events:none"></i>
      <input type="text" id="class-search" placeholder="Search classes…"
        style="width:100%;height:40px;padding:0 1rem 0 2.4rem;background:var(--surface-2);border:1px solid var(--border);border-radius:var(--radius-sm);color:var(--text-primary);font-size:0.875rem;font-family:inherit;outline:none"
        oninput="filterCards(this.value)">
    </div>
    <select id="sort-select" onchange="sortCards(this.value)"
      style="height:40px;padding:0 1rem;background:var(--surface-2);border:1px solid var(--border);border-radius:var(--radius-sm);color:var(--text-primary);font-size:0.85rem;font-family:inherit;outline:none;cursor:pointer">
      <option value="newest">Newest First</option>
      <option value="oldest">Oldest First</option>
      <option value="name">Name A–Z</option>
      <option value="students">Most Students</option>
    </select>
  </div>

  <?php if (empty($classList)): ?>
    <div class="empty-state" style="margin-top:4rem">
      <div class="empty-icon"><i class="fas fa-chalkboard"></i></div>
      <div class="empty-title">No classes yet</div>
      <div class="empty-sub">Create your first class to get started!</div>
      <button class="btn btn-primary" onclick="openModal('create-class-modal')">
        <i class="fas fa-plus"></i> Create Class
      </button>
    </div>
  <?php else: ?>

  <!-- Classes Grid -->
  <div id="classes-grid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:1.25rem">
    <?php
    $colors = ['#4f46e5','#0ea5e9','#10b981','#f59e0b','#a855f7','#ef4444','#ec4899','#06b6d4'];
    foreach ($classList as $i => $cls):
      $col   = $cls['cover_color'] ?? $colors[$i % count($colors)];
      $maxSt = $cls['max_students'] ?? 40;
      $isFull = $cls['member_count'] >= $maxSt;
      $pct    = $maxSt > 0 ? min(100, round($cls['member_count'] / $maxSt * 100)) : 0;
    ?>
    <div class="class-card mc-card"
      data-name="<?= strtolower(e($cls['name'])) ?>"
      data-students="<?= $cls['member_count'] ?>"
      data-created="<?= $cls['created_at'] ?>"
      onclick="window.location='<?= BASE_URL ?>/classroom/index.php?id=<?= $cls['id'] ?>'">

      <!-- Cover -->
      <div class="class-cover" style="background:<?= !empty($cls['banner'])
        ? 'url('.BASE_URL.'/uploads/banners/'.$cls['banner'].') center/cover no-repeat'
        : 'linear-gradient(135deg,'.$col.','.$col.'99)' ?>">
        <i class="fas fa-graduation-cap class-cover-icon"></i>
        <div class="class-cover-name"><?= e($cls['name']) ?></div>
        <div class="class-cover-section"><?= e($cls['section'] ?? '') ?> · <?= e($cls['subject'] ?? '') ?></div>

        <!-- Status badge -->
        <div style="position:absolute;top:0.75rem;right:0.75rem">
          <?php if ($isFull): ?>
            <span style="background:rgba(239,68,68,0.85);color:#fff;font-size:0.65rem;font-weight:700;padding:0.2rem 0.5rem;border-radius:999px;backdrop-filter:blur(4px)">FULL</span>
          <?php else: ?>
            <span style="background:rgba(16,185,129,0.8);color:#fff;font-size:0.65rem;font-weight:700;padding:0.2rem 0.5rem;border-radius:999px;backdrop-filter:blur(4px)">ACTIVE</span>
          <?php endif; ?>
        </div>
      </div>

      <!-- Body -->
      <div class="class-body">
        <div class="class-meta">
          <i class="fas fa-users"></i>
          <span><?= $cls['member_count'] ?>/<?= $maxSt ?> students</span>
          <span style="flex:1"></span>
          <i class="fas fa-key"></i>
          <span onclick="copyText('<?= e($cls['code']) ?>','Code copied!');event.stopPropagation()"
            style="cursor:copy;color:var(--primary-light);font-weight:700;letter-spacing:0.06em">
            <?= e($cls['code']) ?>
          </span>
        </div>

        <!-- Capacity bar -->
        <div style="margin-top:0.6rem">
          <div style="height:4px;background:var(--border);border-radius:999px;overflow:hidden">
            <div style="height:100%;width:<?= $pct ?>%;background:<?= $isFull ? 'var(--danger)' : 'var(--primary)' ?>;border-radius:999px;transition:width 0.4s"></div>
          </div>
          <div style="font-size:0.72rem;color:var(--text-muted);margin-top:0.3rem"><?= $pct ?>% capacity used</div>
        </div>
      </div>

      <!-- Actions -->
      <div class="class-actions" style="display:flex;gap:0.25rem;justify-content:flex-end;padding:0.5rem 0.75rem">
        <a href="<?= BASE_URL ?>/classroom/index.php?id=<?= $cls['id'] ?>&tab=classwork" onclick="event.stopPropagation()" title="Assignments" style="width:34px;height:34px;display:flex;align-items:center;justify-content:center;border-radius:8px;color:var(--text-muted);text-decoration:none;transition:all 0.2s" onmouseover="this.style.background='var(--bg-hover)';this.style.color='var(--primary)'" onmouseout="this.style.background='';this.style.color='var(--text-muted)'"><i class="fas fa-tasks"></i></a>
        <a href="<?= BASE_URL ?>/classroom/grades.php?class_id=<?= $cls['id'] ?>" onclick="event.stopPropagation()" title="Grades" style="width:34px;height:34px;display:flex;align-items:center;justify-content:center;border-radius:8px;color:var(--text-muted);text-decoration:none;transition:all 0.2s" onmouseover="this.style.background='var(--bg-hover)';this.style.color='var(--warning)'" onmouseout="this.style.background='';this.style.color='var(--text-muted)'"><i class="fas fa-star"></i></a>
        <a href="<?= BASE_URL ?>/classroom/attendance.php?class_id=<?= $cls['id'] ?>" onclick="event.stopPropagation()" title="Attendance" style="width:34px;height:34px;display:flex;align-items:center;justify-content:center;border-radius:8px;color:var(--text-muted);text-decoration:none;transition:all 0.2s" onmouseover="this.style.background='var(--bg-hover)';this.style.color='var(--success)'" onmouseout="this.style.background='';this.style.color='var(--text-muted)'"><i class="fas fa-calendar-check"></i></a>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- No results state (hidden by default) -->
  <div id="no-results" style="display:none;text-align:center;padding:4rem 1rem;color:var(--text-muted)">
    <i class="fas fa-search" style="font-size:2rem;margin-bottom:0.75rem;display:block;opacity:0.4"></i>
    No classes match your search.
  </div>

  <?php endif; ?>

</div><!-- end page-content -->
</div><!-- end main-content -->
</div><!-- end app-wrapper -->

<!-- Create Class Modal -->
<div class="modal-overlay" id="create-class-modal">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-title"><i class="fas fa-chalkboard" style="color:var(--primary)"></i> Create New Class</div>
      <button class="modal-close">✕</button>
    </div>
    <form id="create-class-form" method="POST" action="<?= BASE_URL ?>/api/classes.php">
      <input type="hidden" name="action" value="create">
      <div class="modal-body" style="display:flex;flex-direction:column;gap:1rem">
        <div class="form-group">
          <label class="form-label">Class Name *</label>
          <input type="text" name="name" class="form-control" placeholder="e.g., Advanced Web Development" required>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem">
          <div class="form-group">
            <label class="form-label">Section</label>
            <input type="text" name="section" class="form-control" placeholder="Section A">
          </div>
          <div class="form-group">
            <label class="form-label">Room</label>
            <input type="text" name="room" class="form-control" placeholder="Room 301">
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Subject</label>
          <input type="text" name="subject" class="form-control" placeholder="e.g., CSE 479">
        </div>
        <div class="form-group">
          <label class="form-label">Description</label>
          <textarea name="description" class="form-control" placeholder="Brief class description..." rows="3" data-maxlength="300"></textarea>
        </div>
        <div class="form-group">
          <label class="form-label">Class Color Theme</label>
          <div style="display:flex;gap:0.5rem;flex-wrap:wrap">
            <?php foreach (['#4f46e5','#0ea5e9','#10b981','#f59e0b','#a855f7','#ef4444','#ec4899','#06b6d4'] as $c): ?>
            <label style="cursor:pointer">
              <input type="radio" name="cover_color" value="<?= $c ?>" style="display:none" <?= $c === '#4f46e5' ? 'checked' : '' ?>>
              <div style="width:32px;height:32px;border-radius:50%;background:<?= $c ?>"
                onclick="this.previousElementSibling.checked=true;document.querySelectorAll('[name=cover_color]+div').forEach(d=>d.style.outline='');this.style.outline='3px solid white'"></div>
            </label>
            <?php endforeach; ?>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Add Banner <span style="font-size:0.75rem;color:var(--text-muted);font-weight:400">(optional — replaces color theme)</span></label>
          <div id="banner-preview-wrap" style="display:none;margin-bottom:0.5rem;border-radius:0.6rem;overflow:hidden;height:90px;position:relative">
            <img id="banner-preview-img" src="" alt="Banner preview" style="width:100%;height:100%;object-fit:cover">
            <button type="button" onclick="clearBannerPreview()" style="position:absolute;top:6px;right:6px;background:rgba(0,0,0,0.5);border:none;color:#fff;border-radius:50%;width:24px;height:24px;cursor:pointer;font-size:0.8rem">✕</button>
          </div>
          <label style="display:flex;align-items:center;gap:0.6rem;cursor:pointer;padding:0.65rem 1rem;border:1px dashed var(--border);border-radius:0.6rem;color:var(--text-muted);font-size:0.85rem;transition:all 0.2s" onmouseover="this.style.borderColor='var(--primary)'" onmouseout="this.style.borderColor='var(--border)'">
            <i class="fas fa-image" style="color:var(--primary)"></i>
            <span id="banner-label-text">Choose banner image (JPG, PNG, WEBP)</span>
            <input type="file" name="banner" id="banner-upload-input" accept="image/png,image/jpeg,image/webp" style="display:none" onchange="previewBanner(this)">
          </label>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary modal-close">Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="fas fa-plus"></i> Create Class</button>
      </div>
    </form>
  </div>
</div>

<!-- Banner Upload Form (hidden) used by my_classes page if needed elsewhere -->
<form id="bannerUpForm" style="display:none" method="POST" enctype="multipart/form-data">
    <input type="file" id="bannerUploadFile" name="banner" accept="image/png,image/jpeg,image/webp" onchange="submitBannerForm()">
    <input type="hidden" id="bannerClassId" name="class_id">
</form>

<?php renderFooter('<script>
// Banner preview in Create Class modal
function previewBanner(input) {
    if (!input.files || !input.files[0]) return;
    const file = input.files[0];
    const reader = new FileReader();
    reader.onload = e => {
        document.getElementById("banner-preview-img").src = e.target.result;
        document.getElementById("banner-preview-wrap").style.display = "block";
        document.getElementById("banner-label-text").textContent = file.name;
    };
    reader.readAsDataURL(file);
}
function clearBannerPreview() {
    document.getElementById("banner-preview-img").src = "";
    document.getElementById("banner-preview-wrap").style.display = "none";
    document.getElementById("banner-label-text").textContent = "Choose banner image (JPG, PNG, WEBP)";
    document.getElementById("banner-upload-input").value = "";
}

// Search filter
function filterCards(q) {
    q = q.toLowerCase().trim();
    let visible = 0;
    document.querySelectorAll(".mc-card").forEach(c => {
        const match = c.dataset.name.includes(q);
        c.style.display = match ? "" : "none";
        if (match) visible++;
    });
    document.getElementById("no-results").style.display = visible === 0 ? "block" : "none";
}

// Sort
function sortCards(by) {
    const grid = document.getElementById("classes-grid");
    const cards = [...grid.querySelectorAll(".mc-card")];
    cards.sort((a, b) => {
        if (by === "name")     return a.dataset.name.localeCompare(b.dataset.name);
        if (by === "students") return b.dataset.students - a.dataset.students;
        if (by === "oldest")   return new Date(a.dataset.created) - new Date(b.dataset.created);
        return new Date(b.dataset.created) - new Date(a.dataset.created); // newest
    });
    cards.forEach(c => grid.appendChild(c));
}
</script>'); ?>
</body>
</html>

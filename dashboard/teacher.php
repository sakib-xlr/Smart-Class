<?php
// =============================================
// Smart Classroom — Teacher Dashboard
// =============================================
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/layout.php';
requireLogin();
if (userRole() !== 'teacher') redirect(BASE_URL . '/index.php');

$user    = currentUser();
$teacher = $user['id'];

// Stats
$totalClasses   = $pdo->prepare("SELECT COUNT(*) FROM classes WHERE teacher_id = ?");
$totalClasses->execute([$teacher]);
$classCount = $totalClasses->fetchColumn();

$totalStudents  = $pdo->prepare("SELECT COUNT(DISTINCT cm.user_id) FROM class_members cm JOIN classes c ON c.id=cm.class_id WHERE c.teacher_id=?");
$totalStudents->execute([$teacher]);
$studentCount = $totalStudents->fetchColumn();

$totalAssign    = $pdo->prepare("SELECT COUNT(*) FROM assignments a JOIN classes c ON c.id=a.class_id WHERE c.teacher_id=?");
$totalAssign->execute([$teacher]);
$assignCount = $totalAssign->fetchColumn();

$pendingGrade   = $pdo->prepare("SELECT COUNT(*) FROM submissions s JOIN assignments a ON a.id=s.assignment_id JOIN classes c ON c.id=a.class_id WHERE c.teacher_id=? AND s.status='submitted'");
$pendingGrade->execute([$teacher]);
$pendingCount = $pendingGrade->fetchColumn();

// Classes list
$classes = $pdo->prepare("SELECT c.*, (SELECT COUNT(*) FROM class_members WHERE class_id=c.id) as member_count FROM classes c WHERE c.teacher_id=? AND (c.status='active' OR c.status IS NULL) ORDER BY c.created_at DESC");
$classes->execute([$teacher]);
$classList = $classes->fetchAll();

// Recent submissions
$recentSubs = $pdo->prepare("
  SELECT s.*,u.name as student_name,a.title as assignment_title,c.name as class_name
  FROM submissions s
  JOIN users u ON u.id=s.student_id
  JOIN assignments a ON a.id=s.assignment_id
  JOIN classes c ON c.id=a.class_id
  WHERE c.teacher_id=? ORDER BY s.submitted_at DESC LIMIT 8
");
$recentSubs->execute([$teacher]);
$recentSubsList = $recentSubs->fetchAll();

// Upcoming assignments
$upcomingAssign = $pdo->prepare("
  SELECT a.*,c.name as class_name,(SELECT COUNT(*) FROM submissions WHERE assignment_id=a.id) as sub_count
  FROM assignments a JOIN classes c ON c.id=a.class_id
  WHERE c.teacher_id=? AND a.due_date >= NOW() ORDER BY a.due_date ASC LIMIT 5
");
$upcomingAssign->execute([$teacher]);
$upcomingList = $upcomingAssign->fetchAll();

// Analytics data for charts
$gradeData  = $pdo->prepare("SELECT c.name, COALESCE(AVG(s.grade),0) as avg_grade FROM classes c LEFT JOIN assignments a ON a.class_id=c.id LEFT JOIN submissions s ON s.assignment_id=a.id WHERE c.teacher_id=? GROUP BY c.id LIMIT 6");
$gradeData->execute([$teacher]);
$gradeRows = $gradeData->fetchAll();
$chartLabels = json_encode(array_column($gradeRows, 'name'));
$chartValues = json_encode(array_map(fn($r) => round($r['avg_grade'], 1), $gradeRows));

renderHead('Teacher Dashboard');
?>
<body>
<div class="app-wrapper">
<?php renderSidebar($user, 'teacher.php'); ?>
<div class="main-content">
<?php renderTopbar('Teacher Dashboard', $user, []); ?>

<div class="page-content animate-up">

  <!-- Welcome Banner -->
  <div class="welcome-banner" style="margin-bottom:1.5rem">
    <div class="welcome-banner-shimmer"></div>
    <div class="welcome-banner-content">
      <div>
        <h2 class="welcome-banner-title">Hey, <?= e(explode(' ',$user['name'])[0]) ?>! <i class="fas fa-graduation-cap" style="font-size:1.3rem;opacity:0.85"></i></h2>
        <p class="welcome-banner-sub">You have <strong class="welcome-banner-count"><?= $pendingCount ?></strong> submissions waiting for grading.</p>
      </div>
      <div class="welcome-banner-date">
        <div class="welcome-banner-day"><?= date('d') ?></div>
        <div class="welcome-banner-month"><?= strtoupper(date('F')) ?></div>
        <div class="welcome-banner-datelabel"><?= strtoupper(date('l')) ?></div>
      </div>
    </div>
  </div>

  <!-- Stats Row -->
  <div class="grid grid-4 gap-4 mb-4">
    <div class="stat-card" style="border-left:3px solid var(--primary)">
      <div class="stat-icon" style="background:rgba(99,102,241,0.15);color:var(--primary)"><i class="fas fa-chalkboard"></i></div>
      <div class="stat-info">
        <div class="stat-value" style="color:var(--primary)"><?= $classCount ?></div>
        <div class="stat-label">Active Classes</div>
      </div>
    </div>
    <div class="stat-card" style="border-left:3px solid var(--success)">
      <div class="stat-icon" style="background:rgba(16,185,129,0.15);color:var(--success)"><i class="fas fa-user-graduate"></i></div>
      <div class="stat-info">
        <div class="stat-value" style="color:var(--success)"><?= $studentCount ?></div>
        <div class="stat-label">Total Students</div>
      </div>
    </div>
    <div class="stat-card" style="border-left:3px solid var(--warning)">
      <div class="stat-icon" style="background:rgba(245,158,11,0.15);color:var(--warning)"><i class="fas fa-tasks"></i></div>
      <div class="stat-info">
        <div class="stat-value" style="color:var(--warning)"><?= $assignCount ?></div>
        <div class="stat-label">Assignments Created</div>
      </div>
    </div>
    <div class="stat-card" style="border-left:3px solid var(--danger)">
      <div class="stat-icon" style="background:rgba(239,68,68,0.15);color:var(--danger)"><i class="fas fa-clock"></i></div>
      <div class="stat-info">
        <div class="stat-value" style="color:var(--danger)"><?= $pendingCount ?></div>
        <div class="stat-label">Pending Grading</div>
        <?php if ($pendingCount > 0): ?><div class="stat-change text-danger">⚠ Needs attention</div><?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Dashboard Layout -->
  <div style="display:flex;flex-direction:column;gap:1.5rem" id="classes">

    <!-- My Classes — full width, 4 per row -->
    <div style="background:none;border:none;padding:0">
      <div class="card-header" style="background:none;border:none;padding:0 0 1rem 0;align-items:flex-start">
        <div class="card-title" style="font-size:1.122em">My Classes</div>
        <button class="btn btn-teal btn-sm" onclick="openModal('create-class-modal')" style="margin-top:0.45rem"><i class="fas fa-plus"></i> New Class</button>
      </div>
      <?php if (empty($classList)): ?>
        <div class="empty-state">
          <div class="empty-icon"><i class="fas fa-chalkboard"></i></div>
          <div class="empty-title">No classes yet</div>
          <div class="empty-sub">Create your first class to get started</div>
          <button class="btn btn-primary" onclick="openModal('create-class-modal')"><i class="fas fa-plus"></i> Create Class</button>
        </div>
      <?php else: ?>
      <div id="teacher-class-grid" style="display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:1rem;">
        <?php foreach ($classList as $cls):
          $colors = ['#4f46e5','#0ea5e9','#10b981','#f59e0b','#a855f7','#ef4444','#ec4899'];
          $col = $cls['cover_color'] ?? $colors[array_search($cls['id'], array_column($classList,'id')) % count($colors)];
          $maxSt = $cls['max_students'] ?? 40;
          $isFull = $cls['member_count'] >= $maxSt;
        ?>
        <div class="class-card" onclick="window.location='<?= BASE_URL ?>/classroom/index.php?id=<?= $cls['id'] ?>'">
          <div class="class-cover" style="background:<?= !empty($cls['banner']) ? 'url('.BASE_URL.'/uploads/banners/'.$cls['banner'].') center/cover no-repeat' : 'linear-gradient(135deg,'.$col.','.$col.'99)' ?>">
            <i class="fas fa-graduation-cap class-cover-icon"></i>
            <div class="class-cover-name"><?= e($cls['name']) ?></div>
            <div class="class-cover-section"><?= e($cls['section'] ?? '') ?> · <?= e($cls['subject'] ?? '') ?></div>
          </div>
          <div class="class-body">
            <div class="class-meta">
              <i class="fas fa-users"></i> <?= $cls['member_count'] ?>/<?= $maxSt ?> students
              <?php if ($isFull): ?><span class="class-full-badge" style="margin-left:0.5rem">Full</span><?php endif; ?>
              <span style="flex:1"></span>
              <i class="fas fa-key"></i>
              <span onclick="copyText('<?= e($cls['code']) ?>','Code copied!');event.stopPropagation()" style="cursor:copy;color:var(--primary-light);font-weight:600"><?= e($cls['code']) ?></span>
            </div>
          </div>
          <div class="class-actions" style="display:flex;gap:0.25rem;justify-content:flex-end;padding:0.5rem 0.75rem">
            <a href="<?= BASE_URL ?>/classroom/index.php?id=<?= $cls['id'] ?>&tab=classwork" onclick="event.stopPropagation()" title="Assignments" style="width:34px;height:34px;display:flex;align-items:center;justify-content:center;border-radius:8px;color:var(--text-muted);text-decoration:none;transition:all 0.2s" onmouseover="this.style.background='var(--bg-hover)';this.style.color='var(--primary)'" onmouseout="this.style.background='';this.style.color='var(--text-muted)'"><i class="fas fa-tasks"></i></a>
            <a href="<?= BASE_URL ?>/classroom/grades.php?class_id=<?= $cls['id'] ?>" onclick="event.stopPropagation()" title="Grades" style="width:34px;height:34px;display:flex;align-items:center;justify-content:center;border-radius:8px;color:var(--text-muted);text-decoration:none;transition:all 0.2s" onmouseover="this.style.background='var(--bg-hover)';this.style.color='var(--warning)'" onmouseout="this.style.background='';this.style.color='var(--text-muted)'"><i class="fas fa-star"></i></a>
            <a href="<?= BASE_URL ?>/classroom/attendance.php?class_id=<?= $cls['id'] ?>" onclick="event.stopPropagation()" title="Attendance" style="width:34px;height:34px;display:flex;align-items:center;justify-content:center;border-radius:8px;color:var(--text-muted);text-decoration:none;transition:all 0.2s" onmouseover="this.style.background='var(--bg-hover)';this.style.color='var(--success)'" onmouseout="this.style.background='';this.style.color='var(--text-muted)'"><i class="fas fa-calendar-check"></i></a>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>

    <!-- Bottom Row: left (chart + submissions) | right (upcoming + notifications) -->
    <div style="display:grid;grid-template-columns:1fr 360px;gap:1.5rem">

      <!-- Left Column -->
      <div style="display:flex;flex-direction:column;gap:1.5rem">

        <!-- Grade Chart -->
        <div class="card">
          <div class="card-header">
            <div class="card-title"><i class="fas fa-chart-bar" style="color:var(--warning)"></i> Class Average Grades</div>
            <a href="<?= BASE_URL ?>/analytics/performance.php" class="btn btn-ghost btn-sm">View Full Analytics</a>
          </div>
          <div class="chart-wrapper" style="height:220px">
            <canvas id="grade-chart" data-labels='<?= $chartLabels ?>' data-values='<?= $chartValues ?>'></canvas>
          </div>
        </div>

        <!-- Recent Submissions -->
        <div class="card">
          <div class="card-header">
            <div class="card-title"><i class="fas fa-inbox" style="color:var(--info)"></i> Recent Submissions</div>
            <span class="badge badge-warning"><?= $pendingCount ?> pending</span>
          </div>
          <?php if (empty($recentSubsList)): ?>
            <div class="empty-state"><div class="empty-icon"><i class="fas fa-inbox"></i></div><div class="empty-title">No submissions yet</div></div>
          <?php else: ?>
          <div class="table-wrap">
            <table>
              <thead><tr><th>Student</th><th>Assignment</th><th>Class</th><th>Status</th><th>Grade</th><th>Action</th></tr></thead>
              <tbody>
              <?php foreach ($recentSubsList as $sub): ?>
              <tr>
                <td>
                  <div style="display:flex;align-items:center;gap:0.5rem">
                    <div class="avatar" style="width:30px;height:30px;font-size:0.75rem"><?= strtoupper($sub['student_name'][0]) ?></div>
                    <span style="font-size:0.875rem;font-weight:500"><?= e($sub['student_name']) ?></span>
                  </div>
                </td>
                <td style="font-size:0.85rem"><?= e(mb_strimwidth($sub['assignment_title'],0,25,'…')) ?></td>
                <td style="font-size:0.8rem;color:var(--text-muted)"><?= e($sub['class_name']) ?></td>
                <td>
                  <?php $sc = ['submitted'=>'info','graded'=>'success','late'=>'warning','missing'=>'danger'][$sub['status']] ?? 'info'; ?>
                  <span class="badge badge-<?= $sc ?>"><?= $sub['status'] ?></span>
                </td>
                <td style="font-weight:700;color:var(--<?= $sub['grade'] >= 70 ? 'success' : ($sub['grade'] >= 50 ? 'warning' : 'danger') ?>)">
                  <?= $sub['grade'] !== null ? $sub['grade'].'%' : '—' ?>
                </td>
                <td>
                  <a href="<?= BASE_URL ?>/classroom/grades.php?sub=<?= $sub['id'] ?>" class="btn btn-primary btn-sm">
                    <?= $sub['status'] === 'submitted' ? 'Grade' : 'View' ?>
                  </a>
                </td>
              </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <?php endif; ?>
        </div>

      </div><!-- end left column -->

      <!-- Right Column -->
      <div style="display:flex;flex-direction:column;gap:1.5rem">

        <!-- Upcoming Assignments -->
        <div class="card">
          <div class="card-header">
            <div class="card-title"><i class="fas fa-clock" style="color:var(--primary)"></i> Upcoming Due</div>
          </div>
          <?php if (empty($upcomingList)): ?>
            <div class="empty-state"><div class="empty-icon"><i class="fas fa-calendar"></i></div><div class="empty-title">No upcoming</div></div>
          <?php else: ?>
          <div style="display:flex;flex-direction:column;gap:0.75rem">
            <?php foreach ($upcomingList as $a): ?>
            <div class="assignment-card" onclick="window.location='<?= BASE_URL ?>/classroom/index.php?id=<?= $a['class_id'] ?>&tab=classwork'">
              <div class="assignment-icon" style="background:rgba(99,102,241,0.15);color:var(--primary)"><i class="fas fa-file-alt"></i></div>
              <div class="assignment-info">
                <div class="assignment-title"><?= e(mb_strimwidth($a['title'],0,28,'…')) ?></div>
                <div class="assignment-meta">
                  <i class="fas fa-calendar"></i> Due: <?= date('M d', strtotime($a['due_date'])) ?>
                  <span class="badge badge-info" style="font-size:0.65rem"><?= $a['sub_count'] ?> submitted</span>
                </div>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
        </div>

      </div><!-- end right column -->

    </div><!-- end bottom row -->

  </div><!-- end dashboard layout -->

</div><!-- end page-content -->
</div><!-- end main-content -->
</div><!-- end app-wrapper -->

<!-- Create Class Modal — lives on body to avoid stacking context issues -->
<div class="modal-overlay" id="create-class-modal">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-title"><i class="fas fa-chalkboard" style="color:var(--primary)"></i> Create New Class</div>
      <button class="modal-close">✕</button>
    </div>
    <form id="create-class-form" method="POST" action="<?= BASE_URL ?>/api/classes.php" enctype="multipart/form-data">
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
              <div style="width:32px;height:32px;border-radius:50%;background:<?= $c ?>" onclick="this.previousElementSibling.checked=true;document.querySelectorAll('[name=cover_color]+div').forEach(d=>d.style.outline='');this.style.outline='3px solid white'"></div>
            </label>
            <?php endforeach; ?>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Add Banner <span style="font-size:0.75rem;color:var(--text-muted);font-weight:400">(optional — replaces color theme)</span></label>
          <div id="tc-banner-preview-wrap" style="display:none;margin-bottom:0.5rem;border-radius:0.6rem;overflow:hidden;height:90px;position:relative">
            <img id="tc-banner-preview-img" src="" alt="Banner preview" style="width:100%;height:100%;object-fit:cover">
            <button type="button" onclick="clearTCBanner()" style="position:absolute;top:6px;right:6px;background:rgba(0,0,0,0.5);border:none;color:#fff;border-radius:50%;width:24px;height:24px;cursor:pointer;font-size:0.8rem">&times;</button>
          </div>
          <label style="display:flex;align-items:center;gap:0.6rem;cursor:pointer;padding:0.65rem 1rem;border:1px dashed var(--border);border-radius:0.6rem;color:var(--text-muted);font-size:0.85rem" onmouseover="this.style.borderColor='var(--primary)'" onmouseout="this.style.borderColor='var(--border)'">
            <i class="fas fa-image" style="color:var(--primary)"></i>
            <span id="tc-banner-label">Choose banner image (JPG, PNG, WEBP)</span>
            <input type="file" name="banner" id="tc-banner-input" accept="image/png,image/jpeg,image/webp" style="display:none" onchange="previewTCBanner(this)">
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


<?php renderFooter('<script>
// Banner preview helpers for Create Class modal
function previewTCBanner(input) {
    if (!input.files || !input.files[0]) return;
    const reader = new FileReader();
    reader.onload = e => {
        document.getElementById("tc-banner-preview-img").src = e.target.result;
        document.getElementById("tc-banner-preview-wrap").style.display = "block";
        document.getElementById("tc-banner-label").textContent = input.files[0].name;
    };
    reader.readAsDataURL(input.files[0]);
}
function clearTCBanner() {
    document.getElementById("tc-banner-preview-img").src = "";
    document.getElementById("tc-banner-preview-wrap").style.display = "none";
    document.getElementById("tc-banner-label").textContent = "Choose banner image (JPG, PNG, WEBP)";
    document.getElementById("tc-banner-input").value = "";
}

// Class grid: 5 cols → 6 cols when sidebar collapses
document.addEventListener("DOMContentLoaded", function() {
    const grid    = document.getElementById("teacher-class-grid");
    const sidebar = document.querySelector(".sidebar");
    if (!grid || !sidebar) return;

    function updateCols() {
        const cols = sidebar.classList.contains("collapsed") ? 6 : 5;
        grid.style.gridTemplateColumns = "repeat(" + cols + ", minmax(0,1fr))";
    }

    // Run immediately on load
    updateCols();

    // Watch for class changes on the sidebar (catches collapse toggle instantly)
    new MutationObserver(updateCols).observe(sidebar, {
        attributes: true,
        attributeFilter: ["class"]
    });
});
</script>'); ?>
</body>
</html>

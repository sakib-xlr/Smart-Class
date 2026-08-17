<?php
// =============================================
// Smart Classroom — Teacher Analytics (By Class)
// =============================================
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/layout.php';
requireLogin();
if (userRole() !== 'teacher') redirect(BASE_URL . '/index.php');

$user = currentUser();
$uid  = $user['id'];

// All classes for this teacher
$classStmt = $pdo->prepare("SELECT * FROM classes WHERE teacher_id=? ORDER BY created_at DESC");
$classStmt->execute([$uid]);
$classes = $classStmt->fetchAll();

// Selected class (from GET param or first class)
$selectedId = (int)($_GET['class_id'] ?? ($classes[0]['id'] ?? 0));

// Find selected class data
$cls = null;
foreach ($classes as $c) {
    if ($c['id'] === $selectedId) { $cls = $c; break; }
}

// Per-class analytics
$data = null;
if ($cls) {
    $cid = $cls['id'];

    // Student count
    $sc = $pdo->prepare("SELECT COUNT(*) FROM class_members WHERE class_id=?");
    $sc->execute([$cid]);
    $studentCount = (int)$sc->fetchColumn();

    // Assignment count
    $ac = $pdo->prepare("SELECT COUNT(*) FROM assignments WHERE class_id=?");
    $ac->execute([$cid]);
    $assignCount = (int)$ac->fetchColumn();

    // Avg grade
    $sg = $pdo->prepare("SELECT AVG(grade) as avg_grade FROM submissions s JOIN assignments a ON a.id=s.assignment_id WHERE a.class_id=? AND s.grade IS NOT NULL");
    $sg->execute([$cid]);
    $avgGrade = round((float)$sg->fetchColumn(), 1);

    // Attendance rate
    $att = $pdo->prepare("SELECT COUNT(*) as total, SUM(CASE WHEN status='present' THEN 1 ELSE 0 END) as present FROM attendance WHERE class_id=?");
    $att->execute([$cid]);
    $attRow = $att->fetch();
    $attRate = $attRow['total'] > 0 ? round($attRow['present'] / $attRow['total'] * 100, 1) : null;

    // Grade distribution
    $dist = $pdo->prepare("SELECT
        SUM(CASE WHEN s.grade >= 90 THEN 1 ELSE 0 END) as a_count,
        SUM(CASE WHEN s.grade >= 75 AND s.grade < 90 THEN 1 ELSE 0 END) as b_count,
        SUM(CASE WHEN s.grade >= 60 AND s.grade < 75 THEN 1 ELSE 0 END) as c_count,
        SUM(CASE WHEN s.grade < 60 THEN 1 ELSE 0 END) as f_count
        FROM submissions s JOIN assignments a ON a.id=s.assignment_id
        WHERE a.class_id=? AND s.grade IS NOT NULL");
    $dist->execute([$cid]);
    $distRow = $dist->fetch();

    // Top students
    $top = $pdo->prepare("SELECT u.name, AVG(s.grade) as avg FROM submissions s JOIN assignments a ON a.id=s.assignment_id JOIN users u ON u.id=s.student_id WHERE a.class_id=? AND s.grade IS NOT NULL GROUP BY s.student_id ORDER BY avg DESC LIMIT 5");
    $top->execute([$cid]);
    $topStudents = $top->fetchAll();

    // Assignment performance (each assignment avg)
    $aPerf = $pdo->prepare("SELECT a.title, AVG(s.grade) as avg, COUNT(s.id) as subs FROM assignments a LEFT JOIN submissions s ON s.assignment_id=a.id AND s.grade IS NOT NULL WHERE a.class_id=? GROUP BY a.id ORDER BY a.created_at DESC LIMIT 8");
    $aPerf->execute([$cid]);
    $assignPerf = $aPerf->fetchAll();

    // Recent submissions
    $recent = $pdo->prepare("SELECT u.name as student, a.title as assignment, s.grade, s.submitted_at FROM submissions s JOIN assignments a ON a.id=s.assignment_id JOIN users u ON u.id=s.student_id WHERE a.class_id=? ORDER BY s.submitted_at DESC LIMIT 8");
    $recent->execute([$cid]);
    $recentSubs = $recent->fetchAll();

    // Student list with their avg grade & attendance
    $stuList = $pdo->prepare("SELECT u.id, u.name,
        (SELECT AVG(s2.grade) FROM submissions s2 JOIN assignments a2 ON a2.id=s2.assignment_id WHERE a2.class_id=? AND s2.student_id=u.id AND s2.grade IS NOT NULL) as avg_grade,
        (SELECT COUNT(*) FROM attendance att WHERE att.class_id=? AND att.student_id=u.id AND att.status='present') as present,
        (SELECT COUNT(*) FROM attendance att WHERE att.class_id=? AND att.student_id=u.id) as total_att
        FROM class_members cm JOIN users u ON u.id=cm.user_id WHERE cm.class_id=? ORDER BY u.name");
    $stuList->execute([$cid, $cid, $cid, $cid]);
    $studentList = $stuList->fetchAll();

    $distTotal = max(1, array_sum([$distRow['a_count'], $distRow['b_count'], $distRow['c_count'], $distRow['f_count']]));

    $data = compact('studentCount','assignCount','avgGrade','attRate','distRow','distTotal','topStudents','assignPerf','recentSubs','studentList');
}

renderHead('Analytics');
?>
<body>
<div class="app-wrapper">
<?php renderSidebar($user, 'teacher_analytics.php'); ?>
<div class="main-content">
<?php renderTopbar('Analytics', $user); ?>
<div class="page-content animate-up">

<!-- ── Page Header + Class Selector ───────────────── -->
<div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:1rem;margin-bottom:1.75rem">
  <div>
    <h2 style="font-size:1.4rem;font-weight:900;letter-spacing:-0.02em;margin:0">
      <i class="fas fa-chart-line" style="color:var(--primary)"></i> Analytics
    </h2>
    <p style="color:var(--text-muted);margin:0.25rem 0 0;font-size:0.85rem">Select a class to view its detailed analytics</p>
  </div>

  <!-- Class Dropdown -->
  <?php if (!empty($classes)): ?>
  <div style="position:relative;min-width:260px">
    <i class="fas fa-chalkboard" style="position:absolute;left:0.9rem;top:50%;transform:translateY(-50%);color:var(--primary);font-size:0.85rem;pointer-events:none;z-index:1"></i>
    <select id="class-select" onchange="window.location='<?= BASE_URL ?>/analytics/teacher_analytics.php?class_id='+this.value"
      style="width:100%;height:44px;padding:0 2.5rem 0 2.5rem;background:var(--surface-2);border:1.5px solid var(--border);border-radius:var(--radius-sm);color:var(--text-primary);font-size:0.9rem;font-family:inherit;font-weight:600;outline:none;cursor:pointer;appearance:none;transition:border-color 0.2s"
      onfocus="this.style.borderColor='var(--primary)'" onblur="this.style.borderColor='var(--border)'">
      <?php foreach ($classes as $c): ?>
        <option value="<?= $c['id'] ?>" <?= $c['id'] === $selectedId ? 'selected' : '' ?>>
          <?= e($c['name']) ?><?= $c['section'] ? ' · '.$c['section'] : '' ?>
        </option>
      <?php endforeach; ?>
    </select>
    <i class="fas fa-chevron-down" style="position:absolute;right:0.9rem;top:50%;transform:translateY(-50%);color:var(--text-muted);font-size:0.75rem;pointer-events:none"></i>
  </div>
  <?php endif; ?>
</div>

<?php if (empty($classes)): ?>
  <div class="empty-state">
    <div class="empty-icon"><i class="fas fa-chalkboard"></i></div>
    <div class="empty-title">No classes yet</div>
    <div class="empty-sub">Create a class to start seeing analytics</div>
    <a href="<?= BASE_URL ?>/dashboard/my_classes.php" class="btn btn-primary"><i class="fas fa-plus"></i> Create Class</a>
  </div>
<?php elseif (!$cls): ?>
  <div class="empty-state"><div class="empty-title">Class not found</div></div>
<?php else:
  $col = $cls['cover_color'] ?? '#4f46e5';
  $hasBanner = !empty($cls['banner']);
  $gradeColor = $data['avgGrade'] >= 75 ? 'var(--success)' : ($data['avgGrade'] >= 55 ? 'var(--warning)' : 'var(--danger)');
  $attColor = ($data['attRate'] ?? 0) >= 75 ? 'var(--success)' : (($data['attRate'] ?? 0) >= 55 ? 'var(--warning)' : 'var(--danger)');
?>

<!-- ── Class Banner ───────────────────────────────── -->
<div style="<?= $hasBanner ? 'background:url('.BASE_URL.'/uploads/banners/'.e($cls['banner']).') center/cover no-repeat' : 'background:linear-gradient(135deg,'.$col.','.$col.'99)' ?>;border-radius:var(--radius-lg);padding:1.5rem 2rem;position:relative;overflow:hidden;margin-bottom:1.5rem;min-height:100px;display:flex;align-items:flex-end">
  <?php if ($hasBanner): ?><div style="position:absolute;inset:0;background:linear-gradient(180deg,rgba(0,0,0,0.05),rgba(0,0,0,0.6))"></div><?php endif; ?>
  <div style="position:relative;z-index:1;flex:1">
    <div style="font-size:1.4rem;font-weight:900;color:#fff;letter-spacing:-0.02em"><?= e($cls['name']) ?></div>
    <div style="font-size:0.85rem;color:rgba(255,255,255,0.8);margin-top:0.3rem;display:flex;gap:1rem;flex-wrap:wrap">
      <?php if ($cls['section']): ?><span><i class="fas fa-layer-group"></i> <?= e($cls['section']) ?></span><?php endif; ?>
      <?php if ($cls['subject']): ?><span><i class="fas fa-book"></i> <?= e($cls['subject']) ?></span><?php endif; ?>
      <?php if ($cls['room']): ?><span><i class="fas fa-map-marker-alt"></i> <?= e($cls['room']) ?></span><?php endif; ?>
      <span><i class="fas fa-key"></i> <?= e($cls['code']) ?></span>
    </div>
  </div>
  <a href="<?= BASE_URL ?>/classroom/index.php?id=<?= $cls['id'] ?>" style="position:relative;z-index:1;background:rgba(255,255,255,0.18);border:1px solid rgba(255,255,255,0.35);color:#fff;text-decoration:none;padding:0.45rem 1.1rem;border-radius:8px;font-size:0.8rem;font-weight:600;backdrop-filter:blur(6px);white-space:nowrap" onmouseover="this.style.background='rgba(255,255,255,0.3)'" onmouseout="this.style.background='rgba(255,255,255,0.18)'">
    Open Class <i class="fas fa-arrow-right"></i>
  </a>
</div>

<!-- ── Stat Cards ─────────────────────────────────── -->
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(170px,1fr));gap:1rem;margin-bottom:1.5rem">
  <div class="stat-card" style="border-left:3px solid var(--primary)">
    <div class="stat-icon" style="background:rgba(99,102,241,0.15);color:var(--primary)"><i class="fas fa-users"></i></div>
    <div class="stat-info"><div class="stat-value" style="color:var(--primary)"><?= $data['studentCount'] ?></div><div class="stat-label">Students</div></div>
  </div>
  <div class="stat-card" style="border-left:3px solid var(--warning)">
    <div class="stat-icon" style="background:rgba(245,158,11,0.15);color:var(--warning)"><i class="fas fa-tasks"></i></div>
    <div class="stat-info"><div class="stat-value" style="color:var(--warning)"><?= $data['assignCount'] ?></div><div class="stat-label">Assignments</div></div>
  </div>
  <div class="stat-card" style="border-left:3px solid <?= $gradeColor ?>">
    <div class="stat-icon" style="background:rgba(16,185,129,0.15);color:<?= $gradeColor ?>"><i class="fas fa-star"></i></div>
    <div class="stat-info"><div class="stat-value" style="color:<?= $gradeColor ?>"><?= $data['avgGrade'] ? $data['avgGrade'].'%' : '—' ?></div><div class="stat-label">Avg Grade</div></div>
  </div>
  <div class="stat-card" style="border-left:3px solid <?= $attColor ?>">
    <div class="stat-icon" style="background:rgba(168,85,247,0.15);color:<?= $attColor ?>"><i class="fas fa-calendar-check"></i></div>
    <div class="stat-info"><div class="stat-value" style="color:<?= $attColor ?>"><?= $data['attRate'] !== null ? $data['attRate'].'%' : '—' ?></div><div class="stat-label">Attendance</div></div>
  </div>
</div>

<!-- ── Main Grid ──────────────────────────────────── -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;margin-bottom:1.5rem">

  <!-- Grade Distribution -->
  <div class="card">
    <div class="card-header">
      <div class="card-title"><i class="fas fa-chart-bar" style="color:var(--primary)"></i> Grade Distribution</div>
    </div>
    <?php
      $buckets = [
        ['A  (90–100)', $data['distRow']['a_count'] ?? 0, 'var(--success)'],
        ['B  (75–89)',  $data['distRow']['b_count'] ?? 0, 'var(--info)'],
        ['C  (60–74)',  $data['distRow']['c_count'] ?? 0, 'var(--warning)'],
        ['F  (< 60)',   $data['distRow']['f_count'] ?? 0, 'var(--danger)'],
      ];
      foreach ($buckets as [$lbl, $cnt, $bc]):
        $pct = round($cnt / $data['distTotal'] * 100);
    ?>
    <div style="margin-bottom:1rem">
      <div style="display:flex;justify-content:space-between;font-size:0.8rem;margin-bottom:0.35rem">
        <span style="font-weight:600;color:var(--text-secondary)"><?= $lbl ?></span>
        <span style="font-weight:800;color:<?= $bc ?>"><?= $cnt ?> <span style="color:var(--text-muted);font-weight:400">(<?= $pct ?>%)</span></span>
      </div>
      <div style="height:8px;background:var(--border);border-radius:999px;overflow:hidden">
        <div style="height:100%;width:<?= $pct ?>%;background:<?= $bc ?>;border-radius:999px;transition:width 0.7s ease"></div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- Top Students -->
  <div class="card">
    <div class="card-header">
      <div class="card-title"><i class="fas fa-trophy" style="color:var(--warning)"></i> Top Students</div>
    </div>
    <?php if (empty($data['topStudents'])): ?>
      <div class="empty-state" style="padding:1.5rem"><div class="empty-icon" style="font-size:1.5rem"><i class="fas fa-trophy"></i></div><div class="empty-sub">No graded submissions yet</div></div>
    <?php else: ?>
      <?php $medals = ['linear-gradient(135deg,#f59e0b,#fbbf24)','linear-gradient(135deg,#94a3b8,#cbd5e1)','linear-gradient(135deg,#b45309,#d97706)']; ?>
      <?php foreach ($data['topStudents'] as $i => $s): ?>
      <div style="display:flex;align-items:center;gap:0.9rem;padding:0.7rem 0;<?= $i < count($data['topStudents'])-1 ? 'border-bottom:1px solid var(--border)' : '' ?>">
        <div style="width:30px;height:30px;border-radius:50%;background:<?= $medals[$i] ?? 'var(--surface-3)' ?>;display:flex;align-items:center;justify-content:center;font-size:0.7rem;font-weight:900;color:<?= $i < 3 ? '#000' : 'var(--text-primary)' ?>;flex-shrink:0"><?= $i+1 ?></div>
        <div class="avatar" style="width:32px;height:32px;font-size:0.75rem;flex-shrink:0"><?= strtoupper($s['name'][0]) ?></div>
        <div style="flex:1;font-size:0.875rem;font-weight:600"><?= e($s['name']) ?></div>
        <div style="font-size:1rem;font-weight:900;color:var(--success)"><?= round($s['avg'],1) ?>%</div>
      </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>

<!-- ── Assignment Performance ─────────────────────── -->
<div class="card" style="margin-bottom:1.5rem">
  <div class="card-header">
    <div class="card-title"><i class="fas fa-tasks" style="color:var(--warning)"></i> Assignment Performance</div>
  </div>
  <?php if (empty($data['assignPerf'])): ?>
    <div class="empty-state" style="padding:1.5rem"><div class="empty-sub">No assignments yet</div></div>
  <?php else: ?>
  <div style="overflow-x:auto">
    <table style="width:100%;border-collapse:collapse;font-size:0.875rem">
      <thead>
        <tr style="border-bottom:1px solid var(--border)">
          <th style="text-align:left;padding:0.6rem 0.75rem;color:var(--text-muted);font-weight:600;font-size:0.75rem;text-transform:uppercase;letter-spacing:0.06em">Assignment</th>
          <th style="text-align:center;padding:0.6rem 0.75rem;color:var(--text-muted);font-weight:600;font-size:0.75rem;text-transform:uppercase;letter-spacing:0.06em">Submissions</th>
          <th style="text-align:left;padding:0.6rem 0.75rem;color:var(--text-muted);font-weight:600;font-size:0.75rem;text-transform:uppercase;letter-spacing:0.06em;width:40%">Avg Grade</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($data['assignPerf'] as $i => $ap):
          $avg = $ap['avg'] !== null ? round((float)$ap['avg'],1) : null;
          $aColor = $avg === null ? 'var(--text-muted)' : ($avg >= 75 ? 'var(--success)' : ($avg >= 55 ? 'var(--warning)' : 'var(--danger)'));
          $aPct = $avg ?? 0;
        ?>
        <tr style="border-bottom:1px solid var(--border);transition:background 0.15s" onmouseover="this.style.background='var(--surface-2)'" onmouseout="this.style.background=''">
          <td style="padding:0.75rem;font-weight:600;color:var(--text-primary)"><?= e(mb_strimwidth($ap['title'],0,40,'…')) ?></td>
          <td style="padding:0.75rem;text-align:center;color:var(--text-secondary)"><?= $ap['subs'] ?></td>
          <td style="padding:0.75rem">
            <div style="display:flex;align-items:center;gap:0.75rem">
              <div style="flex:1;height:6px;background:var(--border);border-radius:999px;overflow:hidden">
                <div style="height:100%;width:<?= $aPct ?>%;background:<?= $aColor ?>;border-radius:999px;transition:width 0.5s"></div>
              </div>
              <span style="font-weight:800;color:<?= $aColor ?>;min-width:36px;text-align:right"><?= $avg !== null ? $avg.'%' : '—' ?></span>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<!-- ── Student Table ───────────────────────────────── -->
<div class="card">
  <div class="card-header">
    <div class="card-title"><i class="fas fa-users" style="color:var(--primary)"></i> All Students</div>
    <span style="font-size:0.8rem;color:var(--text-muted)"><?= $data['studentCount'] ?> enrolled</span>
  </div>
  <?php if (empty($data['studentList'])): ?>
    <div class="empty-state" style="padding:1.5rem"><div class="empty-sub">No students enrolled yet</div></div>
  <?php else: ?>
  <div style="overflow-x:auto">
    <table style="width:100%;border-collapse:collapse;font-size:0.875rem">
      <thead>
        <tr style="border-bottom:1px solid var(--border)">
          <th style="text-align:left;padding:0.6rem 0.75rem;color:var(--text-muted);font-weight:600;font-size:0.75rem;text-transform:uppercase;letter-spacing:0.06em">Student</th>
          <th style="text-align:center;padding:0.6rem 0.75rem;color:var(--text-muted);font-weight:600;font-size:0.75rem;text-transform:uppercase;letter-spacing:0.06em">Avg Grade</th>
          <th style="text-align:center;padding:0.6rem 0.75rem;color:var(--text-muted);font-weight:600;font-size:0.75rem;text-transform:uppercase;letter-spacing:0.06em">Attendance</th>
          <th style="text-align:center;padding:0.6rem 0.75rem;color:var(--text-muted);font-weight:600;font-size:0.75rem;text-transform:uppercase;letter-spacing:0.06em">Status</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($data['studentList'] as $stu):
          $stuAvg = $stu['avg_grade'] !== null ? round((float)$stu['avg_grade'],1) : null;
          $stuAtt = $stu['total_att'] > 0 ? round($stu['present'] / $stu['total_att'] * 100, 1) : null;
          $gColor = $stuAvg === null ? 'var(--text-muted)' : ($stuAvg >= 75 ? 'var(--success)' : ($stuAvg >= 55 ? 'var(--warning)' : 'var(--danger)'));
          $aColor = $stuAtt === null ? 'var(--text-muted)' : ($stuAtt >= 75 ? 'var(--success)' : ($stuAtt >= 55 ? 'var(--warning)' : 'var(--danger)'));
          $status = ($stuAvg === null || $stuAtt === null) ? ['No Data','var(--text-muted)','var(--surface-3)'] :
                    ($stuAvg >= 75 && $stuAtt >= 75 ? ['Good','var(--success)','rgba(16,185,129,0.12)'] :
                    ($stuAvg >= 55 || $stuAtt >= 55 ? ['Average','var(--warning)','rgba(245,158,11,0.12)'] :
                    ['Needs Help','var(--danger)','rgba(239,68,68,0.12)']));
        ?>
        <tr style="border-bottom:1px solid var(--border);transition:background 0.15s" onmouseover="this.style.background='var(--surface-2)'" onmouseout="this.style.background=''">
          <td style="padding:0.75rem">
            <div style="display:flex;align-items:center;gap:0.75rem">
              <div class="avatar" style="width:32px;height:32px;font-size:0.75rem;flex-shrink:0"><?= strtoupper($stu['name'][0]) ?></div>
              <span style="font-weight:600"><?= e($stu['name']) ?></span>
            </div>
          </td>
          <td style="padding:0.75rem;text-align:center;font-weight:800;color:<?= $gColor ?>"><?= $stuAvg !== null ? $stuAvg.'%' : '—' ?></td>
          <td style="padding:0.75rem;text-align:center;font-weight:800;color:<?= $aColor ?>"><?= $stuAtt !== null ? $stuAtt.'%' : '—' ?></td>
          <td style="padding:0.75rem;text-align:center">
            <span style="background:<?= $status[2] ?>;color:<?= $status[1] ?>;font-size:0.7rem;font-weight:700;padding:0.2rem 0.65rem;border-radius:999px;letter-spacing:0.04em"><?= $status[0] ?></span>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<?php endif; ?>
</div><!-- /page-content -->
</div><!-- /main-content -->
</div><!-- /app-wrapper -->
<?php renderFooter(); ?>
</body>
</html>

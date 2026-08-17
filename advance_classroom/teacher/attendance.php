<?php
/**
 * Daily Attendance view and submit for Teacher
 */
$pageTitle = 'Mark Attendance';
require_once __DIR__ . '/../includes/header.php';
requireTeacher();

$classId = $_GET['class_id'] ?? null;
$date = $_GET['date'] ?? date('Y-m-d');

if (!$classId) {
    $stmt = $pdo->prepare("SELECT id, class_name FROM classes WHERE teacher_id = ? AND status='active'");
    $stmt->execute([$_SESSION['user_id']]);
    $classes = $stmt->fetchAll();
} else {
    requireClassOwner($pdo, $classId);
    $classes = [['id' => $classId, 'class_name' => 'Current Class']];
}

$students = [];
$attendanceMap = [];

if ($classId && $date) {
    // Get students
    $stmt = $pdo->prepare("
        SELECT u.id, u.full_name 
        FROM class_enrollments ce
        JOIN users u ON ce.student_id = u.id
        WHERE ce.class_id = ?
        ORDER BY u.full_name
    ");
    $stmt->execute([$classId]);
    $students = $stmt->fetchAll();

    // Get existing attendance
    $stmt = $pdo->prepare("SELECT student_id, status FROM attendance WHERE class_id = ? AND date = ?");
    $stmt->execute([$classId, $date]);
    foreach ($stmt->fetchAll() as $row) {
        $attendanceMap[$row['student_id']] = $row['status'];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Invalid request.');
    } else {
        $postClassId = $_POST['class_id'];
        $postDate = $_POST['date'];
        $attendanceData = $_POST['attendance'] ?? [];

        $pdo->beginTransaction();
        try {
            // Delete existing for this date/class
            $stmt = $pdo->prepare("DELETE FROM attendance WHERE class_id = ? AND date = ?");
            $stmt->execute([$postClassId, $postDate]);

            // Insert new
            $insertStmt = $pdo->prepare("INSERT INTO attendance (class_id, student_id, date, status, marked_by) VALUES (?, ?, ?, ?, ?)");
            foreach ($attendanceData as $studentId => $status) {
                $insertStmt->execute([$postClassId, $studentId, $postDate, $status, $_SESSION['user_id']]);
            }

            $pdo->commit();
            setFlash('success', 'Attendance saved successfully.');
            redirect(BASE_URL . "/teacher/attendance.php?class_id=$postClassId&date=$postDate");
        } catch (Exception $e) {
            $pdo->rollBack();
            setFlash('error', 'Failed to save attendance.');
        }
    }
}

require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="page-header">
    <div>
        <h1>Mark Attendance</h1>
        <p>Record daily presence for your students.</p>
    </div>
</div>

<div class="card mb-4" style="max-width: 800px;">
    <div class="card-body">
        <form method="GET" action="" style="display:flex;gap:16px;align-items:flex-end;flex-wrap:wrap;">
            <div class="form-group mb-0" style="flex:1;min-width:200px;">
                <label class="form-label">Select Class</label>
                <select name="class_id" class="form-control" onchange="this.form.submit()">
                    <option value="">-- Choose Class --</option>
                    <?php if (isset($classes)) foreach ($classes as $c): ?>
                        <option value="<?php echo $c['id']; ?>" <?php echo $classId == $c['id'] ? 'selected' : ''; ?>><?php echo e($c['class_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group mb-0" style="flex:1;min-width:200px;">
                <label class="form-label">Date</label>
                <input type="date" name="date" class="form-control" value="<?php echo e($date); ?>" onchange="this.form.submit()">
            </div>
        </form>
    </div>
</div>

<?php if ($classId): ?>
<div class="card" style="max-width: 800px;">
    <div class="card-header" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:0.5rem;padding:1rem 1.25rem;border-bottom:1px solid var(--border);">
        <h3 style="font-size:16px;margin:0;">Attendance List for <?php echo formatDate($date); ?></h3>
        <button type="button" class="btn btn-secondary btn-sm" onclick="openCustomStatusModal()" title="Add custom attendance status">
          <i class="fas fa-plus"></i> Custom Status
        </button>
    </div>
    
    <?php if (empty($students)): ?>
        <div class="empty-state">
            <div class="empty-icon">👥</div>
            <h3>No students enrolled</h3>
            <p>Students need to join the class before you can mark attendance.</p>
        </div>
    <?php else: ?>
        <form method="POST" action="">
            <?php echo csrfField(); ?>
            <input type="hidden" name="class_id" value="<?php echo $classId; ?>">
            <input type="hidden" name="date" value="<?php echo $date; ?>">

            <div class="table-wrapper text-left">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Student Name</th>
                            <th style="width:200px;">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($students as $stu): 
                            $status = $attendanceMap[$stu['id']] ?? 'present';
                        ?>
                        <tr>
                            <td style="font-weight:500;"><?php echo e($stu['full_name']); ?></td>
                            <td>
                                <select name="attendance[<?php echo $stu['id']; ?>]" class="form-control attendance-select" data-student-id="<?php echo $stu['id']; ?>" style="padding:6px 12px;font-size:13px;">
                                    <option value="present" <?php echo $status==='present'?'selected':'';?>>Present</option>
                                    <option value="absent" <?php echo $status==='absent'?'selected':'';?>>Absent</option>
                                    <option value="late" <?php echo $status==='late'?'selected':'';?>>Late</option>
                                    <!-- custom options injected by JS -->
                                </select>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="card-body bg-light" style="border-top:1px solid var(--border);">
                <button type="submit" class="btn btn-primary">Save Attendance</button>
            </div>
        </form>
    <?php endif; ?>
</div>
<?php endif; ?>

</main>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>

<script>
// ── Custom Status Management (Advance Classroom) ────────────────────────
const CS_KEY = 'scs_custom_statuses_<?php echo (int)($classId ?? 0); ?>';
const CS_COLORS = ['#8b5cf6','#ec4899','#06b6d4','#f97316','#84cc16','#14b8a6','#f43f5e','#a78bfa'];

function getCustomStatuses() {
  try { return JSON.parse(localStorage.getItem(CS_KEY) || '[]'); } catch(e) { return []; }
}
function saveCustomStatuses(arr) {
  localStorage.setItem(CS_KEY, JSON.stringify(arr));
}

function openCustomStatusModal() {
  renderCustomStatusList();
  document.getElementById('custom-status-modal').style.display = 'flex';
}
function closeCustomStatusModal() {
  document.getElementById('custom-status-modal').style.display = 'none';
}
function renderCustomStatusList() {
  const statuses = getCustomStatuses();
  const list = document.getElementById('cs-list');
  if (!statuses.length) {
    list.innerHTML = '<div style="text-align:center;color:var(--text-muted,#94a3b8);padding:1rem;font-size:0.875rem">No custom statuses yet. Add one below!</div>';
    return;
  }
  list.innerHTML = statuses.map((s,i) => `
    <div style="display:flex;align-items:center;gap:0.625rem;padding:0.5rem 0.75rem;border-radius:6px;background:rgba(99,102,241,0.07);margin-bottom:0.4rem">
      <span style="width:12px;height:12px;border-radius:50%;background:${s.color};flex-shrink:0"></span>
      <span style="flex:1;font-weight:500;font-size:0.9rem;text-transform:capitalize">${s.label}</span>
      <button type="button" onclick="removeCustomStatus(${i})" style="background:none;border:none;color:#ef4444;cursor:pointer;font-size:0.875rem;padding:2px 6px;border-radius:4px" title="Remove"><i class="fas fa-trash"></i></button>
    </div>
  `).join('');
}
function addCustomStatus() {
  const input = document.getElementById('cs-new-input');
  const label = input.value.trim().toLowerCase().replace(/\s+/g,'-');
  if (!label) { alert('Enter a status name'); return; }
  const statuses = getCustomStatuses();
  if (statuses.find(s => s.label === label)) { alert('Status already exists'); return; }
  const color = CS_COLORS[statuses.length % CS_COLORS.length];
  statuses.push({ label, color });
  saveCustomStatuses(statuses);
  input.value = '';
  renderCustomStatusList();
  injectCustomOptions();
}
function removeCustomStatus(idx) {
  const statuses = getCustomStatuses();
  statuses.splice(idx, 1);
  saveCustomStatuses(statuses);
  renderCustomStatusList();
  injectCustomOptions();
}

function injectCustomOptions() {
  const statuses = getCustomStatuses();
  document.querySelectorAll('select.attendance-select').forEach(sel => {
    const currentVal = sel.value;
    // Remove old custom options
    sel.querySelectorAll('option.custom-opt').forEach(o => o.remove());
    // Add new custom options
    statuses.forEach(s => {
      const opt = document.createElement('option');
      opt.value = s.label;
      opt.textContent = s.label.charAt(0).toUpperCase() + s.label.slice(1).replace(/-/g,' ');
      opt.className = 'custom-opt';
      opt.style.color = s.color;
      sel.appendChild(opt);
    });
    // Restore selected value if it was a custom one
    if ([...sel.options].some(o => o.value === currentVal)) sel.value = currentVal;
  });
}

// Run on page load
document.addEventListener('DOMContentLoaded', injectCustomOptions);
</script>

<!-- ── Custom Status Modal ────────────────────────────────────────── -->
<div id="custom-status-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.55);z-index:9999;align-items:center;justify-content:center;backdrop-filter:blur(4px)">
  <div style="background:var(--card-bg,#1e293b);border-radius:12px;padding:0;width:min(440px,95vw);box-shadow:0 24px 64px rgba(0,0,0,0.35);border:1px solid var(--border,rgba(255,255,255,0.1));overflow:hidden">
    <div style="display:flex;align-items:center;justify-content:space-between;padding:1.25rem 1.5rem;border-bottom:1px solid var(--border,rgba(255,255,255,0.1));background:linear-gradient(135deg,rgba(99,102,241,0.12),rgba(139,92,246,0.08))">
      <div>
        <div style="font-weight:700;font-size:1rem;display:flex;align-items:center;gap:0.5rem"><i class="fas fa-tag" style="color:#6366f1"></i> Custom Attendance Statuses</div>
        <div style="font-size:0.78rem;color:var(--text-muted,#94a3b8);margin-top:2px">Saved per-class in your browser</div>
      </div>
      <button onclick="closeCustomStatusModal()" style="background:none;border:none;cursor:pointer;color:var(--text-muted,#94a3b8);font-size:1.1rem;width:32px;height:32px;border-radius:50%;display:flex;align-items:center;justify-content:center" title="Close"><i class="fas fa-times"></i></button>
    </div>
    <div style="padding:1.25rem 1.5rem">
      <div id="cs-list" style="margin-bottom:1.25rem;max-height:220px;overflow-y:auto"></div>
      <div style="display:flex;gap:0.625rem">
        <input type="text" id="cs-new-input" class="form-control" placeholder="e.g. excused, field-trip, medical…" style="flex:1" onkeydown="if(event.key==='Enter'){event.preventDefault();addCustomStatus();}">
        <button type="button" class="btn btn-primary" onclick="addCustomStatus()" style="white-space:nowrap"><i class="fas fa-plus"></i> Add</button>
      </div>
      <div style="font-size:0.75rem;color:var(--text-muted,#94a3b8);margin-top:0.5rem"><i class="fas fa-info-circle"></i> Names are auto-lowercased. Stored only in this browser.</div>
    </div>
    <div style="padding:1rem 1.5rem;border-top:1px solid var(--border,rgba(255,255,255,0.1));display:flex;justify-content:flex-end">
      <button type="button" class="btn btn-secondary" onclick="closeCustomStatusModal()">Done</button>
    </div>
  </div>
</div>

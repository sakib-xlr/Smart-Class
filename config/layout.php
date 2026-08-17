<?php
// =============================================
// Smart Classroom — Shared HTML Layout Helper
// =============================================

function renderHead(string $title = 'Smart Classroom', string $extraCss = ''): void {
    $base = BASE_URL;
    echo <<<HTML
    <!DOCTYPE html>
    <html lang="en">
    <head>
      <meta charset="UTF-8">
      <meta name="viewport" content="width=device-width, initial-scale=1.0">
      <meta name="description" content="Smart Classroom System — Advanced Learning Platform">
      <title>{$title} | Smart Classroom</title>
      <link rel="preconnect" href="https://fonts.googleapis.com">
      <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
      <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
      <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
      <link rel="stylesheet" href="{$base}/assets/css/style.css">
      <script>window.BASE_URL = '{$base}';</script>
      {$extraCss}
    </head>
    HTML;
}

function renderSidebar(array $user, string $activeNav = ''): void {
    $role    = $user['role'];
    $name    = htmlspecialchars($user['name']);
    $initial = strtoupper($name[0]);
    $base    = BASE_URL;
    $cid     = $_GET['id'] ?? $_GET['class_id'] ?? null;

    // Nav items per role
    $nav = [];
    if ($role === 'teacher') {
        $nav = [
            'Main' => [
                ['icon'=>'fa-home',        'label'=>'Dashboard',    'href'=>'/dashboard/teacher.php'],
                ['icon'=>'fa-chalkboard',  'label'=>'My Classes',   'href'=>'/dashboard/my_classes.php'],
            ],
            'Academic' => [
                ['icon'=>'fa-star',          'label'=>'Grades',         'href'=>($cid ? "/classroom/grades.php?class_id={$cid}" : '/classroom/grades.php')],
                ['icon'=>'fa-calendar-check','label'=>'Attendance',     'href'=>($cid ? "/classroom/attendance.php?class_id={$cid}" : '/classroom/attendance.php')],
            ],
            'Tools' => [
                ['icon'=>'fa-video',        'label'=>'Video Meet',   'href'=>($cid ? "/classroom/video_meet.php?class_id={$cid}" : '/classroom/video_meet.php')],
                ['icon'=>'fa-chart-line',   'label'=>'Analytics',    'href'=>'/analytics/teacher_analytics.php'],
                ['icon'=>'fa-wifi-slash',   'label'=>'Offline Sub.', 'href'=>'/offline/submission.php'],
                ['icon'=>'fa-archive',      'label'=>'Archive',      'href'=>'/classroom/archive.php'],
            ],
            'Global' => [
                ['icon'=>'fa-calendar',     'label'=>'Calendar',      'href'=>'/global/calendar.php'],
                ['icon'=>'fa-comment-alt',  'label'=>'Messages',      'href'=>'/global/messages.php'],
                ['icon'=>'fa-file-export',  'label'=>'Export & Reports','href'=>'/global/export.php'],
                ['icon'=>'fa-cog',          'label'=>'Settings',      'href'=>'/global/profile.php'],
            ],
        ];
    } elseif ($role === 'student') {
        $nav = [
            'Main' => [
                ['icon'=>'fa-home',        'label'=>'Dashboard',   'href'=>'/dashboard/student.php'],
                ['icon'=>'fa-sign-in-alt', 'label'=>'Join Class',  'action'=>'openModal("join-class-modal")'],
            ],
            'Learning' => [
                ['icon'=>'fa-star',          'label'=>'My Grades',    'href'=>($cid ? "/classroom/grades.php?class_id={$cid}" : '/dashboard/student.php')],
            ],
            'Track' => [
                ['icon'=>'fa-chart-line',  'label'=>'My Progress',  'href'=>'/analytics/performance.php'],
                ['icon'=>'fa-calendar-check','label'=>'Attendance',  'href'=>($cid ? "/classroom/attendance.php?class_id={$cid}" : '/dashboard/student.php')],
                ['icon'=>'fa-wifi-slash',  'label'=>'Offline Sub.',  'href'=>'/offline/submission.php'],
                ['icon'=>'fa-history',     'label'=>'Timeline',      'href'=>'/dashboard/student.php#timeline'],
                ['icon'=>'fa-archive',     'label'=>'My Archive',    'href'=>'/classroom/archive.php'],
            ],
            'Global' => [
                ['icon'=>'fa-calendar',    'label'=>'Calendar',      'href'=>'/global/calendar.php'],
                ['icon'=>'fa-comment-alt', 'label'=>'Messages',      'href'=>'/global/messages.php'],
                ['icon'=>'fa-cog',         'label'=>'Settings',      'href'=>'/global/profile.php'],
            ],
        ];
    } else { // guardian
        $nav = [
            'Main' => [
                ['icon'=>'fa-home',         'label'=>'Dashboard',     'href'=>'/dashboard/guardian.php'],
                ['icon'=>'fa-link',         'label'=>'Link Student',  'href'=>'/guardian/link.php'],
            ],
            'Monitor' => [
                ['icon'=>'fa-graduation-cap','label'=>'Class Records',  'href'=>'/guardian/records.php'],
                ['icon'=>'fa-chart-line',   'label'=>'Performance',    'href'=>'/analytics/performance.php'],
                ['icon'=>'fa-calendar-check','label'=>'Attendance',    'href'=>'/classroom/attendance.php'],
                ['icon'=>'fa-award',        'label'=>'Certificates',   'href'=>'/guardian/records.php#certs'],
            ],
            'Reports' => [
                ['icon'=>'fa-download',     'label'=>'Download Report','href'=>'/guardian/download.php'],
                ['icon'=>'fa-cog',          'label'=>'Settings',       'href'=>'/global/profile.php'],
            ],
        ];
    }

    $roleColors = ['teacher'=>'var(--primary)', 'student'=>'var(--success)', 'guardian'=>'var(--warning)'];
    $roleColor  = $roleColors[$role] ?? 'var(--primary)';
    $roleBadge  = ['teacher'=>'👨‍🏫 Teacher', 'student'=>'🎓 Student', 'guardian'=>'👪 Guardian'][$role] ?? '';

    ob_start();
    ?>
    <aside class="sidebar" id="main-sidebar">
      <div class="sidebar-logo" onclick="(function(){var s=document.getElementById('main-sidebar');s.classList.toggle('collapsed');})()" style="cursor:pointer;user-select:none" title="Toggle sidebar">
        <img src="<?= $base ?>/assets/img/logo.png" alt="SmartClass Logo" style="width:38px;height:38px;object-fit:contain;flex-shrink:0;border-radius:8px;transition:width 0.28s,height 0.28s;">
        <div class="sidebar-logo-text">SmartClass</div>
      </div>



      <!-- Navigation -->
      <nav class="sidebar-nav">
        <?php foreach ($nav as $section => $items): ?>
        <div class="nav-section">
          <div class="nav-section-label"><?= htmlspecialchars($section) ?></div>
          <?php foreach ($items as $item): ?>
          <?php
            $classes = 'nav-item';
            // Exact basename match to avoid multiple items lighting up
            $itemFile = basename(parse_url($item['href'] ?? '', PHP_URL_PATH) ?? '');
            if ($activeNav && $itemFile === $activeNav) $classes .= ' active';
            $badge = isset($item['badge']) ? "<span class='nav-badge'>{$item['badge']}</span>" : '';
            $label = htmlspecialchars($item['label']);
            $icon  = $item['icon'];
          ?>
          <?php if (isset($item['action'])): ?>
          <button class="<?= $classes ?>" onclick="<?= $item['action'] ?>" data-label="<?= $label ?>" style="width:100%;text-align:left;background:none;border:none;">
            <i class="fas <?= $icon ?>"></i>
            <span><?= $label ?></span>
            <?= $badge ?>
          </button>
          <?php else: ?>
          <a href="<?= $base . $item['href'] ?>" class="<?= $classes ?>" data-label="<?= $label ?>">
            <i class="fas <?= $icon ?>"></i>
            <span><?= $label ?></span>
            <?= $badge ?>
          </a>
          <?php endif; ?>
          <?php endforeach; ?>
        </div>
        <?php endforeach; ?>
      </nav>

      <!-- User Info -->
      <div class="sidebar-footer">
        <div class="user-info" onclick="window.location='<?= $base ?>/global/profile.php'">
          <div class="avatar" style="background:linear-gradient(135deg,<?= $roleColor ?>,<?= $roleColor ?>88)">
            <?php if (!empty($user['avatar'])): ?>
              <img src="<?= $base ?>/uploads/<?= htmlspecialchars($user['avatar']) ?>" alt="Avatar" style="width:100%;height:100%;object-fit:cover;border-radius:50%">
            <?php else: ?>
              <?= $initial ?>
            <?php endif; ?>
          </div>
          <div class="flex-1 overflow-hidden">
            <div class="user-name truncate"><?= $name ?></div>
            <div class="user-role"><?= $role ?></div>
          </div>
          <i class="fas fa-chevron-right text-muted" style="font-size:0.75rem"></i>
        </div>
        <a href="<?= $base ?>/api/auth.php?action=logout" class="nav-item" style="color:var(--danger);margin-top:0.25rem">
          <i class="fas fa-sign-out-alt"></i>
          <span>Logout</span>
        </a>
      </div>
    </aside>
    <?php
    echo ob_get_clean();
}

function renderTopbar(string $title, array $user, array $actions = []): void {
    $base    = BASE_URL;
    $initial = strtoupper($user['name'][0]);
    ?>
    <header class="topbar">
      <button class="sidebar-toggle" onclick="document.getElementById('main-sidebar').classList.toggle('collapsed'); document.querySelector('.main-content').classList.toggle('sidebar-collapsed');">
        <i class="fas fa-bars"></i>
      </button>
      <h1 class="topbar-title"><?= htmlspecialchars($title) ?></h1>

      <div class="topbar-actions">
        <!-- Search -->
        <div class="search-bar" style="position:relative">
          <i class="fas fa-search"></i>
          <input type="text" id="global-search" placeholder="Search… (Ctrl+K)" autocomplete="off">
          <div id="search-suggestions" class="search-suggestions dropdown-menu hidden" style="width:100%; top:calc(100% + 5px);"></div>
        </div>

        <!-- Extra action buttons (icon only) -->
        <?php foreach ($actions as $action): ?>
          <button class="topbar-icon-action" onclick="<?= $action['onclick'] ?? '' ?>" title="<?= htmlspecialchars($action['label']) ?>">
            <i class="fas <?= $action['icon'] ?? 'fa-plus' ?>"></i>
          </button>
        <?php endforeach; ?>

        <!-- Notifications toggle -->
        <button class="icon-btn" id="notif-toggle" onclick="toggleNotifPanel()" title="Notifications" style="position:relative">
          <i class="fas fa-bell"></i>
          <span class="notif-dot" id="notif-dot"></span>
        </button>

        <!-- Messages -->
        <a href="<?= $base ?>/global/messages.php" class="icon-btn">
          <i class="fas fa-comment-alt"></i>
        </a>

        <!-- Avatar dropdown -->
        <div class="dropdown">
          <div class="avatar" style="width:36px;height:36px;cursor:pointer" data-dropdown="topbar-user-menu">
            <?php if (!empty($user['avatar'])): ?>
              <img src="<?= $base ?>/uploads/<?= htmlspecialchars($user['avatar']) ?>" alt="Avatar" style="width:100%;height:100%;object-fit:cover;border-radius:50%">
            <?php else: ?>
              <?= $initial ?>
            <?php endif; ?>
          </div>
          <div class="dropdown-menu" id="topbar-user-menu">
            <div class="dropdown-item" style="flex-direction:column;align-items:flex-start;gap:0.1rem;cursor:default">
              <span style="font-weight:700;font-size:0.875rem"><?= htmlspecialchars($user['name']) ?></span>
              <span style="font-size:0.75rem;color:var(--text-muted)"><?= htmlspecialchars($user['email']) ?></span>
            </div>
            <div class="dropdown-divider"></div>
            <a href="<?= $base ?>/global/profile.php" class="dropdown-item"><i class="fas fa-user"></i> Profile & Settings</a>
            <a href="<?= $base ?>/global/export.php"  class="dropdown-item"><i class="fas fa-file-export"></i> Export Reports</a>
            <div class="dropdown-divider"></div>
            <a href="<?= $base ?>/api/auth.php?action=logout" class="dropdown-item danger"><i class="fas fa-sign-out-alt"></i> Logout</a>
          </div>
        </div>
      </div>
    </header>
    <?php
}

function renderFooter(string $extraJs = ''): void {
    global $user;
    $base = BASE_URL;
    $v = time();
    $userName = htmlspecialchars($user['name'] ?? 'User');
    echo <<<HTML
      <div class="toast-container"></div>

      <!-- Notification slide-in panel -->
      <div id="notif-backdrop" class="notif-backdrop" onclick="toggleNotifPanel()"></div>
      <div id="notif-panel" class="notif-panel">
        <div class="notif-panel-header">
          <div>
            <div class="notif-panel-title">Notifications</div>
            <div class="notif-panel-sub" id="notif-unread-label"></div>
          </div>
          <div style="display:flex;align-items:center;gap:0.5rem">
            <button class="notif-mark-all" id="notif-mark-all" onclick="markAllNotifs()" style="display:none">Mark all read</button>
            <button onclick="toggleNotifPanel()" style="background:none;border:none;color:var(--text-muted);cursor:pointer;font-size:1.1rem;padding:0.25rem"><i class="fas fa-times"></i></button>
          </div>
        </div>
        <!-- Filter tabs -->
        <div class="notif-filters">
          <button class="notif-filter-btn active" id="filter-all"    onclick="setNotifFilter('all',this)">All</button>
          <button class="notif-filter-btn"         id="filter-unread" onclick="setNotifFilter('unread',this)">Unread</button>
          <button class="notif-filter-btn"         id="filter-read"   onclick="setNotifFilter('read',this)">Read</button>
        </div>
        <div class="notif-panel-body" id="notif-list">
          <div class="notif-empty">Loading…</div>
        </div>
        <div class="notif-panel-footer">
          <a href="{$base}/global/notifications.php" style="font-size:0.78rem;color:var(--text-muted)">View all</a>
          <button class="notif-clear-all" onclick="clearAllNotifs()"><i class="fas fa-times"></i> Clear all</button>
        </div>
      </div>
      
      <!-- AI Chat UI -->
      <button class="ai-chat-btn" onclick="document.getElementById('ai-chat-panel').classList.toggle('show')">
        <i class="fas fa-robot"></i>
      </button>
      <div id="ai-chat-panel" class="ai-chat-panel">
        <div class="ai-chat-header">
          <div>
            <div style="font-weight:700;font-size:0.95rem"><i class="fas fa-robot"></i> Smart AI Assistant</div>
            <div style="font-size:0.7rem;opacity:0.8">Powered by Gemini</div>
          </div>
          <button onclick="document.getElementById('ai-chat-panel').classList.remove('show')" style="background:none;border:none;color:white;cursor:pointer"><i class="fas fa-times"></i></button>
        </div>
        <div class="ai-chat-body" id="ai-chat-body">
          <div class="ai-message ai">Hi there, {$userName}! Need help with your classes?</div>
        </div>
        <div class="ai-chat-footer">
          <form id="ai-chat-form" style="display:flex;gap:0.5rem;width:100%">
            <input type="text" id="ai-chat-input" placeholder="Ask about grades, deadlines..." autocomplete="off">
            <button type="submit" style="background:var(--primary);color:white;border:none;border-radius:var(--radius-sm);width:36px;height:36px;display:flex;align-items:center;justify-content:center"><i class="fas fa-paper-plane"></i></button>
          </form>
        </div>
      </div>

      <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
      <script src="{$base}/assets/js/main.js?v={$v}"></script>
      <script src="{$base}/assets/js/charts.js?v={$v}"></script>
      {$extraJs}
    </body>
    </html>
    HTML;
}
?>

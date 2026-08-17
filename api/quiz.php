<?php
// =============================================
// Smart Classroom — Quiz & Poll API
// =============================================
require_once __DIR__ . '/../config/db.php';
requireLogin();

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$user   = currentUser();
$uid    = $user['id'];

// ── Create Quiz (Teacher)
if ($action === 'create_quiz' && $user['role'] === 'teacher') {
    $classId   = (int)($_POST['class_id'] ?? 0);
    $title     = trim($_POST['title'] ?? '');
    $desc      = trim($_POST['description'] ?? '');
    $timeLimit = (int)($_POST['time_limit'] ?? 30);
    $status    = in_array($_POST['status'] ?? 'draft', ['draft','live','closed']) ? $_POST['status'] : 'draft';

    if (!$title || !$classId) {
        redirect(BASE_URL . "/classroom/quiz.php?class_id={$classId}&error=1");
    }

    $ins = $pdo->prepare("INSERT INTO quizzes (class_id, title, description, time_limit, status, is_live, created_by) VALUES (?,?,?,?,?,?,?)");
    $ins->execute([$classId, $title, $desc, $timeLimit, $status, $status === 'live' ? 1 : 0, $uid]);
    $quizId = $pdo->lastInsertId();

    // Save questions
    $questions = $_POST['questions'] ?? [];
    foreach ($questions as $q) {
        $question = trim($q['question'] ?? '');
        if (!$question) continue;
        $options = array_filter($q['options'] ?? [], fn($o) => trim($o) !== '');
        $correct = strtoupper(trim($q['correct'] ?? 'A'));
        $points  = (int)($q['points'] ?? 10);
        $ins2 = $pdo->prepare("INSERT INTO quiz_questions (quiz_id, question, type, options, correct_answer, points) VALUES (?,?,'mcq',?,?,?)");
        $ins2->execute([$quizId, $question, json_encode(array_values($options)), $correct, $points]);
    }

    // Notify students if live
    if ($status === 'live') {
        $members = $pdo->prepare("SELECT user_id FROM class_members WHERE class_id=?");
        $members->execute([$classId]);
        foreach ($members->fetchAll() as $m) {
            $notif = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?,?,?,?)");
            $notif->execute([$m['user_id'], '🔴 Live Quiz!', "Quiz '{$title}' is now live!", 'info']);
        }
    }

    redirect(BASE_URL . "/classroom/quiz.php?class_id={$classId}&created=1");
}

// ── Create Poll (Teacher)
if ($action === 'create_poll' && $user['role'] === 'teacher') {
    $classId  = (int)($_POST['class_id'] ?? 0);
    $question = trim($_POST['question'] ?? '');
    $options  = array_filter($_POST['options'] ?? [], fn($o) => trim($o) !== '');

    if (!$question || count($options) < 2) {
        redirect(BASE_URL . "/classroom/quiz.php?class_id={$classId}&error=poll");
    }

    $ins = $pdo->prepare("INSERT INTO polls (class_id, question, options, created_by) VALUES (?,?,?,?)");
    $ins->execute([$classId, $question, json_encode(array_values($options)), $uid]);
    redirect(BASE_URL . "/classroom/quiz.php?class_id={$classId}&poll_created=1");
}

// ── Vote Poll (Student)
if ($action === 'vote' && $user['role'] === 'student') {
    $pollId     = (int)($_POST['poll_id'] ?? 0);
    $optionIdx  = (int)($_POST['option_index'] ?? 0);

    // Check already voted
    $c = $pdo->prepare("SELECT id FROM poll_votes WHERE poll_id=? AND student_id=?");
    $c->execute([$pollId, $uid]);
    if ($c->fetch()) {
        redirect($_SERVER['HTTP_REFERER'] ?? BASE_URL);
    }

    $ins = $pdo->prepare("INSERT INTO poll_votes (poll_id, student_id, option_index) VALUES (?,?,?)");
    $ins->execute([$pollId, $uid, $optionIdx]);
    redirect($_SERVER['HTTP_REFERER'] ?? BASE_URL);
}

// ── Toggle Quiz Status (Teacher)
if ($action === 'toggle_status' && $user['role'] === 'teacher') {
    $quizId = (int)($_POST['quiz_id'] ?? 0);
    $status = trim($_POST['status'] ?? 'live');
    $upd = $pdo->prepare("UPDATE quizzes SET status=?, is_live=? WHERE id=? AND created_by=?");
    $upd->execute([$status, $status === 'live' ? 1 : 0, $quizId, $uid]);
    jsonResponse(['success' => true]);
}

// ── Submit Quiz Answers (Student)
if ($action === 'submit_quiz' && $user['role'] === 'student') {
    $quizId  = (int)($_POST['quiz_id'] ?? 0);
    $answers = $_POST['answers'] ?? [];

    // Get questions
    $qs = $pdo->prepare("SELECT * FROM quiz_questions WHERE quiz_id=?");
    $qs->execute([$quizId]);
    $questions = $qs->fetchAll();

    $score = 0;
    $total = 0;
    foreach ($questions as $q) {
        $total += $q['points'];
        $ans    = strtoupper(trim($answers[$q['id']] ?? ''));
        if ($ans === strtoupper($q['correct_answer'])) $score += $q['points'];
    }

    // Check if already submitted
    $check = $pdo->prepare("SELECT id FROM quiz_responses WHERE quiz_id=? AND student_id=?");
    $check->execute([$quizId, $uid]);
    if (!$check->fetch()) {
        $ins = $pdo->prepare("INSERT INTO quiz_responses (quiz_id, student_id, answers, score, total_points) VALUES (?,?,?,?,?)");
        $ins->execute([$quizId, $uid, json_encode($answers), $score, $total]);
    }

    jsonResponse(['success' => true, 'score' => $score, 'total' => $total, 'pct' => $total > 0 ? round($score/$total*100) : 0]);
}

jsonResponse(['error' => 'Invalid action'], 400);
?>

<?php
// Auto-create course_sessions from weekly routines. Safe to call from cron or a simple HTTP ping.
// Usage: php auto_generate_routines.php
//        curl "http://your-host/auto_generate_routines.php?days=14"

require 'connectDB.php';

function refresh_session_status(mysqli $conn): void {
    // Sessions open 10 minutes before start_time to allow early arrivals
    $conn->query("UPDATE course_sessions SET status = CASE WHEN session_date = CURDATE() AND CURTIME() >= SUBTIME(start_time, '00:10:00') AND CURTIME() <= end_time THEN 'scheduled' ELSE 'closed' END WHERE status <> 'attendance_generated'");
}

function ensure_routine_table(mysqli $conn): void {
    $conn->query("CREATE TABLE IF NOT EXISTS course_session_routines (
      routine_id INT AUTO_INCREMENT PRIMARY KEY,
      course_id INT NOT NULL,
      room_id INT NOT NULL,
      day_of_week TINYINT NOT NULL,
      start_time TIME NOT NULL,
      end_time TIME NOT NULL,
      grace_minutes INT DEFAULT 10,
      start_date DATE DEFAULT NULL,
      end_date DATE DEFAULT NULL,
      is_active TINYINT(1) DEFAULT 1,
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      INDEX idx_dow (day_of_week),
      INDEX idx_active (is_active)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");
}

function room_overlap_exists(mysqli $conn, int $room_id, string $date, string $start_time, string $end_time): bool {
    $check = $conn->prepare("SELECT session_id FROM course_sessions WHERE room_id = ? AND session_date = ? AND status <> 'attendance_generated' AND (? < ADDTIME(end_time, SEC_TO_TIME(COALESCE(grace_minutes,10)*60))) AND (? > start_time) LIMIT 1");
    if (!$check) return true;
    $check->bind_param("isss", $room_id, $date, $start_time, $end_time);
    $check->execute();
    $res = $check->get_result();
    $has = $res && $res->num_rows > 0;
    $check->close();
    return $has;
}

function insert_course_session(mysqli $conn, int $course_id, int $room_id, string $date, string $start_time, string $end_time, int $grace_minutes): array {
    if (room_overlap_exists($conn, $room_id, $date, $start_time, $end_time)) {
        return [false, "Overlapping session exists in this room for this date/time."];
    }
    $stmt = $conn->prepare("INSERT INTO course_sessions (course_id, room_id, session_date, start_time, end_time, grace_minutes, status) VALUES (?, ?, ?, ?, ?, ?, 'closed')");
    if (!$stmt) return [false, "DB prepare failed (insert): " . $conn->error];
    $stmt->bind_param("iisssi", $course_id, $room_id, $date, $start_time, $end_time, $grace_minutes);
    $ok = $stmt->execute();
    $err = $stmt->error;
    $stmt->close();
    if (!$ok) return [false, "Failed to create session: " . $err];
    return [true, null];
}

function generate_sessions_from_routines(mysqli $conn, int $daysAhead): array {
    $created = 0; $skippedOverlap = 0; $skippedRange = 0; $errors = 0;

    $routines = [];
    $q = $conn->query("SELECT * FROM course_session_routines WHERE is_active = 1");
    if ($q) while ($row = $q->fetch_assoc()) $routines[] = $row;
    if (empty($routines)) return [$created, $skippedOverlap, $skippedRange, $errors];

    $today = new DateTime('today');

    for ($i = 0; $i < $daysAhead; $i++) {
        $dt = (clone $today)->modify("+{$i} day");
        $dateStr = $dt->format('Y-m-d');
        $dow = (int)$dt->format('w');

        foreach ($routines as $r) {
            if ((int)$r['day_of_week'] !== $dow) continue;
            if ($r['start_date'] && $dateStr < $r['start_date']) { $skippedRange++; continue; }
            if ($r['end_date'] && $dateStr > $r['end_date']) { $skippedRange++; continue; }

            $dup = $conn->prepare("SELECT session_id FROM course_sessions WHERE course_id = ? AND session_date = ? AND start_time = ? LIMIT 1");
            if (!$dup) { $errors++; continue; }
            $dup->bind_param("iss", $r['course_id'], $dateStr, $r['start_time']);
            $dup->execute();
            $existing = $dup->get_result();
            $hasDup = $existing && $existing->num_rows > 0;
            $dup->close();
            if ($hasDup) { $skippedOverlap++; continue; }

            [$ok, $msg] = insert_course_session(
                $conn,
                (int)$r['course_id'],
                (int)$r['room_id'],
                $dateStr,
                $r['start_time'],
                $r['end_time'],
                (int)($r['grace_minutes'] ?? 10)
            );

            if ($ok) {
                $created++;
            } else {
                if (strpos($msg, 'Overlap') !== false) $skippedOverlap++; else $errors++;
            }
        }
    }

    refresh_session_status($conn);
    return [$created, $skippedOverlap, $skippedRange, $errors];
}

$daysAhead = isset($_GET['days']) ? (int)$_GET['days'] : 14;
if ($daysAhead < 1) $daysAhead = 1;
if ($daysAhead > 60) $daysAhead = 60;

ensure_routine_table($conn);
[$created, $skippedOverlap, $skippedRange, $errors] = generate_sessions_from_routines($conn, $daysAhead);

header('Content-Type: application/json');
echo json_encode([
    'status' => $errors > 0 ? 'warning' : 'ok',
    'created' => $created,
    'skipped_overlap_or_duplicate' => $skippedOverlap,
    'skipped_outside_range' => $skippedRange,
    'errors' => $errors,
    'days_ahead' => $daysAhead,
]);

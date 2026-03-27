<?php
// manage/ajax_groups.php
declare(strict_types=1);
require_once __DIR__ . "/../connection/db.php";
require_once __DIR__ . "/includes/auth.php";

start_secure_session();
require_admin();

if (isset($_GET['action']) && $_GET['action'] === 'get_group_students') {
    $current_group_id = isset($_GET['group_id']) ? (int)$_GET['group_id'] : 0;
    $filter_prefix_id = isset($_GET['filter_prefix']) && $_GET['filter_prefix'] !== 'all' ? (int)$_GET['filter_prefix'] : null;
    $search_term = isset($_GET['search']) ? trim($_GET['search']) : '';

    // Fetch all students and their group info
    $q = "SELECT s.id, s.name, s.studentid, s.prefix_id, sp.prefix_name, 
          (SELECT sg.group_id FROM student_groups sg WHERE sg.student_id = s.id AND sg.group_id = $current_group_id LIMIT 1) as assigned_here,
          (SELECT g.group_name FROM student_groups sg JOIN `groups` g ON sg.group_id = g.group_id WHERE sg.student_id = s.id AND sg.group_id != $current_group_id LIMIT 1) as other_group
          FROM students s 
          LEFT JOIN student_prefixes sp ON s.prefix_id = sp.id";
    
    $where_clauses = [];
    if ($filter_prefix_id !== null) {
        $where_clauses[] = "s.prefix_id = " . $filter_prefix_id;
    }
    if (!empty($search_term)) {
        $search_term_escaped = mysqli_real_escape_string($conn, $search_term);
        $where_clauses[] = "(s.name LIKE '%$search_term_escaped%' OR CONCAT(sp.prefix_name, s.studentid) LIKE '%$search_term_escaped%')";
    }

    if (!empty($where_clauses)) {
        $q .= " WHERE " . implode(" AND ", $where_clauses);
    }

    $q .= " ORDER BY s.name ASC";

    $res = mysqli_query($conn, $q);
    $students = [];
    while($r = mysqli_fetch_assoc($res)) {
        $students[] = [
            'id' => $r['id'],
            'name' => $r['name'],
            'sid' => ($r['prefix_name'] ?? '') . $r['studentid'],
            'prefix_id' => (string)($r['prefix_id'] ?? 'none'),
            'group_id' => $r['assigned_here'], // For UI checked state
            'group_name' => $r['other_group'] // For telling if they are in another group
        ];
    }
    header('Content-Type: application/json');
    echo json_encode($students);
    exit;
}
?>

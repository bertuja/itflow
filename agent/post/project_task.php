<?php

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

// Add project task
if (isset($_POST['add_project_task'])) {
    validateCSRFToken($_POST['csrf_token']);
    enforceUserPermission('module_support', 2);

    $project_id   = intval($_POST['project_id']);
    $name         = sanitizeInput($_POST['project_task_name']);
    $description  = sanitizeInput($_POST['project_task_description'] ?? '');
    $status       = in_array($_POST['project_task_status'] ?? '', ['todo','in_progress','done']) ? $_POST['project_task_status'] : 'todo';
    $priority     = in_array($_POST['project_task_priority'] ?? '', ['low','medium','high','urgent']) ? $_POST['project_task_priority'] : 'medium';
    $due          = !empty($_POST['project_task_due']) ? sanitizeInput($_POST['project_task_due']) : 'NULL';
    $assigned_to  = intval($_POST['project_task_assigned_to'] ?? 0);
    $due_val      = ($due === 'NULL') ? 'NULL' : "'$due'";

    // Max order in this status column
    $row_order = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COALESCE(MAX(project_task_order),0)+1 AS next_order FROM project_tasks WHERE project_task_project_id=$project_id AND project_task_status='$status'"));
    $next_order = intval($row_order['next_order']);

    mysqli_query($mysqli, "INSERT INTO project_tasks SET
        project_task_name        = '$name',
        project_task_description = '$description',
        project_task_status      = '$status',
        project_task_priority    = '$priority',
        project_task_due         = $due_val,
        project_task_assigned_to = $assigned_to,
        project_task_order       = $next_order,
        project_task_project_id  = $project_id,
        project_task_created_by  = $session_user_id"
    );

    logAction("Project Task", "Create", "$session_name added task '$name' to project $project_id");
    flash_alert("Task added");
    redirect();
}

// Edit project task
if (isset($_POST['edit_project_task'])) {
    validateCSRFToken($_POST['csrf_token']);
    enforceUserPermission('module_support', 2);

    $task_id     = intval($_POST['project_task_id']);
    $name        = sanitizeInput($_POST['project_task_name']);
    $description = sanitizeInput($_POST['project_task_description'] ?? '');
    $status      = in_array($_POST['project_task_status'] ?? '', ['todo','in_progress','done']) ? $_POST['project_task_status'] : 'todo';
    $priority    = in_array($_POST['project_task_priority'] ?? '', ['low','medium','high','urgent']) ? $_POST['project_task_priority'] : 'medium';
    $due         = !empty($_POST['project_task_due']) ? sanitizeInput($_POST['project_task_due']) : 'NULL';
    $assigned_to = intval($_POST['project_task_assigned_to'] ?? 0);
    $due_val     = ($due === 'NULL') ? 'NULL' : "'$due'";

    $completed_at_sql = '';
    if ($status === 'done') {
        // Set completed_at if not already set
        $existing = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT project_task_completed_at FROM project_tasks WHERE project_task_id=$task_id"));
        if (empty($existing['project_task_completed_at'])) {
            $completed_at_sql = ", project_task_completed_at = NOW()";
        }
    } else {
        $completed_at_sql = ", project_task_completed_at = NULL";
    }

    mysqli_query($mysqli, "UPDATE project_tasks SET
        project_task_name        = '$name',
        project_task_description = '$description',
        project_task_status      = '$status',
        project_task_priority    = '$priority',
        project_task_due         = $due_val,
        project_task_assigned_to = $assigned_to
        $completed_at_sql
        WHERE project_task_id = $task_id"
    );

    logAction("Project Task", "Edit", "$session_name edited task $task_id");
    flash_alert("Task updated");
    redirect();
}

// Delete project task
if (isset($_GET['delete_project_task'])) {
    validateCSRFToken($_GET['csrf_token']);
    enforceUserPermission('module_support', 2);

    $task_id = intval($_GET['delete_project_task']);
    mysqli_query($mysqli, "DELETE FROM project_tasks WHERE project_task_id = $task_id");
    logAction("Project Task", "Delete", "$session_name deleted task $task_id");
    flash_alert("Task deleted");
    redirect();
}

// Move task (status + order) — called via AJAX
if (isset($_POST['move_project_task'])) {
    validateCSRFToken($_POST['csrf_token']);
    enforceUserPermission('module_support', 2);

    $task_id    = intval($_POST['task_id']);
    $new_status = in_array($_POST['new_status'] ?? '', ['todo','in_progress','done']) ? $_POST['new_status'] : 'todo';
    $new_order  = intval($_POST['new_order'] ?? 0);

    $completed_at_sql = '';
    if ($new_status === 'done') {
        $existing = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT project_task_completed_at FROM project_tasks WHERE project_task_id=$task_id"));
        if (empty($existing['project_task_completed_at'])) {
            $completed_at_sql = ", project_task_completed_at = NOW()";
        }
    } else {
        $completed_at_sql = ", project_task_completed_at = NULL";
    }

    mysqli_query($mysqli, "UPDATE project_tasks SET
        project_task_status = '$new_status',
        project_task_order  = $new_order
        $completed_at_sql
        WHERE project_task_id = $task_id"
    );

    // Reorder siblings
    $order = 0;
    $siblings = mysqli_query($mysqli, "SELECT project_task_id FROM project_tasks WHERE project_task_status='$new_status' AND project_task_id != $task_id ORDER BY project_task_order ASC");
    while ($s = mysqli_fetch_assoc($siblings)) {
        if ($order == $new_order) $order++; // skip the inserted slot
        mysqli_query($mysqli, "UPDATE project_tasks SET project_task_order=$order WHERE project_task_id={$s['project_task_id']}");
        $order++;
    }

    echo json_encode(['success' => true]);
    exit;
}

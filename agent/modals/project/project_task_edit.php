<?php

require_once '../../../includes/modal_header.php';

$task_id = intval($_GET['id']);

$sql = mysqli_query($mysqli, "SELECT pt.*, u.user_name FROM project_tasks pt LEFT JOIN users u ON pt.project_task_assigned_to = u.user_id WHERE project_task_id = $task_id LIMIT 1");
$task = mysqli_fetch_assoc($sql);

if (!$task) {
    echo json_encode(['content' => '<div class="p-4 text-danger">Tarea no encontrada.</div>']);
    exit;
}

$sql_users = mysqli_query($mysqli, "SELECT user_id, user_name FROM users WHERE user_archived_at IS NULL AND user_role_id > 1 AND user_status = 1 ORDER BY user_name ASC");

$priority_labels = ['low' => 'Baja', 'medium' => 'Media', 'high' => 'Alta', 'urgent' => 'Urgente'];
$status_labels   = ['todo' => 'Por hacer', 'in_progress' => 'En progreso', 'done' => 'Hecho'];

ob_start();
?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-tasks mr-2"></i>Editar Tarea</h5>
    <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
</div>

<form action="post.php" method="post">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="project_task_id" value="<?= $task_id ?>">

    <div class="modal-body">

        <div class="form-group">
            <label>Nombre <span class="text-danger">*</span></label>
            <input type="text" class="form-control" name="project_task_name"
                   value="<?= nullable_htmlentities($task['project_task_name']) ?>" maxlength="255" required autofocus>
        </div>

        <div class="form-group">
            <label>Descripción</label>
            <textarea class="form-control" name="project_task_description" rows="4"><?= nullable_htmlentities($task['project_task_description']) ?></textarea>
        </div>

        <div class="row">
            <div class="col-md-6">
                <div class="form-group">
                    <label>Estado</label>
                    <select class="form-control" name="project_task_status">
                        <?php foreach ($status_labels as $val => $lbl): ?>
                            <option value="<?= $val ?>" <?= $task['project_task_status'] === $val ? 'selected' : '' ?>><?= $lbl ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-group">
                    <label>Prioridad</label>
                    <select class="form-control" name="project_task_priority">
                        <?php foreach ($priority_labels as $val => $lbl): ?>
                            <option value="<?= $val ?>" <?= $task['project_task_priority'] === $val ? 'selected' : '' ?>><?= $lbl ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-6">
                <div class="form-group">
                    <label>Responsable</label>
                    <select class="form-control select2" name="project_task_assigned_to">
                        <option value="0">— Sin asignar —</option>
                        <?php while ($u = mysqli_fetch_assoc($sql_users)): ?>
                            <option value="<?= intval($u['user_id']) ?>"
                                <?= intval($task['project_task_assigned_to']) === intval($u['user_id']) ? 'selected' : '' ?>>
                                <?= nullable_htmlentities($u['user_name']) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-group">
                    <label>Vencimiento</label>
                    <input type="date" class="form-control" name="project_task_due"
                           value="<?= nullable_htmlentities($task['project_task_due']) ?>">
                </div>
            </div>
        </div>

        <?php if ($task['project_task_completed_at']): ?>
            <div class="alert alert-success py-1 small">
                <i class="fas fa-check-circle mr-1"></i>
                Completada el <?= date('d/m/Y H:i', strtotime($task['project_task_completed_at'])) ?>
            </div>
        <?php endif; ?>

    </div>

    <div class="modal-footer d-flex justify-content-between">
        <a href="post.php?delete_project_task=<?= $task_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>"
           class="btn btn-outline-danger btn-sm confirm-link">
            <i class="fas fa-trash mr-1"></i>Eliminar
        </a>
        <div>
            <button type="submit" name="edit_project_task" class="btn btn-primary"><i class="fa fa-check mr-2"></i>Guardar</button>
            <button type="button" class="btn btn-outline-secondary" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Cancelar</button>
        </div>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';

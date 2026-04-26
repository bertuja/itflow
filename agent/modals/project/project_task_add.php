<?php

require_once '../../../includes/modal_header.php';

$project_id     = intval($_GET['project_id']);
$default_status = sanitizeInput($_GET['status'] ?? 'todo');

$sql_users = mysqli_query($mysqli, "SELECT user_id, user_name FROM users WHERE user_archived_at IS NULL AND user_role_id > 1 AND user_status = 1 ORDER BY user_name ASC");

ob_start();
?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-plus mr-2"></i>Nueva Tarea</h5>
    <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
</div>

<form action="../post.php" method="post">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="project_id" value="<?= $project_id ?>">

    <div class="modal-body">

        <div class="form-group">
            <label>Nombre <span class="text-danger">*</span></label>
            <input type="text" class="form-control" name="project_task_name" placeholder="¿Qué hay que hacer?" maxlength="255" required autofocus>
        </div>

        <div class="form-group">
            <label>Descripción</label>
            <textarea class="form-control" name="project_task_description" rows="3" placeholder="Detalle opcional..."></textarea>
        </div>

        <div class="row">
            <div class="col-md-6">
                <div class="form-group">
                    <label>Estado</label>
                    <select class="form-control" name="project_task_status">
                        <option value="todo"        <?= $default_status === 'todo'        ? 'selected' : '' ?>>Por hacer</option>
                        <option value="in_progress" <?= $default_status === 'in_progress' ? 'selected' : '' ?>>En progreso</option>
                        <option value="done"        <?= $default_status === 'done'        ? 'selected' : '' ?>>Hecho</option>
                    </select>
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-group">
                    <label>Prioridad</label>
                    <select class="form-control" name="project_task_priority">
                        <option value="low">Baja</option>
                        <option value="medium" selected>Media</option>
                        <option value="high">Alta</option>
                        <option value="urgent">Urgente</option>
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
                            <option value="<?= intval($u['user_id']) ?>"><?= nullable_htmlentities($u['user_name']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-group">
                    <label>Vencimiento</label>
                    <input type="date" class="form-control" name="project_task_due">
                </div>
            </div>
        </div>

    </div>

    <div class="modal-footer">
        <button type="submit" name="add_project_task" class="btn btn-primary"><i class="fa fa-check mr-2"></i>Agregar tarea</button>
        <button type="button" class="btn btn-outline-secondary" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Cancelar</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';

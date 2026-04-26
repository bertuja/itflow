<?php

require_once '../../../includes/modal_header.php';

$ticket_id  = sanitizeInput($_GET['ticket_id'] ?? '');
$ticket_num = intval($_GET['ticket_num'] ?? 0);
$web_url    = sanitizeInput($_GET['web_url'] ?? '');

if (empty($ticket_id)) {
    echo json_encode(['content' => '<div class="p-4 text-danger">Ticket ID missing.</div>']);
    exit;
}

$token  = getZohoAccessToken($mysqli);
$org_id = $config_zoho_org_id ?: '758029195';

function zoho_get($url, $token, $org_id) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => [
            "Authorization: Zoho-oauthtoken $token",
            "orgId: $org_id",
        ],
    ]);
    $raw = curl_exec($ch);
    curl_close($ch);
    return json_decode($raw, true);
}

function zoho_clean_content($text) {
    // Decode HTML entities (&nbsp; etc)
    $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    // Strip HTML tags
    $text = strip_tags($text);
    // Remove Zoho user mention markup: zsu[@user:123456789]zsu
    $text = preg_replace('/zsu\[@user:\d+\]zsu\s*/i', '', $text);
    // Collapse multiple blank lines
    $text = preg_replace('/\n{3,}/', "\n\n", $text);
    return trim($text);
}

// Fetch ticket detail and threads in parallel
$ticket_data  = zoho_get("https://desk.zoho.com/api/v1/tickets/{$ticket_id}", $token, $org_id);
$threads_data = zoho_get("https://desk.zoho.com/api/v1/tickets/{$ticket_id}/threads?limit=25&sortBy=-createdTime", $token, $org_id);
$comments_data = zoho_get("https://desk.zoho.com/api/v1/tickets/{$ticket_id}/comments?limit=25", $token, $org_id);

$subject     = htmlspecialchars($ticket_data['subject'] ?? '(Sin asunto)', ENT_QUOTES);
$status      = htmlspecialchars($ticket_data['status'] ?? '', ENT_QUOTES);
$priority    = htmlspecialchars($ticket_data['priority'] ?? '', ENT_QUOTES);
$description = zoho_clean_content($ticket_data['description'] ?? '');
$created     = substr($ticket_data['createdTime'] ?? '', 0, 10);

$assignee_data = $ticket_data['assignee'] ?? [];
$assignee_name = htmlspecialchars(trim(($assignee_data['firstName'] ?? '') . ' ' . ($assignee_data['lastName'] ?? '')), ENT_QUOTES);

$threads  = $threads_data['data'] ?? [];
$comments = $comments_data['data'] ?? [];

$status_classes = [
    'Open' => 'badge-primary', 'Abierto' => 'badge-primary',
    'Listo para Iniciar' => 'badge-info',
    'En proceso' => 'badge-warning',
    'Esperando respuesta cliente' => 'badge-secondary',
    'Para Retirar o Entregar' => 'badge-secondary',
    'Proyecto' => 'badge-purple',
    'Resuelto' => 'badge-success', 'Resolved' => 'badge-success',
    'Closed' => 'badge-dark', 'Cerrado' => 'badge-dark',
    'Cerrado Automáticamente' => 'badge-dark',
];
$badge = $status_classes[$status] ?? 'badge-secondary';

ob_start();
?>

<div class="modal-header bg-dark">
    <h5 class="modal-title">
        <i class="fas fa-fw fa-headset mr-2"></i>
        <span class="text-muted mr-1">#<?php echo $ticket_num; ?></span>
        <?php echo $subject; ?>
    </h5>
    <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
</div>

<div class="modal-body p-0">

    <!-- Ticket meta bar -->
    <div class="d-flex flex-wrap align-items-center px-3 py-2 border-bottom bg-light" style="gap:1rem;font-size:.85rem;">
        <span><span class="badge <?php echo $badge; ?>"><?php echo $status; ?></span></span>
        <?php if ($priority): ?>
            <span class="text-muted"><i class="fas fa-flag mr-1"></i><?php echo $priority; ?></span>
        <?php endif; ?>
        <?php if ($assignee_name): ?>
            <span class="text-muted"><i class="fas fa-user mr-1"></i><?php echo $assignee_name; ?></span>
        <?php endif; ?>
        <span class="text-muted"><i class="fas fa-calendar mr-1"></i><?php echo $created; ?></span>
        <?php if ($web_url): ?>
            <a href="<?php echo htmlspecialchars($web_url, ENT_QUOTES); ?>" target="_blank" rel="noopener noreferrer" class="ml-auto btn btn-xs btn-outline-secondary">
                <i class="fas fa-external-link-alt mr-1"></i>Abrir en Zoho
            </a>
        <?php endif; ?>
    </div>

    <!-- Description -->
    <?php if (!empty($description)): ?>
    <div class="px-3 pt-3 pb-2">
        <div class="text-muted small text-uppercase mb-1 font-weight-bold">Descripción</div>
        <div class="border rounded p-3 bg-white" style="font-size:.9rem;max-height:200px;overflow-y:auto;white-space:pre-wrap;"><?php echo nl2br(htmlspecialchars(strip_tags($description), ENT_QUOTES)); ?></div>
    </div>
    <?php endif; ?>

    <!-- Thread history -->
    <div class="px-3 pt-2 pb-1">
        <div class="text-muted small text-uppercase mb-2 font-weight-bold">
            Historial
            <?php if (!empty($threads)): ?>
                <span class="badge badge-secondary ml-1"><?php echo count($threads); ?></span>
            <?php endif; ?>
        </div>

        <?php if (empty($threads)): ?>
            <p class="text-muted small">Sin mensajes registrados.</p>
        <?php else: ?>
            <div style="max-height:380px;overflow-y:auto;">
                <?php foreach (array_reverse($threads) as $thread):
                    $author    = $thread['author']['name'] ?? $thread['fromEmailAddress'] ?? 'Sistema';
                    $thread_ts = substr($thread['createdTime'] ?? '', 0, 16);
                    $direction = $thread['direction'] ?? '';
                    $summary   = zoho_clean_content($thread['summary'] ?? '');
                    $is_out    = ($direction === 'out');
                    $channel   = $thread['channel'] ?? '';
                ?>
                <div class="d-flex mb-3" style="<?php echo $is_out ? 'flex-direction:row-reverse;' : ''; ?>">
                    <div style="max-width:85%;">
                        <div class="small text-muted mb-1 <?php echo $is_out ? 'text-right' : ''; ?>">
                            <?php if (!$is_out): ?>
                                <i class="fas fa-user-circle mr-1"></i>
                            <?php else: ?>
                                <i class="fas fa-reply mr-1"></i>
                            <?php endif; ?>
                            <strong><?php echo htmlspecialchars($author, ENT_QUOTES); ?></strong>
                            <span class="mx-1">·</span>
                            <?php echo htmlspecialchars($thread_ts, ENT_QUOTES); ?>
                            <?php if ($channel): ?>
                                <span class="mx-1">·</span>
                                <i class="fas fa-<?php echo $channel === 'EMAIL' ? 'envelope' : 'comment'; ?> mr-1"></i>
                            <?php endif; ?>
                        </div>
                        <div class="rounded p-2 <?php echo $is_out ? 'bg-primary text-white' : 'bg-light border'; ?>"
                             style="font-size:.88rem;white-space:pre-wrap;word-break:break-word;">
                            <?php echo nl2br(htmlspecialchars($summary ?: '(Sin contenido)', ENT_QUOTES)); ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Comments (internal notes) -->
    <?php if (!empty($comments)): ?>
    <div class="px-3 pt-1 pb-3 border-top mt-2">
        <div class="text-muted small text-uppercase mb-2 font-weight-bold">
            Notas internas <span class="badge badge-warning ml-1"><?php echo count($comments); ?></span>
        </div>
        <?php foreach ($comments as $comment):
            $c_author  = $comment['commenter']['name'] ?? $comment['author']['name'] ?? '?';
            $c_ts      = substr($comment['commentedTime'] ?? $comment['createdTime'] ?? '', 0, 16);
            $c_content = zoho_clean_content($comment['content'] ?? $comment['body'] ?? '');
        ?>
        <div class="d-flex align-items-start mb-2">
            <span class="badge badge-warning mr-2 mt-1" style="font-size:.7rem;">NOTA</span>
            <div class="border-left border-warning pl-2" style="font-size:.88rem;">
                <div class="small text-muted mb-1">
                    <strong><?php echo htmlspecialchars($c_author, ENT_QUOTES); ?></strong> · <?php echo htmlspecialchars($c_ts, ENT_QUOTES); ?>
                </div>
                <div style="white-space:pre-wrap;"><?php echo nl2br(htmlspecialchars($c_content, ENT_QUOTES)); ?></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

</div>

<div class="modal-footer">
    <button type="button" class="btn btn-outline-secondary" data-dismiss="modal"><i class="fa fa-times mr-1"></i>Cerrar</button>
    <?php if ($web_url): ?>
        <a href="<?php echo htmlspecialchars($web_url, ENT_QUOTES); ?>" target="_blank" rel="noopener noreferrer" class="btn btn-secondary">
            <i class="fas fa-external-link-alt mr-1"></i>Ver completo en Zoho
        </a>
    <?php endif; ?>
</div>

<?php
require_once '../../../includes/modal_footer.php';

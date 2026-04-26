<?php

require_once "includes/inc_all_client.php";

enforceUserPermission('module_support');

$zoho_account_id = $client_zoho_account_id;

// Filter: 'open' (default) or 'month'
$filter = (isset($_GET['zoho_filter']) && $_GET['zoho_filter'] === 'month') ? 'month' : 'open';

$closed_statuses = ['Closed', 'Cerrado', 'Cerrado Automáticamente', 'Resuelto', 'Resolved'];

$status_classes = [
    'Open'                        => 'badge-primary',
    'Abierto'                     => 'badge-primary',
    'Listo para Iniciar'          => 'badge-info',
    'En proceso'                  => 'badge-warning',
    'Esperando respuesta cliente' => 'badge-secondary',
    'Para Retirar o Entregar'     => 'badge-secondary',
    'Proyecto'                    => 'badge-purple',
    'On Hold'                     => 'badge-secondary',
    'Resuelto'                    => 'badge-success',
    'Resolved'                    => 'badge-success',
    'Closed'                      => 'badge-dark',
    'Cerrado'                     => 'badge-dark',
    'Cerrado Automáticamente'     => 'badge-dark',
];

$priority_classes = [
    'Baja'   => 'text-success',
    'Media'  => 'text-warning',
    'Alta'   => 'text-orange',
    'Urgente'=> 'text-danger',
    'Low'    => 'text-success',
    'Medium' => 'text-warning',
    'High'   => 'text-orange',
    'Urgent' => 'text-danger',
];

?>

<div class="card card-dark">
    <div class="card-header py-2">
        <h3 class="card-title"><i class="fas fa-fw fa-headset mr-2"></i>Zoho Desk Tickets</h3>
        <div class="card-tools">
            <?php if (!empty($zoho_account_id)): ?>
                <div class="btn-group btn-group-sm mr-2" role="group">
                    <a href="?client_id=<?php echo $client_id; ?>&zoho_filter=open"
                       class="btn <?php echo $filter === 'open' ? 'btn-primary' : 'btn-outline-secondary'; ?>">
                        Abiertos
                    </a>
                    <a href="?client_id=<?php echo $client_id; ?>&zoho_filter=month"
                       class="btn <?php echo $filter === 'month' ? 'btn-primary' : 'btn-outline-secondary'; ?>">
                        Último mes
                    </a>
                </div>
                <a href="https://hdesk.d-byte.com.ar/support/soportedbyte/ShowHomePage.do#Cases/dv/" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-secondary">
                    <i class="fas fa-external-link-alt mr-1"></i>Zoho
                </a>
            <?php endif; ?>
        </div>
    </div>
    <div class="card-body p-0">

        <?php

        if (empty($config_zoho_client_id)) {
            echo '<div class="p-4 text-muted"><i class="fas fa-info-circle mr-2"></i>Zoho Desk no está configurado. Ir a <a href="/admin/settings_zoho.php">Admin → Settings → Zoho</a>.</div>';
        } elseif (empty($zoho_account_id)) {
            echo '<div class="p-4 text-muted"><i class="fas fa-info-circle mr-2"></i>Este cliente no tiene Zoho Account ID. Editá el cliente y completá el campo.</div>';
        } else {

            $token = getZohoAccessToken($mysqli);

            if (!$token) {
                echo '<div class="p-4 text-danger"><i class="fas fa-exclamation-triangle mr-2"></i>No se pudo obtener el token de Zoho. Verificar credenciales en <a href="/admin/settings_zoho.php">Zoho Settings</a>.</div>';
            } else {

                $org_id = $config_zoho_org_id ?: '758029195';
                $zoho_account_id_safe = urlencode($zoho_account_id);
                $cutoff = strtotime('-30 days');

                // Fetch latest 100 tickets sorted newest first, filter client-side
                $ch = curl_init("https://desk.zoho.com/api/v1/accounts/{$zoho_account_id_safe}/tickets?limit=100&sortBy=-createdTime&include=contacts,assignee");
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_HTTPHEADER     => [
                        "Authorization: Zoho-oauthtoken $token",
                        "orgId: $org_id",
                    ],
                ]);
                $data = json_decode(curl_exec($ch), true);
                curl_close($ch);

                $all_tickets = $data['data'] ?? [];

                // Apply filter
                $tickets = array_filter($all_tickets, function($t) use ($filter, $closed_statuses, $cutoff) {
                    if ($filter === 'open') {
                        return !in_array($t['status'] ?? '', $closed_statuses);
                    } else {
                        return strtotime($t['createdTime'] ?? '') >= $cutoff;
                    }
                });

                if (empty($tickets)) {
                    $label = $filter === 'open' ? 'tickets abiertos' : 'tickets en el último mes';
                    echo "<div class='p-4 text-muted'><i class='fas fa-check-circle mr-2'></i>No hay $label para este cliente.</div>";
                } else {
                    ?>
                    <div class="table-responsive">
                        <table class="table table-hover table-sm mb-0 dataTable">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Asunto</th>
                                    <th>Estado</th>
                                    <th>Prioridad</th>
                                    <th>Asignado</th>
                                    <th>Creado</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($tickets as $ticket):
                                    $tid           = htmlspecialchars($ticket['id'] ?? '', ENT_QUOTES);
                                    $tnum          = intval($ticket['ticketNumber']);
                                    $status        = $ticket['status'] ?? '';
                                    $priority      = $ticket['priority'] ?? '';
                                    $assignee      = $ticket['assignee'] ?? [];
                                    $assignee_name = trim(($assignee['firstName'] ?? '') . ' ' . ($assignee['lastName'] ?? ''));
                                    $badge_class   = $status_classes[$status] ?? 'badge-secondary';
                                    $prio_class    = $priority_classes[$priority] ?? '';
                                    $created       = substr($ticket['createdTime'] ?? '', 0, 10);
                                    $web_url       = urlencode($ticket['webUrl'] ?? '');
                                    $modal_url     = "modals/zoho/ticket_detail.php?ticket_id={$tid}&ticket_num={$tnum}&web_url={$web_url}";
                                ?>
                                <tr class="ajax-modal" data-modal-url="<?php echo $modal_url; ?>" data-modal-size="lg" style="cursor:pointer;">
                                    <td class="text-monospace text-muted">#<?php echo $tnum; ?></td>
                                    <td><?php echo htmlspecialchars($ticket['subject'] ?? '(Sin asunto)', ENT_QUOTES); ?></td>
                                    <td><span class="badge <?php echo $badge_class; ?>"><?php echo htmlspecialchars($status, ENT_QUOTES); ?></span></td>
                                    <td class="<?php echo $prio_class; ?>"><?php echo htmlspecialchars($priority ?: '—', ENT_QUOTES); ?></td>
                                    <td><?php echo htmlspecialchars($assignee_name ?: '—', ENT_QUOTES); ?></td>
                                    <td><?php echo htmlspecialchars($created, ENT_QUOTES); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="px-3 py-2 text-muted small">
                        <?php echo count($tickets); ?> ticket<?php echo count($tickets) != 1 ? 's' : ''; ?>
                        <?php echo $filter === 'open' ? 'abiertos' : 'en el último mes'; ?>
                    </div>
                    <?php
                }
            }
        }
        ?>

    </div>
</div>

<?php require_once "../includes/footer.php"; ?>

<?php
require_once "includes/inc_all_admin.php";
?>

    <div class="card card-dark">
        <div class="card-header py-3">
            <h3 class="card-title"><i class="fas fa-fw fa-headset mr-2"></i>Zoho Desk Integration</h3>
        </div>
        <div class="card-body">
            <form action="post.php" method="post" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?>">

                <p class="text-muted">
                    Connect ITFlow to your Zoho Desk account to display tickets per client.
                    You need a <strong>Server-based OAuth</strong> app registered at
                    <a href="https://api-console.zoho.com/" target="_blank" rel="noopener noreferrer">api-console.zoho.com</a>
                    with scope <code>Desk.tickets.READ</code>.
                </p>

                <div class="form-group">
                    <label>Client ID</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-key"></i></span>
                        </div>
                        <input type="text" class="form-control" name="config_zoho_client_id"
                               placeholder="1000.XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX"
                               value="<?php echo nullable_htmlentities($config_zoho_client_id); ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label>Client Secret</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-lock"></i></span>
                        </div>
                        <input type="password" class="form-control" name="config_zoho_client_secret"
                               placeholder="Leave blank to keep existing secret"
                               value="">
                        <small class="form-text text-muted">Leave blank to keep the existing value.</small>
                    </div>
                </div>

                <div class="form-group">
                    <label>Refresh Token</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-sync"></i></span>
                        </div>
                        <input type="password" class="form-control" name="config_zoho_refresh_token"
                               placeholder="Leave blank to keep existing refresh token"
                               value="">
                        <small class="form-text text-muted">Leave blank to keep the existing value.</small>
                    </div>
                </div>

                <div class="form-group">
                    <label>Organisation ID</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-building"></i></span>
                        </div>
                        <input type="text" class="form-control" name="config_zoho_org_id"
                               placeholder="e.g. 758029195"
                               value="<?php echo nullable_htmlentities($config_zoho_org_id); ?>">
                    </div>
                </div>

                <?php if (!empty($config_zoho_access_token_expires_at)): ?>
                    <div class="alert alert-info py-2">
                        <i class="fas fa-info-circle mr-1"></i>
                        Cached access token expires: <strong><?php echo nullable_htmlentities($config_zoho_access_token_expires_at); ?></strong>
                    </div>
                <?php endif; ?>

                <button type="submit" name="edit_zoho_settings" class="btn btn-primary">
                    <i class="fa fa-check mr-2"></i>Save Zoho Settings
                </button>
            </form>
        </div>
    </div>

<?php require_once "../includes/footer.php"; ?>

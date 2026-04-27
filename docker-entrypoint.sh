#!/bin/bash
set -e

CONFIG=/var/www/html/config.php
CONFIG_VOLUME=/var/www/html/config-volume/config.php

# ---------------------------------------------------------------------------
# config.php persistence strategy (priority order):
#   1. EFS volume copy (config-volume/config.php) — survives all redeploys
#   2. Generate from environment variables — first-run only
# This guarantees installation_id, MFA secrets and credential encryption
# remain consistent across every deploy.
# ---------------------------------------------------------------------------
if [ -f "$CONFIG_VOLUME" ]; then
    echo "[entrypoint] Restoring config.php from EFS volume..."
    cp "$CONFIG_VOLUME" "$CONFIG"
    chown www-data:www-data "$CONFIG"
    chmod 640 "$CONFIG"
    echo "[entrypoint] config.php restored from EFS."
elif [ ! -f "$CONFIG" ]; then
    echo "[entrypoint] Generating config.php from environment variables..."

    # INSTALLATION_ID must be fixed — if not set, warn loudly
    if [ -z "$INSTALLATION_ID" ]; then
        echo "[entrypoint] WARNING: INSTALLATION_ID not set. Generating random ID — MFA and encryption will break on next redeploy!"
        INSTALLATION_ID="$(openssl rand -hex 16)"
    fi

    cat > "$CONFIG" <<EOF
<?php

\$dbhost     = '${DB_HOST:-db}';
\$dbusername = '${DB_USER:?DB_USER is required}';
\$dbpassword = '${DB_PASS:?DB_PASS is required}';
\$database   = '${DB_NAME:-itflow}';

\$mysqli = mysqli_connect(\$dbhost, \$dbusername, \$dbpassword, \$database)
    or die('Database Connection Failed');

\$config_app_name      = '${APP_NAME:-ITFlow}';
\$config_base_url      = '${APP_URL:?APP_URL is required}';
\$config_https_only    = ${HTTPS_ONLY:-FALSE};
\$config_enable_setup  = ${SETUP_ENABLED:-0};
\$repo_branch          = 'master';
\$installation_id      = '${INSTALLATION_ID}';
EOF

    chown www-data:www-data "$CONFIG"
    chmod 640 "$CONFIG"
    # Persist to EFS immediately so future containers use this copy
    cp "$CONFIG" "$CONFIG_VOLUME" 2>/dev/null || true
    echo "[entrypoint] config.php created and saved to EFS volume."
else
    echo "[entrypoint] config.php already exists — skipping generation."
fi

# ---------------------------------------------------------------------------
# Wait for MySQL to be ready before starting Apache
# ---------------------------------------------------------------------------
echo "[entrypoint] Waiting for database at ${DB_HOST:-db}:3306..."
until php -r "
\$c = @mysqli_connect('${DB_HOST:-db}', '${DB_USER}', '${DB_PASS}', '${DB_NAME:-itflow}');
if (\$c) { exit(0); } exit(1);
" 2>/dev/null; do
    echo "[entrypoint] DB not ready yet, retrying in 3s..."
    sleep 3
done
echo "[entrypoint] Database is ready."

# ---------------------------------------------------------------------------
# Importar schema DB si las tablas no existen (primer run)
# ---------------------------------------------------------------------------
echo "[entrypoint] Checking database schema..."
TABLES=$(php -r "
\$c = @mysqli_connect('${DB_HOST:-db}', '${DB_USER}', '${DB_PASS}', '${DB_NAME:-itflow}');
if (!\$c) { echo '0'; exit; }
\$r = mysqli_query(\$c, \"SELECT COUNT(*) AS n FROM information_schema.tables WHERE table_schema='${DB_NAME:-itflow}'\");
\$row = mysqli_fetch_assoc(\$r);
echo \$row['n'];
" 2>/dev/null)

if [ "$TABLES" -lt "10" ]; then
    echo "[entrypoint] Schema not found ($TABLES tables). Importing db.sql..."
    php -r "
\$c = mysqli_connect('${DB_HOST:-db}', '${DB_USER}', '${DB_PASS}', '${DB_NAME:-itflow}');
if (!\$c) { echo 'ERROR: Cannot connect'; exit(1); }
\$sql = file_get_contents('/var/www/html/db.sql');
// Split on semicolons respecting delimiters
\$statements = array_filter(array_map('trim', explode(\";\n\", \$sql)));
\$errors = 0;
foreach (\$statements as \$stmt) {
    if (empty(\$stmt) || strpos(\$stmt, '--') === 0) continue;
    if (!mysqli_query(\$c, \$stmt)) {
        \$errors++;
    }
}
echo \$errors === 0 ? 'Schema imported OK' : \"Import finished with \$errors warnings\";
echo PHP_EOL;
" 2>&1
    echo "[entrypoint] Schema import complete."
else
    echo "[entrypoint] Schema already exists ($TABLES tables). Skipping import."
fi

# ---------------------------------------------------------------------------
# Start cron daemon
# ---------------------------------------------------------------------------
echo "[entrypoint] Starting cron..."
service cron start

# ---------------------------------------------------------------------------
# Hand off to Apache
# ---------------------------------------------------------------------------
echo "[entrypoint] Starting Apache..."
exec apache2-foreground

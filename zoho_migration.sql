-- Zoho Desk Integration Migration
-- Run once on your MySQL/MariaDB database

ALTER TABLE settings
    ADD COLUMN IF NOT EXISTS config_zoho_client_id varchar(200) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS config_zoho_client_secret varchar(200) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS config_zoho_refresh_token text DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS config_zoho_org_id varchar(50) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS config_zoho_access_token text DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS config_zoho_access_token_expires_at datetime DEFAULT NULL;

ALTER TABLE clients
    ADD COLUMN IF NOT EXISTS client_zoho_account_id varchar(100) DEFAULT NULL;

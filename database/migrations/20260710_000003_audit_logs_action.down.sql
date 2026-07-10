DROP INDEX idx_audit_logs_action_created_at ON audit_logs;
ALTER TABLE audit_logs DROP COLUMN action;


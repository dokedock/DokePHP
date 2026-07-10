ALTER TABLE audit_logs
ADD COLUMN action VARCHAR(64) NOT NULL DEFAULT '';

CREATE INDEX idx_audit_logs_action_created_at ON audit_logs (action, created_at);


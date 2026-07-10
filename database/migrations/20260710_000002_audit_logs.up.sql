CREATE TABLE IF NOT EXISTS audit_logs (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  request_id VARCHAR(64) NOT NULL DEFAULT '',
  scope VARCHAR(32) NOT NULL DEFAULT '',
  actor_uid VARCHAR(64) NULL,
  actor_identity_id BIGINT NULL,
  ip VARCHAR(64) NOT NULL DEFAULT '',
  method VARCHAR(16) NOT NULL DEFAULT '',
  path VARCHAR(255) NOT NULL DEFAULT '',
  status_code INT NOT NULL DEFAULT 0,
  success TINYINT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  meta TEXT NULL,
  INDEX idx_audit_logs_request_id (request_id),
  INDEX idx_audit_logs_scope_created_at (scope, created_at),
  INDEX idx_audit_logs_actor_uid_created_at (actor_uid, created_at)
);


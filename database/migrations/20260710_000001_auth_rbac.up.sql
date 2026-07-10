CREATE TABLE IF NOT EXISTS auth_identities (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  uid VARCHAR(64) NOT NULL UNIQUE,
  name VARCHAR(128) NULL,
  status TINYINT NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL
);

CREATE TABLE IF NOT EXISTS auth_tokens (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  identity_id BIGINT NOT NULL,
  token_hash CHAR(64) NOT NULL UNIQUE,
  token_prefix VARCHAR(16) NOT NULL DEFAULT '',
  expires_at DATETIME NULL,
  revoked_at DATETIME NULL,
  last_used_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  meta TEXT NULL,
  INDEX idx_auth_tokens_identity_id (identity_id),
  INDEX idx_auth_tokens_prefix (token_prefix)
);

CREATE TABLE IF NOT EXISTS rbac_roles (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  name VARCHAR(64) NOT NULL UNIQUE,
  title VARCHAR(128) NULL,
  status TINYINT NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL
);

CREATE TABLE IF NOT EXISTS rbac_permissions (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  name VARCHAR(128) NOT NULL UNIQUE,
  title VARCHAR(128) NULL,
  status TINYINT NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL
);

CREATE TABLE IF NOT EXISTS rbac_role_permissions (
  role_id BIGINT NOT NULL,
  permission_id BIGINT NOT NULL,
  PRIMARY KEY (role_id, permission_id),
  INDEX idx_rbac_rp_permission_id (permission_id)
);

CREATE TABLE IF NOT EXISTS rbac_identity_roles (
  identity_id BIGINT NOT NULL,
  role_id BIGINT NOT NULL,
  PRIMARY KEY (identity_id, role_id),
  INDEX idx_rbac_ir_role_id (role_id)
);


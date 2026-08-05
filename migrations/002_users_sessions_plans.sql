PRAGMA foreign_keys = OFF;

CREATE TABLE IF NOT EXISTS users_new (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    email TEXT UNIQUE,
    password_hash TEXT,
    plan TEXT NOT NULL DEFAULT 'free' CHECK (plan IN ('free', 'paid', 'admin')),
    name TEXT,
    last_login_at TEXT,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
);

INSERT INTO users_new (id, email, password_hash, plan, name, last_login_at, created_at, updated_at)
SELECT id, email, password_hash,
       CASE WHEN plan IN ('free', 'paid', 'admin') THEN plan ELSE 'free' END,
       name,
       NULL,
       created_at, updated_at
FROM users;

DROP TABLE users;
ALTER TABLE users_new RENAME TO users;

CREATE TABLE IF NOT EXISTS sessions_new (
    id TEXT PRIMARY KEY,
    user_id INTEGER,
    data TEXT,
    expires_at TEXT NOT NULL,
    created_at TEXT NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

INSERT INTO sessions_new (id, user_id, data, expires_at, created_at)
SELECT id, user_id, data, expires_at, created_at FROM sessions;

DROP TABLE sessions;
ALTER TABLE sessions_new RENAME TO sessions;

CREATE TABLE IF NOT EXISTS api_tokens_new (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER,
    token_hash TEXT NOT NULL UNIQUE,
    label TEXT,
    last_used_at TEXT,
    expires_at TEXT,
    created_at TEXT NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

INSERT INTO api_tokens_new (id, user_id, token_hash, label, last_used_at, expires_at, created_at)
SELECT id, user_id, token_hash, label, last_used_at, expires_at, created_at FROM api_tokens;

DROP TABLE api_tokens;
ALTER TABLE api_tokens_new RENAME TO api_tokens;

CREATE UNIQUE INDEX IF NOT EXISTS idx_users_email_lower ON users(lower(email));

INSERT INTO users (email, password_hash, plan, name, created_at, updated_at)
SELECT 'legacy/import', NULL, 'admin', 'Legacy import', datetime('now'), datetime('now')
WHERE NOT EXISTS (SELECT 1 FROM users WHERE lower(email) = lower('legacy/import'));

UPDATE properties
SET user_id = (SELECT id FROM users WHERE lower(email) = lower('legacy/import') LIMIT 1),
    visibility = 'shared'
WHERE user_id IS NULL;

PRAGMA foreign_keys = ON;

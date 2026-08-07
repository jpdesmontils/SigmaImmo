CREATE TABLE IF NOT EXISTS cache_entries (
    cache_key TEXT PRIMARY KEY,
    value_json TEXT NOT NULL,
    expires_at INTEGER NOT NULL,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
);

CREATE INDEX IF NOT EXISTS idx_cache_entries_expires_at ON cache_entries(expires_at);

-- Archive exhaustif des fichiers historiques qui n'ont pas de table métier dédiée.
-- Cette table garantit que la suppression de data/ ne détruit aucune donnée.
CREATE TABLE IF NOT EXISTS legacy_data_files (
    relative_path TEXT PRIMARY KEY,
    contents BLOB NOT NULL,
    modified_at INTEGER,
    imported_at TEXT NOT NULL
);

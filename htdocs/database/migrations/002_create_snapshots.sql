CREATE TABLE IF NOT EXISTS snapshots (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    site_id INTEGER NOT NULL REFERENCES sites(id) ON DELETE CASCADE,
    content_hash TEXT NOT NULL,
    normalized_content TEXT NOT NULL,
    raw_length INTEGER NOT NULL,
    similarity REAL,
    is_signature INTEGER NOT NULL DEFAULT 0,
    triggered_by TEXT NOT NULL DEFAULT 'cron',
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE INDEX IF NOT EXISTS idx_snapshots_site_id ON snapshots(site_id);

CREATE TABLE IF NOT EXISTS plugin_updates (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    plugin_name TEXT NOT NULL,
    version TEXT,
    site_url TEXT,
    raw_excerpt TEXT,
    detected_at TEXT NOT NULL DEFAULT (datetime('now'))
);

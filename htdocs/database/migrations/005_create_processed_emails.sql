CREATE TABLE IF NOT EXISTS processed_emails (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    message_id TEXT NOT NULL UNIQUE,
    subject TEXT,
    classification TEXT NOT NULL,
    processed_at TEXT NOT NULL DEFAULT (datetime('now'))
);

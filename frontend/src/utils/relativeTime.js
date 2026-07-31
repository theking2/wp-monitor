// Upper-bound relative time for compact table display (e.g. "< 10 min", "< 3 hours").
// The detail view shows the full timestamp; this is deliberately coarse, not exact.
const BUCKETS = [
  { ms: 60 * 1000, label: '< 1 min' },
  { ms: 10 * 60 * 1000, label: '< 10 min' },
  { ms: 30 * 60 * 1000, label: '< 30 min' },
  { ms: 60 * 60 * 1000, label: '< 1 hour' },
  { ms: 3 * 60 * 60 * 1000, label: '< 3 hours' },
  { ms: 6 * 60 * 60 * 1000, label: '< 6 hours' },
  { ms: 12 * 60 * 60 * 1000, label: '< 12 hours' },
  { ms: 24 * 60 * 60 * 1000, label: '< 1 day' },
  { ms: 3 * 24 * 60 * 60 * 1000, label: '< 3 days' },
  { ms: 7 * 24 * 60 * 60 * 1000, label: '< 1 week' },
  { ms: 30 * 24 * 60 * 60 * 1000, label: '< 1 month' },
]

export function formatRelativeUpperBound(dateString) {
  if (!dateString) {
    return '—'
  }

  // last_checked_at is written via PHP's date('c') — already ISO 8601 with an offset (has "T").
  // Other timestamps in this API come from SQLite's datetime('now') — "YYYY-MM-DD HH:MM:SS",
  // UTC but with no timezone marker — which needs both fixed up before Date() can parse it.
  const iso = dateString.includes('T') ? dateString : dateString.replace(' ', 'T') + 'Z'
  const then = new Date(iso)
  const diff = Date.now() - then.getTime()

  if (Number.isNaN(diff)) {
    return '—'
  }

  if (diff < 0) {
    return '< 1 min'
  }

  for (const bucket of BUCKETS) {
    if (diff < bucket.ms) {
      return bucket.label
    }
  }

  return '> 1 month'
}

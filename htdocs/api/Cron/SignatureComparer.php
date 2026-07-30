<?php

namespace App\Cron;

use App\Core\Logger;
use App\Models\Alert;
use App\Models\Site;
use App\Models\Snapshot;

final class SignatureComparer
{
    private float $threshold;

    public function __construct(?float $threshold = null)
    {
        $this->threshold = $threshold ?? (float) (getenv('SIGNATURE_SIMILARITY_THRESHOLD') ?: 0.97);
    }

    /** @param array<string, mixed> $site @return array<string, mixed> */
    public function compareAndStore(array $site, string $normalizedContent, string $triggeredBy = 'cron'): array
    {
        $logger = Logger::get();
        $hash = hash('sha256', $normalizedContent);
        $signature = Snapshot::latestSignature($site['id']);

        if ($signature === null) {
            $snapshot = Snapshot::create($site['id'], $hash, $normalizedContent, null, true, $triggeredBy);
            Site::updateStatus($site['id'], 'ok');
            $logger->info('First signature captured', ['site_id' => $site['id'], 'url' => $site['url'] ?? null]);

            return [
                'site_id' => $site['id'],
                'status' => 'ok',
                'tampered' => false,
                'first_signature' => true,
                'similarity' => 1.0,
                'snapshot_id' => $snapshot['id'],
            ];
        }

        if (hash_equals($signature['content_hash'], $hash)) {
            $snapshot = Snapshot::create($site['id'], $hash, $normalizedContent, 1.0, false, $triggeredBy);
            Site::updateStatus($site['id'], 'ok');
            $logger->debug('Content unchanged', ['site_id' => $site['id']]);

            return [
                'site_id' => $site['id'],
                'status' => 'ok',
                'tampered' => false,
                'similarity' => 1.0,
                'snapshot_id' => $snapshot['id'],
            ];
        }

        similar_text($signature['normalized_content'], $normalizedContent, $percent);
        $similarity = round($percent / 100, 4);
        $tampered = $similarity < $this->threshold;

        $snapshot = Snapshot::create($site['id'], $hash, $normalizedContent, $similarity, false, $triggeredBy);

        if (!$tampered) {
            // Within tolerance: treat the drift as legitimate and roll it forward as the new
            // accepted signature, so genuine small edits don't keep re-triggering alerts.
            Snapshot::promoteToSignature($snapshot['id']);
            Site::updateStatus($site['id'], 'ok');
            $logger->info('Content drift within tolerance, signature updated', [
                'site_id' => $site['id'],
                'similarity' => $similarity,
            ]);

            return [
                'site_id' => $site['id'],
                'status' => 'ok',
                'tampered' => false,
                'similarity' => $similarity,
                'snapshot_id' => $snapshot['id'],
            ];
        }

        $diffSummary = $this->summarizeDiff($signature['normalized_content'], $normalizedContent);
        Site::updateStatus($site['id'], 'tampered');
        $alert = Alert::create($site['id'], $snapshot['id'], $similarity, $diffSummary);
        $logger->warning('Possible tampering detected', [
            'site_id' => $site['id'],
            'url' => $site['url'] ?? null,
            'similarity' => $similarity,
            'alert_id' => $alert['id'],
        ]);

        return [
            'site_id' => $site['id'],
            'status' => 'tampered',
            'tampered' => true,
            'similarity' => $similarity,
            'snapshot_id' => $snapshot['id'],
            'alert_id' => $alert['id'],
            'diff_summary' => $diffSummary,
        ];
    }

    private function summarizeDiff(string $before, string $after, int $maxLen = 600): string
    {
        $beforeWords = preg_split('/\s+/', $before) ?: [];
        $afterWords = preg_split('/\s+/', $after) ?: [];

        $added = array_slice(array_values(array_diff($afterWords, $beforeWords)), 0, 40);
        $removed = array_slice(array_values(array_diff($beforeWords, $afterWords)), 0, 40);

        $summary = '';
        if ($removed !== []) {
            $summary .= '- ' . implode(' ', $removed) . "\n";
        }
        if ($added !== []) {
            $summary .= '+ ' . implode(' ', $added);
        }

        return mb_substr(trim($summary), 0, $maxLen);
    }
}

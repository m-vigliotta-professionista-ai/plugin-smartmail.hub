<?php
if (!defined('ABSPATH')) {
    exit;
}

class V24_SMH_Sync_Job_Runner
{
    public function run(int $limit = 3): int
    {
        $jobs = new V24_SMH_Job_Repository();
        $jobs->release_stale_running_jobs();
        $processed = 0;

        while ($processed < max(1, $limit)) {
            $job = $jobs->claim_next();
            if (!$job) {
                break;
            }

            try {
                if ($job['job_type'] === 'mail_sync' && !empty($job['account_id'])) {
                    (new V24_SMH_Mail_Sync_Service())->sync_account((int) $job['account_id'], (int) $job['id']);
                }
                $jobs->done((int) $job['id']);
            } catch (Throwable $e) {
                $jobs->failed((int) $job['id'], $e->getMessage());
            }

            $processed++;
        }

        return $processed;
    }
}

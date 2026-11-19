<?php
/**
 * Sends the next batch of queued profile e-mails (confirmation mails and the updates staff queued in admin/updates.php).
 * Command line only - it refuses to run from the web. Put it in the host's cron list, for example every 10 minutes:
 *
 *     php /home/USER/public_html/cron/process-email-queue.php
 *
 * One run sends at most one batch (setting profile_email_batch, default 20), so the host's hourly mail limit is respected whatever the
 * queue holds. Options:  --limit=N  a different batch size (1-200)    --all  keep going until every queued mail was tried once
 * Finished rows older than 30 days are deleted on every run. Two runs at the same time do not clash: the second one exits at once.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('This script runs from the command line only.');
}

require_once __DIR__ . '/../includes/mail-updates.php';

$all = false;
$limit = 0;
foreach (array_slice($argv ?? [], 1) as $arg) {
    if ($arg === '--all') {
        $all = true;
    } elseif (preg_match('/\A--limit=(\d{1,3})\z/', $arg, $m)) {
        $limit = (int) $m[1];
    } else {
        fwrite(STDERR, "Unknown option: $arg\n");
        exit(2);
    }
}

$lock = fopen(ugc_temp_file('email-queue-cron'), 'c');
if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
    echo "Another run is in progress; nothing to do.\n";
    exit(0);
}

try {
    ensure_profile_schema();
    if (!profile_schema_ready()) {
        fwrite(STDERR, "The profile tables are missing. Run database/profiles.sql first.\n");
        exit(1);
    }
    $total = ['sent' => 0, 'failed' => 0, 'retry' => 0, 'skipped' => 0];
    $after = 0;
    do {
        $r = profile_process_queue($limit, $after);
        $after = $r['last_id'];
        foreach ($total as $key => $n) {
            $total[$key] = $n + $r[$key];
        }
    } while ($all && $r['processed'] > 0);

    $pruned = mail_queue_prune();
    $queue = mail_queue_status();
    echo date('Y-m-d H:i:s') . ' sent ' . $total['sent'] . ', failed ' . $total['failed'] . ', will retry ' . $total['retry']
        . ', skipped ' . $total['skipped'] . '; still queued ' . $queue['queued'] . '; old rows deleted ' . $pruned . "\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'process-email-queue failed: ' . $e->getMessage() . "\n");
    exit(1);
} finally {
    flock($lock, LOCK_UN);
    fclose($lock);
}

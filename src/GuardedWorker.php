<?php
/**
 * GuardedWorker - one-shot token-gated patch worker for shared hosting.
 *
 * Drop this file plus a small worker script onto a cPanel host (no shell, no SSH),
 * hit the worker URL once with its token, and the patch applies with guards:
 * every needle must match exactly once, the result is verified, failures restore
 * the backup automatically, and the worker deletes itself after reporting.
 *
 * Zero dependencies. PHP 7.4+.
 * https://github.com/bryanhamiltondev/guarded-deploy-workers
 */

class GuardedWorker
{
    /** @var string */
    private $token;

    /** @var callable|null Renders the site's real 404 when the token is missing/wrong. */
    private $notFound;

    public function __construct(string $token, ?callable $notFound = null)
    {
        $this->token = $token;
        $this->notFound = $notFound;
    }

    /** Abort with the site's real 404 when the token is wrong. Call first. */
    public function authorize(): void
    {
        if (!isset($_GET['t']) || !hash_equals($this->token, (string) $_GET['t'])) {
            http_response_code(404);
            if ($this->notFound) {
                ($this->notFound)();
            } elseif (is_file('404.php')) {
                include '404.php';
            } else {
                echo '404';
            }
            exit;
        }
        header('Content-Type: text/plain');
    }

    /** Read the target file. Dies with a clear message on failure. */
    public function read(string $path): string
    {
        $src = @file_get_contents($path);
        if ($src === false) {
            $this->fail('READ FAIL: ' . basename($path));
        }
        return $src;
    }

    /** Every needle must appear EXACTLY once in $src, or we die before any write. */
    public function guard(string $src, array $needles): void
    {
        foreach ($needles as $needle) {
            $count = substr_count($src, $needle);
            if ($count !== 1) {
                $this->fail('NEEDLE AMBIGUOUS (count=' . $count . '): ' . substr($needle, 0, 60) . ' - ABORT, nothing written');
            }
        }
    }

    public function backup(string $path, string $tag): string
    {
        $backup = $path . '.bak-' . $tag . '.bak';
        if (!@copy($path, $backup)) {
            $this->fail('BACKUP FAIL: ' . basename($path));
        }
        return $backup;
    }

    /** Apply needle=>replacement pairs, write, verify each landed; restore on any failure. */
    public function apply(string $path, string $backup, string $src, array $replacements, array $mustContain = array()): void
    {
        $patched = str_replace(array_keys($replacements), array_values($replacements), $src);
        if (@file_put_contents($path, $patched) === false) {
            $this->restore($path, $backup, 'WRITE FAIL');
        }
        $chk = @file_get_contents($path);
        foreach ($replacements as $needle => $replacement) {
            if ($chk === false || substr_count($chk, $replacement) !== 1 || substr_count($chk, $needle) !== 0) {
                $this->restore($path, $backup, 'VERIFY FAIL: replacement did not land - restored');
            }
        }
        foreach ($mustContain as $marker) {
            if ($chk === false || strpos($chk, $marker) === false) {
                $this->restore($path, $backup, 'VERIFY FAIL: marker missing: ' . $marker . ' - restored');
            }
        }
    }

    public function restore(string $path, string $backup, string $why): void
    {
        @copy($backup, $path);
        $this->fail($why . ' - RESTORED FROM BACKUP');
    }

    /** Report success, delete the worker script (and backup unless kept), then exit. */
    public function done(string $workerFile, string $backup, string $message, bool $keepBackup = false): void
    {
        echo $message . "\n";
        if (!$keepBackup && is_file($backup)) {
            @unlink($backup);
            echo "backup self-cleaned\n";
        }
        @unlink($workerFile);
        echo "done - worker self-deleted\n";
        exit;
    }

    private function fail(string $message): void
    {
        echo $message . "\n";
        exit;
    }
}

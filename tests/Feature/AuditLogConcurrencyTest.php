<?php

use App\Models\AuditLogEntry;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// R-01 follow-up (docs/project-memory/10-risk-register.md): the existing
// AuditLogGrantEnforcementTest.php proves the DB-grant half of the fix
// (UPDATE/DELETE genuinely revoked); it says nothing about the advisory
// lock AuditLogger::record() added to replace ->lockForUpdate() (which
// stopped working once UPDATE was revoked, since Postgres requires the
// UPDATE privilege for SELECT ... FOR UPDATE too). This test proves that
// replacement lock actually does its job: genuinely concurrent writers —
// not just sequential calls from one PHP process — must still produce a
// single, unbroken, gapless hash chain, with no two entries forked off
// the same prev_hash.
//
// "Genuinely concurrent" here means real, separate OS processes: this
// forks real children (pcntl_fork, installed in CI's test job and in
// docker/Dockerfile for pest-plugin-browser's benefit — confirmed
// present via `php -m` before writing this test, not assumed), each
// opening its own fresh Postgres connection and calling
// AuditLogger::record() for real. A single PHP process issuing
// "concurrent-looking" calls in a loop would not exercise
// pg_advisory_xact_lock at all, since nothing would actually contend for
// it — Postgres locks serialize separate sessions, not separate function
// calls in the same session.
//
// Side effect this test must manage itself, unlike every other test in
// this suite: RefreshDatabase wraps each test in a transaction on *this*
// process's own connection and rolls it back afterward, but the forked
// children below commit through entirely separate Postgres sessions —
// RefreshDatabase's rollback never sees those commits, because it only
// controls its own session's transaction. Left alone, this test would
// permanently leave WORKER_COUNT real rows in whatever database it runs
// against every time it runs. The afterEach() below cleans those rows up
// explicitly via the schema-owning `pgsql_migrate` connection (the same
// connection AuditLogEntry's append-only model guard is already
// deliberately bypassed through elsewhere in this suite, e.g.
// ConsentCaptureTest.php / AuditChainAnchorTest.php), leaving the
// database exactly as this test found it (aside from the sequence
// generator's counter advancing, an expected, harmless Postgres
// characteristic — sequences are never guaranteed gapless in the first
// place, and nothing in this codebase assumes they are).
beforeEach(function () {
    if (! extension_loaded('pcntl')) {
        $this->markTestSkipped('pcntl extension not available in this environment.');
    }
});

$marker = 'test.r01-concurrency.'.getmypid();

afterEach(function () use (&$marker) {
    DB::connection('pgsql_migrate')->table('audit_log_entries')->where('action', $marker)->delete();
});

it('serializes genuinely concurrent audit-log writers into one unbroken, gapless chain', function () use (&$marker) {
    $workerCount = 8;
    $pids = [];

    for ($i = 0; $i < $workerCount; $i++) {
        $pid = pcntl_fork();

        if ($pid === -1) {
            throw new RuntimeException('pcntl_fork failed — cannot run this concurrency test.');
        }

        if ($pid === 0) {
            // Child process. It inherited a copy of the parent's PDO
            // connection — same underlying kernel socket, not a fresh
            // one. Two things must both be avoided, discovered the hard
            // way while writing this test (the first version below
            // reliably killed the parent's own connection with "server
            // closed the connection unexpectedly"):
            //
            // 1. Never disconnect/purge the inherited 'pgsql' connection
            //    in the child. Laravel's disconnect() drops the PHP-level
            //    reference to the PDO object; if that was the last
            //    reference, PDO's destructor runs immediately, and for
            //    pdo_pgsql that means a real libpq PQfinish() — an actual
            //    Terminate message sent over the wire. Postgres then
            //    closes that connection from its side, for *every*
            //    process still holding a duplicated fd to it, including
            //    the parent, still mid-test.
            // 2. Never let the child reach normal PHP shutdown either
            //    (a plain exit(), or falling off the end of the
            //    callback). Zend's request-shutdown sequence destructs
            //    every remaining object, including that same untouched,
            //    inherited 'pgsql' connection — triggering the identical
            //    PQfinish() at that point instead.
            //
            // The fix: leave the inherited 'pgsql' connection completely
            // alone (never call it, disconnect it, or let it be
            // destructed normally), do the real write through a
            // separately-named connection that has never existed before
            // in this process (so it opens a genuinely new, independent
            // TCP connection), and terminate via SIGKILL — a raw signal,
            // not exit() — so Zend's destructor sweep for this process
            // never runs at all. A bare kernel close(fd) on process
            // teardown (which SIGKILL still causes) never sends any
            // protocol bytes, unlike PQfinish(); it's exactly the kind
            // of silent close that's actually safe to leave to the OS.
            config(['database.connections.pgsql_audit_concurrency_child' => config('database.connections.pgsql')]);
            config(['database.default' => 'pgsql_audit_concurrency_child']);

            try {
                app(AuditLogger::class)->record(
                    actorType: 'system',
                    actor: null,
                    action: $marker,
                    resourceType: 'test_resource',
                    resourceId: (string) Str::uuid(),
                );
            } catch (Throwable $e) {
                fwrite(STDERR, "child $i failed: {$e->getMessage()}".PHP_EOL);
            }

            posix_kill(posix_getpid(), SIGKILL);
        }

        $pids[] = $pid;
    }

    // Every child self-terminates via SIGKILL (see above), so the only
    // thing to verify here is that each one was reaped — whether the
    // write itself actually succeeded and produced a correctly-chained
    // entry is verified below, against the database's real state, not
    // against a per-child exit code.
    foreach ($pids as $pid) {
        pcntl_waitpid($pid, $status);
        expect(pcntl_wifsignaled($status) && pcntl_wtermsig($status) === SIGKILL)->toBeTrue(
            "Child process $pid did not terminate the expected way (status: $status)."
        );
    }

    // The parent's own connection is mid-transaction (RefreshDatabase)
    // under Postgres's default READ COMMITTED isolation, so this query
    // sees every child's commit made since the transaction started —
    // no reconnect needed, and reconnecting here would tear down
    // RefreshDatabase's own wrapping transaction.
    $entries = AuditLogEntry::query()
        ->where('action', $marker)
        ->orderBy('sequence')
        ->get();

    expect($entries)->toHaveCount($workerCount, 'Expected every concurrent writer to have committed exactly one entry.');

    // Gapless: Postgres's own SEQUENCE guarantees uniqueness regardless
    // of the advisory lock, so this alone would pass even if the lock
    // were entirely removed — it is not, by itself, evidence the lock
    // works. It is asserted anyway as a sanity check that no writer's
    // insert silently vanished.
    $sequences = $entries->pluck('sequence')->values();

    for ($i = 1; $i < $sequences->count(); $i++) {
        expect($sequences[$i])->toBe($sequences[$i - 1] + 1);
    }

    // The actual proof: replay this batch against the entry that
    // immediately precedes it in the *global* chain (not just among
    // these $workerCount rows), confirming no two concurrent writers
    // forked off the same prev_hash. Without the advisory lock, two
    // concurrent transactions can both read the same "current latest"
    // row before either commits — Postgres's SEQUENCE still hands them
    // distinct, ordered sequence numbers (that part can't fork), but
    // both would then compute their entry_hash from the *same* prevHash,
    // so the later-by-sequence entry's prev_hash would not match the
    // entry_hash of the row immediately before it. That mismatch is
    // exactly what this loop checks for.
    $anchor = AuditLogEntry::query()->where('sequence', $sequences->first() - 1)->first();
    $expectedPrevHash = $anchor?->entry_hash ?? AuditLogger::genesisHash();

    foreach ($entries as $entry) {
        expect($entry->prev_hash)->toBe(
            $expectedPrevHash,
            "Entry at sequence {$entry->sequence} has prev_hash that doesn't match the entry immediately before it — the chain forked under concurrency."
        );
        $expectedPrevHash = $entry->entry_hash;
    }

    // Strongest available proof: a full replay of the entire chain, not
    // just this test's own slice of it, must still verify end to end.
    expect(app(AuditLogger::class)->verifyChain())
        ->toBe(['valid' => true, 'brokenAtSequence' => null]);
});

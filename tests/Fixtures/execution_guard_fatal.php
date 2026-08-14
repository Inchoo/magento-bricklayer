<?php

/**
 * Copyright (c) Inchoo. All rights reserved.
 * See LICENSE.txt for license details.
 *
 * Fixture for ExecutionGuardTest: dies on a fatal error while a request is
 * in flight, so the test can assert the shutdown hook writes a JSON-RPC
 * tool-error response to STDOUT. Usage: php execution_guard_fatal.php <autoload>
 */

declare(strict_types=1);

$autoload = $_SERVER['argv'][1] ?? null;
if ($autoload === null) {
    fwrite(STDERR, "Usage: php execution_guard_fatal.php <autoload>\n");
    exit(1);
}

require $autoload;

\Inchoo\MagentoBricklayer\Support\ExecutionGuard::beginRequest('code-runner', 42);

// Redeclare via two evals → uncatchable fatal at runtime (mirrors what user
// code can do to the server process).
eval('function bricklayerGuardFixtureBoom() {}');
eval('function bricklayerGuardFixtureBoom() {}');

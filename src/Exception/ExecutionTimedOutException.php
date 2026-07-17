<?php

/**
 * Copyright (c) Inchoo. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Inchoo\MagentoBricklayer\Exception;

/**
 * Thrown by ExecutionGuard's SIGALRM handler when a code-runner execution
 * exceeds its wall-clock timeout. Unlike set_time_limit()'s uncatchable fatal,
 * this propagates as a normal exception, so the transaction rolls back and the
 * MCP server process survives the timeout.
 */
class ExecutionTimedOutException extends BricklayerException
{
}

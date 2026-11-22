<?php

declare(strict_types=1);

namespace Agilis\Ads\Exception;

/**
 * The request conflicts with the current state, for example an Idempotency-Key still in use (HTTP 409).
 */
class ConflictException extends AgilisException {}

<?php

declare(strict_types=1);

namespace Agilis\Ads\Exception;

/**
 * No response arrived: the connection failed or timed out, after every retry.
 */
class ConnectionException extends AgilisException {}

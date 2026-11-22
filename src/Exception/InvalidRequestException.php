<?php

declare(strict_types=1);

namespace Agilis\Ads\Exception;

/**
 * The request was refused: HTTP 400 or 422, or HTTP 200 with a return_code starting with E.
 */
class InvalidRequestException extends AgilisException {}

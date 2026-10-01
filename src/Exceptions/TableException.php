<?php

/**
 * Table domain exception.
 *
 * Thrown when a table label or state move is invalid.
 *
 * @package SmoothRestaurant
 */

declare(strict_types=1);

namespace SmoothRestaurant\Exceptions;

/**
 * Class TableException
 *
 * Carries an optional machine-readable error code so transport layers can map
 * a failure to a status without matching on the message text. Transport-agnostic
 * code names live with the controller that raises them; the default is empty,
 * which read as "invalid" at the REST boundary.
 */
final class TableException extends SmoothException
{
    /**
     * @param string $message   Human-readable failure description.
     * @param string $errorCode Machine-readable code, empty when not raised by a transport layer.
     */
    public function __construct(string $message, private readonly string $errorCode = '')
    {
        parent::__construct($message);
    }

    /**
     * Machine-readable error code, empty when none was supplied.
     *
     * @return string
     */
    public function errorCode(): string
    {
        return $this->errorCode;
    }
}

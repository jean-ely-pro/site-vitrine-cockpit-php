<?php

declare(strict_types=1);

namespace App\Cockpit;

/** Raised when the content cannot be read — network, key or Cockpit error. */
final class ContentUnavailable extends \RuntimeException
{
}

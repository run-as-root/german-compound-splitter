<?php

declare(strict_types=1);

namespace RunAsRoot\GermanCompoundSplitter\Exception;

/**
 * Thrown when an FST data file is missing, unreadable or not a valid FST (version 3).
 */
final class InvalidFstException extends GermanCompoundSplitterException
{
}

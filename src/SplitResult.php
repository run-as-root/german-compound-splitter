<?php

declare(strict_types=1);

namespace RunAsRoot\GermanCompoundSplitter;

/**
 * One candidate split of a compound word. Both parts are title-cased.
 */
final readonly class SplitResult
{
    public function __construct(
        public float $score,
        public string $part1,
        public string $part2
    ) {
    }

    /**
     * @return array{0: float, 1: string, 2: string} Same shape as the Python API tuple.
     */
    public function toArray(): array
    {
        return [$this->score, $this->part1, $this->part2];
    }
}

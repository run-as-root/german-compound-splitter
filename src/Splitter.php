<?php

declare(strict_types=1);

namespace RunAsRoot\GermanCompoundSplitter;

use RunAsRoot\GermanCompoundSplitter\Fst\FstMap;

/**
 * Splits German compound words into two parts (port of charsplit-fst / CharSplit).
 *
 * Create one instance and reuse it: the three FST data files (~19 MB) are read once on construction.
 */
final class Splitter
{
    private readonly ScoreCalculator $calculator;

    /**
     * @param string|null $dataDir directory containing suffix.fst, prefix.fst and infix.fst
     *                             (defaults to the data/ directory shipped with this package)
     */
    public function __construct(?string $dataDir = null)
    {
        $dir = rtrim($dataDir ?? dirname(__DIR__) . '/data', '/\\');

        $this->calculator = new ScoreCalculator(new NgramLookup(
            FstMap::fromFile($dir . '/suffix.fst'),
            FstMap::fromFile($dir . '/prefix.fst'),
            FstMap::fromFile($dir . '/infix.fst')
        ));
    }

    /**
     * All candidate splits ordered by score (highest first), like `Splitter.split_compound` in the
     * original library.
     *
     * - A word containing "-" is split at the last hyphen into one result with score 1.0.
     * - A word that cannot be split (shorter than 6 characters) yields [(0.0, Word, Word)].
     *
     * @return list<SplitResult>
     */
    public function split(string $word): array
    {
        $lower = mb_strtolower($word, 'UTF-8');
        $candidates = $this->candidates($lower);

        if ($candidates === []) {
            $title = self::titleCase($lower);

            return [new SplitResult(0.0, $title, $title)];
        }

        return $candidates;
    }

    /**
     * The single best split, or null when the word cannot be split or the best score is below
     * $minScore. Convenience wrapper around split() for callers that only want a decision.
     */
    public function splitBest(string $word, float $minScore = 0.0): ?SplitResult
    {
        $candidates = $this->candidates(mb_strtolower($word, 'UTF-8'));
        if ($candidates === [] || $candidates[0]->score < $minScore) {
            return null;
        }

        return $candidates[0];
    }

    /**
     * @return list<SplitResult> empty when there is no candidate split position
     */
    private function candidates(string $lower): array
    {
        $hyphen = strrpos($lower, '-');
        if ($hyphen !== false) {
            return [new SplitResult(
                1.0,
                self::titleCase(substr($lower, 0, $hyphen)),
                self::titleCase(substr($lower, $hyphen + 1))
            )];
        }

        $chars = mb_str_split($lower, 1, 'UTF-8');
        $count = count($chars);
        $results = [];

        for ($pos = 3, $max = max(0, $count - 2); $pos < $max; $pos++) {
            $score = $this->calculator->score($lower, $pos);
            if ($score === null) {
                continue;
            }
            $results[] = new SplitResult(
                $score,
                self::titleCase(implode('', array_slice($chars, 0, $pos))),
                self::titleCase(implode('', array_slice($chars, $pos)))
            );
        }

        // usort is stable (PHP >= 8.0), matching the stable sort of the original.
        usort($results, static fn (SplitResult $a, SplitResult $b): int => $b->score <=> $a->score);

        return $results;
    }

    /**
     * Uppercases the first character of each hyphen-separated segment (the rest is left as is).
     */
    private static function titleCase(string $s): string
    {
        $segments = explode('-', $s);
        foreach ($segments as $i => $segment) {
            $segments[$i] = mb_strtoupper(mb_substr($segment, 0, 1, 'UTF-8'), 'UTF-8')
                . mb_substr($segment, 1, null, 'UTF-8');
        }

        return implode('-', $segments);
    }
}

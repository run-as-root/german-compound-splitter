<?php

declare(strict_types=1);

namespace RunAsRoot\GermanCompoundSplitter;

/**
 * Scores a candidate split position: score = prefix(part2) - minInfix(part2...) + suffix(part1).
 */
final class ScoreCalculator
{
    /** Probability used when a full part is not found in the suffix/prefix map. */
    private const NOT_FOUND = -1.0;

    public function __construct(private readonly NgramLookup $lookup)
    {
    }

    /**
     * @param string $word lowercase word
     * @param int $splitPos split position in characters
     */
    public function score(string $word, int $splitPos): ?float
    {
        $chars = mb_str_split($word, 1, 'UTF-8');
        if ($splitPos >= count($chars)) {
            return null;
        }

        $preSlice = implode('', array_slice($chars, 0, $splitPos));
        $startSlice = implode('', array_slice($chars, $splitPos));

        $preSlice = FugenS::remove($preSlice) ?? $preSlice;
        $preProb = $this->lookup->suffixProbability($preSlice) ?? self::NOT_FOUND;

        $startKey = FugenS::remove($startSlice) ?? $startSlice;
        $startProb = $this->lookup->prefixProbability($startKey) ?? self::NOT_FOUND;

        // Infix probabilities use the original (unreduced) remainder of the word.
        $inProb = $this->lookup->minInfixProbability($startSlice);

        return $startProb - $inProb + $preProb;
    }
}

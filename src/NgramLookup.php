<?php

declare(strict_types=1);

namespace RunAsRoot\GermanCompoundSplitter;

use RunAsRoot\GermanCompoundSplitter\Fst\FstMap;

/**
 * Ngram probability lookups backed by the three FST maps (suffix, prefix, infix).
 * Values are f64 numbers stored as their raw IEEE-754 bits.
 */
final class NgramLookup
{
    /** Shortest ngram length (in characters) considered for infix probabilities. */
    private const MIN_INFIX_CHARS = 3;

    public function __construct(
        private readonly FstMap $suffixMap,
        private readonly FstMap $prefixMap,
        private readonly FstMap $infixMap
    ) {
    }

    public function suffixProbability(string $ngram): ?float
    {
        $raw = $this->suffixMap->get($ngram);

        return $raw === null ? null : self::toFloat($raw);
    }

    public function prefixProbability(string $ngram): ?float
    {
        $raw = $this->prefixMap->get($ngram);

        return $raw === null ? null : self::toFloat($raw);
    }

    /**
     * Minimum infix probability over all ngrams of at least 3 characters that start at the
     * beginning of $text (i.e. all prefixes of $text of length >= 3). Ngrams that are unknown
     * count as the neutral probability 1.0, which is also the result when there are no ngrams.
     */
    public function minInfixProbability(string $text): float
    {
        $values = $this->infixMap->prefixValues($text, self::MIN_INFIX_CHARS);
        $ngramCount = max(0, mb_strlen($text, 'UTF-8') - (self::MIN_INFIX_CHARS - 1));

        // Any ngram missing from the map (or no ngrams at all) contributes the neutral 1.0.
        $min = count($values) < $ngramCount || $ngramCount === 0 ? 1.0 : null;
        foreach ($values as $raw) {
            $p = self::toFloat($raw);
            if ($min === null || $p < $min) {
                $min = $p;
            }
        }

        return $min ?? 1.0;
    }

    private static function toFloat(int $raw): float
    {
        /** @var array{1: float} $decoded */
        $decoded = unpack('e', pack('P', $raw));

        return $decoded[1];
    }
}

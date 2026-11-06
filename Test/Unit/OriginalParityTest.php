<?php

declare(strict_types=1);

namespace RunAsRoot\GermanCompoundSplitter\Test\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RunAsRoot\GermanCompoundSplitter\Splitter;

/**
 * Compares the complete candidate list with the output of the original Python package
 * (charsplit-fst 0.1.4, `Splitter().split_compound(word)`), scores included.
 */
final class OriginalParityTest extends TestCase
{
    private static ?Splitter $splitter = null;

    /**
     * @param list<array{0: float, 1: string, 2: string}> $expected
     */
    #[DataProvider('referenceProvider')]
    public function testMatchesOriginalImplementation(string $word, array $expected): void
    {
        self::$splitter ??= new Splitter();

        $actual = array_map(static fn ($r) => $r->toArray(), self::$splitter->split($word));

        self::assertSame($expected, $actual);
    }

    /**
     * @return iterable<string, array{string, list<array{0: float, 1: string, 2: string}>}>
     */
    public static function referenceProvider(): iterable
    {
        $reference = json_decode(
            (string) file_get_contents(__DIR__ . '/_files/parity.json'),
            true,
            512,
            JSON_THROW_ON_ERROR
        );
        foreach ($reference as $word => $expected) {
            yield (string) $word => [(string) $word, $expected];
        }
    }
}

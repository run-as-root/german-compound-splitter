<?php

declare(strict_types=1);

namespace RunAsRoot\GermanCompoundSplitter\Test\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RunAsRoot\GermanCompoundSplitter\Exception\InvalidFstException;
use RunAsRoot\GermanCompoundSplitter\Splitter;

final class SplitterTest extends TestCase
{
    private static ?Splitter $splitter = null;

    public function testSplitsAutobahnraststaette(): void
    {
        $results = self::splitter()->split('Autobahnraststätte');

        self::assertSame('Autobahn', $results[0]->part1);
        self::assertSame('Raststätte', $results[0]->part2);
        self::assertEqualsWithDelta(0.7945872450631273, $results[0]->score, 1e-12);
        self::assertSame(['Auto', 'Bahnraststätte'], [$results[1]->part1, $results[1]->part2]);
    }

    #[DataProvider('topSplitProvider')]
    public function testTopSplit(string $word, string $part1, string $part2): void
    {
        $top = self::splitter()->split($word)[0];

        self::assertSame($part1, $top->part1);
        self::assertSame($part2, $top->part2);
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function topSplitProvider(): array
    {
        return [
            'donau' => ['Donaudampfschifffahrt', 'Donau', 'Dampfschifffahrt'],
            'fugen-s' => ['Bundesregierung', 'Bundes', 'Regierung'],
            'upper case input' => ['AUTOBAHNRASTSTÄTTE', 'Autobahn', 'Raststätte'],
            'lower case input' => ['autobahnraststätte', 'Autobahn', 'Raststätte'],
        ];
    }

    public function testResultsAreSortedByScoreDescending(): void
    {
        $scores = array_map(static fn ($r) => $r->score, self::splitter()->split('Gartenmöbelset'));

        $sorted = $scores;
        rsort($sorted);
        self::assertSame($sorted, $scores);
    }

    public function testHyphenSplitsAtLastHyphen(): void
    {
        $results = self::splitter()->split('Bundes-Autobahn-Kapitän');

        self::assertCount(1, $results);
        self::assertSame(1.0, $results[0]->score);
        self::assertSame('Bundes-Autobahn', $results[0]->part1);
        self::assertSame('Kapitän', $results[0]->part2);
    }

    public function testHyphenResultIsTitleCasedPerSegment(): void
    {
        $result = self::splitter()->split('bundes-autobahn')[0];

        self::assertSame(['Bundes', 'Autobahn'], [$result->part1, $result->part2]);
    }

    #[DataProvider('unsplittableProvider')]
    public function testUnsplittableWordIsReturnedAsBothParts(string $word, string $expected): void
    {
        $results = self::splitter()->split($word);

        self::assertCount(1, $results);
        self::assertSame(0.0, $results[0]->score);
        self::assertSame($expected, $results[0]->part1);
        self::assertSame($expected, $results[0]->part2);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function unsplittableProvider(): array
    {
        return [
            'empty' => ['', ''],
            'one char' => ['A', 'A'],
            'two chars' => ['AB', 'Ab'],
            'three chars' => ['Bad', 'Bad'],
            'five chars' => ['Baume', 'Baume'],
        ];
    }

    public function testToArrayMatchesPythonTupleShape(): void
    {
        $result = self::splitter()->split('Autobahnraststätte')[0];

        self::assertSame([$result->score, 'Autobahn', 'Raststätte'], $result->toArray());
    }

    public function testSplitBestReturnsTopCandidate(): void
    {
        $best = self::splitter()->splitBest('Autobahnraststätte');

        self::assertNotNull($best);
        self::assertSame('Autobahn', $best->part1);
        self::assertSame('Raststätte', $best->part2);
    }

    public function testSplitBestAppliesMinScore(): void
    {
        self::assertNotNull(self::splitter()->splitBest('Autobahnraststätte', 0.5));
        self::assertNull(self::splitter()->splitBest('Autobahnraststätte', 0.9));
    }

    public function testSplitBestReturnsNullForUnsplittableWord(): void
    {
        self::assertNull(self::splitter()->splitBest('Haus'));
        self::assertNull(self::splitter()->splitBest(''));
    }

    public function testSplitBestKeepsHyphenSplit(): void
    {
        $best = self::splitter()->splitBest('Bundes-Autobahn', 0.9);

        self::assertNotNull($best);
        self::assertSame('Autobahn', $best->part2);
    }

    public function testSecondCallsAreIndependent(): void
    {
        $first = self::splitter()->split('Bundesregierung');
        $second = self::splitter()->split('Bundesregierung');

        self::assertEquals($first, $second);
    }

    public function testThrowsWhenDataDirectoryIsInvalid(): void
    {
        $this->expectException(InvalidFstException::class);

        new Splitter(sys_get_temp_dir() . '/definitely-missing-dir');
    }

    private static function splitter(): Splitter
    {
        return self::$splitter ??= new Splitter();
    }
}

<?php

declare(strict_types=1);

namespace RunAsRoot\GermanCompoundSplitter\Test\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RunAsRoot\GermanCompoundSplitter\Exception\InvalidFstException;
use RunAsRoot\GermanCompoundSplitter\Fst\FstMap;

/**
 * The sample fixture was dumped from the original Rust `fst` crate (key => raw u64 value).
 */
final class FstMapTest extends TestCase
{
    private const DATA_DIR = __DIR__ . '/../../data';

    /** @var array<string, FstMap> */
    private static array $maps = [];

    #[DataProvider('mapNameProvider')]
    public function testKeyCountMatchesOriginal(string $name, int $expected): void
    {
        self::assertSame($expected, self::map($name)->count());
    }

    /**
     * @return array<string, array{string, int}>
     */
    public static function mapNameProvider(): array
    {
        return [
            'suffix' => ['suffix', 435910],
            'prefix' => ['prefix', 424816],
            'infix' => ['infix', 1010100],
        ];
    }

    #[DataProvider('sampleProvider')]
    public function testGetReturnsValuesDumpedFromOriginal(string $name, string $key, int $expected): void
    {
        self::assertSame($expected, self::map($name)->get($key));
    }

    /**
     * @return iterable<string, array{string, string, int}>
     */
    public static function sampleProvider(): iterable
    {
        $sample = json_decode(
            (string) file_get_contents(__DIR__ . '/_files/fst_sample.json'),
            true,
            512,
            JSON_THROW_ON_ERROR
        );
        foreach ($sample as $name => $rows) {
            foreach ($rows as [$key, $value]) {
                yield $name . ':' . $key => [$name, $key, $value];
            }
        }
    }

    public function testGetReturnsNullForAbsentKeys(): void
    {
        $map = self::map('suffix');

        self::assertNull($map->get(''));
        self::assertNull($map->get('qqqqqqqqqq'));
        self::assertNull($map->get("\xff"));
    }

    public function testPrefixValuesReturnsOnlyPrefixesWithEnoughCharacters(): void
    {
        $map = self::map('infix');
        $text = 'raststätte';

        $expected = [];
        for ($len = 3; $len <= mb_strlen($text); $len++) {
            $value = $map->get(mb_substr($text, 0, $len));
            if ($value !== null) {
                $expected[] = $value;
            }
        }

        self::assertNotSame([], $expected);
        self::assertSame($expected, $map->prefixValues($text, 3));
    }

    public function testRejectsTooShortData(): void
    {
        $this->expectException(InvalidFstException::class);

        new FstMap('too short');
    }

    public function testRejectsUnsupportedVersion(): void
    {
        $this->expectException(InvalidFstException::class);
        $this->expectExceptionMessage('Unsupported FST version');

        new FstMap(pack('P', 99) . str_repeat("\0", 40));
    }

    public function testRejectsMissingFile(): void
    {
        $this->expectException(InvalidFstException::class);

        FstMap::fromFile(self::DATA_DIR . '/does-not-exist.fst');
    }

    private static function map(string $name): FstMap
    {
        return self::$maps[$name] ??= FstMap::fromFile(self::DATA_DIR . '/' . $name . '.fst');
    }
}

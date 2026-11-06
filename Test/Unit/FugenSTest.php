<?php

declare(strict_types=1);

namespace RunAsRoot\GermanCompoundSplitter\Test\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RunAsRoot\GermanCompoundSplitter\FugenS;

final class FugenSTest extends TestCase
{
    #[DataProvider('removeProvider')]
    public function testRemove(string $input, ?string $expected): void
    {
        self::assertSame($expected, FugenS::remove($input));
    }

    /**
     * @return array<string, array{string, ?string}>
     */
    public static function removeProvider(): array
    {
        return [
            'ts' => ['beits', 'beit'],
            'gs' => ['tags', 'tag'],
            'ks' => ['werks', 'werk'],
            'hls' => ['mehls', 'mehl'],
            'ns' => ['laufens', 'laufen'],
            'no pattern' => ['haus', null],
            'rls is not hls' => ['fahrls', null],
            'ms is not ns' => ['teams', null],
            'too short (3 chars)' => ['ots', null],
            'exactly 4 chars' => ['arts', 'art'],
            'multibyte length counts characters' => ['äöts', 'äöt'],
            'empty' => ['', null],
        ];
    }
}

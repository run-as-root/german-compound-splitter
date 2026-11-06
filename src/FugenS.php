<?php

declare(strict_types=1);

namespace RunAsRoot\GermanCompoundSplitter;

/**
 * Detection and removal of the German linking "s" (Fugen-S).
 */
final class FugenS
{
    /**
     * Removes the trailing linking "s" when the (lowercase) string ends in ts/gs/ks/hls/ns
     * and is longer than 3 characters. Returns null when nothing should be removed.
     */
    public static function remove(string $s): ?string
    {
        if (!self::shouldRemove($s) || mb_strlen($s, 'UTF-8') <= 3) {
            return null;
        }

        return substr($s, 0, -1);
    }

    public static function shouldRemove(string $s): bool
    {
        return str_ends_with($s, 'ts')
            || str_ends_with($s, 'gs')
            || str_ends_with($s, 'ks')
            || str_ends_with($s, 'hls')
            || str_ends_with($s, 'ns');
    }
}

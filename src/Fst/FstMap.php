<?php

declare(strict_types=1);

namespace RunAsRoot\GermanCompoundSplitter\Fst;

use RunAsRoot\GermanCompoundSplitter\Exception\InvalidFstException;

/**
 * Read-only decoder for an `fst::Map` (BurntSushi/fst crate, format version 3).
 *
 * Values are u64 and are returned as PHP ints holding the raw 64 bits (two's complement),
 * so values with the top bit set appear negative. Output sums along a path use wrapping
 * arithmetic for the same reason.
 *
 * Node layout (nodes are written back to front; an address points at the node's LAST byte):
 *  - OneTransNext (top bits 11): single transition to the previously written node, no output.
 *  - OneTrans     (top bits 10): single transition with optional output.
 *  - AnyTrans     (top bits 0x): any number of transitions, optional final flag/outputs,
 *                                 plus a 256 byte lookup index when there are more than 32.
 */
final class FstMap
{
    private const VERSION = 3;
    private const TRANS_INDEX_THRESHOLD = 32;

    /** Inverse of the crate's COMMON_INPUTS table: index (minus 1) -> input byte. */
    private const COMMON_INPUTS_INV = [
        0x74, 0x65, 0x2f, 0x6f, 0x61, 0x73, 0x72, 0x69, 0x70, 0x63, 0x6e, 0x77, 0x2e, 0x68, 0x6c, 0x6d,
        0x2d, 0x64, 0x75, 0x30, 0x31, 0x32, 0x67, 0x3d, 0x3a, 0x62, 0x66, 0x33, 0x79, 0x35, 0x26, 0x5f,
        0x34, 0x76, 0x39, 0x36, 0x37, 0x38, 0x6b, 0x25, 0x3f, 0x78, 0x43, 0x44, 0x41, 0x53, 0x46, 0x49,
        0x42, 0x45, 0x6a, 0x50, 0x54, 0x7a, 0x52, 0x4e, 0x4d, 0x2b, 0x4c, 0x4f, 0x71, 0x48, 0x47, 0x57,
        0x55, 0x56, 0x2c, 0x59, 0x4b, 0x4a, 0x5a, 0x58, 0x51, 0x3b, 0x29, 0x28, 0x7e, 0x5b, 0x5d, 0x24,
        0x21, 0x27, 0x2a, 0x40, 0x00, 0x01, 0x02, 0x03, 0x04, 0x05, 0x06, 0x07, 0x08, 0x09, 0x0a, 0x0b,
        0x0c, 0x0d, 0x0e, 0x0f, 0x10, 0x11, 0x12, 0x13, 0x14, 0x15, 0x16, 0x17, 0x18, 0x19, 0x1a, 0x1b,
        0x1c, 0x1d, 0x1e, 0x1f, 0x20, 0x22, 0x23, 0x3c, 0x3e, 0x5c, 0x5e, 0x60, 0x7b, 0x7c, 0x7d, 0x7f,
        0x80, 0x81, 0x82, 0x83, 0x84, 0x85, 0x86, 0x87, 0x88, 0x89, 0x8a, 0x8b, 0x8c, 0x8d, 0x8e, 0x8f,
        0x90, 0x91, 0x92, 0x93, 0x94, 0x95, 0x96, 0x97, 0x98, 0x99, 0x9a, 0x9b, 0x9c, 0x9d, 0x9e, 0x9f,
        0xa0, 0xa1, 0xa2, 0xa3, 0xa4, 0xa5, 0xa6, 0xa7, 0xa8, 0xa9, 0xaa, 0xab, 0xac, 0xad, 0xae, 0xaf,
        0xb0, 0xb1, 0xb2, 0xb3, 0xb4, 0xb5, 0xb6, 0xb7, 0xb8, 0xb9, 0xba, 0xbb, 0xbc, 0xbd, 0xbe, 0xbf,
        0xc0, 0xc1, 0xc2, 0xc3, 0xc4, 0xc5, 0xc6, 0xc7, 0xc8, 0xc9, 0xca, 0xcb, 0xcc, 0xcd, 0xce, 0xcf,
        0xd0, 0xd1, 0xd2, 0xd3, 0xd4, 0xd5, 0xd6, 0xd7, 0xd8, 0xd9, 0xda, 0xdb, 0xdc, 0xdd, 0xde, 0xdf,
        0xe0, 0xe1, 0xe2, 0xe3, 0xe4, 0xe5, 0xe6, 0xe7, 0xe8, 0xe9, 0xea, 0xeb, 0xec, 0xed, 0xee, 0xef,
        0xf0, 0xf1, 0xf2, 0xf3, 0xf4, 0xf5, 0xf6, 0xf7, 0xf8, 0xf9, 0xfa, 0xfb, 0xfc, 0xfd, 0xfe, 0xff,
    ];

    private readonly int $root;
    private readonly int $length;

    public function __construct(private readonly string $data)
    {
        $size = strlen($data);
        if ($size < 36) {
            throw new InvalidFstException('FST data is too short.');
        }

        /** @var array{v: int} $version */
        $version = unpack('Pv', $data, 0);
        if ($version['v'] !== self::VERSION) {
            throw new InvalidFstException(sprintf('Unsupported FST version %d (expected 3).', $version['v']));
        }

        $end = $size - 4; // trailing CRC32 checksum
        /** @var array{r: int, n: int} $footer */
        $footer = unpack('Pn/Pr', $data, $end - 16);
        $this->length = $footer['n'];
        $this->root = $footer['r'];

        if ($this->root !== 0 && $this->root + 21 !== $size) {
            throw new InvalidFstException('FST data is corrupt: root address does not match data size.');
        }
    }

    public static function fromFile(string $path): self
    {
        $data = is_file($path) && is_readable($path) ? file_get_contents($path) : false;
        if ($data === false) {
            throw new InvalidFstException(sprintf('Cannot read FST file "%s".', $path));
        }

        return new self($data);
    }

    /**
     * Number of keys stored in the map.
     */
    public function count(): int
    {
        return $this->length;
    }

    /**
     * Raw u64 value for an exact key, or null when the key is absent.
     */
    public function get(string $key): ?int
    {
        $addr = $this->root;
        $out = 0;
        $len = strlen($key);

        for ($i = 0; $i < $len; $i++) {
            if (!$this->transition($addr, ord($key[$i]), $next, $tOut)) {
                return null;
            }
            $out = self::add($out, $tOut);
            $addr = $next;
        }

        if (!$this->isFinal($addr, $finalOut)) {
            return null;
        }

        return self::add($out, $finalOut);
    }

    /**
     * Values of every stored key that is a prefix of $input and consists of at least $minChars
     * UTF-8 characters. Walks the automaton once, so this is much cheaper than calling get()
     * for every prefix.
     *
     * @return list<int> raw u64 values, ordered from shortest to longest matching prefix
     */
    public function prefixValues(string $input, int $minChars): array
    {
        $values = [];
        $addr = $this->root;
        $out = 0;
        $chars = 0;
        $len = strlen($input);

        for ($i = 0; $i < $len; $i++) {
            $byte = ord($input[$i]);
            if (($byte & 0xC0) !== 0x80) {
                $chars++;
            }
            if (!$this->transition($addr, $byte, $next, $tOut)) {
                break;
            }
            $out = self::add($out, $tOut);
            $addr = $next;

            $atBoundary = $i + 1 === $len || (ord($input[$i + 1]) & 0xC0) !== 0x80;
            if ($atBoundary && $chars >= $minChars && $this->isFinal($addr, $finalOut)) {
                $values[] = self::add($out, $finalOut);
            }
        }

        return $values;
    }

    /**
     * Finds the transition labelled $byte leaving the node at $addr.
     *
     * @param-out int $nextAddr
     * @param-out int $output
     */
    private function transition(int $addr, int $byte, ?int &$nextAddr, ?int &$output): bool
    {
        if ($addr === 0) { // empty final node
            return false;
        }

        $d = $this->data;
        $v = ord($d[$addr]);
        $kind = $v >> 6;

        if ($kind === 3) { // OneTransNext
            $idx = $v & 0x3F;
            if ($idx !== 0) {
                $input = self::COMMON_INPUTS_INV[$idx - 1];
                $inLen = 0;
            } else {
                $input = ord($d[$addr - 1]);
                $inLen = 1;
            }
            if ($input !== $byte) {
                return false;
            }
            $nextAddr = $addr - $inLen - 1;
            $output = 0;

            return true;
        }

        if ($kind === 2) { // OneTrans
            $idx = $v & 0x3F;
            if ($idx !== 0) {
                $input = self::COMMON_INPUTS_INV[$idx - 1];
                $inLen = 0;
            } else {
                $input = ord($d[$addr - 1]);
                $inLen = 1;
            }
            if ($input !== $byte) {
                return false;
            }
            $sizes = ord($d[$addr - $inLen - 1]);
            $tSize = $sizes >> 4;
            $oSize = $sizes & 0x0F;
            $end = $addr - $inLen - 1 - $tSize - $oSize;
            $output = $oSize > 0 ? $this->unpackUint($end, $oSize) : 0;
            $delta = $this->unpackUint($addr - $inLen - 1 - $tSize, $tSize);
            $nextAddr = $delta === 0 ? 0 : $end - $delta;

            return true;
        }

        // AnyTrans
        $nTrans = $v & 0x3F;
        $nLen = 0;
        if ($nTrans === 0) {
            $nTrans = ord($d[$addr - 1]);
            $nLen = 1;
            if ($nTrans === 1) {
                $nTrans = 256;
            }
        }
        $sizes = ord($d[$addr - $nLen - 1]);
        $tSize = $sizes >> 4;
        $oSize = $sizes & 0x0F;
        $indexSize = $nTrans > self::TRANS_INDEX_THRESHOLD ? 256 : 0;
        $base = $addr - $nLen - 1 - $indexSize;

        if ($indexSize > 0) {
            $i = ord($d[$base + $byte]);
            if ($i >= $nTrans) {
                return false;
            }
        } else {
            $start = $base - $nTrans;
            $i = -1;
            for ($p = 0; $p < $nTrans; $p++) {
                if (ord($d[$start + $p]) === $byte) {
                    $i = $nTrans - $p - 1;
                    break;
                }
            }
            if ($i < 0) {
                return false;
            }
        }

        $isFinal = ($v & 0x40) !== 0;
        $totalTrans = $nTrans + $nTrans * $tSize + $indexSize;
        $end = $addr - $nLen - 1 - $totalTrans - $nTrans * $oSize - ($isFinal ? $oSize : 0);
        $delta = $this->unpackUint($base - $nTrans - $i * $tSize - $tSize, $tSize);
        $nextAddr = $delta === 0 ? 0 : $end - $delta;
        $output = $oSize > 0
            ? $this->unpackUint($addr - $nLen - 1 - $totalTrans - $i * $oSize - $oSize, $oSize)
            : 0;

        return true;
    }

    /**
     * Whether the node at $addr is final, and its final output.
     *
     * @param-out int $finalOutput
     */
    private function isFinal(int $addr, ?int &$finalOutput): bool
    {
        $finalOutput = 0;
        if ($addr === 0) {
            return true;
        }

        $d = $this->data;
        $v = ord($d[$addr]);
        if (($v >> 6) !== 0 && ($v >> 6) !== 1) {
            return false; // OneTrans / OneTransNext are never final
        }
        if (($v & 0x40) === 0) {
            return false;
        }

        $nTrans = $v & 0x3F;
        $nLen = 0;
        if ($nTrans === 0) {
            $nTrans = ord($d[$addr - 1]);
            $nLen = 1;
            if ($nTrans === 1) {
                $nTrans = 256;
            }
        }
        $sizes = ord($d[$addr - $nLen - 1]);
        $tSize = $sizes >> 4;
        $oSize = $sizes & 0x0F;
        if ($oSize > 0) {
            $indexSize = $nTrans > self::TRANS_INDEX_THRESHOLD ? 256 : 0;
            $totalTrans = $nTrans + $nTrans * $tSize + $indexSize;
            $finalOutput = $this->unpackUint($addr - $nLen - 1 - $totalTrans - $nTrans * $oSize - $oSize, $oSize);
        }

        return true;
    }

    /**
     * Little-endian unsigned integer of $bytes (1..8) bytes at $offset.
     */
    private function unpackUint(int $offset, int $bytes): int
    {
        $d = $this->data;
        $n = 0;
        for ($i = 0; $i < $bytes; $i++) {
            $n |= ord($d[$offset + $i]) << (8 * $i);
        }

        return $n;
    }

    /**
     * Wrapping 64-bit addition (PHP would otherwise turn an overflowing sum into a float).
     */
    private static function add(int $a, int $b): int
    {
        if ($a >= 0 && $b >= 0 && $a > PHP_INT_MAX - $b) {
            // The u64 sum exceeds PHP_INT_MAX: wrap by shifting each operand into the negative range.
            return ($a - PHP_INT_MAX - 1) + ($b - PHP_INT_MAX - 1);
        }

        return $a + $b;
    }
}

<?php

namespace App\Service;

final class UlidGenerator
{
    private const ENCODING = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';

    public static function generate(?\DateTimeInterface $time = null): string
    {
        $milliseconds = (int) floor(((float) ($time ?? new \DateTimeImmutable())->format('U.u')) * 1000);
        $random = random_bytes(10);

        return self::encodeTime($milliseconds) . self::encodeRandom($random);
    }

    private static function encodeTime(int $milliseconds): string
    {
        $encoded = '';

        for ($i = 0; $i < 10; $i++) {
            $encoded = self::ENCODING[$milliseconds & 31] . $encoded;
            $milliseconds >>= 5;
        }

        return $encoded;
    }

    private static function encodeRandom(string $bytes): string
    {
        $value = 0;
        $bits = 0;
        $encoded = '';

        foreach (unpack('C*', $bytes) as $byte) {
            $value = ($value << 8) | $byte;
            $bits += 8;

            while ($bits >= 5) {
                $bits -= 5;
                $encoded .= self::ENCODING[($value >> $bits) & 31];
            }
        }

        if ($bits > 0) {
            $encoded .= self::ENCODING[($value << (5 - $bits)) & 31];
        }

        return substr($encoded, 0, 16);
    }
}

<?php

declare(strict_types=1);

namespace App\Support;

/**
 * So khớp vector khuôn mặt theo đúng engine đã tạo chúng.
 *
 * Human và SFace không cùng không gian vector: Human dùng khoảng cách Euclid đã chuẩn hóa,
 * còn SFace là embedding 128 chiều chuẩn L2 và dùng cosine. Vì vậy mọi lời gọi phải mang engine;
 * vector khác engine/khác độ dài luôn được xem là không giống nhau.
 */
final class FaceMatcher
{
    public const HUMAN = 'human-v1';
    public const SFACE = 'sface-v1';
    public const ENGINES = [self::HUMAN, self::SFACE];

    /** Độ dài vector chấp nhận được của Human; SFace được kiểm tra chính xác ở validDescriptor(). */
    public const MIN_SIZE = 64;
    public const MAX_SIZE = 2048;
    public const SFACE_SIZE = 128;
    public const VALUE_LIMIT = 1000;

    private const HUMAN_MULTIPLIER = 25.0;
    private const HUMAN_MIN = 0.2;
    private const HUMAN_MAX = 0.8;

    public static function validEngine(string $engine): bool
    {
        return in_array($engine, self::ENGINES, true);
    }

    /** @param array<int, float|int|string> $descriptor */
    public static function validDescriptor(array $descriptor, string $engine): bool
    {
        $count = count($descriptor);

        return $engine === self::SFACE
            ? $count === self::SFACE_SIZE
            : $engine === self::HUMAN && $count >= self::MIN_SIZE && $count <= self::MAX_SIZE;
    }

    /** Threshold is deliberately native to each engine; it is not an invented conversion between scores. */
    public static function threshold(string $engine): float
    {
        return $engine === self::SFACE
            ? (float) config('face.sface_match_cosine', 0.45)
            : (float) config('face.match_similarity', 0.5);
    }

    public static function margin(string $engine): float
    {
        return $engine === self::SFACE
            ? (float) config('face.sface_margin', 0.05)
            : (float) config('face.margin', 0.03);
    }

    /**
     * @param array<int, float|int|string> $a
     * @param array<int, float|int|string> $b
     */
    public static function similarity(array $a, array $b, string $engine = self::HUMAN): float
    {
        $count = count($a);
        if (! self::validEngine($engine) || $count === 0 || $count !== count($b) || ! self::validDescriptor($a, $engine) || ! self::validDescriptor($b, $engine)) {
            return 0.0;
        }

        if ($engine === self::SFACE) {
            return self::cosineSimilarity($a, $b);
        }

        $sum = 0.0;
        for ($i = 0; $i < $count; $i++) {
            $diff = (float) $a[$i] - (float) $b[$i];
            $sum += $diff * $diff;
        }
        if ($sum === 0.0) {
            return 1.0;
        }

        $normalized = (1 - sqrt(self::HUMAN_MULTIPLIER * $sum) / 100 - self::HUMAN_MIN) / (self::HUMAN_MAX - self::HUMAN_MIN);

        return max(0.0, min(1.0, $normalized));
    }

    /** @param array<int, float|int|string> $a @param array<int, float|int|string> $b */
    private static function cosineSimilarity(array $a, array $b): float
    {
        $dot = 0.0;
        $normA = 0.0;
        $normB = 0.0;
        foreach ($a as $index => $value) {
            $x = (float) $value;
            $y = (float) $b[$index];
            $dot += $x * $y;
            $normA += $x * $x;
            $normB += $y * $y;
        }
        if ($normA <= 0 || $normB <= 0) {
            return 0.0;
        }

        // SFace's published threshold is a cosine score, so keep this native score unchanged.
        return max(-1.0, min(1.0, $dot / sqrt($normA * $normB)));
    }

    /** @param array<int, float|int|string> $a @param array<int, float|int|string>|null $b */
    public static function isSamePerson(array $a, ?array $b, string $engine = self::HUMAN): bool
    {
        return $b !== null && self::similarity($a, $b, $engine) >= self::threshold($engine);
    }
}

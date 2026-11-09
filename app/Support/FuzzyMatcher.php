<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Tìm gần đúng: không phân biệt hoa/thường + dấu tiếng Việt, đảo thứ tự từ,
 * gõ thiếu/sai/thừa 1-2 ký tự (iphon 15 -> iPhone 15, nguyen van a -> Nguyễn Văn An).
 */
class FuzzyMatcher
{
    public static function normalize(string $value): string
    {
        $value = Str::ascii(str_replace(['đ', 'Đ'], 'd', mb_strtolower($value)));

        return trim((string) preg_replace('/[^a-z0-9]+/', ' ', $value));
    }

    /** @return array<int, string> */
    public static function tokens(string $term): array
    {
        return array_values(array_unique(array_filter(explode(' ', self::normalize($term)))));
    }

    /** Số lỗi cho phép theo độ dài từ khóa. */
    public static function tolerance(string $token): int
    {
        $len = strlen($token);

        return $len >= 8 ? 2 : ($len >= 4 ? 1 : 0);
    }

    /**
     * Điểm khớp của $text với toàn bộ $tokens (mọi từ đều phải khớp), null nếu không khớp.
     * Điểm càng thấp càng sát (0 = chứa nguyên từ khóa).
     */
    public static function score(array $tokens, string $text): ?int
    {
        $normalized = self::normalize($text);
        if ($normalized === '' || $tokens === []) {
            return null;
        }
        $words = explode(' ', $normalized);
        $total = 0;

        foreach ($tokens as $token) {
            if (str_contains($normalized, $token)) {
                continue;
            }
            $tolerance = self::tolerance($token);
            if ($tolerance === 0) {
                return null;
            }
            $best = null;
            $length = strlen($token);
            foreach ($words as $word) {
                // so với cả từ lẫn phần đầu từ (gõ dở: "samsu" -> "samsung")
                $distance = min(
                    levenshtein($token, $word),
                    $length < strlen($word) ? levenshtein($token, substr($word, 0, $length)) : PHP_INT_MAX,
                );
                if ($distance <= $tolerance && ($best === null || $distance < $best)) {
                    $best = $distance;
                }
            }
            if ($best === null) {
                return null;
            }
            $total += $best;
        }

        return $total;
    }

    /** Các mẩu 3 ký tự dùng để lấy ứng viên từ DB khi cần tìm gần đúng. */
    public static function fragments(array $tokens): array
    {
        $fragments = [];
        foreach ($tokens as $token) {
            if (strlen($token) < 3) {
                $fragments[] = $token;
                continue;
            }
            $fragments[] = substr($token, 0, 3);
            $fragments[] = substr($token, -3);
            if (strlen($token) >= 7) {
                $fragments[] = substr($token, (int) (strlen($token) / 2) - 1, 3);
            }
        }

        return array_values(array_unique($fragments));
    }
}

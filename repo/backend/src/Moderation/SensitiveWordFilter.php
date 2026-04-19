<?php

declare(strict_types=1);

namespace CampusLearn\Moderation;

final class SensitiveWordFilter
{
    /**
     * Scan $body against $rules. Case-insensitive and UTF-8 safe.
     *
     * @param iterable<SensitiveWordRule> $rules
     */
    public function inspect(string $body, iterable $rules): FilterResult
    {
        $matches = [];
        $normalized = $this->normalize($body);

        foreach ($rules as $rule) {
            if (! $rule->isActive || $rule->pattern === '') {
                continue;
            }
            $needle = $this->normalize($rule->pattern);
            $needleLen = mb_strlen($needle);
            if ($needleLen === 0) {
                continue;
            }
            $offset = 0;
            while (($pos = mb_strpos($normalized, $needle, $offset)) !== false) {
                if ($rule->matchType === 'exact' && ! $this->isWordBoundary($normalized, $pos, $needleLen)) {
                    $offset = $pos + $needleLen;
                    continue;
                }
                $matches[] = [
                    'term' => $rule->pattern,
                    'start' => $pos,
                    'end' => $pos + $needleLen,
                ];
                $offset = $pos + $needleLen;
            }
        }

        usort($matches, static fn (array $a, array $b) => $a['start'] <=> $b['start']);

        return new FilterResult($matches);
    }

    private function normalize(string $text): string
    {
        $normalized = class_exists(\Normalizer::class)
            ? \Normalizer::normalize($text, \Normalizer::FORM_C)
            : $text;
        return mb_strtolower($normalized ?: $text);
    }

    private function isWordBoundary(string $haystack, int $pos, int $length): bool
    {
        $before = $pos === 0 ? '' : mb_substr($haystack, $pos - 1, 1);
        $after = mb_substr($haystack, $pos + $length, 1);
        $isWord = static fn (string $ch): bool => $ch !== '' && preg_match('/\p{L}|\p{N}/u', $ch) === 1;
        // Exact mode in this domain enforces only a trailing word boundary.
        // This lets patterns match suffixes like "superblocked" while still
        // preventing expansions like "blockedx".
        return ! $isWord($after);
    }
}

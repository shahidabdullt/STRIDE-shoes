<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Turns free text into safe words for MySQL FULLTEXT (docs/phase-0-findings.md, S6).
 * Raw text is never passed to MATCH ... AGAINST: operators such as + - * " ( ) < > ~ @ are stripped.
 */
class SearchTerms
{
    /** Words InnoDB FULLTEXT ignores. A required (+) stopword would match nothing, so these are dropped. */
    private const STOPWORDS = [
        'a', 'about', 'an', 'are', 'as', 'at', 'be', 'by', 'com', 'de', 'en', 'for', 'from', 'how', 'i', 'in',
        'is', 'it', 'la', 'of', 'on', 'or', 'that', 'the', 'this', 'to', 'was', 'what', 'when', 'where', 'who',
        'will', 'with', 'und', 'www',
    ];

    /** @return list<string> Lower-case words, stopwords removed. */
    public static function words(string $text): array
    {
        $words = preg_split('/[^\p{L}\p{N}]+/u', Str::lower($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(array_diff($words, self::STOPWORDS));
    }

    /**
     * Words FULLTEXT can index (3+ characters) as a Boolean-mode query, e.g. "+trail* +shoe*".
     * With $requireAll false any word may match and MySQL ranks rows by how many do.
     */
    public static function booleanQuery(array $words, bool $requireAll = true): string
    {
        $indexable = array_filter($words, fn (string $w) => mb_strlen($w) >= 3);

        return implode(' ', array_map(fn (string $w) => ($requireAll ? '+' : '').$w.'*', $indexable));
    }
}

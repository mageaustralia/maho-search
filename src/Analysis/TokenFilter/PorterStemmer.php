<?php

declare(strict_types=1);

namespace Maho\Search\Lucene\Analysis\TokenFilter;

use Maho\Search\Lucene\Analysis\Token;
use Maho\Search\Lucene\Analysis\TokenFilter;

/**
 * Porter Stemmer token filter for English language.
 *
 * Reduces words to their root form at index/search time:
 *   jeans → jean, women → women (irregular), running → run,
 *   caresses → caress, ponies → poni, etc.
 *
 * Based on the Porter Stemming Algorithm (1980).
 * @see https://tartarus.org/martin/PorterStemmer/
 */
class PorterStemmer extends TokenFilter
{
    /**
     * Minimum word length to stem (shorter words pass through unchanged).
     */
    private int $minLength = 3;

    public function normalize(Token $srcToken): ?Token
    {
        $term = $srcToken->getTermText();

        if (strlen($term) >= $this->minLength && ctype_alpha($term)) {
            $stemmed = $this->stem($term);
            $srcToken->setTermText($stemmed);
        }

        return $srcToken;
    }

    public function stem(string $word): string
    {
        $word = strtolower($word);

        if (strlen($word) <= 2) {
            return $word;
        }

        $word = $this->step1a($word);
        $word = $this->step1b($word);
        $word = $this->step1c($word);
        $word = $this->step2($word);
        $word = $this->step3($word);
        $word = $this->step4($word);
        $word = $this->step5a($word);
        $word = $this->step5b($word);

        return $word;
    }

    /**
     * Measure: the number of VC (vowel-consonant) sequences in the stem.
     */
    private function m(string $stem): int
    {
        $cv = preg_replace('/[aeiou]+/', 'V', $stem);
        $cv = preg_replace('/[^V]+/', 'C', $cv);
        $cv = preg_replace('/^C/', '', $cv);
        $cv = preg_replace('/V$/', '', $cv);
        return (int) (strlen($cv) / 2);
    }

    private function containsVowel(string $stem): bool
    {
        return (bool) preg_match('/[aeiou]/', $stem);
    }

    private function endsWithDouble(string $word): bool
    {
        $len = strlen($word);
        return $len >= 2 && $word[$len - 1] === $word[$len - 2] && !$this->isVowel($word, $len - 1);
    }

    /**
     * *o — the stem ends cvc, where the second c is not W, X or Y.
     */
    private function cvc(string $word): bool
    {
        $len = strlen($word);
        if ($len < 3) {
            return false;
        }
        $c2 = $word[$len - 1];
        return !$this->isVowel($word, $len - 1)
            && $this->isVowel($word, $len - 2)
            && !$this->isVowel($word, $len - 3)
            && $c2 !== 'w' && $c2 !== 'x' && $c2 !== 'y';
    }

    private function isVowel(string $word, int $pos): bool
    {
        $c = $word[$pos];
        if ($c === 'a' || $c === 'e' || $c === 'i' || $c === 'o' || $c === 'u') {
            return true;
        }
        // y is a vowel if preceded by a consonant
        return $c === 'y' && $pos > 0 && !$this->isVowel($word, $pos - 1);
    }

    /**
     * Step 1a: plurals
     *   caresses → caress, ponies → poni, ties → ti, cats → cat, ss → ss
     */
    private function step1a(string $word): string
    {
        if (str_ends_with($word, 'sses')) {
            return substr($word, 0, -2);
        }
        if (str_ends_with($word, 'ies')) {
            return substr($word, 0, -2);
        }
        if (str_ends_with($word, 'ss')) {
            return $word;
        }
        if (str_ends_with($word, 's')) {
            return substr($word, 0, -1);
        }
        return $word;
    }

    /**
     * Step 1b: past tenses
     */
    private function step1b(string $word): string
    {
        if (str_ends_with($word, 'eed')) {
            $stem = substr($word, 0, -3);
            if ($this->m($stem) > 0) {
                return substr($word, 0, -1);
            }
            return $word;
        }

        $modified = false;
        if (str_ends_with($word, 'ed')) {
            $stem = substr($word, 0, -2);
            if ($this->containsVowel($stem)) {
                $word = $stem;
                $modified = true;
            }
        } elseif (str_ends_with($word, 'ing')) {
            $stem = substr($word, 0, -3);
            if ($this->containsVowel($stem)) {
                $word = $stem;
                $modified = true;
            }
        }

        if ($modified) {
            if (str_ends_with($word, 'at') || str_ends_with($word, 'bl') || str_ends_with($word, 'iz')) {
                return $word . 'e';
            }
            if ($this->endsWithDouble($word)) {
                $last = $word[strlen($word) - 1];
                if ($last !== 'l' && $last !== 's' && $last !== 'z') {
                    return substr($word, 0, -1);
                }
            }
            if ($this->m($word) === 1 && $this->cvc($word)) {
                return $word . 'e';
            }
        }

        return $word;
    }

    /**
     * Step 1c: y → i when stem contains vowel
     */
    private function step1c(string $word): string
    {
        if (str_ends_with($word, 'y')) {
            $stem = substr($word, 0, -1);
            if ($this->containsVowel($stem)) {
                return $stem . 'i';
            }
        }
        return $word;
    }

    /**
     * Step 2: double suffixes
     */
    private function step2(string $word): string
    {
        $mappings = [
            'ational' => 'ate',  'tional'  => 'tion', 'enci'    => 'ence',
            'anci'    => 'ance', 'izer'    => 'ize',  'abli'    => 'able',
            'alli'    => 'al',   'entli'   => 'ent',  'eli'     => 'e',
            'ousli'   => 'ous',  'ization' => 'ize',  'ation'   => 'ate',
            'ator'    => 'ate',  'alism'   => 'al',   'iveness' => 'ive',
            'fulness' => 'ful',  'ousness' => 'ous',  'aliti'   => 'al',
            'iviti'   => 'ive',  'biliti'  => 'ble',
        ];

        foreach ($mappings as $suffix => $replacement) {
            if (str_ends_with($word, $suffix)) {
                $stem = substr($word, 0, -strlen($suffix));
                if ($this->m($stem) > 0) {
                    return $stem . $replacement;
                }
                return $word;
            }
        }

        return $word;
    }

    /**
     * Step 3: -icate, -ative, -alize, etc.
     */
    private function step3(string $word): string
    {
        $mappings = [
            'icate' => 'ic', 'ative' => '',   'alize' => 'al',
            'iciti' => 'ic', 'ical'  => 'ic', 'ful'   => '',
            'ness'  => '',
        ];

        foreach ($mappings as $suffix => $replacement) {
            if (str_ends_with($word, $suffix)) {
                $stem = substr($word, 0, -strlen($suffix));
                if ($this->m($stem) > 0) {
                    return $stem . $replacement;
                }
                return $word;
            }
        }

        return $word;
    }

    /**
     * Step 4: remove suffixes where m > 1
     */
    private function step4(string $word): string
    {
        $suffixes = [
            'al', 'ance', 'ence', 'er', 'ic', 'able', 'ible', 'ant',
            'ement', 'ment', 'ent', 'ion', 'ou', 'ism', 'ate', 'iti',
            'ous', 'ive', 'ize',
        ];

        // Sort by length descending so longer suffixes match first
        usort($suffixes, fn($a, $b) => strlen($b) - strlen($a));

        foreach ($suffixes as $suffix) {
            if (str_ends_with($word, $suffix)) {
                $stem = substr($word, 0, -strlen($suffix));

                if ($suffix === 'ion') {
                    // Special case: -ion requires stem to end in s or t
                    if ($this->m($stem) > 1 && strlen($stem) > 0
                        && ($stem[strlen($stem) - 1] === 's' || $stem[strlen($stem) - 1] === 't')) {
                        return $stem;
                    }
                } elseif ($this->m($stem) > 1) {
                    return $stem;
                }

                return $word;
            }
        }

        return $word;
    }

    /**
     * Step 5a: remove trailing e
     */
    private function step5a(string $word): string
    {
        if (str_ends_with($word, 'e')) {
            $stem = substr($word, 0, -1);
            if ($this->m($stem) > 1) {
                return $stem;
            }
            if ($this->m($stem) === 1 && !$this->cvc($stem)) {
                return $stem;
            }
        }
        return $word;
    }

    /**
     * Step 5b: -ll → -l when m > 1
     */
    private function step5b(string $word): string
    {
        if (str_ends_with($word, 'll') && $this->m($word) > 1) {
            return substr($word, 0, -1);
        }
        return $word;
    }
}

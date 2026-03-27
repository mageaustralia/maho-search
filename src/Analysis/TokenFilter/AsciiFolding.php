<?php

declare(strict_types=1);

namespace Maho\Search\Lucene\Analysis\TokenFilter;

use Maho\Search\Lucene\Analysis\Token;
use Maho\Search\Lucene\Analysis\TokenFilter;

/**
 * ASCII folding token filter — normalizes accented/special Unicode characters
 * to their ASCII equivalents.
 *
 * Examples: café→cafe, naïve→naive, über→uber, résumé→resume, piñata→pinata
 */
class AsciiFolding extends TokenFilter
{
    public function normalize(Token $srcToken): ?Token
    {
        $text = $srcToken->getTermText();
        $folded = $this->fold($text);

        if ($folded !== $text) {
            $srcToken->setTermText($folded);
        }

        return $srcToken;
    }

    public function fold(string $text): string
    {
        // transliterator_transliterate is the most comprehensive approach
        // but requires intl extension. Fall back to iconv if not available.
        if (function_exists('transliterator_transliterate')) {
            $result = transliterator_transliterate(
                'Any-Latin; Latin-ASCII; [\u0080-\u7fff] remove',
                $text
            );
            return $result !== false ? $result : $text;
        }

        // Fallback: iconv transliteration
        if (function_exists('iconv')) {
            $result = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
            if ($result !== false) {
                // Remove any non-ASCII characters that iconv couldn't transliterate
                return preg_replace('/[^\x00-\x7F]/', '', $result);
            }
        }

        // Last resort: manual mapping of common accented characters
        return strtr($text, [
            'À' => 'A', 'Á' => 'A', 'Â' => 'A', 'Ã' => 'A', 'Ä' => 'A', 'Å' => 'A',
            'Æ' => 'AE', 'Ç' => 'C', 'È' => 'E', 'É' => 'E', 'Ê' => 'E', 'Ë' => 'E',
            'Ì' => 'I', 'Í' => 'I', 'Î' => 'I', 'Ï' => 'I', 'Ð' => 'D', 'Ñ' => 'N',
            'Ò' => 'O', 'Ó' => 'O', 'Ô' => 'O', 'Õ' => 'O', 'Ö' => 'O', 'Ø' => 'O',
            'Ù' => 'U', 'Ú' => 'U', 'Û' => 'U', 'Ü' => 'U', 'Ý' => 'Y', 'Þ' => 'TH',
            'ß' => 'ss',
            'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'å' => 'a',
            'æ' => 'ae', 'ç' => 'c', 'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e',
            'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i', 'ð' => 'd', 'ñ' => 'n',
            'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o', 'ø' => 'o',
            'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u', 'ý' => 'y', 'þ' => 'th',
            'ÿ' => 'y', 'Ł' => 'L', 'ł' => 'l', 'Ś' => 'S', 'ś' => 's',
            'Ź' => 'Z', 'ź' => 'z', 'Ż' => 'Z', 'ż' => 'z',
            'Ą' => 'A', 'ą' => 'a', 'Ę' => 'E', 'ę' => 'e',
            'Ć' => 'C', 'ć' => 'c', 'Ń' => 'N', 'ń' => 'n',
            'Š' => 'S', 'š' => 's', 'Č' => 'C', 'č' => 'c',
            'Ř' => 'R', 'ř' => 'r', 'Ž' => 'Z', 'ž' => 'z',
            'Đ' => 'D', 'đ' => 'd',
        ]);
    }
}

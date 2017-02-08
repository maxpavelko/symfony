<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Utf8;

/**
 * Turkish locale specialized version of Patchwork\Utf8.
 */
class TurkishUtf8 extends Utf8
{
    public static function strtocasefold($s, $full = true)
    {
        if (false !== strpos($s, 'İ')) {
            $s = str_replace('İ', 'i', $s);
        }

        return parent::strtocasefold($s, $full);
    }

    public static function stripos($s, $needle, $offset = 0)
    {
        if (false !== strpos($needle, 'I')) {
            $needle = str_replace('I', 'ı', $needle);
        }
        if (false !== strpos($needle, 'İ')) {
            $needle = str_replace('İ', 'i', $needle);
        }
        if (false !== strpos($s, 'I')) {
            $s = str_replace('I', 'ı', $s);
        }
        if (false !== strpos($s, 'İ')) {
            $s = str_replace('İ', 'i', $s);
        }

        return parent::stripos($s, $needle, $offset);
    }

    public static function strripos($s, $needle, $offset = 0)
    {
        if (false !== strpos($needle, 'I')) {
            $needle = str_replace('I', 'ı', $needle);
        }
        if (false !== strpos($needle, 'İ')) {
            $needle = str_replace('İ', 'i', $needle);
        }
        if (false !== strpos($s, 'I')) {
            $s = str_replace('I', 'ı', $s);
        }
        if (false !== strpos($s, 'İ')) {
            $s = str_replace('İ', 'i', $s);
        }

        return parent::strripos($s, $needle, $offset);
    }

    public static function stristr($s, $needle, $before_needle = false)
    {
        $needle = self::stripos($s, $needle);
        if (false === $needle) {
            return false;
        }
        if ($before_needle) {
            return self::substr($s, 0, $needle);
        }

        return self::substr($s, $needle);
    }

    public static function strrichr($s, $needle, $before_needle = false)
    {
        $needle = self::strripos($s, $needle);
        if (false === $needle) {
            return false;
        }
        if ($before_needle) {
            return self::substr($s, 0, $needle);
        }

        return self::substr($s, $needle);
    }

    public static function strtolower($s)
    {
        if (false !== strpos($s, 'İ')) {
            $s = str_replace('İ', 'i', $s);
        }
        if (false !== strpos($s, 'I')) {
            $s = str_replace('I', 'ı', $s);
        }

        return parent::strtolower($s);
    }

    public static function strtoupper($s)
    {
        if (false !== strpos($s, 'i')) {
            $s = str_replace('i', 'İ', $s);
        }

        return parent::strtoupper($s);
    }

    public static function str_ireplace($search, $replace, $subject, &$count = null)
    {
        $search = (array) $search;

        foreach ($search as $i => $s) {
            if ('' === $s .= '') {
                $s = '/^(?<=.)$/';
            } else {
                $s = preg_quote($s, '/');
                $s = strtr($s, array(
                    'i' => '(?-i:[iİ])',
                    'İ' => '(?-i:[iİ])',
                    'ı' => '(?-i:[ıI])',
                    'I' => '(?-i:[ıI])',
                ));
                $s = "/{$s}/ui";
            }

            $search[$i] = $s;
        }

        $subject = preg_replace($search, $replace, $subject, -1, $replace);
        $count = $replace;

        return $subject;
    }

    public static function ucfirst($s)
    {
        if ('i' === substr($s, 0, 1)) {
            return 'İ'.substr($s, 1);
        } else {
            return parent::ucfirst($s);
        }
    }

    public static function ucwords($s)
    {
        if (false !== strpos($s, 'i')) {
            $s = preg_replace('/\bi/u', 'İ', $s);
        }

        return parent::ucwords($s);
    }
}

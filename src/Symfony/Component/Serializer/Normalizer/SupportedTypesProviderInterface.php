<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Normalizer;

/**
 * Exposes the types supported by a (de)normalizer and their vary-by characteristics,
 * so that the list of normalizers can be cached per type from a Serializer.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
interface SupportedTypesProviderInterface
{
    const VARY_BY_TYPE = 0;
    const VARY_BY_DATA = 1;
    const VARY_BY_CONTEXT = 2;

    /**
     * Defines the vary-by characteristics of the supportsNormalization() and supportsDenormalization() methods.
     *
     * @return array bitfields of self::VARY_BY_*, keyed by the applicable FQCN, or by "*" when all types apply.
     */
    public function getSupportedTypes();
}

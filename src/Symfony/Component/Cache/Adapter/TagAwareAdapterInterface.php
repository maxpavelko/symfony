<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Cache\Adapter;

use Psr\Cache\InvalidArgumentException;

/**
 * Interface for invalidating cached items using tags.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
interface TagAwareAdapterInterface extends AdapterInterface
{
    /**
     * Invalidates cached items using tags.
     *
     * @param string|string[] $tags A tag or an array of tags to invalidate
     *
     * @return bool True on success
     *
     * @throws InvalidArgumentException When $tags is not valid
     */
    public function invalidateTags($tags);

    /**
     * Returns cache items even they have been tag-invalidated.
     *
     * The isHit() method on the resulting items should return false
     * when they have been invalidated by one of their tags.
     *
     * @param string[] $keys                  The keys of the items to fetch from the cache
     * @param int      $revalidationGraceTime The number of seconds before items definitely expire
     *
     * @return CacheItem
     *
     * @throws InvalidArgumentException When $keys is not valid or when $revalidateGraceTime <= 0
     */
    public function graceItems(array $keys, $graceTime);
}

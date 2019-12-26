<?php
/**
 * This file is part of the HumanToTsQuery package.
 *
 * (c) Evgeniy Budanov <budanov.ua@gmail.comm> 2019.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Zk2\HumanToTsQuery;

interface HumanToTsQueryInterface
{
    /**
     * @return string
     */
    public function getQuery(): string;
}
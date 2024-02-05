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
    public function getQuery(\Closure $sqlExecutor = null, string $conf = 'english'): string;

    public function getElasticSearchQuery(): string;

    public function getElasticCompoundSearchQuery(array $fields): ?array;
}

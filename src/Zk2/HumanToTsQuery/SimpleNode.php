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

class SimpleNode extends HumanToTsQuery implements HumanToTsQueryInterface
{
    const TS_FUNCTION = 'plainto_tsquery';

    protected function buildQuery(): ?string
    {
        $this->buildTsQuery();
        if ($this->tsQuery) {
            return sprintf('%s%s %s ', $this->exclude ? '!' : null, $this->tsQuery, $this->logicalOperator);
        }

        return null;
    }
}

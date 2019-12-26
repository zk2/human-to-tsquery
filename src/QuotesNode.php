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

class QuotesNode extends HumanToTsQuery implements HumanToTsQueryInterface
{
    const TS_FUNCTION = 'phraseto_tsquery';

    /**
     * QuotesNode constructor.
     *
     * @param string      $token
     * @param bool        $exclude
     * @param string|null $logicalOperator
     */
    public function __construct(string $token, bool $exclude, ?string $logicalOperator)
    {
        parent::__construct($token, $exclude, $logicalOperator);
    }

    /**
     * @return string|null
     */
    protected function buildQuery(): ?string
    {
        if ($this->tsQuery) {
            return sprintf('%s(%s) %s ', $this->exclude ? '!' : null, $this->tsQuery, $this->logicalOperator);
        }

        return null;
    }
}

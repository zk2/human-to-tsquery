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

class BracketsNode extends HumanToTsQuery implements HumanToTsQueryInterface
{
    /**
     * BracketsNode constructor.
     *
     * @param string      $token
     * @param bool        $exclude
     * @param string|null $logicalOperator
     */
    public function __construct(string $token, bool $exclude, ?string $logicalOperator)
    {
        parent::__construct($token, $exclude, $logicalOperator);
        $this->parse();
    }

    /**
     * @return string
     *
     * @throws HumanToTsQueryException
     */
    protected function buildQuery(): ?string
    {
        $token = '';
        foreach ($this->nodes as $node) {
            $token .= $node->buildQuery();
        }
        return sprintf('%s(%s) %s ', $this->exclude ? '!' : null, trim($token), $this->logicalOperator);
    }
}

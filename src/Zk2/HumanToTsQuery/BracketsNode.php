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
    public function __construct(string $token, bool $exclude, ?LogicalOperator $logicalOperator, ?\Closure $sqlExecutor = null, string $conf = 'english')
    {
        parent::__construct($token, $exclude, $logicalOperator, $sqlExecutor, $conf);
        $this->parse();
    }

    protected function buildQuery(): ?string
    {
        $token = '';
        foreach ($this->nodes as $node) {
            $token .= $node->buildTsQuery()->buildQuery();
        }
        return sprintf('%s(%s) %s ', $this->exclude ? '!' : null, trim($token), $this->logicalOperator);
    }
}

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

class ProximityNode extends HumanToTsQuery implements HumanToTsQueryInterface
{
    /**
     * @var HumanToTsQuery
     */
    protected $leftNode;

    /**
     * @var HumanToTsQuery
     */
    protected $rightNode;

    public function __construct(HumanToTsQuery $leftNode, HumanToTsQuery $rightNode, bool $exclude, ?LogicalOperator $logicalOperator, ?\Closure $sqlExecutor = null, string $conf = 'english')
    {
        $this->leftNode = $leftNode;
        $this->rightNode = $rightNode;
        $this->rightNode->logicalOperator = null;
        $token = sprintf("%s %s%s %s", $this->leftNode->token, $this->leftNode->logicalOperator->getName(), $this->leftNode->logicalOperator->getOperator(), $this->leftNode->token);
        parent::__construct($token, $exclude, $logicalOperator, $sqlExecutor, $conf);
    }

    /**
     * @return string
     *
     * @throws HumanToTsQueryException
     */
    protected function buildQuery(): ?string
    {
        $leftTsQuery = $this->leftNode->buildTsQuery()->buildQuery();
        $leftTsQuery = trim($leftTsQuery, ' ' . $this->leftNode->logicalOperator->getOperator()) . ' ';
        $rightTsQuery = $this->rightNode->buildTsQuery()->buildQuery();
        $tsQuery = '';
        for ($i = $this->leftNode->logicalOperator->getOperator(); $i > 0; $i--) {
            $proximity = $i + 1;
            $tsQuery .= "$leftTsQuery <$proximity> $rightTsQuery | ";
            if ('N' === $this->leftNode->logicalOperator->getName()) {
                $tsQuery .= "$rightTsQuery <$proximity> $leftTsQuery | ";
            }
        }

        return sprintf('(%s) %s ', trim($tsQuery, '| '), $this->logicalOperator);
    }
}

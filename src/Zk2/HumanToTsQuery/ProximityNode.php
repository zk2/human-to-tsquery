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
        $leftQuery = $this->leftNode->buildTsQuery()->buildQuery();
        $leftQuery = trim($leftQuery, ' ' . $this->leftNode->logicalOperator->getOperator()) . ' ';
        $rightQuery = $this->rightNode->buildTsQuery()->buildQuery();
        $query = '';
        for ($i = $this->leftNode->logicalOperator->getOperator(); $i > 0; $i--) {
            $proximity = $i + 1;
            $query .= "$leftQuery <$proximity> $rightQuery | ";
            if ('N' === $this->leftNode->logicalOperator->getName()) {
                $query .= "$rightQuery <$proximity> $leftQuery | ";
            }
        }

        return sprintf('(%s) %s ', trim($query, '| '), $this->logicalOperator);
    }

    protected function buildElasticSearchQuery(): ?string
    {
        $leftQuery = $this->leftNode->buildEsQuery()->buildElasticSearchQuery();
        $leftQuery = trim($leftQuery, ' ' . $this->leftNode->logicalOperator->getName()) . ' ';
        $rightQuery = $this->rightNode->buildEsQuery()->buildElasticSearchQuery();
        $query = sprintf('"\"%s\" \"%s\""~%u', trim($leftQuery), trim($rightQuery), $this->leftNode->logicalOperator->getOperator());

        return sprintf('(%s) %s ', trim($query, '| '), $this->logicalOperator->getName());
    }

    protected function buildElasticSearchCompoundQuery(array $fields): ?array
    {
        $leftQuery = $this->leftNode->buildEsQuery()->buildElasticSearchQuery();
        $leftQuery = trim($leftQuery, ' ()' . $this->leftNode->logicalOperator->getName());
        $rightQuery = $this->rightNode->buildEsQuery()->buildElasticSearchQuery();
        $rightQuery = trim($rightQuery, ' ()');
        $fields = $fields['fields'] ?? $fields;
        $queries = [];

        if (str_ends_with($rightQuery, 'AND')) {
            $rightQuery = trim(substr_replace($rightQuery, '', -3));
        }
        if (str_ends_with($leftQuery, 'AND')) {
            $leftQuery = trim(substr_replace($leftQuery, '', -3));
        }

        foreach ($fields as $field) {
            $queries[] = [
                'intervals' => [
                    $field => [
                        'all_of' => [
                            'max_gaps' => $this->leftNode->logicalOperator->getOperator(),
                            'intervals' => [['match' => ['query' => $leftQuery]], ['match' => ['query' => $rightQuery]]],
                        ],
                    ],
                ],
            ];
        }

        if (1 === count($queries)) {
            return $queries[0];
        }

        return ['bool' => ['should' => $queries]];
    }
}

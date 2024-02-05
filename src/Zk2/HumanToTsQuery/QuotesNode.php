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

    protected function buildQuery(): ?string
    {
        $this->buildTsQuery();
        if ($this->query) {
            return sprintf('%s(%s) %s ', $this->exclude ? '!' : null, $this->query, $this->logicalOperator);
        }

        return null;
    }

    protected function buildElasticSearchQuery(): ?string
    {
        $this->buildEsQuery();
        if ($this->query) {
            $operator = $this->logicalOperator ? $this->logicalOperator->getName() : null;
            return sprintf('%s"%s" %s ', $this->exclude ? ' NOT ' : null, $this->query, $operator);
        }

        return null;
    }

    protected function buildElasticSearchCompoundQuery(array $fields): ?array
    {
        $this->buildEsQuery();
        if ($this->query) {
            return [
                'query_string' => [
                    'fields' => $fields['quotes'] ?? $fields ,
                    'query' => sprintf('%s"%s"', $this->exclude ? 'NOT ' : null, $this->query)
                ],
            ];
        }

        return null;
    }
}

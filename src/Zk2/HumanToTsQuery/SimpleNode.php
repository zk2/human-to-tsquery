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

    /**
     * Characters that Lucene's classic query parser reads as operators or term separators.
     * A bare word built with them never means what the user typed: "agri-food" is parsed
     * as "agri OR food" and "9/11" does not parse at all. Such a word is sent as a phrase
     * instead.
     *
     * The phrase goes to the 'compound' fields, falling back to the 'quotes' ones a user
     * typed the quotes for. Keeping the two apart lets an index answer an auto-built
     * phrase from a differently analyzed field, and 'compound_options' carries whatever
     * query_string options that field needs - a quote_analyzer, most likely.
     */
    const OPERATOR_CHARS = '-+!^/\\[]{}';

    /**
     * Wildcard and fuzziness operators. A word carrying one of them is left as is: there
     * the operator is the point of the query, not a typo.
     */
    const PATTERN_CHARS = '*?~';

    protected function buildQuery(): ?string
    {
        $this->buildTsQuery();
        if ($this->query) {
            return sprintf('%s%s %s ', $this->exclude ? '!' : null, $this->query, $this->logicalOperator);
        }

        return null;
    }

    protected function buildElasticSearchQuery(): ?string
    {
        $this->buildEsQuery();
        if ($this->query) {
            $phrase = $this->needsPhrase();
            $this->query = str_replace([':'], ['\:'], $this->query);
            $operator = $this->logicalOperator ? $this->logicalOperator->getName() : null;
            $query = $phrase ? sprintf('"%s"', $this->query) : $this->query;

            return sprintf('%s%s %s ', $this->exclude ? ' NOT ' : null, $query, $operator);
        }

        return null;
    }

    protected function buildElasticSearchCompoundQuery(array $fields): ?array
    {
        $this->buildEsQuery();
        if ($this->query) {
            $phrase = $this->needsPhrase();
            $this->query = str_replace([':'], ['\:'], $this->query);

            if ($phrase) {
                return [
                    'query_string' => array_merge(
                        [
                            'fields' => $fields['compound'] ?? $fields['quotes'] ?? $fields,
                            'query' => sprintf('%s"%s"', $this->exclude ? 'NOT ' : null, $this->query)
                        ],
                        $fields['compound_options'] ?? []
                    ),
                ];
            }

            return [
                'query_string' => [
                    'fields' => $fields['fields'] ?? $fields,
                    'query' => sprintf('%s%s', $this->exclude ? 'NOT ' : null, $this->query)
                ],
            ];
        }

        return null;
    }

    /**
     * Must be called before the query is escaped: escaping adds a backslash, which is
     * itself one of the operator characters.
     */
    private function needsPhrase(): bool
    {
        if (strcspn($this->query, self::PATTERN_CHARS) !== strlen($this->query)) {
            return false;
        }

        return strcspn($this->query, self::OPERATOR_CHARS) !== strlen($this->query);
    }
}

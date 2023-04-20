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
     * ProximityNode constructor.
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
     * @param \Closure|null $sqlExecutor - The Closure should take a SQL string and return a string
     * @param string        $conf        - regconfig (english, simple, etc...)
     *
     * @return null|string
     *
     * @throws HumanToTsQueryException
     */
    protected function getTsQuery(\Closure $sqlExecutor = null, string $conf = 'english'): ?string
    {
        if ($this->nodes) {
            foreach ($this->nodes as $node) {
                $this->tsQuery = $node->getTsQuery(null, $conf);
            }
        }
        return null;
    }

    protected function parse(): void
    {
        $arrayTokens = explode(' ', $this->token);
        $this->token = '';
        $count = count($arrayTokens);

        if (
            $count !== 3 || 
            false === self::isProximityOperator($arrayTokens[1]) || 
            true === in_array($arrayTokens[0], array_keys(self::LOGICAL_OPERATORS)) ||
            true === in_array($arrayTokens[2], array_keys(self::LOGICAL_OPERATORS))
        ) {
            throw new HumanToTsQueryException(sprintf('The query is not valid: %s', $this->token));
        }

        $term1 = strtolower($arrayTokens[0]);
        $term2 = strtolower($arrayTokens[2]);
        $proximity = preg_replace('/\D+/', '', $arrayTokens[1]);
        $order = preg_replace('/[^NW]/', '', $arrayTokens[1]);
        $count = $proximity + 1;

        for ($i = 1; $i <= $count; $i++) {
            $this->addNodes($i, $count, $term1, $term2, $order === 'W');
        }
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
        return sprintf('%s%s %s ', $this->exclude ? '!' : null, trim($token), $this->logicalOperator);
    }

    private function addNodes(int $i, int $count, string $term1, string $term2, bool $isOrder): void
    {
        if ($isOrder) {
            $this->nodes[] = new SimpleNode("{$term1} <{$i}> {$term2}", false, $i !== $count ? 'OR' : null);
        } else {
            $this->nodes[] = new SimpleNode("{$term1} <{$i}> {$term2}", false, 'OR');
            $this->nodes[] = new SimpleNode("{$term2} <{$i}> {$term1}", false, $i !== $count ? 'OR' : null);
        }
    }
}

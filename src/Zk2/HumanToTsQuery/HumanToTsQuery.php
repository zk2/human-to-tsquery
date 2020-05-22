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

class HumanToTsQuery
{
    const TS_FUNCTION = null;

    const LOGICAL_OPERATORS = [
        'AND' => '&',
        'OR' => '|',
    ];

    /**
     * @var HumanToTsQuery[]
     */
    protected $nodes = [];

    /**
     * @var string
     */
    protected $token;

    /**
     * @var string
     */
    protected $tsQuery;

    /**
     * @var bool
     */
    protected $exclude;

    /**
     * @var string|null
     */
    protected $logicalOperator;

    /**
     * HumanQueryToTsQuery constructor.
     *
     * @param string      $token
     * @param bool        $exclude
     * @param string|null $logicalOperator
     */
    public function __construct(string $token, bool $exclude = false, ?string $logicalOperator = null)
    {
        $this->token = $token;
        $this->exclude = $exclude;
        $this->logicalOperator = $logicalOperator ? self::LOGICAL_OPERATORS[$logicalOperator] : null;
    }

    /**
     * @param \Closure|null $sqlExecutor - The Closure should take a SQL string and return a string
     * @param string        $conf        - regconfig (english, simple, etc...)
     *
     * @return string
     *
     * @throws HumanToTsQueryException
     */
    public function getQuery(\Closure $sqlExecutor = null, string $conf = 'english'): string
    {
        $this->validate();
        $this->parse();
        $tsQuery = '';
        foreach ($this->nodes as $node) {
            $node->getTsQuery($sqlExecutor, $conf);
            $tsQuery .= $node->buildQuery();
        }

        return str_replace("'", "", trim($tsQuery, ' |&'));
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
        if ($function = static::TS_FUNCTION) {
            if ($sqlExecutor) {
                $sqlExecutor->bindTo($this);
                $this->tsQuery = $sqlExecutor->call($this, sprintf("SELECT %s('%s', '%s')", $function, $conf, str_replace("'", "''", $this->token)));
            } else {
                $this->tsQuery = $this->token;
            }
        } elseif ($this->nodes) {
            foreach ($this->nodes as $node) {
                $this->tsQuery = $node->getTsQuery($sqlExecutor, $conf);
            }
        }

        return $this->tsQuery;
    }

    /**
     * Build array of nodes
     */
    protected function parse(): void
    {
        $arrayTokens = explode(' ', $this->token);
        $count = count($arrayTokens);

        for ($i = 0; $i < $count; $i++) {
            if (in_array($arrayTokens[$i], array_keys(self::LOGICAL_OPERATORS))) {
                continue;
            }
            $node = null;
            if ($exclude = ('-' === substr($arrayTokens[$i], 0, 1))) {
                $arrayTokens[$i] = substr($arrayTokens[$i], 1);
            }
            if ('(' === substr($arrayTokens[$i], 0, 1)) {
                $subQueryData = $this->processBrackets($i, $count, $arrayTokens);
                $i = $subQueryData['key'];
                $node = new BracketsNode($subQueryData['subQuery'], $exclude, $this->defineLogicalOperator($i, $arrayTokens));
            } elseif ('"' === substr($arrayTokens[$i], 0, 1)) {
                $subQueryData = $this->processQuotes($i, $count, $arrayTokens);
                $i = $subQueryData['key'];
                $node = new QuotesNode($subQueryData['subQuery'], $exclude, $this->defineLogicalOperator($i, $arrayTokens));
            } else {
                $node = new SimpleNode($arrayTokens[$i], $exclude, $this->defineLogicalOperator($i, $arrayTokens));
            }
            if ($node) {
                $this->nodes[] = $node;
            }
        }
    }

    /**
     * @return string|null
     *
     * @throws HumanToTsQueryException
     */
    protected function buildQuery(): ?string
    {
        throw new HumanToTsQueryException('The method is available only for end nodes.');
    }

    /**
     * @param int   $i
     * @param array $arrayTokens
     *
     * @return string|null
     */
    private function defineLogicalOperator(int $i, array $arrayTokens): ?string
    {
        $logicalOperator = null;
        if (isset($arrayTokens[$i + 1])) {
            if (in_array($arrayTokens[$i + 1], array_keys(self::LOGICAL_OPERATORS))) {
                $logicalOperator = $arrayTokens[$i + 1];
            } else {
                $logicalOperator = 'AND';
            }
        }

        return $logicalOperator;
    }

    /**
     * @param int   $i
     * @param int   $count
     * @param array $arrayTokens
     *
     * @return array
     */
    private function processBrackets(int $i, int $count, array $arrayTokens): array
    {
        $subQuery = [];
        $open = 0;
        $returnKey = null;
        for ($j = $i; $j < $count; $j++) {
            if ('(' === substr($arrayTokens[$j], 0, 1) || '-(' === substr($arrayTokens[$j], 0, 2)) {
                $open++;
            }
            if (')' === substr($arrayTokens[$j], -1, 1)) {
                $open--;
                if (0 === $open) {
                    $returnKey = $j;
                }
            }
            $subQuery[] = $arrayTokens[$j];
            if (null !== $returnKey) {
                break;
            }
        }

        return ['key' => $returnKey, 'subQuery' => substr(implode(' ', $subQuery), 1, -1)];
    }

    /**
     * @param int   $i
     * @param int   $count
     * @param array $arrayTokens
     *
     * @return array
     */
    private function processQuotes(int $i, int $count, array $arrayTokens): array
    {
        $subQuery = [];
        $returnKey = null;
        for ($j = $i; $j < $count; $j++) {
            if ('"' === substr($arrayTokens[$j], -1, 1)) {
                $returnKey = $j;
            }
            $subQuery[] = $arrayTokens[$j];
            if (null !== $returnKey) {
                break;
            }
        }

        return ['key' => $returnKey, 'subQuery' => substr(implode(' ', $subQuery), 1, -1)];
    }

    /**
     * @throws HumanToTsQueryException
     */
    private function validate(): void
    {
        if (!$this->checkBracketsAndQuotes()) {
            throw new HumanToTsQueryException(sprintf('The query is not valid: %s', $this->token));
        }
        $this->token = str_replace(['&', '|'], '', $this->token);
        $this->token = trim(preg_replace('/\s{2,}/', ' ', $this->token));
        $this->token = str_replace(['( ', ' )'], ['(', ')'], $this->token);
        preg_match_all('/"([^"]*)"/', $this->token, $matched);
        $this->token = str_replace(
            $matched[0],
            array_map(
                function ($el) {
                    return str_replace(['" ', ' "'], '"', $el);
                },
                $matched[0]
            ),
            $this->token
        );
    }

    /**
     * @return bool
     */
    private function checkBracketsAndQuotes(): bool
    {
        $len = strlen($this->token);
        $brackets = $quotes = [];
        for ($i = 0; $i < $len; $i++) {
            switch ($this->token[$i]) {
                case '(':
                    array_push($brackets, 0); break;
                case ')':
                    if (array_pop($brackets) !== 0)
                        return false;
                    break;
                case '"':
                    array_push($quotes, 1); break;
                default: break;
            }
        }
        return 0 === count($brackets) && count($quotes) % 2 === 0;
    }
}

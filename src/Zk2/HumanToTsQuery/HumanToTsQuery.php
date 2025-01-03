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
    const PG_SEARCH = 'postgres';

    const ES_SEARCH = 'elastic';

    const AVAILABLE_SEARCH = [
        self::PG_SEARCH,
        self::ES_SEARCH,
    ];

    const TS_FUNCTION = null;

    /**
     * @var array|HumanToTsQuery[]
     */
    protected array $nodes = [];

    protected string $token;

    protected ?string $query = null;

    protected bool $exclude = false;

    protected ?LogicalOperator $logicalOperator;

    protected int $countOfLexemes;

    protected ?\Closure $sqlExecutor = null;

    protected string $conf = 'english';

    public function __construct(string $token, bool $exclude = false, ?LogicalOperator $logicalOperator = null, ?\Closure $sqlExecutor = null, string $conf = 'english')
    {
        $this->token = $token;
        $this->countOfLexemes = count(explode(' ', $token));
        $this->exclude = $exclude;
        $this->logicalOperator = null === $logicalOperator ? LogicalOperator::create($logicalOperator) : $logicalOperator;
        $this->sqlExecutor = $sqlExecutor;
        $this->conf = $conf;
    }

    public function getQuery(?\Closure $sqlExecutor = null, string $conf = 'english'): string
    {
        $this->validate();
        $this->sqlExecutor = $sqlExecutor;
        $this->conf = $conf;
        $this->parse();
        $tsQuery = '';
        foreach ($this->nodes as $node) {
            $tsQuery .= $node->buildQuery();
        }

        return str_replace("'", "", trim(str_replace('&)', ')', $tsQuery), ' |&'));
    }

    public function getElasticSearchQuery(): string
    {
        $this->validate();
        $this->parse();
        $esQuery = '';
        foreach ($this->nodes as $node) {
            $esQuery .= $node->buildElasticSearchQuery();
        }
        $esQuery = trim(str_replace(['AND)', 'OR)'], ')', $esQuery));
        if ('AND' === substr($esQuery, -3, 3)) {
            $esQuery = substr($esQuery, 0, strlen($esQuery) - 3);
        }
        if ('OR' === substr($esQuery, -2, 2)) {
            $esQuery = substr($esQuery, 0, strlen($esQuery) - 2);
        }

        return trim($esQuery);
    }

    public function getElasticCompoundSearchQuery(array $fields): ?array
    {
        $this->validate();
        $this->parse();

        return $this->buildElasticSearchCompoundQuery($fields);
    }

    protected function checkLogicalOperators(): bool
    {
        $prevOperator = null;
        $exludesStart = false;
        $lastKey = count($this->nodes) - 1;

        foreach ($this->nodes as $num => $node) {
            if (!is_null($prevOperator) && $prevOperator !== $node->logicalOperator->getName() && $num !== $lastKey && !$this->nodes[$num + 1]->exclude) {
                return false;
            }
            if ($exludesStart && !$node->exclude) {
                return false;
            }
            if ($num !== $lastKey && $this->nodes[$num + 1]->exclude) {
                $exludesStart = true;
            }
            $prevOperator = $node->logicalOperator->getName();
        }

        return true;
    }

    protected function buildTsQuery(): self
    {
        if ($function = static::TS_FUNCTION) {
            if ($this->sqlExecutor) {
                $this->query = $this->sqlExecutor
                    ->bindTo($this)
                    ->call(
                        $this, sprintf("SELECT %s('%s', '%s')", $function, $this->conf, str_replace("'", "''", $this->token))
                    );
            } else {
                $this->query = $this->token;
            }
        } elseif ($this->nodes) {
            foreach ($this->nodes as $node) {
                $node->buildTsQuery();
            }
        }

        return $this;
    }

    protected function buildEsQuery(): self
    {
        if (static::TS_FUNCTION) {
            $this->query = $this->token;
        } elseif ($this->nodes) {
            foreach ($this->nodes as $node) {
                $node->buildEsQuery();
            }
        }

        return $this;
    }

    protected function parse(): void
    {
        $arrayTokens = explode(' ', $this->token);
        $count = count($arrayTokens);
        for ($i = 0; $i < $count; $i++) {
            if (LogicalOperator::check($arrayTokens[$i])) {
                continue;
            }
            $node = null;
            $leftNode = $this->getNode($arrayTokens, $i);
            if ($leftNode->logicalOperator && $leftNode->logicalOperator->isProximity()) {
                if (isset($arrayTokens[$i + $leftNode->countOfLexemes + 1])) {
                    $rightNode = $this->getNode($arrayTokens, $i + $leftNode->countOfLexemes + 1);
                    $node = new ProximityNode($leftNode, $rightNode, false, $rightNode->logicalOperator, $this->sqlExecutor, $this->conf);
                    $i += $leftNode->countOfLexemes + $rightNode->countOfLexemes + 1;
                }
            } else {
                if (in_array($arrayTokens[$i] ?? null, ['AND', 'OR'])) {
                    $i += $leftNode->countOfLexemes;
                } else {
                    $i += $leftNode->countOfLexemes -1;
                }
                $node = $leftNode;
            }
            if ($node) {
                $this->nodes[] = $node;
            }
        }
    }

    protected function getNode(array $arrayTokens, int $i): HumanToTsQuery
    {
        if ($exclude = ('-' === substr($arrayTokens[$i], 0, 1))) {
            $arrayTokens[$i] = substr($arrayTokens[$i], 1);
        }
        if ('(' === substr($arrayTokens[$i], 0, 1)) {
            $subQueryData = $this->processBrackets($i, $arrayTokens);
            $i = $subQueryData['key'];
            $node = new BracketsNode($subQueryData['subQuery'], $exclude, $this->defineLogicalOperator($arrayTokens, $i), $this->sqlExecutor, $this->conf);
        } elseif ('"' === substr($arrayTokens[$i], 0, 1)) {
            $subQueryData = $this->processQuotes($i, $arrayTokens);
            $i = $subQueryData['key'];
            $node = new QuotesNode($subQueryData['subQuery'], $exclude, $this->defineLogicalOperator($arrayTokens, $i), $this->sqlExecutor, $this->conf);
        } else {
            $node = new SimpleNode($arrayTokens[$i], $exclude, $this->defineLogicalOperator($arrayTokens, $i), $this->sqlExecutor, $this->conf);
        }

        return $node;
    }

    protected function buildQuery(): ?string
    {
        throw new HumanToTsQueryException('The method is available only for end nodes.');
    }

    protected function buildElasticSearchQuery(): ?string
    {
        throw new HumanToTsQueryException('The method is available only for end nodes.');
    }

    protected function buildElasticSearchCompoundQuery(array $fields): ?array
    {
        if (!$this->checkLogicalOperators()) {
            throw new HumanToTsQueryException(
                sprintf(
                    'The query does not contain a valid set of logical operators: %s. Use brackets to set priority',
                    $this->token
                )
            );
        }
        $esQueries = $esExcludeQueries = [];
        $cond = 'must';

        foreach ($this->nodes as $node) {
            if ($node->exclude) {
                $esExcludeQueries[] = $node->buildElasticSearchCompoundQuery($fields);
            } else {
                $esQueries[] = $node->buildElasticSearchCompoundQuery($fields);
            }
            if ($node->logicalOperator->isLogical() && 'OR' === $node->logicalOperator->getName()) {
                $cond = 'should';
            }
        }

        if ($esExcludeQueries && 'should' === $cond) {
            return [
                'bool' => [
                    'must' => [
                        ['bool' => [$cond => $esQueries]],
                        ['bool' => ['must' => $esExcludeQueries]],
                    ]
                ]
            ];
        }

        return ['bool' => [$cond => array_values(array_filter(array_merge($esQueries, $esExcludeQueries)))]];
    }

    private function defineLogicalOperator(array $arrayTokens, ?int $key): LogicalOperator
    {
        if (null !== $key && isset($arrayTokens[$key + 1])) {
            $logicalOperator = LogicalOperator::create($arrayTokens[$key + 1]);
        } else {
            $logicalOperator = LogicalOperator::create('AND');
        }

        return $logicalOperator;
    }

    private function processBrackets(int $key, array $arrayTokens): array
    {
        $subQuery = [];
        $open = 0;
        $returnKey = null;
        $count = count($arrayTokens);
        for ($i = $key; $i < $count; $i++) {
            if ('(' === substr($arrayTokens[$i], 0, 1) || '-(' === substr($arrayTokens[$i], 0, 2)) {
                $open++;
            }
            if (')' === substr($arrayTokens[$i], -1, 1)) {
                $open--;
                if (0 === $open) {
                    $returnKey = $i;
                }
            }
            $subQuery[] = $arrayTokens[$i];
            if (null !== $returnKey) {
                break;
            }
        }

        return ['key' => $returnKey, 'subQuery' => substr(implode(' ', $subQuery), 1, -1)];
    }

    private function processQuotes(int $key, array $arrayTokens): array
    {
        $subQuery = [];
        $returnKey = null;
        $count = count($arrayTokens);
        for ($i = $key; $i < $count; $i++) {
            if ('"' === substr($arrayTokens[$i], -1, 1)) {
                $returnKey = $i;
            }
            $subQuery[] = $arrayTokens[$i];
            if (null !== $returnKey) {
                break;
            }
        }

        return ['key' => $returnKey, 'subQuery' => substr(implode(' ', $subQuery), 1, -1)];
    }

    private function validate(): void
    {
        if (!$this->checkBracketsAndQuotes()) {
            throw new HumanToTsQueryException(sprintf('The query is not valid: %s', $this->token));
        }
        $this->token = str_replace(['&', '|'], '', $this->token);
        $this->token = str_replace('/<\d+>/', '', $this->token);
        $this->token = trim(preg_replace('/\s{2,}/', ' ', $this->token));
        $this->token = str_replace(['( ', ' )'], ['(', ')'], $this->token);
        $search = $replace = [];
        foreach (range(2, 10) as $num) {
            $search[] = str_repeat('(', $num);
            $search[] = str_repeat(')', $num);
            $replace[] = trim(str_repeat('( ', $num));
            $replace[] = trim(str_repeat(' )', $num));
        }
        $this->token = str_replace($search, $replace, $this->token);
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
        $pattern = '';
        $operators = [' AND ', ' OR ', ' N\d+ ', ' W\d+ '];
        foreach ($operators as $operator) {
            foreach ($operators as $operator2) {
                $pattern .= sprintf('%s %s|', $operator, $operator2);
            }
        }
        $pattern = trim($pattern, '|');
        $pattern = preg_replace('/\s+/', ' ', $pattern);
        if (preg_match("/$pattern/", $this->token)) {
            throw new HumanToTsQueryException(sprintf('The query is not valid: %s', $this->token));
        }
    }

    private function checkBracketsAndQuotes(): bool
    {
        $len = strlen($this->token);
        $brackets = $quotes = [];
        for ($i = 0; $i < $len; $i++) {
            switch ($this->token[$i]) {
                case '(':
                    $brackets[] = 0; break;
                case ')':
                    if (array_pop($brackets) !== 0) {
                        return false;
                    }
                    break;
                case '"':
                    $quotes[] = 1; break;
                default: break;
            }
        }
        return 0 === count($brackets) && count($quotes) % 2 === 0;
    }
}

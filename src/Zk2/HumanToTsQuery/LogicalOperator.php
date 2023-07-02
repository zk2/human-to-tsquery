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

class LogicalOperator
{
    const MAX_PROXIMITY = 15;

    private string $name;

    private string $operator;

    public static function create(?string $operator): self
    {
        if ($logicalOperator = self::get($operator)) {
            return new self($logicalOperator['name'], $logicalOperator['operator']);
        }

        return new self();
    }

    public static function check(?string $operator): bool
    {
        return (bool) self::get($operator);
    }

    private static function get(?string $operator): ?array
    {
        $operator = strtoupper((string)$operator);
        if ('AND' === $operator) {
            return ['name' => 'AND', 'operator' => '&'];
        } elseif ('OR' === $operator) {
            return ['name' => 'OR', 'operator' => '|'];
        } elseif (preg_match('/^[N](\d+)$/', $operator, $matches)) {
            if ((int) $matches[1] > self::MAX_PROXIMITY) {
                return ['name' => 'AND', 'operator' => '&'];
            }
            return ['name' => 'N', 'operator' => (int) $matches[1]];
        } elseif (preg_match('/^[W](\d+)$/', $operator, $matches)) {
            if ((int) $matches[1] > self::MAX_PROXIMITY) {
                return ['name' => 'AND', 'operator' => '&'];
            }
            return ['name' => 'W', 'operator' => (int) $matches[1]];
        }

        return null;
    }
    
    private function __construct(string $name = 'AND', string $operator = '&')
    {
        $this->name = $name;
        $this->operator = $operator;
    }

    public function __toString()
    {
        return (string) $this->operator;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getOperator(): string
    {
        return $this->operator;
    }
    
    public function isProximity(): bool
    {
        return in_array($this->name, ['N', 'W']);
    }

    public function isLogical(): bool
    {
        return in_array($this->name, ['AND', 'OR']);
    }
}

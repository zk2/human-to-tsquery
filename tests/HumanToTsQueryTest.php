<?php
/**
 * This file is part of the HumanToTsQuery package.
 *
 * (c) Evgeniy Budanov <budanov.ua@gmail.comm> 2019.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Zk2\Tests;

use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\ClientBuilder;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use PHPUnitColors\Display;
use Zk2\HumanToTsQuery\HumanToTsQuery;
use Zk2\HumanToTsQuery\HumanToTsQueryException;

class HumanToTsQueryTest extends TestCase
{
    protected Connection $connection;

    protected ?Client $elastic = null;

    protected bool $realPostgres = false;

    protected bool $realElastic = false;

    /**
     * @dataProvider humanQueries
     */
    public function test(string $humanQuery): void
    {
        $connection = $this->connection;
        $function = function (string $sql) use ($connection) {
            $stmt = $connection->executeQuery($sql);

            return $stmt->fetchOne();
        };

        $humanToTsQuery = new HumanToTsQuery($humanQuery);
        $tsQuery = $humanToTsQuery->getQuery($function);
        $this->assertNotEmpty($tsQuery);
        $esQuery = $humanToTsQuery->getElasticSearchQuery();
        $this->assertNotEmpty($esQuery);
        if ($this->realElastic) {
            $params = [
                "index" => 'human-to-tsquery',
                "body" => [
                    "query" => [
                        "bool" => [
                            "should" => [
                                "query_string" => [
                                    "query" => $esQuery,
                                ],
                            ],
                        ],
                    ],
                    "_source" => false,
                ],
            ];
            $res = $this->elastic->search($params)->getStatusCode();
            $this->assertEquals(200, $res);
        }
    }

    /**
     * @dataProvider badHumanQueries
     */
    public function testBad(string $humanQuery): void
    {
        $this->expectException(HumanToTsQueryException::class);
        $humanToTsQuery = new HumanToTsQuery($humanQuery);
        $humanToTsQuery->getQuery();
        $humanToTsQuery->getElasticSearchQuery();
    }

    public function testIsPostgres(): void
    {
        if (false === $this->realPostgres) {
            echo Display::caution(" Postgresql is down...");
        }
        if (false === $this->realElastic) {
            echo Display::caution(" ElasticSearch is down...");
        }
        $this->assertTrue(true);
    }

    public function humanQueries(): array
    {
        return [
            ['(indigenous OR texas) W2 ("debt financing" OR lalala) AND ("New York" OR Boston)'],
            ['Opel AND (auto car (patrol OR diesel OR "electric car") AND sale)'],
            ['Nissan\'s AND \'Qashqai\' (auto AND \'car\' (patrol OR diesel OR "electric car") AND sale)'],
            ['Opel AND -(auto car (patrol OR diesel OR "electric car") AND -sale)'],
            ['Nissan\'s AND -\'Qashqai\' (auto AND \'car\' (patrol OR diesel OR "electric car") AND sale)'],
            ['(Opel N2 auto) AND (auto car (patrol OR diesel OR "electric car") AND sale)'],
            ['(Opel W2 auto) AND (auto car (patrol OR diesel OR "electric car") AND sale)'],
            ['Opel N1 car'],
            ['Opel W5 car'],
            ['intitle:"مقالاتي" -"market growth" -"market report" -"research report" -"market research" -"market analysis" -"service market"'],
        ];
    }

    public function badHumanQueries(): array
    {
        return [
            ['Opel AND (auto) car (patrol OR diesel OR "electric car") AND sale)'],
            ['Nissan\'s AND \'Qashqai\' (auto AND \'car\' (patrol OR "diesel OR "electric car") AND sale)'],
            ['Opel) AND -(auto car (patrol OR diesel OR "electric car") AND -sale)'],
            ['"Nissan\'s AND -\'Qashqai\' (auto AND \'car\' (patrol OR diesel OR "electric car") AND sale)'],
            ['Opel N5 AND Car'],
            ['Opel W5 AND Car'],
            ['Opel OR AND Car'],
        ];
    }

    protected function getConnectionMock(): Connection
    {
        $mock = $this->getMockBuilder('Doctrine\DBAL\Connection')
            ->disableOriginalConstructor()
            ->onlyMethods(['executeQuery'])
            ->getMock();
        $mock->expects($this->any())
            ->method('executeQuery')
            ->will($this->returnValue($this->getStatementMock()));

        return $mock;
    }

    /**
     * @dataProvider esCompoundQueries
     */
    public function testEsCompoundQuery(string $humanQuery, array $expectedEsQuery): void
    {
        $humanToTsQuery = new HumanToTsQuery($humanQuery);
        $esQuery = $humanToTsQuery->getElasticCompoundSearchQuery(
            [
                'fields' => ['field_1', 'field_2'],
                'quotes' => ['field_1_q', 'field_2_q'],
            ]
        );

        $this->assertEquals($expectedEsQuery, $esQuery);
        if ($this->realElastic) {
            $params = [
                "index" => 'human-to-tsquery',
                "body" => [
                    "query" => $esQuery,
                    "_source" => false,
                ],
            ];
            $res = $this->elastic->search($params)->getStatusCode();
            $this->assertEquals(200, $res);
        }
    }

    public function esCompoundQueries(): array
    {
        return [
            [
                '(indigenous OR texas) W2 ("debt financing" OR lalala) AND ("New York" OR Boston)',
                ['bool' => [
                    'must' => [
                        ['bool' => ['should' => [
                            ['intervals' =>
                                ['field_1' =>
                                    ['all_of' => [
                                        'max_gaps' => '2',
                                        'intervals' =>
                                            [
                                                ['match' => ['query' => 'indigenous OR texas']],
                                                ['match' => ['query' => '"debt financing" OR lalala']]
                                            ],

                                    ]]]
                            ],
                            ['intervals' =>
                                ['field_2' =>
                                    ['all_of' => [
                                        'max_gaps' => '2',
                                        'intervals' =>
                                            [
                                                ['match' => ['query' => 'indigenous OR texas']],
                                                ['match' => ['query' => '"debt financing" OR lalala']]
                                            ],
                                    ]]]
                            ]
                        ]]],
                        ['bool' => ['should' =>
                            [
                                ['query_string' => ['fields' => ['field_1_q', 'field_2_q'], 'query' => '"New York"']],
                                ['query_string' => ['fields' => ['field_1', 'field_2'], 'query' => 'Boston']]]
                        ]]
                    ]
                ]]
            ],
            [
                'Opel OR (auto car AND (patrol OR diesel OR "electric car") AND sale)',
                ['bool' => ['should' => [
                        ['query_string' => ['fields' => ['field_1', 'field_2'], 'query' => 'Opel']],
                        ['bool' => [
                                'must' => [
                                    ['query_string' => ['fields' => ['field_1', 'field_2'], 'query' => 'auto']],
                                    ['query_string' => ['fields' => ['field_1', 'field_2'], 'query' => 'car']],
                                    [
                                        'bool' => ['should' => [
                                            ['query_string' => ['fields' => ['field_1', 'field_2'], 'query' => 'patrol']],
                                            ['query_string' => ['fields' => ['field_1', 'field_2'], 'query' => 'diesel']],
                                            ['query_string' => ['fields' => ['field_1_q', 'field_2_q'], 'query' => '"electric car"']],
                                        ]]
                                    ],
                                    ['query_string' => ['fields' => ['field_1', 'field_2'], 'query' => 'sale']],
                                ]
                            ]
                        ],
                    ]]]
            ],
            [
                'Nissan\'s AND \'Qashqai\' auto AND (patrol OR diesel OR "electric car")',
                ['bool' => ['must' => [
                    ['query_string' => ['fields' => ['field_1', 'field_2'], 'query' => 'Nissan\'s']],
                    ['query_string' => ['fields' => ['field_1', 'field_2'], 'query' => '\'Qashqai\'']],
                    ['query_string' => ['fields' => ['field_1', 'field_2'], 'query' => 'auto']],
                    ['bool' => ['should' => [
                        ['query_string' => ['fields' => ['field_1', 'field_2'], 'query' => 'patrol']],
                        ['query_string' => ['fields' => ['field_1', 'field_2'], 'query' => 'diesel']],
                        ['query_string' => ['fields' => ['field_1_q', 'field_2_q'], 'query' => '"electric car"']],
                    ]]]
                ]]]
            ],
            [
                'Opel -sale',
                ['bool' => ['must' => [
                    ['query_string' => ['fields' => ['field_1', 'field_2'], 'query' => 'Opel']],
                    ['query_string' => ['fields' => ['field_1', 'field_2'], 'query' => 'NOT sale']],
                ]]]
            ],
            [
                'Opel OR car -sale',
                ['bool' => ['must' => [
                    ['bool' => ['should' => [
                        ['query_string' => ['fields' => ['field_1', 'field_2'], 'query' => 'Opel']],
                        ['query_string' => ['fields' => ['field_1', 'field_2'], 'query' => 'car']],
                    ]]],
                    ['bool' => ['must' => [
                        ['query_string' => ['fields' => ['field_1', 'field_2'], 'query' => 'NOT sale']],
                    ]]],
                ]]]
            ],
            [
            '"big bus" AND (Opel OR car) -sale -"car shop"',
                ['bool' => ['must' => [
                    ['query_string' => ['fields' => ['field_1_q', 'field_2_q'], 'query' => '"big bus"']],
                    ['bool' => ['should' => [
                        ['query_string' => ['fields' => ['field_1', 'field_2'], 'query' => 'Opel']],
                        ['query_string' => ['fields' => ['field_1', 'field_2'], 'query' => 'car']],
                    ]]],
                    ['query_string' => ['fields' => ['field_1', 'field_2'], 'query' => 'NOT sale']],
                    ['query_string' => ['fields' => ['field_1_q', 'field_2_q'], 'query' => 'NOT "car shop"']],
                ]]]
            ],
            [
                'Opel N1 car',
                ['bool' => ['must' => [
                    ['bool' => ['should' => [
                        ['intervals' =>
                            ['field_1' =>
                                ['all_of' => [
                                    'max_gaps' => '1',
                                    'intervals' => [['match' => ['query' => 'Opel']], ['match' => ['query' => 'car']]],
                                ]]]
                        ],
                        ['intervals' =>
                            ['field_2' =>
                                ['all_of' => [
                                    'max_gaps' => '1',
                                    'intervals' => [['match' => ['query' => 'Opel']], ['match' => ['query' => 'car']]],
                                ]]]
                        ]
                    ]]]
                ]]],
            ],
            [
                'Opel W5 car',
                ['bool' => ['must' => [
                    ['bool' => ['should' => [
                        ['intervals' =>
                            ['field_1' =>
                                ['all_of' => [
                                    'max_gaps' => '5',
                                    'intervals' => [['match' => ['query' => 'Opel']], ['match' => ['query' => 'car']]],
                                ]]]
                        ],
                        ['intervals' =>
                            ['field_2' =>
                                ['all_of' => [
                                    'max_gaps' => '5',
                                    'intervals' => [['match' => ['query' => 'Opel']], ['match' => ['query' => 'car']]],
                                ]]]
                        ]
                    ]]]
                ]]],
            ],
            [
                '"market growth" -"market report" -"research report" -"market research" -"market analysis" -"service market"',
                ['bool' => ['must' => [
                    ['query_string' => ['fields' => ['field_1_q', 'field_2_q'], 'query' => '"market growth"']],
                    ['query_string' => ['fields' => ['field_1_q', 'field_2_q'], 'query' => 'NOT "market report"']],
                    ['query_string' => ['fields' => ['field_1_q', 'field_2_q'], 'query' => 'NOT "research report"']],
                    ['query_string' => ['fields' => ['field_1_q', 'field_2_q'], 'query' => 'NOT "market research"']],
                    ['query_string' => ['fields' => ['field_1_q', 'field_2_q'], 'query' => 'NOT "market analysis"']],
                    ['query_string' => ['fields' => ['field_1_q', 'field_2_q'], 'query' => 'NOT "service market"']],
                ]]]
            ],
        ];
    }

    protected function getStatementMock(): MockObject
    {
        $mock = $this->getAbstractMock('Doctrine\DBAL\Driver\Statement', ['fetchOne']);
        $mock->expects($this->any())
            ->method('fetchOne')
            ->will($this->returnValue('token'));

        return $mock;
    }

    protected function getAbstractMock(string $class, array $methods): MockObject
    {
        return $this->getMockForAbstractClass($class, [], '', true, true, true, $methods, false);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $check = @fsockopen('127.0.0.1', 5444);
        $this->realPostgres = (bool) $check;
        if (false !== $check) {
            fclose($check);
            $config = new Configuration();
            $connectionParams = [
                'dbname' => 'human-to-tsquery',
                'user' => 'human-to-tsquery',
                'password' => 'human-to-tsquery',
                'host' => '127.0.0.1',
                'port' => 5444,
                'driver' => 'pdo_pgsql',
            ];
            $this->connection = DriverManager::getConnection($connectionParams, $config);
        } else {
            $this->connection = $this->getConnectionMock();
        }
        $check = @fsockopen('127.0.0.1', 9222);
        $this->realElastic = (bool) $check;
        if (false !== $check) {
            fclose($check);
            $cb = ClientBuilder::create()
                ->setHosts(['http://127.0.0.1:9222']);
            $this->elastic = $cb->build();
            if (!$this->elastic->indices()->exists(["index" => 'human-to-tsquery'])->asBool()) {
                $this->elastic->indices()->create(['index' => 'human-to-tsquery']);
                $this->elastic->info();
            }
        }
    }
}

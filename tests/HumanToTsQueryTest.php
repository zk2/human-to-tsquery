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
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use PHPUnitColors\Display;
use Zk2\HumanToTsQuery\HumanToTsQuery;
use Zk2\HumanToTsQuery\HumanToTsQueryException;

/**
 * Class AbstractQueryBuilderTest
 */
class HumanToTsQueryTest extends TestCase
{
    /**
     * @var Connection
     */
    protected $connection;

    /**
     * @var bool
     */
    protected $realPostgres;

    /**
     * @dataProvider humanQueries
     *
     * @param $humanQuery
     *
     * @throws HumanToTsQueryException
     */
    public function test($humanQuery)
    {
        $connection = $this->connection;
        $function = function (string $sql) use ($connection) {
            $stmt = $connection->executeQuery($sql);

            return $stmt->fetchColumn(0);
        };

        $humanToTsQuery = new HumanToTsQuery($humanQuery);
        $tsQuery = $humanToTsQuery->getQuery($function);
        $this->assertNotEmpty($tsQuery);
    }

    /**
     * @dataProvider badHumanQueries
     *
     * @param $humanQuery
     *
     * @throws HumanToTsQueryException
     */
    public function testBad($humanQuery)
    {
        $this->expectException(HumanToTsQueryException::class);
        $humanToTsQuery = new HumanToTsQuery($humanQuery);
        $humanToTsQuery->getQuery();
    }

    public function testIsPostgres()
    {
        if (false === $this->realPostgres) {
            echo Display::caution(" Docker is down...");
        }
        $this->assertTrue(true);
    }

    /**
     * @return array
     */
    public function humanQueries()
    {
        return [
            ['Opel AND (auto car (patrol OR diesel OR "electric car") AND sale)'],
            ['Nissan\'s AND \'Qashqai\' (auto AND \'car\' (patrol OR diesel OR "electric car") AND sale)'],
            ['Opel AND -(auto car (patrol OR diesel OR "electric car") AND -sale)'],
            ['Nissan\'s AND -\'Qashqai\' (auto AND \'car\' (patrol OR diesel OR "electric car") AND sale)'],
        ];
    }

    /**
     * @return array
     */
    public function badHumanQueries()
    {
        return [
            ['Opel AND (auto) car (patrol OR diesel OR "electric car") AND sale)'],
            ['Nissan\'s AND \'Qashqai\' (auto AND \'car\' (patrol OR "diesel OR "electric car") AND sale)'],
            ['Opel) AND -(auto car (patrol OR diesel OR "electric car") AND -sale)'],
            ['"Nissan\'s AND -\'Qashqai\' (auto AND \'car\' (patrol OR diesel OR "electric car") AND sale)'],
        ];
    }

    /**
     * @return \Doctrine\DBAL\Connection|MockObject
     */
    protected function getConnectionMock()
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
     * @return \Doctrine\DBAL\Driver\Statement|MockObject
     */
    protected function getStatementMock()
    {
        $mock = $this->getAbstractMock('Doctrine\DBAL\Driver\Statement', ['fetchColumn']);
        $mock->expects($this->any())
            ->method('fetchColumn')
            ->will($this->returnValue('token'));

        return $mock;
    }

    /**
     * @param string $class   The class name
     * @param array  $methods The available methods
     *
     * @return MockObject
     */
    protected function getAbstractMock($class, array $methods)
    {
        return $this->getMockForAbstractClass($class, [], '', true, true, true, $methods, false);
    }

    /**
     * @throws \Doctrine\DBAL\DBALException
     */
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
            //echo Display::caution(" Docker is down...");
        }
    }
}

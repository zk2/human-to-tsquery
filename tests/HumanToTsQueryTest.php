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

class HumanToTsQueryTest extends TestCase
{
    protected Connection $connection;

    protected bool $realPostgres = false;

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
    }

    /**
     * @dataProvider badHumanQueries
     */
    public function testBad(string $humanQuery): void
    {
        $this->expectException(HumanToTsQueryException::class);
        $humanToTsQuery = new HumanToTsQuery($humanQuery);
        $humanToTsQuery->getQuery();
    }

    public function testIsPostgres(): void
    {
        if (false === $this->realPostgres) {
            echo Display::caution(" Docker is down...");
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
    }
}

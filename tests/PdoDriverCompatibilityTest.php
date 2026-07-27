<?php

declare(strict_types=1);

use Elavora\Api\Extension\DatabasePdo\PdoDatabase;
use PHPUnit\Framework\TestCase;

final class PdoDriverCompatibilityTest extends TestCase
{
    public function testFiltersAndSelectOptionsArePortableAcrossConfiguredDrivers(): void
    {
        foreach ($this->connections() as $driver => $connection) {
            $database = new PdoDatabase($connection);
            $temporary = $driver === 'sqlite' ? '' : 'TEMPORARY ';
            $database->execute(
                "CREATE {$temporary}TABLE pdo_compatibility_probe "
                . '(id INTEGER PRIMARY KEY, name VARCHAR(50) NOT NULL, active INTEGER NOT NULL)'
            );
            $database->execute(
                'INSERT INTO pdo_compatibility_probe (id, name, active) VALUES (:id, :name, :active)',
                ['id' => 1, 'name' => 'Ana', 'active' => 1]
            );
            $database->execute(
                'INSERT INTO pdo_compatibility_probe (id, name, active) VALUES (:id, :name, :active)',
                ['id' => 2, 'name' => 'Bia', 'active' => 1]
            );

            self::assertSame(
                [],
                $database->select(
                    'pdo_compatibility_probe',
                    where: ['active' => 1, 'id' => []]
                ),
                "select IN vazio no driver $driver"
            );
            self::assertFalse(
                $database->exists('pdo_compatibility_probe', ['id' => []]),
                "exists IN vazio no driver $driver"
            );
            self::assertSame(
                0,
                $database->update(
                    'pdo_compatibility_probe',
                    ['active' => 0],
                    ['id' => []]
                ),
                "update IN vazio no driver $driver"
            );
            self::assertSame(
                0,
                $database->delete('pdo_compatibility_probe', ['id' => []]),
                "delete IN vazio no driver $driver"
            );
            self::assertSame(
                [1, 2],
                array_column(
                    $database->select(
                        'pdo_compatibility_probe',
                        ['id'],
                        ['id' => [1, 2]],
                        'id',
                        orderDirection: 'ASC'
                    ),
                    'id'
                ),
                "IN parametrizado no driver $driver"
            );
            self::assertSame(
                [],
                $database->select('pdo_compatibility_probe', limit: 0),
                "LIMIT 0 no driver $driver"
            );
        }
    }

    /**
     * @return iterable<string, PDO>
     */
    private function connections(): iterable
    {
        yield 'sqlite' => new PDO(
            'sqlite::memory:',
            options: [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );

        $mysqlDsn = getenv('PDO_MYSQL_DSN');
        if (is_string($mysqlDsn) && $mysqlDsn !== '') {
            yield 'mysql' => new PDO(
                $mysqlDsn,
                getenv('PDO_MYSQL_USER') ?: getenv('MYSQL_USERNAME') ?: null,
                getenv('PDO_MYSQL_PASSWORD') ?: getenv('MYSQL_PASSWORD') ?: null,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
        }

        $postgresDsn = getenv('PDO_POSTGRES_DSN');
        if (is_string($postgresDsn) && $postgresDsn !== '') {
            yield 'pgsql' => new PDO(
                $postgresDsn,
                getenv('PDO_POSTGRES_USER') ?: getenv('POSTGRES_USERNAME') ?: null,
                getenv('PDO_POSTGRES_PASSWORD') ?: getenv('POSTGRES_PASSWORD') ?: null,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
        }
    }
}

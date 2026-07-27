<?php

declare(strict_types=1);

use Elavora\Api\Extension\DatabasePdo\PdoDatabase;
use PHPUnit\Framework\TestCase;

final class PdoSelectSafetyTest extends TestCase
{
    private PdoDatabase $database;

    protected function setUp(): void
    {
        $this->database = new PdoDatabase(new PDO('sqlite::memory:'));
        $this->database->execute(
            'CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL)'
        );
        $this->database->insert('users', ['name' => 'Ana']);
        $this->database->insert('users', ['name' => 'Bia']);
    }

    public function testZeroLimitReturnsNoRows(): void
    {
        self::assertSame([], $this->database->select('users', limit: 0));
    }

    public function testNegativeLimitIsRejectedBeforeQueryExecution(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('maior ou igual a zero');

        $this->database->select('users', limit: -1);
    }

    public function testStringCannotBeInjectedAsLimit(): void
    {
        try {
            $this->database->select('users', limit: '0; DROP TABLE users');
            self::fail('Um limit textual deveria causar TypeError.');
        } catch (TypeError) {
            self::assertSame(2, (int) $this->database->value('SELECT COUNT(*) FROM users'));
        }
    }

    public function testOrderDirectionOnlyAcceptsAscOrDesc(): void
    {
        self::assertSame(
            ['Bia', 'Ana'],
            array_column($this->database->select('users', orderBy: 'name', orderDirection: 'DESC'), 'name')
        );

        try {
            $this->database->select(
                'users',
                orderBy: 'name',
                orderDirection: 'DESC; DROP TABLE users'
            );
            self::fail('Uma direcao SQL invalida deveria ser rejeitada.');
        } catch (InvalidArgumentException) {
            self::assertSame(2, (int) $this->database->value('SELECT COUNT(*) FROM users'));
        }
    }

    public function testFilterValuesRemainBoundParameters(): void
    {
        self::assertSame(
            [],
            $this->database->select('users', where: ['name' => "Ana' OR 1 = 1 --"])
        );
        self::assertSame(2, (int) $this->database->value('SELECT COUNT(*) FROM users'));
    }
}

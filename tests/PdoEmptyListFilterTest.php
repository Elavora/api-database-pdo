<?php

declare(strict_types=1);

use Elavora\Api\Extension\DatabasePdo\PdoDatabase;
use PHPUnit\Framework\TestCase;

final class PdoEmptyListFilterTest extends TestCase
{
    private PdoDatabase $database;

    protected function setUp(): void
    {
        $this->database = new PdoDatabase(new PDO('sqlite::memory:'));
        $this->database->execute(
            'CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, active INTEGER NOT NULL)'
        );
        $this->database->insert('users', ['name' => 'Ana', 'active' => 1]);
        $this->database->insert('users', ['name' => 'Bia', 'active' => 1]);
    }

    public function testEmptyListIsAlwaysFalseAcrossCrudHelpers(): void
    {
        self::assertSame([], $this->database->select('users', where: ['id' => []]));
        self::assertFalse($this->database->exists('users', ['id' => []]));
        self::assertSame(0, $this->database->update('users', ['active' => 0], ['id' => []]));
        self::assertSame(0, $this->database->delete('users', ['id' => []]));
        self::assertSame(2, (int) $this->database->value('SELECT COUNT(*) FROM users'));
    }

    public function testNonEmptyListKeepsUsingBoundInParameters(): void
    {
        self::assertSame(
            [['id' => 1, 'name' => 'Ana']],
            $this->database->select('users', ['id', 'name'], ['id' => [1]])
        );
        self::assertTrue($this->database->exists('users', ['id' => [2]]));
        self::assertSame(
            [1, 2],
            array_column($this->database->select('users', ['id'], ['id' => [1, 2]]), 'id')
        );
    }

    public function testEmptyListRemainsFalseWhenCombinedWithOtherFilters(): void
    {
        self::assertSame(
            [],
            $this->database->select('users', where: ['active' => 1, 'id' => []])
        );
    }
}

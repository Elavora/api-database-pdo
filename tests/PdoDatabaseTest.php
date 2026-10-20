<?php

declare(strict_types=1);

use Elavora\Api\Extension\DatabasePdo\PdoDatabase;
use Elavora\Api\Extension\DatabasePdo\Tests\Support\InsertableValue;
use PHPUnit\Framework\TestCase;

final class PdoDatabaseTest extends TestCase
{
    private PDO $pdo;
    private PdoDatabase $database;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->database = new PdoDatabase($this->pdo);
    }

    private function seedUsers(): void
    {
        $this->database->execute('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT, active INTEGER)');
        $this->pdo->exec("INSERT INTO users (id, name, active) VALUES (1, 'Ana', 1), (2, 'Bia', 0), (3, 'Caio', 1)");
    }

    // Conexao e driver

    public function testConnectionReturnsSamePdo(): void
    {
        self::assertSame($this->pdo, $this->database->connection());
    }

    public function testDriverIsSqlite(): void
    {
        self::assertSame('sqlite', $this->database->driver());
    }

    public function testSqliteDoesNotSupportReturning(): void
    {
        self::assertFalse($this->database->supportsReturning());
    }

    // Leitura

    public function testExecuteReturnsExecutedStatement(): void
    {
        $this->seedUsers();

        $statement = $this->database->execute('SELECT name FROM users WHERE id = :id', ['id' => 2]);

        self::assertInstanceOf(PDOStatement::class, $statement);
        self::assertSame('Bia', $statement->fetchColumn());
    }

    public function testFetchAllReturnsAssociativeRows(): void
    {
        $this->seedUsers();

        self::assertSame(
            [['id' => 1, 'name' => 'Ana'], ['id' => 3, 'name' => 'Caio']],
            $this->database->fetchAll('SELECT id, name FROM users WHERE active = :active ORDER BY id', ['active' => 1]),
        );
    }

    public function testFetchAllWithoutRowsReturnsEmptyArray(): void
    {
        $this->seedUsers();

        self::assertSame([], $this->database->fetchAll('SELECT id FROM users WHERE id = :id', ['id' => 99]));
    }

    public function testFetchReturnsFirstRowAssociative(): void
    {
        $this->seedUsers();

        self::assertSame(['id' => 1, 'name' => 'Ana', 'active' => 1], $this->database->fetch('SELECT * FROM users ORDER BY id'));
    }

    public function testFetchWithoutRowsReturnsNull(): void
    {
        $this->seedUsers();

        self::assertNull($this->database->fetch('SELECT * FROM users WHERE id = :id', ['id' => 99]));
    }

    public function testValueReturnsFirstColumnOfFirstRow(): void
    {
        $this->seedUsers();

        self::assertSame('Ana', $this->database->value('SELECT name FROM users WHERE id = :id', ['id' => 1]));
    }

    public function testValueWithoutRowsReturnsNull(): void
    {
        $this->seedUsers();

        self::assertNull($this->database->value('SELECT name FROM users WHERE id = :id', ['id' => 99]));
    }

    // select e regras do where

    public function testSelectExampleColumnsArrayAndWhere(): void
    {
        $this->seedUsers();

        self::assertSame(
            [['id' => 1, 'name' => 'Ana'], ['id' => 3, 'name' => 'Caio']],
            $this->database->select('users', ['id', 'name'], ['active' => 1]),
        );
    }

    public function testSelectExampleOrderByAndLimit(): void
    {
        $this->seedUsers();

        self::assertSame([['name' => 'Caio']], $this->database->select('users', 'name', null, 'id DESC', 1));
    }

    public function testSelectLimitAsString(): void
    {
        $this->seedUsers();

        self::assertSame([['name' => 'Caio']], $this->database->select('users', 'name', null, 'id DESC', '1'));
    }

    public function testSelectEmptyColumnsAndInList(): void
    {
        $this->seedUsers();

        self::assertSame(
            [['id' => 1, 'name' => 'Ana', 'active' => 1], ['id' => 2, 'name' => 'Bia', 'active' => 0]],
            $this->database->select('users', [], ['id' => [1, 2]], 'id'),
        );
    }

    public function testSelectWithoutMatchesReturnsEmptyArray(): void
    {
        $this->seedUsers();

        self::assertSame([], $this->database->select('users', 'id', ['name' => 'Zé']));
    }

    public function testSelectDefaultsReturnAllRowsAndColumns(): void
    {
        $this->seedUsers();

        $rows = $this->database->select('users');

        self::assertCount(3, $rows);
        self::assertSame(['id', 'name', 'active'], array_keys($rows[0]));
    }

    public function testSelectEmptyWhereArrayHasNoWhere(): void
    {
        $this->seedUsers();

        self::assertCount(3, $this->database->select('users', 'id', []));
    }

    public function testSelectEmptyOrderByAndLimitAreIgnored(): void
    {
        $this->seedUsers();

        self::assertCount(3, $this->database->select('users', 'id', null, '', ''));
    }

    public function testSelectColumnsStringIsUsedAsIs(): void
    {
        $this->seedUsers();

        self::assertSame([['total' => 3]], $this->database->select('users', 'COUNT(*) AS total'));
    }

    public function testWhereStringIsUsedAsIs(): void
    {
        $this->seedUsers();

        self::assertSame([['id' => 3]], $this->database->select('users', 'id', 'id > 2'));
    }

    public function testWhereNullValueBecomesIsNull(): void
    {
        $this->seedUsers();
        $this->pdo->exec("INSERT INTO users (id, name, active) VALUES (4, NULL, 1)");

        self::assertSame([['id' => 4]], $this->database->select('users', 'id', ['name' => null]));
    }

    public function testWhereMultipleConditionsAreJoinedWithAnd(): void
    {
        $this->seedUsers();

        self::assertSame([['id' => 3]], $this->database->select('users', 'id', ['active' => 1, 'name' => 'Caio']));
    }

    public function testWhereNumericKeyIsRawTextJoinedWithAnd(): void
    {
        $this->seedUsers();

        self::assertSame([['id' => 3]], $this->database->select('users', 'id', ['active' => 1, 'id > 1']));
    }

    // insert

    public function testInsertReturnsLastInsertIdAsString(): void
    {
        $this->database->execute('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT, active INTEGER)');

        self::assertSame('1', $this->database->insert('users', ['name' => 'Ana', 'active' => 1]));
        self::assertSame('2', $this->database->insert('users', ['name' => 'Bia', 'active' => 0]));
        self::assertSame(['id' => 2, 'name' => 'Bia', 'active' => 0], $this->database->fetch('SELECT * FROM users WHERE id = 2'));
    }

    public function testInsertWithReturningOnNonPgsqlReturnsLastInsertId(): void
    {
        $this->database->execute('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT)');

        self::assertSame('1', $this->database->insert('users', ['name' => 'Ana'], 'id'));
    }

    public function testInsertWithEmptyDataThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Insert PDO deve receber ao menos um campo.');

        $this->database->insert('users', []);
    }

    // update

    public function testUpdateReturnsAffectedRows(): void
    {
        $this->seedUsers();

        self::assertSame(2, $this->database->update('users', ['active' => 0], ['active' => 1]));
        self::assertSame(0, (int) $this->database->value('SELECT COUNT(*) FROM users WHERE active = 1'));
    }

    public function testUpdateWithoutMatchesReturnsZero(): void
    {
        $this->seedUsers();

        self::assertSame(0, $this->database->update('users', ['name' => 'X'], ['id' => 99]));
    }

    public function testUpdateSameColumnInSetAndWhere(): void
    {
        $this->seedUsers();

        self::assertSame(1, $this->database->update('users', ['name' => 'Maria'], ['name' => 'Ana']));
        self::assertSame('Maria', $this->database->value('SELECT name FROM users WHERE id = 1'));
    }

    public function testUpdateWithStringWhere(): void
    {
        $this->seedUsers();

        self::assertSame(2, $this->database->update('users', ['active' => 5], 'id >= 2'));
    }

    public function testUpdateWithEmptyDataThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Update PDO deve receber ao menos um campo.');

        $this->database->update('users', [], ['id' => 1]);
    }

    public function testUpdateWithUnnamedFieldsThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Update PDO deve receber campos nomeados.');

        $this->database->update('users', ['Maria'], ['id' => 1]);
    }

    public function testUpdateWithEmptyStringKeyThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Update PDO deve receber campos nomeados.');

        $this->database->update('users', ['' => 'Maria'], ['id' => 1]);
    }

    // delete

    public function testDeleteReturnsDeletedRows(): void
    {
        $this->seedUsers();

        self::assertSame(2, $this->database->delete('users', ['id' => [1, 3]]));
        self::assertSame([['id' => 2]], $this->database->select('users', 'id'));
    }

    public function testDeleteWithStringWhere(): void
    {
        $this->seedUsers();

        self::assertSame(1, $this->database->delete('users', 'id = 2'));
    }

    // exists

    public function testExistsTrueAndFalse(): void
    {
        $this->seedUsers();

        self::assertTrue($this->database->exists('users', ['name' => 'Bia']));
        self::assertFalse($this->database->exists('users', ['name' => 'Zé']));
        self::assertTrue($this->database->exists('users', 'id > 2'));
        self::assertTrue($this->database->exists('users'));
    }

    public function testExistsWithNullWhereOnEmptyTableIsFalse(): void
    {
        $this->database->execute('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT, active INTEGER)');

        self::assertFalse($this->database->exists('users'));
        self::assertFalse($this->database->exists('users', null));
    }

    // Insertable

    public function testInsertConvertsInsertableValues(): void
    {
        $this->database->execute('CREATE TABLE users (email TEXT)');

        $this->database->insert('users', ['email' => new InsertableValue('team@api.dev')]);

        self::assertSame('team@api.dev', $this->database->value('SELECT email FROM users'));
    }

    public function testExecuteConvertsInsertableValues(): void
    {
        $this->seedUsers();

        $statement = $this->database->execute('SELECT name FROM users WHERE id = :id', ['id' => new InsertableValue(3)]);

        self::assertSame('Caio', $statement->fetchColumn());
    }

    public function testUpdateConvertsInsertableValuesInDataAndWhere(): void
    {
        $this->seedUsers();

        self::assertSame(1, $this->database->update('users', ['name' => new InsertableValue('Maria')], ['name' => new InsertableValue('Ana')]));
        self::assertSame('Maria', $this->database->value('SELECT name FROM users WHERE id = 1'));
    }

    public function testDeleteConvertsInsertableValues(): void
    {
        $this->seedUsers();

        self::assertSame(1, $this->database->delete('users', ['id' => new InsertableValue(2)]));
    }

    public function testSelectConvertsInsertableValues(): void
    {
        $this->seedUsers();

        self::assertSame([['id' => 2]], $this->database->select('users', 'id', ['name' => new InsertableValue('Bia')]));
    }

    public function testSelectConvertsInsertableValuesInsideInList(): void
    {
        $this->seedUsers();

        self::assertSame(
            [['id' => 1], ['id' => 3]],
            $this->database->select('users', 'id', ['id' => [new InsertableValue(1), new InsertableValue(3)]], 'id'),
        );
    }

    public function testExistsConvertsInsertableValues(): void
    {
        $this->seedUsers();

        self::assertTrue($this->database->exists('users', ['name' => new InsertableValue('Caio')]));
        self::assertFalse($this->database->exists('users', ['name' => new InsertableValue('Zé')]));
    }
}

<?php

declare(strict_types=1);

use Elavora\Api\Extension\DatabasePdo\PdoDatabase;
use Elavora\Api\Extension\DatabasePdo\PdoExtension;
use Elavora\Api\Extension\DatabasePdo\Tests\Support\InsertableValue;
use Elavora\Api\Framework\Application;
use Elavora\Api\Framework\Contracts\TransactionManager;
use PHPUnit\Framework\TestCase;

final class PdoTransactionManagerTest extends TestCase
{
    public function testRegistersPdoDatabaseAsTransactionManager(): void
    {
        $application = Application::create()->extend(new PdoExtension(['dsn' => 'sqlite::memory:']));
        $transactionManager = $application->container()->get(TransactionManager::class);

        self::assertInstanceOf(TransactionManager::class, $transactionManager);
        self::assertInstanceOf(PdoDatabase::class, $transactionManager);
        self::assertTrue($transactionManager->begin());
        self::assertTrue($transactionManager->rollback());
    }

    public function testPdoDatabaseImplementsTransactionManager(): void
    {
        self::assertInstanceOf(TransactionManager::class, new PdoDatabase(new PDO('sqlite::memory:')));
    }

    public function testConvertsInsertableValuesBeforeExecutingSql(): void
    {
        $database = new PdoDatabase(new PDO('sqlite::memory:'));
        $database->execute('CREATE TABLE users (email TEXT)');

        $database->insert('users', ['email' => new InsertableValue('team@api.dev')]);

        self::assertSame('team@api.dev', $database->value('SELECT email FROM users'));
    }

    public function testRollbackDiscardsInsert(): void
    {
        $database = new PdoDatabase(new PDO('sqlite::memory:'));
        $database->execute('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT)');

        self::assertTrue($database->begin());
        $database->insert('users', ['name' => 'Ana']);
        self::assertTrue($database->rollback());

        self::assertFalse($database->exists('users', ['name' => 'Ana']));
    }

    public function testCommitKeepsInsert(): void
    {
        $database = new PdoDatabase(new PDO('sqlite::memory:'));
        $database->execute('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT)');

        self::assertTrue($database->begin());
        $database->insert('users', ['name' => 'Ana']);
        self::assertTrue($database->commit());

        self::assertTrue($database->exists('users', ['name' => 'Ana']));
        self::assertFalse($database->connection()->inTransaction());
    }

    public function testBeginOpensTransactionOnPdo(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $database = new PdoDatabase($pdo);

        $database->begin();

        self::assertTrue($pdo->inTransaction());
    }

    public function testCommitWithoutTransactionThrowsPdoException(): void
    {
        $this->expectException(PDOException::class);

        (new PdoDatabase(new PDO('sqlite::memory:')))->commit();
    }

    public function testRollbackWithoutTransactionThrowsPdoException(): void
    {
        $this->expectException(PDOException::class);

        (new PdoDatabase(new PDO('sqlite::memory:')))->rollback();
    }

    public function testBeginWithOpenTransactionThrowsPdoException(): void
    {
        $database = new PdoDatabase(new PDO('sqlite::memory:'));
        $database->begin();

        $this->expectException(PDOException::class);

        $database->begin();
    }

    public function testContainerTransactionRollbackIsSeenByPdoDatabase(): void
    {
        $container = Application::create()->extend(new PdoExtension(['dsn' => 'sqlite::memory:']))->container();
        $transactionManager = $container->get(TransactionManager::class);
        $database = $container->get(PdoDatabase::class);
        $database->execute('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT)');

        $transactionManager->begin();
        $database->insert('users', ['name' => 'Ana']);
        self::assertTrue($database->connection()->inTransaction());
        $transactionManager->rollback();

        self::assertFalse($database->exists('users', ['name' => 'Ana']));
    }

    public function testContainerTransactionCommitIsSeenByPdoDatabase(): void
    {
        $container = Application::create()->extend(new PdoExtension(['dsn' => 'sqlite::memory:']))->container();
        $transactionManager = $container->get(TransactionManager::class);
        $database = $container->get(PdoDatabase::class);
        $database->execute('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT)');

        $transactionManager->begin();
        $database->insert('users', ['name' => 'Ana']);
        $transactionManager->commit();

        self::assertTrue($database->exists('users', ['name' => 'Ana']));
    }
}

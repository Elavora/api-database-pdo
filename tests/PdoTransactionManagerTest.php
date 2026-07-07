<?php

declare(strict_types=1);

use Elavora\Api\Extension\DatabasePdo\PdoExtension;
use Elavora\Api\Extension\DatabasePdo\PdoDatabase;
use Elavora\Api\Framework\Application;
use Elavora\Api\Framework\Contracts\Insertable;
use Elavora\Api\Framework\Contracts\TransactionManager;
use PHPUnit\Framework\TestCase;

final class PdoTransactionManagerTest extends TestCase
{
    public function testRegistersPdoDatabaseAsTransactionManager(): void
    {
        $application = Application::create()->extend(new PdoExtension(['dsn' => 'sqlite::memory:']));
        $transactionManager = $application->container()->get(TransactionManager::class);

        self::assertInstanceOf(TransactionManager::class, $transactionManager);
        self::assertTrue($transactionManager->begin());
        self::assertTrue($transactionManager->rollback());
    }

    public function testConvertsInsertableValuesBeforeExecutingSql(): void
    {
        $database = new PdoDatabase(new PDO('sqlite::memory:'));
        $database->execute('CREATE TABLE users (email TEXT)');

        $database->insert('users', ['email' => new InsertableValue('team@api.dev')]);

        self::assertSame('team@api.dev', $database->value('SELECT email FROM users'));
    }
}

final class InsertableValue implements Insertable
{
    public function __construct(private readonly string $value)
    {
    }

    public function value(): string|int|bool|float|null
    {
        return $this->value;
    }
}

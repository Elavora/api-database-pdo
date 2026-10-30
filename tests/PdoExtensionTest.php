<?php

declare(strict_types=1);

use Elavora\Api\Extension\DatabasePdo\PdoConnectionFactory;
use Elavora\Api\Extension\DatabasePdo\PdoDatabase;
use Elavora\Api\Extension\DatabasePdo\PdoExtension;
use Elavora\Api\Framework\Application;
use Elavora\Api\Framework\Contracts\DatabaseConnectionFactory;
use Elavora\Api\Framework\Contracts\Extension;
use Elavora\Api\Framework\Contracts\TransactionManager;
use PHPUnit\Framework\TestCase;

final class PdoExtensionTest extends TestCase
{
    public function testImplementsExtension(): void
    {
        self::assertInstanceOf(Extension::class, new PdoExtension(['dsn' => 'sqlite::memory:']));
    }

    public function testRegistersFactoryDatabaseAndTransactionManager(): void
    {
        $container = Application::create()->extend(new PdoExtension(['dsn' => 'sqlite::memory:']))->container();

        self::assertInstanceOf(PdoConnectionFactory::class, $container->get(DatabaseConnectionFactory::class));
        self::assertInstanceOf(PdoDatabase::class, $container->get(PdoDatabase::class));
        self::assertInstanceOf(PdoDatabase::class, $container->get(TransactionManager::class));
    }

    public function testTransactionManagerIsSameObjectAsPdoDatabase(): void
    {
        $container = Application::create()->extend(new PdoExtension(['dsn' => 'sqlite::memory:']))->container();

        self::assertSame($container->get(PdoDatabase::class), $container->get(TransactionManager::class));
    }

    public function testTransactionManagerResolvedFirstIsSameObjectAsPdoDatabase(): void
    {
        $container = Application::create()->extend(new PdoExtension(['dsn' => 'sqlite::memory:']))->container();

        $transactionManager = $container->get(TransactionManager::class);

        self::assertSame($transactionManager, $container->get(PdoDatabase::class));
    }

    public function testResolutionsAreReused(): void
    {
        $container = Application::create()->extend(new PdoExtension(['dsn' => 'sqlite::memory:']))->container();

        self::assertSame($container->get(DatabaseConnectionFactory::class), $container->get(DatabaseConnectionFactory::class));
        self::assertSame($container->get(PdoDatabase::class), $container->get(PdoDatabase::class));
        self::assertSame($container->get(TransactionManager::class), $container->get(TransactionManager::class));
    }

    public function testPdoDatabaseUsesDefaultConnectionOfRegisteredFactory(): void
    {
        $container = Application::create()->extend(new PdoExtension(['dsn' => 'sqlite::memory:']))->container();

        self::assertSame(
            $container->get(DatabaseConnectionFactory::class)->connection(),
            $container->get(PdoDatabase::class)->connection(),
        );
    }

    public function testPdoDatabaseUsesDefaultOfNamedConnections(): void
    {
        $container = Application::create()->extend(new PdoExtension([
            'connections' => [
                'default' => ['dsn' => 'sqlite::memory:'],
                'relatorios' => ['dsn' => 'sqlite::memory:'],
            ],
        ]))->container();

        $factory = $container->get(DatabaseConnectionFactory::class);
        $database = $container->get(PdoDatabase::class);

        self::assertSame($factory->connection('default'), $database->connection());
        self::assertNotSame($factory->connection('relatorios'), $database->connection());
    }

    public function testRegisterWithInvalidConfigDoesNotThrow(): void
    {
        $application = Application::create()->extend(new PdoExtension([]));

        self::assertInstanceOf(Application::class, $application);
    }

    public function testRegisterDoesNotOpenConnection(): void
    {
        $container = Application::create()->extend(new PdoExtension(['dsn' => 'driverinexistente:qualquer']))->container();

        self::assertInstanceOf(PdoConnectionFactory::class, $container->get(DatabaseConnectionFactory::class));
    }

    public function testInvalidConfigFailsWhenResolvingPdoDatabase(): void
    {
        $container = Application::create()->extend(new PdoExtension([]))->container();

        self::assertResolutionFails(static fn() => $container->get(PdoDatabase::class));
    }

    public function testInvalidConfigFailsWhenResolvingTransactionManager(): void
    {
        $container = Application::create()->extend(new PdoExtension([]))->container();

        self::assertResolutionFails(static fn() => $container->get(TransactionManager::class));
    }

    /**
     * A doc diz que o erro aparece ao resolver, sem fixar se o container embrulha a exceção.
     * Procura a InvalidArgumentException da factory na cadeia de exceções.
     */
    private static function assertResolutionFails(callable $resolve): void
    {
        try {
            $resolve();
        } catch (Throwable $throwable) {
            for ($current = $throwable; $current !== null; $current = $current->getPrevious()) {
                if ($current instanceof InvalidArgumentException) {
                    self::assertSame("Conexao PDO 'default' nao esta configurada.", $current->getMessage());

                    return;
                }
            }

            self::fail('Esperava InvalidArgumentException na cadeia, veio ' . $throwable::class . ': ' . $throwable->getMessage());
        }

        self::fail('Esperava falha ao resolver com configuracao invalida.');
    }
}

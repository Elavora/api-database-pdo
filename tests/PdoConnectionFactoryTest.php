<?php

declare(strict_types=1);

use Elavora\Api\Extension\DatabasePdo\PdoConnectionFactory;
use Elavora\Api\Extension\DatabasePdo\PdoDatabase;
use Elavora\Api\Extension\DatabasePdo\PdoExtension;
use Elavora\Api\Framework\Application;
use Elavora\Api\Framework\Contracts\DatabaseConnectionFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PdoConnectionFactoryTest extends TestCase
{
    public function testCreatesReusablePdoConnection(): void
    {
        $application = Application::create()->extend(new PdoExtension(['dsn' => 'sqlite::memory:']));
        $factory = $application->container()->get(DatabaseConnectionFactory::class);

        self::assertSame($factory->connection(), $factory->connection());
        self::assertSame(1, (int) $factory->connection()->query('SELECT 1')->fetchColumn());
    }

    public function testImplementsDatabaseConnectionFactory(): void
    {
        self::assertInstanceOf(DatabaseConnectionFactory::class, new PdoConnectionFactory(['dsn' => 'sqlite::memory:']));
    }

    public function testConstructorDoesNotValidateNorOpenConnection(): void
    {
        new PdoConnectionFactory([]);
        new PdoConnectionFactory(['dsn' => '']);
        new PdoConnectionFactory(['dsn' => 'driverinexistente:qualquer']);

        $this->addToAssertionCount(1);
    }

    public function testSingleConnectionReturnsSqlitePdo(): void
    {
        $pdo = (new PdoConnectionFactory(['dsn' => 'sqlite::memory:']))->connection();

        self::assertInstanceOf(PDO::class, $pdo);
        self::assertSame('sqlite', $pdo->getAttribute(PDO::ATTR_DRIVER_NAME));
    }

    public function testNullAndDefaultReturnSameInstance(): void
    {
        $factory = new PdoConnectionFactory(['dsn' => 'sqlite::memory:']);

        self::assertSame($factory->connection(), $factory->connection('default'));
        self::assertSame($factory->connection('default'), $factory->connection(null));
    }

    public function testSingleConnectionAcceptsUsernamePasswordAndOptions(): void
    {
        $factory = new PdoConnectionFactory([
            'dsn' => 'sqlite::memory:',
            'username' => null,
            'password' => null,
            'options' => [PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_NUM],
        ]);

        self::assertSame(PDO::FETCH_NUM, $factory->connection()->getAttribute(PDO::ATTR_DEFAULT_FETCH_MODE));
    }

    public function testNamedConnectionsAreReusedPerNameAndDistinctAcrossNames(): void
    {
        $factory = new PdoConnectionFactory([
            'connections' => [
                'a' => ['dsn' => 'sqlite::memory:'],
                'b' => ['dsn' => 'sqlite::memory:'],
            ],
        ]);

        $a = $factory->connection('a');
        $b = $factory->connection('b');

        self::assertInstanceOf(PDO::class, $a);
        self::assertInstanceOf(PDO::class, $b);
        self::assertSame($a, $factory->connection('a'));
        self::assertSame($b, $factory->connection('b'));
        self::assertNotSame($a, $b);
    }

    public function testNamedConnectionPassesOptionsToPdo(): void
    {
        $factory = new PdoConnectionFactory([
            'connections' => [
                'default' => ['dsn' => 'sqlite::memory:'],
                'relatorios' => ['dsn' => 'sqlite::memory:', 'options' => [PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_NUM]],
            ],
        ]);

        self::assertSame($factory->connection(), $factory->connection('default'));
        self::assertSame(PDO::FETCH_NUM, $factory->connection('relatorios')->getAttribute(PDO::ATTR_DEFAULT_FETCH_MODE));
    }

    /** @return iterable<string, array{array<string, mixed>, ?string, string}> */
    public static function invalidConfigurations(): iterable
    {
        yield 'nome diferente de default na conexao unica' => [['dsn' => 'sqlite::memory:'], 'outra', "Conexao PDO 'outra' nao esta configurada."];
        yield 'dsn vazio' => [['dsn' => ''], null, 'A configuracao PDO deve informar um DSN valido.'];
        yield 'dsn nao string' => [['dsn' => 123], null, 'A configuracao PDO deve informar um DSN valido.'];
        yield 'configuracao vazia' => [[], null, "Conexao PDO 'default' nao esta configurada."];
        yield 'configuracao vazia com default explicito' => [[], 'default', "Conexao PDO 'default' nao esta configurada."];
        yield 'default ausente em connections' => [['connections' => ['a' => ['dsn' => 'sqlite::memory:']]], null, "Conexao PDO 'default' nao esta configurada."];
        yield 'nome ausente em connections' => [['connections' => ['a' => ['dsn' => 'sqlite::memory:']]], 'b', "Conexao PDO 'b' nao esta configurada."];
        yield 'conexao nomeada nao array' => [['connections' => ['a' => 'sqlite::memory:']], 'a', "Conexao PDO 'a' nao esta configurada."];
        yield 'conexao nomeada sem dsn' => [['connections' => ['a' => ['username' => 'x']]], 'a', 'A configuracao PDO deve informar um DSN valido.'];
        yield 'conexao nomeada com dsn vazio' => [['connections' => ['a' => ['dsn' => '']]], 'a', 'A configuracao PDO deve informar um DSN valido.'];
    }

    /** @param array<string, mixed> $config */
    #[DataProvider('invalidConfigurations')]
    public function testInvalidConfigurationThrowsOnConnection(array $config, ?string $name, string $message): void
    {
        $factory = new PdoConnectionFactory($config);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($message);

        $factory->connection($name);
    }

    public function testPdoErrorsBubbleAsPdoException(): void
    {
        $factory = new PdoConnectionFactory(['dsn' => 'driverinexistente:qualquer']);

        $this->expectException(PDOException::class);

        $factory->connection();
    }

    public function testPdoDatabaseExecutesCrudQueries(): void
    {
        $application = Application::create()->extend(new PdoExtension(['dsn' => 'sqlite::memory:']));
        $database = $application->container()->get(PdoDatabase::class);

        $database->execute('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, active INTEGER NOT NULL)');

        $id = $database->insert('users', ['name' => 'Ana', 'active' => 1]);

        self::assertSame('1', $id);
        self::assertTrue($database->exists('users', ['name' => 'Ana']));
        self::assertSame([['id' => 1, 'name' => 'Ana']], $database->select('users', ['id', 'name'], ['active' => 1]));
        self::assertSame(1, $database->update('users', ['name' => 'Maria'], ['id' => 1]));
        self::assertSame('Maria', $database->value('SELECT name FROM users WHERE id = :id', ['id' => 1]));
        self::assertSame(1, $database->delete('users', ['id' => 1]));
        self::assertFalse($database->exists('users', ['id' => 1]));
    }
}

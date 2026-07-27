<?php

declare(strict_types=1);

use Elavora\Api\Extension\DatabasePdo\PdoConnectionFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PdoConfigurationValidationTest extends TestCase
{
    public function testCachesDefaultAndNamedConnectionsIndependently(): void
    {
        $factory = new PdoConnectionFactory([
            'connections' => [
                'default' => ['dsn' => 'sqlite::memory:'],
                'analytics' => [
                    'dsn' => 'sqlite::memory:',
                    'options' => [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
                ],
            ],
        ]);

        $default = $factory->connection();
        $analytics = $factory->connection('analytics');

        self::assertSame($default, $factory->connection('default'));
        self::assertSame($analytics, $factory->connection('analytics'));
        self::assertNotSame($default, $analytics);
    }

    public function testRejectsUnknownNamedConnectionInSingleConfiguration(): void
    {
        $factory = new PdoConnectionFactory(['dsn' => 'sqlite::memory:']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Conexao PDO 'analytics' nao esta configurada.");

        $factory->connection('analytics');
    }

    public function testRejectsUnknownDefaultConnectionInConnectionMap(): void
    {
        $factory = new PdoConnectionFactory([
            'connections' => [
                'analytics' => ['dsn' => 'sqlite::memory:'],
            ],
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Conexao PDO 'default' nao esta configurada.");

        $factory->connection();
    }

    /**
     * @param array<string, mixed> $config
     */
    #[DataProvider('invalidConfigurationProvider')]
    public function testRejectsInvalidConfiguration(array $config, string $message): void
    {
        $factory = new PdoConnectionFactory($config);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($message);

        $factory->connection();
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function invalidConfigurationProvider(): iterable
    {
        yield 'dsn ausente' => [[], 'DSN valido'];
        yield 'dsn vazio' => [['dsn' => ''], 'DSN valido'];
        yield 'dsn nao textual' => [['dsn' => 123], 'DSN valido'];
        yield 'username invalido' => [
            ['dsn' => 'sqlite::memory:', 'username' => []],
            'username PDO deve ser uma string ou null',
        ];
        yield 'password invalido' => [
            ['dsn' => 'sqlite::memory:', 'password' => []],
            'password PDO deve ser uma string ou null',
        ];
        yield 'options nao array' => [
            ['dsn' => 'sqlite::memory:', 'options' => 'invalid'],
            'options PDO devem ser um array',
        ];
        yield 'chave de options nao inteira' => [
            ['dsn' => 'sqlite::memory:', 'options' => ['mode' => PDO::ERRMODE_EXCEPTION]],
            'chaves de options PDO devem ser inteiros',
        ];
    }
}

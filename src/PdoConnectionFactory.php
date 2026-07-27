<?php

declare(strict_types=1);

namespace Elavora\Api\Extension\DatabasePdo;

use Elavora\Api\Framework\Contracts\DatabaseConnectionFactory;
use InvalidArgumentException;
use PDO;

final class PdoConnectionFactory implements DatabaseConnectionFactory
{
    /** @var array<string, PDO> */
    private array $connections = [];

    /**
     * @param array<string, mixed> $config Configuracao PDO unica ou mapa de conexoes.
     */
    public function __construct(private readonly array $config)
    {
    }

    /**
     * Retorna uma conexao PDO reutilizada pelo nome informado.
     */
    public function connection(?string $name = null): PDO
    {
        $connectionName = $name ?? 'default';

        if (!isset($this->connections[$connectionName])) {
            $config = $this->connectionConfig($name);
            $this->connections[$connectionName] = new PDO(
                dsn: $config['dsn'],
                username: $config['username'] ?? null,
                password: $config['password'] ?? null,
                options: $config['options'] ?? []
            );
        }

        return $this->connections[$connectionName];
    }

    /**
     * @return array{
     *     dsn: string,
     *     username: string|null,
     *     password: string|null,
     *     options: array<int, mixed>
     * }
     */
    private function connectionConfig(?string $name): array
    {
        if (!array_key_exists('connections', $this->config)) {
            if ($name !== null && $name !== 'default') {
                throw new InvalidArgumentException("Conexao PDO '$name' nao esta configurada.");
            }

            return $this->validatedConfig($this->config);
        }

        $connectionName = $name ?? 'default';
        $connection = $this->config['connections'][$connectionName] ?? null;

        if (!is_array($connection)) {
            throw new InvalidArgumentException("Conexao PDO '$connectionName' nao esta configurada.");
        }

        return $this->validatedConfig($connection);
    }

    /**
     * @param array<string, mixed> $config
     * @return array{
     *     dsn: string,
     *     username: string|null,
     *     password: string|null,
     *     options: array<int, mixed>
     * }
     */
    private function validatedConfig(array $config): array
    {
        if (!isset($config['dsn']) || !is_string($config['dsn']) || $config['dsn'] === '') {
            throw new InvalidArgumentException('A configuracao PDO deve informar um DSN valido.');
        }

        $username = $config['username'] ?? null;
        if ($username !== null && !is_string($username)) {
            throw new InvalidArgumentException('O username PDO deve ser uma string ou null.');
        }

        $password = $config['password'] ?? null;
        if ($password !== null && !is_string($password)) {
            throw new InvalidArgumentException('O password PDO deve ser uma string ou null.');
        }

        $options = $config['options'] ?? [];
        if (!is_array($options)) {
            throw new InvalidArgumentException('As options PDO devem ser um array.');
        }

        foreach (array_keys($options) as $attribute) {
            if (!is_int($attribute)) {
                throw new InvalidArgumentException('As chaves de options PDO devem ser inteiros.');
            }
        }

        return [
            'dsn' => $config['dsn'],
            'username' => $username,
            'password' => $password,
            'options' => $options,
        ];
    }
}

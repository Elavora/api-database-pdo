<?php

declare(strict_types=1);

namespace Elavora\Api\Extension\DatabasePdo;

use Elavora\Api\Framework\Application;
use Elavora\Api\Framework\Container;
use Elavora\Api\Framework\Contracts\DatabaseConnectionFactory;
use Elavora\Api\Framework\Contracts\Extension;
use Elavora\Api\Framework\Contracts\TransactionManager;
use LogicException;

final class PdoExtension implements Extension
{
    /**
     * @param array<string, mixed> $config Configuracao PDO unica ou mapa de conexoes.
     */
    public function __construct(private readonly array $config)
    {
    }

    /**
     * Registra factory PDO, banco padrao e gerenciador de transacao.
     */
    public function register(Application $application): void
    {
        $factory = new PdoConnectionFactory(config: $this->config);

        $application->container()->bind(
            DatabaseConnectionFactory::class,
            $factory
        );

        $application->container()->bind(
            PdoDatabase::class,
            static fn (Container $container): PdoDatabase => self::database($container)
        );

        $application->container()->bind(
            TransactionManager::class,
            static fn (Container $container): TransactionManager => self::transactionManager($container)
        );
    }

    private static function database(Container $container): PdoDatabase
    {
        $factory = $container->get(DatabaseConnectionFactory::class);

        if (!$factory instanceof DatabaseConnectionFactory) {
            throw new LogicException('O servico PDO deve resolver para DatabaseConnectionFactory.');
        }

        return new PdoDatabase(connection: $factory->connection());
    }

    private static function transactionManager(Container $container): TransactionManager
    {
        $database = $container->get(PdoDatabase::class);

        if (!$database instanceof PdoDatabase) {
            throw new LogicException('O servico PDO deve resolver para PdoDatabase.');
        }

        return $database;
    }
}

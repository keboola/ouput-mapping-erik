<?php

declare(strict_types=1);

namespace Keboola\OutputMapping\Storage;

use Keboola\StorageApi\Client;
use Keboola\StorageApi\ClientException;
use Keboola\StorageApiBranch\ClientWrapper;
use Psr\Log\LoggerInterface;

abstract class AbstractTableStructureModifier
{
    public function __construct(
        protected readonly ClientWrapper $clientWrapper,
        protected readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @param array $keys
     * @param LoggerInterface $logger
     * @return array
     */
    protected function normalizeKeyArray(LoggerInterface $logger, array $keys)
    {
        return array_map(
            function ($key) {
                return trim($key);
            },
            array_unique(
                array_filter($keys, function ($col) use ($logger) {
                    if ($col !== '') {
                        return true;
                    }
                    $logger->warning('Found empty column name in key array.');
                    return false;
                }),
            ),
        );
    }

    protected function modifyPrimaryKeyDecider(
        LoggerInterface $logger,
        array $currentTablePrimaryKey,
        array $newTableConfigurationPrimaryKey,
    ): bool {
        $configPK = $this->normalizeKeyArray($logger, $newTableConfigurationPrimaryKey);
        if (count($currentTablePrimaryKey) !== count($configPK)) {
            return true;
        }
        $currentTablePkColumnsCount = count($currentTablePrimaryKey);
        if (count(array_intersect($currentTablePrimaryKey, $configPK)) !== $currentTablePkColumnsCount) {
            return true;
        }
        return false;
    }

    protected function modifyPrimaryKey(
        LoggerInterface $logger,
        Client $client,
        string $tableId,
        array $tablePrimaryKey,
        array $configPrimaryKey,
    ): void {
        $logger->warning(sprintf(
            'Modifying primary key of table "%s" from "%s" to "%s".',
            $tableId,
            join(', ', $tablePrimaryKey),
            join(', ', $configPrimaryKey),
        ));
        if ($this->removePrimaryKey($logger, $client, $tableId, $tablePrimaryKey)) {
            // modify primary key
            try {
                if (count($configPrimaryKey)) {
                    $client->createTablePrimaryKey($tableId, $configPrimaryKey);
                }
            } catch (ClientException $e) {
                // warn and try to rollback to original state
                $logger->warning(
                    "Error changing primary key of table {$tableId}: " . $e->getMessage(),
                );
                if (count($tablePrimaryKey) > 0) {
                    $client->createTablePrimaryKey($tableId, $tablePrimaryKey);
                }
            }
        }
    }

    protected function removePrimaryKey(
        LoggerInterface $logger,
        Client $client,
        string $tableId,
        array $tablePrimaryKey,
    ): bool {
        if (count($tablePrimaryKey) > 0) {
            try {
                $client->removeTablePrimaryKey($tableId);
            } catch (ClientException $e) {
                // warn and go on
                $logger->warning(
                    "Error deleting primary key of table {$tableId}: " . $e->getMessage(),
                );
                return false;
            }
        }
        return true;
    }
}

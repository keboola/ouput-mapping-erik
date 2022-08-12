<?php

declare(strict_types=1);

namespace Keboola\OutputMapping\Writer\Table\TableDefinition;

use Keboola\Datatype\Definition\Exasol;
use Keboola\Datatype\Definition\Snowflake;
use Keboola\Datatype\Definition\Synapse;

class TableDefinitionFactory
{
    private array $tableMetadata;

    private string $backendType;

    public function __construct(array $tableMetadata, string $backendType)
    {
        $this->tableMetadata = $tableMetadata;
        $this->backendType = $backendType;
    }

    public function createTableDefinition(string $tableName, array $primaryKeys, array $columnMetadata): TableDefinition
    {
        $tableDefinition = new TableDefinition();
        $tableDefinition->setName($tableName);
        $tableDefinition->setPrimaryKeysNames($primaryKeys);
        foreach ($columnMetadata as $columnName => $metadata) {
            $tableDefinition->addColumn(
                $columnName,
                $columnMetadata,
                $this->tableMetadata,
                $this->backendType
            );
        }
        return $tableDefinition;
    }
}

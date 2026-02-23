<?php

namespace BuildCake\Framework\Scaffold;

use BuildCake\Utils\Utils;
use BuildCake\SqlKit\Sql;

class TableService
{
    private ScaffoldConfig $config;

    public function __construct(?ScaffoldConfig $config = null)
    {
        $this->config = $config ?? new ScaffoldConfig();
    }

    public function getTable(array $filters = []): array
    {
        if (isset($filters['table_name'])) {
            $tableName = $filters['table_name'];
            return Sql::runQuery(
                "SELECT c.COLUMN_NAME, c.COLUMN_TYPE, c.IS_NULLABLE, c.COLUMN_DEFAULT, c.COLUMN_KEY, c.EXTRA, c.COLUMN_COMMENT, c.CHARACTER_MAXIMUM_LENGTH, c.NUMERIC_PRECISION, c.NUMERIC_SCALE, c.DATETIME_PRECISION, k.CONSTRAINT_NAME AS FK_NAME, k.REFERENCED_TABLE_NAME, k.REFERENCED_COLUMN_NAME
                 FROM information_schema.COLUMNS c
                 LEFT JOIN information_schema.KEY_COLUMN_USAGE k ON k.TABLE_SCHEMA = c.TABLE_SCHEMA AND k.TABLE_NAME = c.TABLE_NAME AND k.COLUMN_NAME = c.COLUMN_NAME AND k.REFERENCED_TABLE_NAME IS NOT NULL
                 WHERE c.TABLE_SCHEMA = DATABASE() AND c.TABLE_NAME = :table_name
                 ORDER BY c.ORDINAL_POSITION",
                ['table_name' => $tableName]
            );
        }

        return Sql::runQuery(
            "SELECT TABLE_NAME, ENGINE, TABLE_ROWS, DATA_LENGTH/1024 AS data_kb, INDEX_LENGTH/1024 AS index_kb, (DATA_LENGTH+INDEX_LENGTH)/1024 AS total_kb, AUTO_INCREMENT, CREATE_TIME, TABLE_COLLATION
             FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE()",
            $filters
        );
    }

    public function postTable(array $data): array
    {
        if (!isset($data['table_name']) || empty($data['table_name'])) {
            throw new \Exception("Parâmetro 'table_name' é obrigatório.", 400);
        }
        if (!isset($data['fields']) || !is_array($data['fields']) || empty($data['fields'])) {
            throw new \Exception("Parâmetro 'fields' é obrigatório e deve ser um array não vazio.", 400);
        }

        $tableName = $data['table_name'];
        $templatePath = $this->config->getTemplatePath('table.template');

        if (!file_exists($templatePath)) {
            Utils::sendResponse(200, [], 'Template não encontrado.');
        }

        $existingTables = Sql::runQuery(
            "SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name",
            ['table_name' => $tableName]
        );
        if (!empty($existingTables)) {
            return ['message' => 'Tabela criada com sucesso.', 'table_name' => $tableName];
        }

        $templateContent = file_get_contents($templatePath);
        $templateContent = str_replace('`users`', "`{$tableName}`", $templateContent);
        $templateContent = str_replace('{aditional_rows}', $this->generateFieldsSQL($data['fields']), $templateContent);
        $templateContent = str_replace('{aditional_index}', $this->generateIndexesSQL($data), $templateContent);

        try {
            Sql::Call()->PureCommand($templateContent);
        } catch (\Exception $e) {
            throw new \Exception('Erro ao criar a tabela: ' . $e->getMessage(), 500);
        }

        $migrationsDir = $this->config->getProjectRoot() . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'Database' . DIRECTORY_SEPARATOR . 'migrations';
        if (!is_dir($migrationsDir)) {
            @mkdir($migrationsDir, 0755, true);
        }
        $migrationName = date('YmdHis') . "_cr_{$tableName}.sql";
        @file_put_contents($migrationsDir . DIRECTORY_SEPARATOR . $migrationName, $templateContent);

        return ['message' => 'Tabela criada com sucesso.', 'table_name' => $tableName];
    }

    public function putTable(array $data): array
    {
        if (!isset($data['table_name']) || empty($data['table_name'])) {
            throw new \Exception("Parâmetro 'table_name' é obrigatório.", 400);
        }
        if (!isset($data['fields']) || !is_array($data['fields']) || empty($data['fields'])) {
            throw new \Exception("Parâmetro 'fields' é obrigatório e deve ser um array não vazio.", 400);
        }

        $tableName = $data['table_name'];
        $existingTables = Sql::runQuery(
            "SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name",
            ['table_name' => $tableName]
        );
        if (empty($existingTables)) {
            Utils::sendResponse(200, [], 'Tabela não existe.');
        }

        $currentColumns = $this->getCurrentColumns($tableName);
        $currentColumnNames = array_column($currentColumns, 'COLUMN_NAME');
        $alterCommands = [];

        foreach ($data['fields'] as $field) {
            if (!isset($field['name'])) continue;
            $fieldName = $field['name'];
            $action = isset($field['action']) ? strtolower($field['action']) : '';
            $columnExists = in_array($fieldName, $currentColumnNames);

            switch ($action) {
                case 'add':
                    if ($columnExists) throw new \Exception("Coluna '{$fieldName}' já existe na tabela.", 400);
                    $alterCommands[] = 'ADD COLUMN ' . $this->generateFieldDefinition($field);
                    break;
                case 'remove':
                case 'drop':
                    if (!$columnExists) Utils::sendResponse(200, [], "Coluna '{$fieldName}' não existe na tabela.");
                    $protected = ['id', 'is_active', 'created_at', 'updated_at', 'created_by', 'updated_by'];
                    if (in_array($fieldName, $protected)) throw new \Exception("Não é permitido remover a coluna '{$fieldName}'.", 400);
                    $alterCommands[] = "DROP COLUMN `{$fieldName}`";
                    break;
                case 'alter':
                case 'modify':
                    if (!$columnExists) Utils::sendResponse(200, [], "Coluna '{$fieldName}' não existe na tabela.");
                    $alterCommands[] = 'MODIFY COLUMN ' . $this->generateFieldDefinition($field);
                    break;
                default:
                    throw new \Exception("Ação '{$action}' inválida. Use 'add', 'remove' ou 'alter'.", 400);
            }
        }

        if (empty($alterCommands)) {
            return ['message' => 'Nenhuma alteração necessária.', 'table_name' => $tableName];
        }

        $executedCommands = [];
        foreach ($alterCommands as $command) {
            $alterQuery = "ALTER TABLE `{$tableName}` " . $command;
            Sql::Call()->PureCommand($alterQuery);
            $executedCommands[] = $command;
        }

        return [
            'message' => 'Tabela atualizada com sucesso.',
            'table_name' => $tableName,
            'executed_commands' => $executedCommands,
        ];
    }

    public function deleteTable(array $data): array
    {
        if (!isset($data['table_name']) || empty($data['table_name'])) {
            throw new \Exception("Parâmetro 'table_name' é obrigatório.", 400);
        }
        $tableName = $data['table_name'];
        $existingTables = Sql::runQuery("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?", [$tableName]);
        if (empty($existingTables)) {
            Utils::sendResponse(200, [], 'Tabela não existe.');
        }
        Sql::Call()->PureCommand("DROP TABLE `{$tableName}`");
        return ['message' => 'Tabela deletada com sucesso.', 'table_name' => $tableName];
    }

    private function getCurrentColumns(string $tableName): array
    {
        return Sql::runQuery(
            "SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT, COLUMN_KEY, EXTRA, COLUMN_COMMENT
             FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name ORDER BY ORDINAL_POSITION",
            ['table_name' => $tableName]
        );
    }

    private function generateFieldsSQL(array $fields): string
    {
        $sqlFields = [];
        foreach ($fields as $field) {
            if (!isset($field['name']) || !isset($field['type'])) continue;
            $name = $field['name'];
            $type = strtoupper($field['type']);
            $length = $field['length'] ?? null;
            $null = $field['null'] ?? true;
            $default = $field['default'] ?? null;
            $comment = $field['comment'] ?? '';
            $unsigned = $field['unsigned'] ?? false;

            $isNullable = !($null === false || $null === 'false' || $null === 0 || $null === '0');
            $isUnsigned = ($unsigned === true || $unsigned === 'true' || $unsigned === 1 || $unsigned === '1');

            $typeDef = $type;
            if ($length !== null && $length !== '' && in_array($type, ['VARCHAR', 'CHAR', 'INT', 'TINYINT', 'SMALLINT', 'MEDIUMINT', 'BIGINT', 'DECIMAL', 'FLOAT', 'DOUBLE', 'ENUM'])) {
                $typeDef = "{$type}({$length})";
            }
            if ($isUnsigned && in_array($type, ['INT', 'TINYINT', 'SMALLINT', 'MEDIUMINT', 'BIGINT', 'DECIMAL', 'FLOAT', 'DOUBLE'])) {
                $typeDef .= ' UNSIGNED';
            }

            $fieldSQL = "\t`{$name}` {$typeDef}";
            $fieldSQL .= $isNullable ? ' NULL' : ' NOT NULL';
            if ($default !== null && $default !== '') {
                if (is_numeric($default) || $default === 'CURRENT_TIMESTAMP' || in_array($default, [true, false, 'true', 'false'], true)) {
                    if ($default === true || $default === 'true') $default = '1';
                    elseif ($default === false || $default === 'false') $default = '0';
                    $fieldSQL .= " DEFAULT {$default}";
                } else {
                    $fieldSQL .= " DEFAULT '" . str_replace("'", "''", $default) . "'";
                }
            }
            if ($comment !== '') {
                $fieldSQL .= " COMMENT '" . str_replace("'", "''", $comment) . "'";
            }
            $sqlFields[] = $fieldSQL;
        }
        return implode(",\n", $sqlFields);
    }

    private function generateIndexesSQL(array $data): string
    {
        $indexes = [];
        if (isset($data['foreign_keys']) && is_array($data['foreign_keys'])) {
            foreach ($data['foreign_keys'] as $fk) {
                if (!isset($fk['column'], $fk['references_table'], $fk['references_column'])) continue;
                $col = $fk['column'];
                $refTable = $fk['references_table'];
                $refCol = $fk['references_column'];
                $fkName = $fk['name'] ?? 'fk_' . $col . '_' . $refTable . '_' . substr(md5(microtime(true)), 0, 6);
                $onDelete = $fk['on_delete'] ?? 'RESTRICT';
                $onUpdate = $fk['on_update'] ?? 'RESTRICT';
                $indexes[] = "\tCONSTRAINT `{$fkName}` FOREIGN KEY (`{$col}`) REFERENCES `{$refTable}` (`{$refCol}`) ON DELETE {$onDelete} ON UPDATE {$onUpdate},";
            }
        }
        if (isset($data['additional_indexes']) && is_array($data['additional_indexes'])) {
            foreach ($data['additional_indexes'] as $idx) {
                if (!isset($idx['columns']) || !is_array($idx['columns']) || empty($idx['columns'])) continue;
                $idxName = $idx['name'] ?? 'idx_' . implode('_', $idx['columns']);
                $idxType = (isset($idx['type']) && strtoupper($idx['type']) === 'UNIQUE') ? 'UNIQUE INDEX' : 'INDEX';
                $indexes[] = "\t{$idxType} `{$idxName}` (`" . implode('`, `', $idx['columns']) . "`) USING BTREE";
            }
        }
        return implode("\n", $indexes);
    }

    private function generateFieldDefinition(array $field): string
    {
        if (!isset($field['name'], $field['type'])) {
            throw new \Exception("Campo deve ter 'name' e 'type' definidos.", 400);
        }
        $name = $field['name'];
        $type = strtoupper($field['type']);
        $length = $field['length'] ?? null;
        $null = $field['null'] ?? true;
        $default = $field['default'] ?? null;
        $comment = $field['comment'] ?? '';
        $unsigned = $field['unsigned'] ?? false;

        $isNullable = !($null === false || $null === 'false' || $null === 0 || $null === '0');
        $isUnsigned = ($unsigned === true || $unsigned === 'true' || $unsigned === 1 || $unsigned === '1');

        $typeDef = $type;
        if ($length !== null && $length !== '' && in_array($type, ['VARCHAR', 'CHAR', 'INT', 'TINYINT', 'SMALLINT', 'MEDIUMINT', 'BIGINT', 'DECIMAL', 'FLOAT', 'DOUBLE'])) {
            $typeDef = "{$type}({$length})";
        }
        if ($isUnsigned && in_array($type, ['INT', 'TINYINT', 'SMALLINT', 'MEDIUMINT', 'BIGINT', 'DECIMAL', 'FLOAT', 'DOUBLE'])) {
            $typeDef .= ' UNSIGNED';
        }

        $fieldSQL = "`{$name}` {$typeDef}";
        $fieldSQL .= $isNullable ? ' NULL' : ' NOT NULL';
        if ($default !== null && $default !== '') {
            if (is_numeric($default) || $default === 'CURRENT_TIMESTAMP' || in_array($default, [true, false, 'true', 'false'], true)) {
                if ($default === true || $default === 'true') $default = '1';
                elseif ($default === false || $default === 'false') $default = '0';
                $fieldSQL .= " DEFAULT {$default}";
            } else {
                $fieldSQL .= " DEFAULT '" . str_replace("'", "''", $default) . "'";
            }
        }
        if ($comment !== '') {
            $fieldSQL .= " COMMENT '" . str_replace("'", "''", $comment) . "'";
        }
        return $fieldSQL;
    }
}

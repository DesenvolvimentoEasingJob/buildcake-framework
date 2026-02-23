<?php

namespace BuildCake\Framework\Scaffold;

use BuildCake\SqlKit\Sql;
use BuildCake\Utils\Utils;

class ModuleService
{
    private ScaffoldConfig $config;

    public function __construct(?ScaffoldConfig $config = null)
    {
        $this->config = $config ?? new ScaffoldConfig();
    }

    public function getModule(array $filters = []): array
    {
        $modules = [];
        $srcPath = $this->config->getSrcPath() . DIRECTORY_SEPARATOR;

        $moduleDirs = @scandir($srcPath) ?: [];
        foreach ($moduleDirs as $module) {
            if ($module[0] === '.') continue;
            $modulePath = $srcPath . $module;
            if (!is_dir($modulePath)) continue;

            $controllers = $this->getControllers($modulePath, $module);
            $services = $this->getServices($modulePath, $module);
            $otherFiles = $this->otherFiles($modulePath, $module);
            $tableName = $this->checkTableExists($module);

            $modules[] = [
                'name' => $module,
                'path' => $modulePath,
                'controllers' => $controllers,
                'services' => $services,
                'other_files' => $otherFiles,
                'table_name' => $tableName,
            ];
        }

        if ($filters) {
            $modules = array_filter($modules, function ($module) use ($filters) {
                [$key, $value] = [array_key_first($filters), reset($filters)];
                return isset($module[$key]) && $module[$key] == $value;
            });
        }

        $modules = array_values($modules);
        foreach ($modules as $index => &$module) {
            $module['id'] = $index + 1;
        }
        unset($module);

        return $modules;
    }

    public function postModule(array $data): array
    {
        if (!isset($data['name']) || empty($data['name'])) {
            throw new \Exception("Parâmetro 'name' é obrigatório.", 400);
        }
        if (!isset($data['table_name']) || empty($data['table_name'])) {
            throw new \Exception("Parâmetro 'table_name' é obrigatório.", 400);
        }

        $module = $data['module'] ?? '';
        $table_name = $data['table_name'];
        $name = $data['name'];
        $srcPath = $this->config->getSrcPath();
        $modulePath = $srcPath . DIRECTORY_SEPARATOR . $module;

        if (empty($module)) {
            throw new \Exception("Parâmetro 'module' é obrigatório.", 400);
        }

        if (!is_dir($modulePath)) {
            mkdir($modulePath, 0755, true);
            mkdir($modulePath . DIRECTORY_SEPARATOR . 'controllers', 0755, true);
            mkdir($modulePath . DIRECTORY_SEPARATOR . 'services', 0755, true);
        }

        if (isset($data['fields']) && !empty($data['fields'])) {
            $table = new TableService($this->config);
            $table->postTable($data);
        }

        $api = new ApiService($this->config);
        $api->postApi([
            'name' => $name,
            'module' => $module,
            'table_name' => $table_name,
        ]);

        $service = new ServiceService($this->config);
        $service->postService([
            'name' => $name,
            'table_name' => $table_name,
            'module' => $module,
        ]);

        return [
            'message' => 'Módulo criado com sucesso.',
            'path' => $modulePath,
            'name' => $name,
        ];
    }

    public function putModule(array $data)
    {
        return Sql::runPut('modules', $data);
    }

    public function deleteModule(array $data)
    {
        $moduleName = null;
        if (isset($data['name']) && !empty($data['name'])) {
            $moduleName = $data['name'];
        } else {
            try {
                $modules = Sql::runQuery('SELECT name FROM modules WHERE ' . $this->buildWhereClause($data), $data);
                if (!empty($modules) && isset($modules[0]['name'])) {
                    $moduleName = $modules[0]['name'];
                }
            } catch (\Exception $e) {
                // ignore
            }
        }

        if ($moduleName) {
            $this->deleteModuleDocumentation($moduleName);
        }

        return Sql::runDelet('modules', $data);
    }

    private function getControllers(string $modulePath, string $module): array
    {
        $controllers = [];
        $controllersPath = $modulePath . DIRECTORY_SEPARATOR . 'controllers';
        if (is_dir($controllersPath)) {
            $files = @scandir($controllersPath) ?: [];
            foreach ($files as $file) {
                if ($file[0] === '.') continue;
                $controllers[] = ['file' => $file, 'path' => $module . '/controllers/' . $file];
            }
        }
        return $controllers;
    }

    private function getServices(string $modulePath, string $module): array
    {
        $services = [];
        $servicesPath = $modulePath . DIRECTORY_SEPARATOR . 'services';
        if (is_dir($servicesPath)) {
            $files = @scandir($servicesPath) ?: [];
            foreach ($files as $file) {
                if ($file[0] === '.') continue;
                $services[] = ['file' => $file, 'path' => $module . '/services/' . $file];
            }
        }
        return $services;
    }

    private function otherFiles(string $modulePath, string $module): array
    {
        $otherFiles = [];
        $excludedDirs = ['controllers', 'services'];
        $allItems = @scandir($modulePath) ?: [];

        foreach ($allItems as $item) {
            if ($item[0] === '.') continue;
            $itemPath = $modulePath . DIRECTORY_SEPARATOR . $item;
            if (is_dir($itemPath) && in_array($item, $excludedDirs)) continue;

            if (is_file($itemPath)) {
                $otherFiles[] = ['file' => $item, 'path' => $module . '/' . $item, 'type' => 'file'];
            } elseif (is_dir($itemPath)) {
                $otherFiles[] = ['file' => $item, 'path' => $module . '/' . $item, 'type' => 'directory'];
                $dirItems = @scandir($itemPath) ?: [];
                foreach ($dirItems as $dirItem) {
                    if ($dirItem[0] === '.') continue;
                    $dirItemPath = $itemPath . DIRECTORY_SEPARATOR . $dirItem;
                    if (is_file($dirItemPath)) {
                        $otherFiles[] = ['file' => $dirItem, 'path' => $module . '/' . $item . '/' . $dirItem, 'type' => 'file'];
                    }
                }
            }
        }
        return $otherFiles;
    }

    private function checkTableExists(string $tableName): string
    {
        try {
            $existingTables = Sql::runQuery(
                "SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name",
                ['table_name' => $tableName]
            );
            return !empty($existingTables) ? $tableName : 'no_table';
        } catch (\Exception $e) {
            return 'no_table';
        }
    }

    private function deleteModuleDocumentation(string $moduleName): void
    {
        $documentsPath = $this->config->getDocumentsPath() . DIRECTORY_SEPARATOR;
        if (!is_dir($documentsPath)) return;
        $pattern = $documentsPath . $moduleName . '_*.json';
        $files = glob($pattern) ?: [];
        foreach ($files as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
    }

    private function buildWhereClause(array $data): string
    {
        $conditions = [];
        foreach ($data as $key => $value) {
            $conditions[] = "`{$key}` = :{$key}";
        }
        return implode(' AND ', $conditions);
    }
}

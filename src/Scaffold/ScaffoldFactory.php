<?php

namespace BuildCake\Framework\Scaffold;

/**
 * Factory para criar config e serviços do scaffold usando templates do pacote quando instalado via composer.
 */
class ScaffoldFactory
{
    /**
     * Cria ScaffoldConfig para o projeto, usando templates do buildcake/framework quando em vendor.
     *
     * @param string|null $projectRoot Raiz do projeto (onde está src/, composer.json). Se null, usa getcwd().
     * @return ScaffoldConfig
     */
    public static function configForProject(?string $projectRoot = null): ScaffoldConfig
    {
        $projectRoot = $projectRoot ?? getcwd();
        $projectRoot = rtrim($projectRoot, '/\\');

        $vendorFramework = $projectRoot . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'buildcake' . DIRECTORY_SEPARATOR . 'framework';
        if (is_dir($vendorFramework)) {
            return ScaffoldConfig::withFrameworkTemplates($projectRoot, $vendorFramework);
        }

        return new ScaffoldConfig($projectRoot);
    }

    /**
     * Cria ModuleService com config padrão do projeto.
     */
    public static function moduleService(?string $projectRoot = null): ModuleService
    {
        return new ModuleService(self::configForProject($projectRoot));
    }

    /**
     * Cria ApiService com config padrão do projeto.
     */
    public static function apiService(?string $projectRoot = null): ApiService
    {
        return new ApiService(self::configForProject($projectRoot));
    }

    /**
     * Cria ServiceService com config padrão do projeto.
     */
    public static function serviceService(?string $projectRoot = null): ServiceService
    {
        return new ServiceService(self::configForProject($projectRoot));
    }

    /**
     * Cria TableService com config padrão do projeto.
     */
    public static function tableService(?string $projectRoot = null): TableService
    {
        return new TableService(self::configForProject($projectRoot));
    }

    /**
     * Cria FileService com config padrão do projeto.
     */
    public static function fileService(?string $projectRoot = null): FileService
    {
        return new FileService(self::configForProject($projectRoot));
    }

    /**
     * Cria SQLService (não usa paths do projeto).
     */
    public static function sqlService(): SQLService
    {
        return new SQLService();
    }
}

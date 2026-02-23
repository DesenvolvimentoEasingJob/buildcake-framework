<?php

namespace BuildCake\Framework\Scaffold;

use BuildCake\Utils\Utils;

class ServiceService
{
    private ScaffoldConfig $config;

    public function __construct(?ScaffoldConfig $config = null)
    {
        $this->config = $config ?? new ScaffoldConfig();
    }

    public function getService(array $filters = []): array
    {
        $services = [];
        $srcPath = $this->config->getSrcPath() . DIRECTORY_SEPARATOR;

        $modules = @scandir($srcPath) ?: [];
        foreach ($modules as $module) {
            if ($module[0] === '.') continue;
            $modulePath = $srcPath . $module;
            $servicesPath = $modulePath . DIRECTORY_SEPARATOR . 'services';

            if (is_dir($modulePath) && is_dir($servicesPath)) {
                $files = @scandir($servicesPath) ?: [];
                foreach ($files as $file) {
                    if ($file[0] === '.') continue;
                    $services[] = [
                        'module' => $module,
                        'file' => $file,
                        'path' => $module . '/services/' . $file,
                    ];
                }
            }
        }

        if ($filters) {
            $services = array_filter($services, function ($svc) use ($filters) {
                [$key, $value] = [array_key_first($filters), reset($filters)];
                return isset($svc[$key]) && $svc[$key] == $value;
            });
        }

        return array_values($services);
    }

    public function postService(array $data): array
    {
        if (!isset($data['name']) || empty($data['name'])) {
            throw new \Exception("Parâmetro 'name' é obrigatório.", 400);
        }
        if (!isset($data['table_name']) || empty($data['table_name'])) {
            throw new \Exception("Parâmetro 'table_name' é obrigatório.", 400);
        }

        $moduleName = $data['module'] ?? '';
        $name = $data['name'];
        $templatePath = $this->config->getTemplatePath('service.template');

        if (!file_exists($templatePath)) {
            Utils::sendResponse(200, [], 'Template não encontrado.');
        }

        $serviceContent = Utils::replaceFields(file_get_contents($templatePath), $data);
        if (isset($data['filename'])) {
            $name = $data['filename'];
        }

        $srcPath = $this->config->getSrcPath();
        $modulePath = $srcPath . DIRECTORY_SEPARATOR . $moduleName;
        $servicesPath = $modulePath . DIRECTORY_SEPARATOR . 'services';
        $serviceFile = $servicesPath . DIRECTORY_SEPARATOR . $name . 'Service.php';

        if (file_exists($serviceFile)) {
            throw new \Exception('Service já existe.', 400);
        }

        if (!is_dir($servicesPath)) {
            if (!is_dir($modulePath)) {
                mkdir($modulePath, 0755, true);
            }
            mkdir($servicesPath, 0755, true);
        }

        if (file_put_contents($serviceFile, $serviceContent) === false) {
            throw new \Exception('Erro ao gravar o arquivo do Service.', 500);
        }

        return [
            'message' => 'Service criado com sucesso.',
            'path' => $moduleName . '/services/' . $name . 'Service.php',
            'module' => $moduleName,
        ];
    }

    public function putService(array $data): array
    {
        if (!isset($data['module']) || empty($data['module'])) {
            throw new \Exception("Parâmetro 'module' é obrigatório.", 400);
        }

        $moduleName = $data['module'];
        $name = $data['name'];
        $srcPath = $this->config->getSrcPath();
        $serviceFile = $srcPath . DIRECTORY_SEPARATOR . $moduleName . DIRECTORY_SEPARATOR . 'services' . DIRECTORY_SEPARATOR . $name . 'Service.php';

        if (!file_exists($serviceFile)) {
            Utils::sendResponse(200, [], 'Service não existe.');
        }

        $serviceContent = Utils::replaceFields(file_get_contents($serviceFile), $data);
        if (file_put_contents($serviceFile, $serviceContent) === false) {
            throw new \Exception('Erro ao gravar o arquivo do Service.', 500);
        }

        return [
            'message' => 'Service atualizado com sucesso.',
            'path' => $moduleName . '/services/' . $name . 'Service.php',
            'module' => $moduleName,
        ];
    }

    public function deleteService(array $data): array
    {
        if (!isset($data['module']) || empty($data['module'])) {
            throw new \Exception("Parâmetro 'module' é obrigatório.", 400);
        }

        $moduleName = $data['module'];
        $name = $data['name'];
        $srcPath = $this->config->getSrcPath();
        $serviceFile = $srcPath . DIRECTORY_SEPARATOR . $moduleName . DIRECTORY_SEPARATOR . 'services' . DIRECTORY_SEPARATOR . $name . 'Service.php';

        if (!file_exists($serviceFile)) {
            Utils::sendResponse(200, [], 'Service não existe.');
        }

        if (unlink($serviceFile)) {
            return [
                'message' => 'Service deletado com sucesso.',
                'path' => $moduleName . '/services/' . $name . 'Service.php',
                'module' => $moduleName,
            ];
        }
        throw new \Exception('Erro ao deletar o Service.', 500);
    }
}

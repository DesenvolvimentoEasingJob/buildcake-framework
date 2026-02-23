<?php

namespace BuildCake\Framework\Scaffold;

use BuildCake\Utils\Utils;

class ApiService
{
    private ScaffoldConfig $config;

    public function __construct(?ScaffoldConfig $config = null)
    {
        $this->config = $config ?? new ScaffoldConfig();
    }

    public function getApi(array $filters = []): array
    {
        $controllers = [];
        $srcPath = $this->config->getSrcPath() . DIRECTORY_SEPARATOR;

        $modules = @scandir($srcPath) ?: [];
        foreach ($modules as $module) {
            if ($module[0] === '.') {
                continue;
            }
            $modulePath = $srcPath . $module;
            $controllersPath = $modulePath . DIRECTORY_SEPARATOR . 'controllers';

            if (is_dir($modulePath) && is_dir($controllersPath)) {
                $files = @scandir($controllersPath) ?: [];
                foreach ($files as $file) {
                    if ($file[0] === '.') continue;
                    $controllers[] = [
                        'module' => $module,
                        'file' => $file,
                        'path' => $module . '/controllers/' . $file,
                    ];
                }
            }
        }

        if ($filters) {
            $controllers = array_filter($controllers, function ($controller) use ($filters) {
                [$key, $value] = [array_key_first($filters), reset($filters)];
                return isset($controller[$key]) && $controller[$key] == $value;
            });
        }

        return array_values($controllers);
    }

    public function postApi(array $data): array
    {
        if (!isset($data['name']) || empty($data['name'])) {
            throw new \Exception("Parâmetro 'name' é obrigatório.", 400);
        }
        if (!isset($data['module']) || empty($data['module'])) {
            throw new \Exception("Parâmetro 'module' é obrigatório.", 400);
        }

        $moduleName = $data['module'];
        $name = $data['name'];
        $templatePath = $this->config->getTemplatePath('controller.template');

        if (!file_exists($templatePath)) {
            Utils::sendResponse(200, [], 'Template não encontrado.');
        }

        $controllerContent = Utils::replaceFields(file_get_contents($templatePath), $data);
        if (isset($data['filename'])) {
            $name = $data['filename'];
        }

        $srcPath = $this->config->getSrcPath();
        $modulePath = $srcPath . DIRECTORY_SEPARATOR . $moduleName;
        $controllersPath = $modulePath . DIRECTORY_SEPARATOR . 'controllers';
        $controllerFile = $controllersPath . DIRECTORY_SEPARATOR . $name . 'Controller.php';

        if (file_exists($controllerFile)) {
            throw new \Exception('Controller já existe.', 400);
        }

        if (!is_dir($controllersPath)) {
            if (!is_dir($modulePath)) {
                mkdir($modulePath, 0755, true);
            }
            mkdir($controllersPath, 0755, true);
        }

        if (file_put_contents($controllerFile, $controllerContent) === false) {
            throw new \Exception('Erro ao gravar o arquivo do controller.', 500);
        }

        return [
            'message' => 'Controller criado com sucesso.',
            'path' => $moduleName . '/controllers/' . $name . 'Controller.php',
            'module' => $moduleName,
        ];
    }

    public function putApi(array $data): array
    {
        if (!isset($data['module']) || empty($data['module'])) {
            throw new \Exception("Parâmetro 'module' é obrigatório.", 400);
        }

        $moduleName = $data['module'];
        $name = $data['name'];
        $srcPath = $this->config->getSrcPath();
        $controllerFile = $srcPath . DIRECTORY_SEPARATOR . $moduleName . DIRECTORY_SEPARATOR . 'controllers' . DIRECTORY_SEPARATOR . $name . 'Controller.php';

        if (!file_exists($controllerFile)) {
            Utils::sendResponse(200, [], 'Controller não existe.');
        }

        $controllerContent = Utils::replaceFields(file_get_contents($controllerFile), $data);
        if (file_put_contents($controllerFile, $controllerContent) === false) {
            throw new \Exception('Erro ao gravar o arquivo do controller.', 500);
        }

        return [
            'message' => 'Controller atualizado com sucesso.',
            'path' => $moduleName . '/controllers/' . $name . 'Controller.php',
            'module' => $moduleName,
        ];
    }

    public function deleteApi(array $data): array
    {
        if (!isset($data['module']) || empty($data['module'])) {
            throw new \Exception("Parâmetro 'module' é obrigatório.", 400);
        }

        $moduleName = $data['module'];
        $name = $data['name'];
        $srcPath = $this->config->getSrcPath();
        $controllerFile = $srcPath . DIRECTORY_SEPARATOR . $moduleName . DIRECTORY_SEPARATOR . 'controllers' . DIRECTORY_SEPARATOR . $name . 'Controller.php';

        if (!file_exists($controllerFile)) {
            Utils::sendResponse(200, [], 'Controller não existe.');
        }

        if (unlink($controllerFile)) {
            return [
                'message' => 'Controller deletado com sucesso.',
                'path' => $moduleName . '/controllers/' . $name . 'Controller.php',
                'module' => $moduleName,
            ];
        }
        throw new \Exception('Erro ao deletar o controller.', 500);
    }
}

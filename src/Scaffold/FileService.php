<?php

namespace BuildCake\Framework\Scaffold;

use BuildCake\Utils\Utils;

class FileService
{
    private ScaffoldConfig $config;

    public function __construct(?ScaffoldConfig $config = null)
    {
        $this->config = $config ?? new ScaffoldConfig();
    }

    public function getFile(array $filters = []): array
    {
        $files = [];
        $srcPath = $this->config->getSrcPath() . DIRECTORY_SEPARATOR;

        $scanDirectory = function ($dir, $basePath = '') use (&$scanDirectory, &$files) {
            $items = @scandir($dir) ?: [];
            foreach ($items as $item) {
                if ($item[0] === '.') continue;
                $itemPath = $dir . DIRECTORY_SEPARATOR . $item;
                $relativePath = $basePath ? $basePath . '/' . $item : $item;
                if (is_dir($itemPath)) {
                    $scanDirectory($itemPath, $relativePath);
                } else {
                    $files[] = ['file' => $item, 'path' => $relativePath, 'fullPath' => $itemPath];
                }
            }
        };

        $scanDirectory($srcPath);

        if ($filters) {
            if (isset($filters['path']) || isset($filters['filepath'])) {
                $filePath = $filters['path'] ?? $filters['filepath'];
                $fullPath = $srcPath . str_replace('/', DIRECTORY_SEPARATOR, $filePath);
                if (!file_exists($fullPath)) {
                    Utils::sendResponse(200, [], 'Arquivo não encontrado.');
                }
                if (is_dir($fullPath)) {
                    throw new \Exception('O caminho especificado é um diretório, não um arquivo.', 400);
                }
                return ['path' => $filePath, 'content' => file_get_contents($fullPath)];
            }
            $files = array_filter($files, function ($file) use ($filters) {
                [$key, $value] = [array_key_first($filters), reset($filters)];
                return isset($file[$key]) && $file[$key] == $value;
            });
        }

        return array_values($files);
    }

    public function postFile(array $data): array
    {
        if (!isset($data['directory']) || empty($data['directory'])) {
            throw new \Exception("Parâmetro 'directory' é obrigatório.", 400);
        }
        if (!isset($data['filename']) || empty($data['filename'])) {
            throw new \Exception("Parâmetro 'filename' é obrigatório.", 400);
        }
        if (!isset($data['content'])) {
            throw new \Exception("Parâmetro 'content' é obrigatório.", 400);
        }

        $directory = ltrim($data['directory'], '/\\');
        $filename = $data['filename'];
        $content = $data['content'];
        $srcPath = $this->config->getSrcPath();
        $filePath = $srcPath . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $directory) . DIRECTORY_SEPARATOR . $filename;

        if (file_exists($filePath)) {
            throw new \Exception('Arquivo já existe.', 400);
        }

        $dirPath = dirname($filePath);
        if (!is_dir($dirPath)) {
            if (!mkdir($dirPath, 0755, true)) {
                throw new \Exception('Erro ao criar o diretório.', 500);
            }
        }

        if (file_put_contents($filePath, $content) === false) {
            throw new \Exception('Erro ao criar o arquivo.', 500);
        }

        return [
            'message' => 'Arquivo criado com sucesso.',
            'path' => $directory . '/' . $filename,
            'directory' => $directory,
            'filename' => $filename,
        ];
    }

    public function putFile(array $data): array
    {
        if (!isset($data['filepath']) || empty($data['filepath'])) {
            throw new \Exception("Parâmetro 'filepath' é obrigatório.", 400);
        }
        if (!isset($data['content'])) {
            throw new \Exception("Parâmetro 'content' é obrigatório.", 400);
        }

        $filePath = ltrim($data['filepath'], '/\\');
        if (strpos($filePath, 'src/') !== 0 && strpos($filePath, 'src' . DIRECTORY_SEPARATOR) !== 0) {
            $filePath = 'src/' . $filePath;
        }
        $fullPath = $this->config->getProjectRoot() . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $filePath);

        if (!file_exists($fullPath)) {
            Utils::sendResponse(200, [], 'Arquivo não encontrado.');
        }
        if (is_dir($fullPath)) {
            throw new \Exception('O caminho especificado é um diretório, não um arquivo.', 400);
        }

        if (file_put_contents($fullPath, $data['content']) === false) {
            throw new \Exception('Erro ao editar o arquivo.', 500);
        }

        return ['message' => 'Arquivo editado com sucesso.', 'path' => $filePath];
    }

    public function deleteFile(array $data): array
    {
        if (!isset($data['filepath']) || empty($data['filepath'])) {
            throw new \Exception("Parâmetro 'filepath' é obrigatório.", 400);
        }

        $filePath = ltrim($data['filepath'], '/\\');
        if (strpos($filePath, 'src/') !== 0 && strpos($filePath, 'src' . DIRECTORY_SEPARATOR) !== 0) {
            $filePath = 'src/' . $filePath;
        }
        $fullPath = $this->config->getProjectRoot() . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $filePath);

        if (!file_exists($fullPath)) {
            Utils::sendResponse(200, [], 'Arquivo não encontrado.');
        }
        if (is_dir($fullPath)) {
            throw new \Exception('O caminho especificado é um diretório, não um arquivo.', 400);
        }

        if (unlink($fullPath)) {
            return ['message' => 'Arquivo deletado com sucesso.', 'path' => $filePath];
        }
        throw new \Exception('Erro ao deletar o arquivo.', 500);
    }
}

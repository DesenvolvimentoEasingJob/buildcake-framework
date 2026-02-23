<?php

namespace BuildCake\Framework\Scaffold;

/**
 * Configuração do scaffold (raiz do projeto e caminhos).
 * Usado por todos os serviços do framework para resolver paths.
 */
class ScaffoldConfig
{
    /** @var string Raiz do projeto (onde está src/, public/, etc.) */
    private string $projectRoot;

    /** @var string Caminho da pasta de templates dentro do framework ou do projeto */
    private string $templatesPath;

    /** @var string Caminho da pasta de documentos (ex: src/Scaffold/documents) */
    private string $documentsPath;

    public function __construct(?string $projectRoot = null)
    {
        $this->projectRoot = rtrim($projectRoot ?? getcwd(), '/\\');
        $this->templatesPath = $this->projectRoot . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'Scaffold' . DIRECTORY_SEPARATOR . 'templates';
        $this->documentsPath = $this->projectRoot . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'Scaffold' . DIRECTORY_SEPARATOR . 'documents';
    }

    /**
     * Cria config usando templates embutidos do framework (quando instalado via composer).
     * $frameworkVendorPath = dirname(__DIR__, 3) em contexto do pacote em vendor/buildcake/framework
     */
    public static function withFrameworkTemplates(string $projectRoot, string $frameworkVendorPath): self
    {
        $config = new self($projectRoot);
        $config->templatesPath = rtrim($frameworkVendorPath, '/\\') . DIRECTORY_SEPARATOR . 'templates';
        return $config;
    }

    public function getProjectRoot(): string
    {
        return $this->projectRoot;
    }

    public function getSrcPath(): string
    {
        return $this->projectRoot . DIRECTORY_SEPARATOR;
    }

    public function getTemplatesPath(): string
    {
        return $this->templatesPath;
    }

    public function getDocumentsPath(): string
    {
        return $this->documentsPath;
    }

    public function getTemplatePath(string $name): string
    {
        return $this->templatesPath . DIRECTORY_SEPARATOR . $name;
    }

    public function setTemplatesPath(string $path): self
    {
        $this->templatesPath = rtrim($path, '/\\');
        return $this;
    }

    public function setDocumentsPath(string $path): self
    {
        $this->documentsPath = rtrim($path, '/\\');
        return $this;
    }
}

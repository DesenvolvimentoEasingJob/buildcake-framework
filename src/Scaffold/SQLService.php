<?php

namespace BuildCake\Framework\Scaffold;

use BuildCake\SqlKit\Sql;

class SQLService
{
    public function getSQL(array $data = []): array
    {
        if (!isset($data['query']) || empty($data['query'])) {
            throw new \Exception("Parâmetro 'query' é obrigatório.", 400);
        }

        $query = $data['query'];
        $params = [];
        if (isset($data['params'])) {
            if (is_array($data['params'])) {
                $params = $data['params'];
            } elseif (is_string($data['params'])) {
                $decoded = json_decode($data['params'], true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $params = $decoded;
                }
            }
        }

        if (stripos(trim($query), 'SELECT') !== 0) {
            throw new \Exception('Apenas queries SELECT são permitidas no método GET.', 400);
        }

        try {
            return Sql::runQuery($query, $params);
        } catch (\Exception $e) {
            throw new \Exception('Erro ao executar query: ' . $e->getMessage(), 500);
        }
    }

    public function postSQL(array $data): array
    {
        if (!isset($data['query']) || empty($data['query'])) {
            throw new \Exception("Parâmetro 'query' é obrigatório.", 400);
        }
        $query = $data['query'];
        $params = isset($data['params']) && is_array($data['params']) ? $data['params'] : [];
        if (stripos(trim($query), 'INSERT') !== 0) {
            throw new \Exception('Apenas queries INSERT são permitidas no método POST.', 400);
        }
        if (!empty($params)) {
            foreach ($params as $key => $value) {
                $placeholder = ':' . $key;
                if (is_string($value)) {
                    $value = "'" . str_replace("'", "''", $value) . "'";
                }
                $query = str_replace($placeholder, (string) $value, $query);
            }
        }
        try {
            Sql::Call()->PureCommand($query);
            return ['message' => 'Registro inserido com sucesso.', 'query' => $query];
        } catch (\Exception $e) {
            throw new \Exception('Erro ao executar query: ' . $e->getMessage(), 500);
        }
    }

    public function putSQL(array $data): array
    {
        if (!isset($data['query']) || empty($data['query'])) {
            throw new \Exception("Parâmetro 'query' é obrigatório.", 400);
        }
        $query = $data['query'];
        $params = isset($data['params']) && is_array($data['params']) ? $data['params'] : [];
        if (stripos(trim($query), 'UPDATE') !== 0) {
            throw new \Exception('Apenas queries UPDATE são permitidas no método PUT.', 400);
        }
        if (!empty($params)) {
            foreach ($params as $key => $value) {
                $placeholder = ':' . $key;
                if (is_string($value)) {
                    $value = "'" . str_replace("'", "''", $value) . "'";
                }
                $query = str_replace($placeholder, (string) $value, $query);
            }
        }
        try {
            Sql::Call()->PureCommand($query);
            return ['message' => 'Registro(s) atualizado(s) com sucesso.', 'query' => $query];
        } catch (\Exception $e) {
            throw new \Exception('Erro ao executar query: ' . $e->getMessage(), 500);
        }
    }

    public function deleteSQL(array $data): array
    {
        if (!isset($data['query']) || empty($data['query'])) {
            throw new \Exception("Parâmetro 'query' é obrigatório.", 400);
        }
        $query = $data['query'];
        $params = isset($data['params']) && is_array($data['params']) ? $data['params'] : [];
        if (stripos(trim($query), 'DELETE') !== 0) {
            throw new \Exception('Apenas queries DELETE são permitidas no método DELETE.', 400);
        }
        if (!empty($params)) {
            foreach ($params as $key => $value) {
                $placeholder = ':' . $key;
                if (is_string($value)) {
                    $value = "'" . str_replace("'", "''", $value) . "'";
                }
                $query = str_replace($placeholder, (string) $value, $query);
            }
        }
        try {
            Sql::Call()->PureCommand($query);
            return ['message' => 'Registro(s) deletado(s) com sucesso.', 'query' => $query];
        } catch (\Exception $e) {
            throw new \Exception('Erro ao executar query: ' . $e->getMessage(), 500);
        }
    }
}

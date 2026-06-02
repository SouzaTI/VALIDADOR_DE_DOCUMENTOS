<?php
/**
 * Arquivo: db.php
 * Conexão Centralizada Segura via .env (Módulo Validador de Documentos)
 */

$envPath = __DIR__ . '/.env';

if (file_exists($envPath)) {
    $envVariables = parse_ini_file($envPath);
    
    $host = $envVariables['DB_HOST'] ?? null;
    $port = $envVariables['DB_PORT'] ?? null;
    $db   = $envVariables['DB_NAME'] ?? null;
    $user = $envVariables['DB_USER'] ?? null;
    $pass = $envVariables['DB_PASS'] ?? '';
    
    if (!$host || !$db || !$user) {
        die("Erro crítico: Variáveis obrigatórias não foram definidas no arquivo .env.");
    }
} else {
    die("Erro crítico: Arquivo .env não encontrado no módulo Validador de Documentos.");
}

// Criando a conexão centralizada usando os dados dinâmicos do cofre
$conn = new mysqli($host, $user, $pass, $db, $port);

// Se der erro, a gente para tudo aqui com uma mensagem limpa
if ($conn->connect_error) {
    die("Falha na Conexão: " . $conn->connect_error);
}

// Define o charset para não ter erro de acentuação
$conn->set_charset("utf8mb4");
?>
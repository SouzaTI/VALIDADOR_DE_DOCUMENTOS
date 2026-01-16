<?php
// Arquivo: log.php - SIMPLES PARA TESTES

/**
 * Escreve uma mensagem em um arquivo de log simples (para fins de diagnóstico).
 * O código assume que a pasta 'logs' existe.
 * @param string $message A mensagem a ser logada.
 * @param string $log_file O nome do arquivo de log (ex: 'email_erros.log').
 */
function write_log($message, $log_file = 'system.log') {
    // Para simplificar no XAMPP, vamos usar a pasta raiz do projeto.
    $log_path = __DIR__ . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR . $log_file;
    
    // Tenta criar a pasta 'logs' se não existir
    if (!is_dir(__DIR__ . DIRECTORY_SEPARATOR . 'logs')) {
        @mkdir(__DIR__ . DIRECTORY_SEPARATOR . 'logs', 0777, true);
    }

    $timestamp = date('Y-m-d H:i:s');
    $log_message = "[{$timestamp}] (LOG) {$message}" . PHP_EOL;

    // Use @file_put_contents para suprimir warnings se a permissão falhar.
    @file_put_contents($log_path, $log_message, FILE_APPEND | LOCK_EX);
}
?>
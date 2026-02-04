<?php
// Arquivo: db.php
$host = '127.0.0.1';
$port = 3307; 
$db = 'db_svd';
$user = 'root';
$pass = '';

// Criando a conexão centralizada
$conn = new mysqli($host, $user, $pass, $db, $port);

// Se der erro, a gente para tudo aqui com uma mensagem clara
if ($conn->connect_error) {
    die("Falha na Conexão: " . $conn->connect_error);
}

// Define o charset para não ter erro de acentuação vindo do banco
$conn->set_charset("utf8mb4");
?>
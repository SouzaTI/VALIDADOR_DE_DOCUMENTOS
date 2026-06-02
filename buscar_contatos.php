<?php
// buscar_contatos.php
header('Content-Type: application/json');

// Puxa o cofre de credenciais centralizado
require_once __DIR__ . '/db.php';

// Como o db.php nativo conecta no banco db_svd, trocamos apenas para o banco da Intranet
$conn_intra = $conn;
$conn_intra->select_db('intranet');

if ($conn_intra->connect_error) {
    echo json_encode([]);
    exit;
}

$query = $_GET['q'] ?? '';
$search = "%$query%";

// Ajuste das colunas conforme sua tabela no PHPMyAdmin
$sql = "SELECT NOME, `E-MAIL`, SETOR FROM matriz_comunicacao 
        WHERE NOME LIKE ? OR SETOR LIKE ? OR `E-MAIL` LIKE ? 
        LIMIT 15";

$stmt = $conn_intra->prepare($sql);
$stmt->bind_param("sss", $search, $search, $search);
$stmt->execute();
$result = $stmt->get_result();

$data = [];
while ($row = $result->fetch_assoc()) {
    $data[] = [
        'id'   => $row['E-MAIL'], // Use o nome exato da coluna aqui também
        'text' => $row['NOME'] . " [" . $row['SETOR'] . "] - " . $row['E-MAIL']
    ];
}

echo json_encode($data);
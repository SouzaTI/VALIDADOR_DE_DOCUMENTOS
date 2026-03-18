<?php
// buscar_contatos.php
header('Content-Type: application/json');

// Configurações do Banco da Intranet
$host_intra = '127.0.0.1';
$user_intra = 'root';
$pass_intra = '';
$db_intra   = 'intranet';
$port_intra = 3307; // Conforme sua imagem do PHPMyAdmin

$conn_intra = new mysqli($host_intra, $user_intra, $pass_intra, $db_intra, $port_intra);

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
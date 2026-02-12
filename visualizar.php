<?php
session_start();
require_once 'db.php';

$doc_id = $_GET['doc_id'] ?? die('ID ausente');
$stmt = $conn->prepare("SELECT caminho_carimbado FROM documentos WHERE id = ?");
$stmt->bind_param("i", $doc_id);
$stmt->execute();
$res = $stmt->get_result()->fetch_assoc();

if ($res && !empty($res['caminho_carimbado'])) {
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="documento.pdf"');
    readfile($res['caminho_carimbado']);
    exit;
}
die("Arquivo assinado não encontrado.");
<?php
// Arquivo: detalhes_workflow.php - RASTREAMENTO E AUDITORIA COMPLETA

session_start();
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit;
}

// --- CONFIGURAÇÃO DE CONEXÃO ---
$host = '127.0.0.1';
$port = 3307; 
$db = 'db_svd';
$user = 'root';
$pass = '';
$url_base = 'http://192.168.0.63:8080/validador_documentos'; 

$conn = new mysqli($host, $user, $pass, $db, $port);
if ($conn->connect_error) {
    die("Falha na Conexão com o Banco de Dados: " . $conn->connect_error);
}

// Verifica o ID do documento
$documento_id = $_GET['doc_id'] ?? die('ID do documento não especificado.');

$nome_arquivo_doc = 'Documento';
$caminho_doc_visualizacao = '';
$status_final_doc = 'EM_FLUXO';

// --- A. BUSCA DADOS BÁSICOS DO DOCUMENTO E O CAMINHO MAIS RECENTE ---
$sql_doc_info = "
SELECT 
    nome_arquivo, 
    caminho_original, 
    caminho_carimbado, 
    status
FROM 
    Documentos
WHERE 
    id = ?";

$stmt_info = $conn->prepare($sql_doc_info);
$stmt_info->bind_param("i", $documento_id);
$stmt_info->execute();
$result_info = $stmt_info->get_result();
$doc_info = $result_info->fetch_assoc();
$stmt_info->close();

if ($doc_info) {
    $nome_arquivo_doc = $doc_info['nome_arquivo'];
    $status_final_doc = $doc_info['status'];
    // Define o caminho para a pré-visualização (Carimbado > Original)
    $caminho_doc_visualizacao = empty($doc_info['caminho_carimbado']) ? $doc_info['caminho_original'] : $doc_info['caminho_carimbado'];
} else {
    die("Documento não encontrado.");
}


// --- B. BUSCA O HISTÓRICO COMPLETO DO WORKFLOW ---
$sql_workflow_history = "
SELECT 
    w.ordem, 
    u.nome AS validador_nome, 
    w.status_etapa, 
    w.data_conclusao
FROM 
    Workflow_Etapas w
JOIN 
    Usuarios u ON w.validador_fk = u.id
WHERE 
    w.doc_fk = ?
ORDER BY 
    w.ordem ASC";

$stmt_history = $conn->prepare($sql_workflow_history);
$stmt_history->bind_param("i", $documento_id);
$stmt_history->execute();
$workflow_history = $stmt_history->get_result();
$stmt_history->close();

$conn->close();


// Função auxiliar para converter o caminho físico em URL
function caminho_para_url($caminho_fisico, $url_base) {
    // A. Padroniza separadores de diretório para barras (Windows para Web)
    $caminho_relativo = str_replace('\\', '/', $caminho_fisico);
    
    // B. Garante que a URL base não termine com barra
    $url_base = rtrim($url_base, '/');

    // C. Encontra o início do caminho Web: a partir da pasta 'arquivos/'
    $pos = strpos($caminho_relativo, 'validador_documentos/'); 
    
    if ($pos !== false) {
        $pos_arquivos = $pos + strlen('validador_documentos/');
        $caminho_http_parcial_bruto = substr($caminho_relativo, $pos_arquivos); 
        
        // D. Separa o diretório do nome do arquivo
        $diretorio = dirname($caminho_http_parcial_bruto); // Ex: arquivos
        $nome_arquivo = basename($caminho_http_parcial_bruto); 
        
// START FIX: Trocando urlencode por rawurlencode para maior compatibilidade com paths e arquivos.
        $nome_arquivo_codificado = rawurlencode($nome_arquivo);
// END FIX
        
        // E. Remonta o caminho relativo: arquivos/nome_codificado.pdf
        $caminho_http_parcial = "{$diretorio}/{$nome_arquivo_codificado}";
    } else {
        // Fallback: Se a pasta do projeto não for encontrada, algo está errado
        return 'erro_caminho'; 
    }
    
    // F. Monta a URL completa.
    $url_final = "{$url_base}/{$caminho_http_parcial}";
    
    return $url_final;
}

?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>SVD - Workflow de <?php echo htmlspecialchars($nome_arquivo_doc); ?></title>
    <style>
        /* Estilos básicos de DARK MODE */
        body { font-family: Arial, sans-serif; padding: 20px; background-color: #121212; color: #e0e0e0; }
        .container { background: #1e1e1e; padding: 30px; border-radius: 8px; box-shadow: 0 4px 10px rgba(0, 0, 0, 0.5); border: 1px solid #333; margin: 0 auto; max-width: 1200px; }
        h1, h2 { color: #66bb6a; }
        .grid { 
            display: grid; 
            grid-template-columns: 2fr 1fr; /* 2/3 para o PDF, 1/3 para a auditoria */
            gap: 20px; 
            margin-top: 20px;
        }
        .pdf-viewer, .history-panel {
            background: #2c2c2c;
            padding: 15px;
            border-radius: 4px;
        }
        .pdf-viewer iframe {
            width: 100%; 
            height: 700px; 
            border: none;
        }
        /* Estilos de Tabela para Histórico */
        .history-table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        .history-table th, .history-table td { border: 1px solid #444; padding: 10px; text-align: left; font-size: 0.9em; }
        .history-table th { background-color: #333; color: #81c784; }
        
        /* Status Badges (copie os estilos do painel.php) */
        .status-badge { padding: 4px 8px; border-radius: 12px; font-size: 0.75em; font-weight: bold; text-transform: uppercase; display: inline-block; }
        .status-VALIDADO { background-color: #4CAF50; color: #fff; }
        .status-REJEITADO { background-color: #F44336; color: #fff; }
        .status-PENDENTE { background-color: #FFC107; color: #333; }
        .status-CANCELADO { background-color: #607D8B; color: #fff; }
        .back-link { display: block; margin-top: 20px; color: #81c784; text-decoration: none; font-weight: bold;}
    </style>
</head>
<body>
    <div class="container">
        <h1>📊 Rastreamento de Workflow</h1>
        <h2>Documento: <?php echo htmlspecialchars($nome_arquivo_doc); ?></h2>
        <p>Status Atual: 
            <span class="status-badge status-<?php echo str_replace(' ', '_', strtoupper($status_final_doc)); ?>">
                <?php echo htmlspecialchars(str_replace('_', ' ', $status_final_doc)); ?>
            </span>
        </p>

        <div class="grid">
            <div class="pdf-viewer">
                <h2>Documento Carimbado (Pré-visualização)</h2>
                <?php
                $url_visualizacao = caminho_para_url($caminho_doc_visualizacao, $url_base);
                ?>
                <iframe src="<?php echo htmlspecialchars($url_visualizacao); ?>" frameborder="0"></iframe>
            </div>

            <div class="history-panel">
                <h2>Histórico de Assinaturas (Auditoria)</h2>
                <table class="history-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Validador</th>
                            <th>Ação</th>
                            <th>Data</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($workflow_history->num_rows > 0): ?>
                            <?php while ($etapa = $workflow_history->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo $etapa['ordem']; ?></td>
                                <td><?php echo htmlspecialchars($etapa['validador_nome']); ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo strtoupper($etapa['status_etapa']); ?>">
                                        <?php echo htmlspecialchars($etapa['status_etapa']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php 
                                        if ($etapa['data_conclusao']) {
                                            echo date('d/m/Y H:i:s', strtotime($etapa['data_conclusao']));
                                        } else {
                                            echo 'Pendente...';
                                        }
                                    ?>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="4">Workflow não iniciado ou não encontrado.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <a href="painel.php" class="back-link">← Voltar para o Painel</a>
    </div>
</body>
</html>
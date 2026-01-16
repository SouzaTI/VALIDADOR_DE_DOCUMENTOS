<?php
// Arquivo: painel.php - VERSÃO FINAL COM LINK DE UPLOAD, DARK MODE E MENSAGEM DE SUCESSO

session_start(); 

// --- CÓDIGO PARA EXIBIR MENSAGEM DE SUCESSO ---
$upload_mensagem = '';
$validation_success_message = ''; // Nova variável para mensagens simples
if (isset($_SESSION['upload_success'])) {
    $upload_mensagem = $_SESSION['upload_success'];
    unset($_SESSION['upload_success']); // Limpa a sessão após exibir
} 
// START FIX 1: Armazena a mensagem de validação em uma variável separada
elseif (isset($_SESSION['validation_success'])) {
    $validation_success_message = $_SESSION['validation_success']; 
    unset($_SESSION['validation_success']); // Limpa a sessão após exibir
}

// --- SEGURANÇA: Redireciona se não houver login ---
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit;
}

// --- DADOS DO USUÁRIO LOGADO ---
$validador_logado_id = $_SESSION['usuario_id'];
$nome_logado = $_SESSION['usuario_nome'] ?? 'Validador';

// --- CONFIGURAÇÃO DE CONEXÃO ---
$host = '127.0.0.1';
$port = 3307; // CONFIRME SUA PORTA!
$db = 'db_svd';
$user = 'root';
$pass = '';

$conn = new mysqli($host, $user, $pass, $db, $port);
if ($conn->connect_error) {
    die("Falha na Conexão com o Banco de Dados: " . $conn->connect_error);
}

$CATEGORIAS_LIST = [
    'TODAS' => 'Todas as Categorias', // Nova opção de filtro
    'NF_USO_CONSUMO' => 'NF Uso e Consumo',
    'NF_MANUTENCAO_PREDIAL' => 'NF Manutenção Predial',
    'NF_OBRAS' => 'NF Obras',
    'CONTRATO_COMPRA' => 'Contrato de Compra/Serviço',
    'RELATORIO_FIN' => 'Relatório Financeiro',
    'GERAL' => 'Geral (Padrão)',
];

// ----------------------------------------------------------------------
// 1. BUSCA DOCUMENTOS PENDENTES (Com mais detalhes e nome do Remetente)
// ----------------------------------------------------------------------
$sql_pendentes = "
SELECT 
    d.id AS doc_id, 
    d.nome_arquivo, 
    d.data_upload,
    w.ordem,
    u_upload.nome AS remetente_nome
FROM 
    Documentos d
JOIN 
    Workflow_Etapas w ON d.id = w.doc_fk
JOIN 
    Usuarios u_upload ON d.validador_fk = u_upload.id
WHERE 
    w.validador_fk = ? 
    AND w.status_etapa = 'PENDENTE'
ORDER BY 
    d.data_upload ASC";

$stmt_pendentes = $conn->prepare($sql_pendentes);
$stmt_pendentes->bind_param("i", $validador_logado_id);
$stmt_pendentes->execute();
$documentos_pendentes = $stmt_pendentes->get_result();
$stmt_pendentes->close();

// ----------------------------------------------------------------------
// 2. BUSCA DOCUMENTOS PARA RASTREAMENTO (Subidos pelo usuário ou que ele já validou/rejeitou)
// ----------------------------------------------------------------------
$status_filtro = $_GET['status_filtro'] ?? 'TODOS';
$status_filtro_sql = '';
$categoria_filtro = $_GET['categoria_filtro'] ?? 'TODAS';
$categoria_filtro_sql = '';

if ($status_filtro !== 'TODOS') {
    // Se o filtro for 'EM_FLUXO', incluímos 'PENDENTE' (o status real na tabela Documentos)
    $status_sql = ($status_filtro === 'EM_FLUXO') ? "'EM_FLUXO', 'PENDENTE'" : "'".$status_filtro."'";
    $status_filtro_sql = " AND d.status IN ({$status_sql})";
}

if ($categoria_filtro !== 'TODAS') {
    // Escapa o valor para a query ser segura
    $categoria_segura = $conn->real_escape_string($categoria_filtro);
    $categoria_filtro_sql = " AND d.categoria = '{$categoria_segura}'";
}

$sql_rastreamento = "
SELECT DISTINCT
    d.id, 
    d.nome_arquivo, 
    d.status AS status_final,
    d.validador_fk
FROM 
    Documentos d
LEFT JOIN 
    Workflow_Etapas w ON d.id = w.doc_fk
WHERE 
    (d.validador_fk = ? OR w.validador_fk = ?)
    {$status_filtro_sql}
    {$categoria_filtro_sql} 
ORDER BY d.id DESC";

$stmt_rastreamento = $conn->prepare($sql_rastreamento);
$stmt_rastreamento->bind_param("ii", $validador_logado_id, $validador_logado_id);
$stmt_rastreamento->execute();
$documentos_rastreamento = $stmt_rastreamento->get_result();
$stmt_rastreamento->close();

$conn->close();

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>SVD - Meu Painel de Aprovações</title>
    <style>
        /* DARK MODE STYLES */
        body { 
            font-family: Arial, sans-serif; 
            padding: 20px; 
            background-color: #121212; 
            color: #e0e0e0;
        }
        .container { 
            background: #1e1e1e; 
            padding: 30px; 
            border-radius: 8px; 
            max-width: 900px; 
            margin: 0 auto; 
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.5);
            border: 1px solid #333;
        }
        h1, h2 { color: #66bb6a; }
        table { 
            width: 100%; 
            border-collapse: collapse; 
            margin-top: 20px; 
            background-color: #2c2c2c;
        }
        th, td { 
            border: 1px solid #444; 
            padding: 12px; 
            text-align: left; 
        }
        th { 
            background-color: #383838; 
            color: #e0e0e0;
        }
        td { color: #bdbdbd; }
        .btn-acao { 
            background-color: #66bb6a; 
            color: #1e1e1e; 
            padding: 8px 12px; 
            text-decoration: none; 
            border-radius: 3px; 
            font-weight: bold;
            transition: background-color 0.3s;
        }
        .btn-acao:hover { background-color: #4caf50; }
        
        /* NOVO ESTILO: BOTÃO DE NOVO UPLOAD */
        .btn-novo-doc {
            background-color: #007bff; 
            color: white; 
            padding: 8px 12px; 
            text-decoration: none; 
            border-radius: 4px; 
            font-weight: bold;
            transition: background-color 0.3s;
            display: inline-block;
        }
        .btn-novo-doc:hover {
            background-color: #0056b3;
        }
        
        .logout { 
            /* Remove float: right; */
            color: #ff5252; 
            text-decoration: none; 
            font-size: 14px;
            padding: 5px 10px;
            border: 1px solid #ff5252;
            border-radius: 4px;
            transition: background-color 0.3s;
        }
        .logout:hover {
            background-color: #ff52521a;
        }
        /* Novo container flex para alinhar o Olá e os botões */
        .header-top {
            display: flex;
            justify-content: space-between; /* Espaço entre o título e os botões */
            align-items: center;
            margin-bottom: 20px;
        }
        /* Container dos botões de ação */
        .action-buttons {
            display: flex;
            gap: 10px; /* Espaço entre o botão de Upload e o de Sair */
            align-items: center;
        }


        /* --- CÓDIGO DA TABELA STYLED-TABLE (Certifique-se de que este bloco está completo) --- */
        .styled-table {
            width: 100%;
            border-collapse: collapse;
            margin: 25px 0;
            font-size: 0.9em;
            min-width: 400px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.3);
        }
        .styled-table thead tr {
            background-color: #388e3c; /* Dark Green Header */
            color: #ffffff;
            text-align: left;
        }
        .styled-table th,
        .styled-table td {
            padding: 12px 15px;
            border: 1px solid #333;
        }
        .styled-table tbody tr {
            border-bottom: 1px solid #333;
            background-color: #2c2c2c; 
            color: #e0e0e0;
        }
        .styled-table tbody tr:nth-of-type(even) {
            background-color: #1e1e1e; /* Alternate row color */
        }

        /* Status Badges */
        .status-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.75em;
            font-weight: bold;
            text-transform: uppercase;
            display: inline-block;
        }
        .status-VALIDADO { background-color: #4CAF50; color: #fff; } /* Green */
        .status-REJEITADO { background-color: #F44336; color: #fff; } /* Red */
        .status-PENDENTE { background-color: #FFC107; color: #333; } /* Yellow/Orange */
        .status-EM_FLUXO { background-color: #2196F3; color: #fff; } /* Blue */
        .status-APROVADO { background-color: #4CAF50; color: #fff; } 


        /* Botões de Ação na Tabela */
        .btn-acao { background-color: #007bff; }
        .btn-rastreio { background-color: #5c6bc0; } /* Novo botão de rastreio */
        .btn-rastreio:hover { background-color: #3f51b5; }

        .table-scroll-container {
            max-height: 500px; /* Define a altura máxima que o container pode ter (Ajuste esse valor conforme sua preferência) */
            overflow-y: auto;  /* Habilita a barra de rolagem vertical quando o conteúdo exceder 400px */
            border: 1px solid #333; /* Uma borda sutil para destacar a área de rolagem */
            border-radius: 4px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header-top">
            <h1>👋 Olá, <?php echo htmlspecialchars($nome_logado); ?>!</h1>
            
            <div class="action-buttons">
                <a href="upload.php" class="btn-novo-doc">➕ Novo Upload de Documento</a>
                <a href="logout.php" class="logout">Sair</a>
            </div>
        </div>
        
        <?php if (!empty($validation_success_message)): ?>
            <div class="empty-message" style="background-color: #1e1e1e; color: #66bb6a; border-color: #66bb6a; margin-bottom: 20px;">
                ✅ **Ação Concluída!** <?php echo htmlspecialchars($validation_success_message); ?>
            </div>
        <?php endif; ?>
        <?php if (!empty($upload_mensagem)): ?>
            <div class="empty-message" style="background-color: #1e1e1e; color: #66bb6a; border-color: #66bb6a; margin-bottom: 20px;">
                ✅ **Upload Concluído!** <?php echo htmlspecialchars($upload_mensagem); ?>
                
                <p>
                    ➡️ **Próximo Passo:** Como autor, você é a **Etapa 1**. Seu documento já está na seção **Documentos Pendentes** aguardando sua revisão inicial (assinatura).
                </p>
            </div>
        <?php endif; ?>
        

        <h2>⚠️Documentos Pendentes de Sua Assinatura⚠️</h2>

        <?php if ($documentos_pendentes->num_rows > 0): ?>
            <table class="styled-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Documento</th>
                        <th>Etapa</th>
                        <th>Remetente</th>
                        <th>Data Upload</th>
                        <th>Ação</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($doc = $documentos_pendentes->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo $doc['doc_id']; ?></td>
                        <td><?php echo htmlspecialchars($doc['nome_arquivo']); ?></td>
                        <td><?php echo $doc['ordem']; ?></td>
                        <td><?php echo htmlspecialchars($doc['remetente_nome']); ?></td>
                        <?php 
                        $data_upload_db = $doc['data_upload'];
                        // Verifica se a string da data é válida e não a data nula do MySQL
                        $data_upload_formatada = ($data_upload_db && $data_upload_db !== '0000-00-00 00:00:00') 
                                                    ? date('d/m/Y H:i', strtotime($data_upload_db))
                                                    : 'N/A';
                        ?>
                        <td><?php echo $data_upload_formatada; ?></td>
                        <td>
                            <a href="validar.php?doc_id=<?php echo $doc['doc_id']; ?>" class="btn-acao">
                                Revisar e Assinar
                            </a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p class="empty-message">🎉 Não há documentos pendentes no momento. Bom trabalho!</p>
        <?php endif; ?>

        <hr style="border-color: #333; margin: 30px 0;">

        <div style="margin-top: 30px;">
            <form method="GET" action="painel.php" style="display: flex; gap: 10px; align-items: center;">
                <label for="status_filtro" style="font-weight: bold; color: #81c784;">Filtrar por Status:</label>
                <select id="status_filtro" name="status_filtro" onchange="this.form.submit()"
                        style="padding: 8px; background: #2c2c2c; color: #e0e0e0; border: 1px solid #444; border-radius: 4px;">
                    
                    <option value="TODOS" <?php echo ($status_filtro === 'TODOS' ? 'selected' : ''); ?>>TODOS</option>
                    <option value="EM_FLUXO" <?php echo ($status_filtro === 'EM_FLUXO' ? 'selected' : ''); ?>>EM FLUXO (Pendentes)</option>
                    <option value="VALIDADO" <?php echo ($status_filtro === 'VALIDADO' ? 'selected' : ''); ?>>VALIDADO</option>
                    <option value="REJEITADO" <?php echo ($status_filtro === 'REJEITADO' ? 'selected' : ''); ?>>REJEITADO</option>

                </select>

                <label for="categoria_filtro" style="font-weight: bold; color: #81c784; margin-left: 15px;">Filtrar por Categoria:</label>
                <select id="categoria_filtro" name="categoria_filtro" onchange="this.form.submit()"
                        style="padding: 8px; background: #2c2c2c; color: #e0e0e0; border: 1px solid #444; border-radius: 4px;">
                    
                    <?php foreach ($CATEGORIAS_LIST as $key => $label): ?>
                        <option value="<?php echo htmlspecialchars($key); ?>" <?php echo ($categoria_filtro === $key ? 'selected' : ''); ?>>
                            <?php echo htmlspecialchars($label); ?>
                        </option>
                    <?php endforeach; ?>
                    
                </select>
                 <noscript><input type="submit" value="Filtrar"></noscript>
                </form>
        </div>
        
        <h2>🔎 Acompanhamento de Documentos Enviados e Validados</h2>
        
    <?php if ($documentos_rastreamento->num_rows > 0): ?>

        <div class="table-scroll-container">
            <table class="styled-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Documento</th>
                        <th>Status Final</th>
                        <th>Ação</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($doc_rastreio = $documentos_rastreamento->fetch_assoc()): 
                        // O status pode ser 'PENDENTE' (se o documento foi subido mas ainda está no meio do fluxo)
                        $status_final = $doc_rastreio['status_final'] ?? 'EM_FLUXO'; 
                        if ($status_final == 'PENDENTE') $status_final = 'EM_FLUXO'; // Simplifica a UX
                    ?>
                    <tr>
                        <td><?php echo $doc_rastreio['id']; ?></td>
                        <td><?php echo htmlspecialchars($doc_rastreio['nome_arquivo']); ?></td>
                        <td>
                            <span class="status-badge status-<?php echo str_replace(' ', '_', strtoupper($status_final)); ?>">
                                <?php echo htmlspecialchars(str_replace('_', ' ', $status_final)); ?>
                            </span>
                        </td>
                        <td>
                            <a href="detalhes_workflow.php?doc_id=<?php echo $doc_rastreio['id']; ?>" class="btn-acao btn-rastreio">
                                Ver Workflow
                            </a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p class="empty-message">Nenhum documento sob seu rastreamento.</p>
    <?php endif; ?>
        
    </div>
</body>
</html>
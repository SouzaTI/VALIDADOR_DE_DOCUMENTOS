<?php
// Arquivo: painel.php - VERSÃO PREMIUM
session_start(); 
require_once 'db.php'; 

// --- SEGURANÇA ---
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit;
}

if (isset($_SESSION['primeiro_login']) && $_SESSION['primeiro_login'] == 1) {
    header("Location: configurar_usuario.php");
    exit;
}

// --- MENSAGENS E DADOS ---
$upload_mensagem = $_SESSION['upload_success'] ?? '';
$validation_success_message = $_SESSION['validation_success'] ?? '';
unset($_SESSION['upload_success'], $_SESSION['validation_success']);

$validador_logado_id = $_SESSION['usuario_id'];
$nome_logado = $_SESSION['usuario_nome'] ?? 'Validador';

// --- SQL PARA MÉTRICAS RÁPIDAS (Contribuição para o uso) ---
$sql_count = "SELECT 
    (SELECT COUNT(*) FROM Workflow_Etapas WHERE validador_fk = ? AND status_etapa = 'PENDENTE') as total_pendente,
    (SELECT COUNT(*) FROM Documentos WHERE validador_fk = ?) as meus_uploads";
$stmt_count = $conn->prepare($sql_count);
$stmt_count->bind_param("ii", $validador_logado_id, $validador_logado_id);
$stmt_count->execute();
$metricas = $stmt_count->get_result()->fetch_assoc();

// --- LISTA DE CATEGORIAS ---
$CATEGORIAS_LIST = [
    'TODAS' => 'Todas as Categorias',
    'NF_USO_CONSUMO' => 'NF Uso e Consumo',
    'NF_MANUTENCAO_PREDIAL' => 'NF Manutenção Predial',
    'NF_OBRAS' => 'NF Obras',
    'CONTRATO_COMPRA' => 'Contrato de Compra/Serviço',
    'RELATORIO_FIN' => 'Relatório Financeiro',
    'GERAL' => 'Geral (Padrão)',
];

// --- LOGICA DE FILTROS ---
$status_filtro = $_GET['status_filtro'] ?? 'TODOS';
$categoria_filtro = $_GET['categoria_filtro'] ?? 'TODAS';

// ... (Queries de Pendentes e Rastreamento permanecem as mesmas que normalizamos antes) ...
// Use a lógica de prepared statements que passei na última resposta aqui.
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SVD - Dashboard</title>
    <style>
        :root {
            --bg-dark: #121212;
            --card-dark: #1e1e1e;
            --primary: #66bb6a;
            --secondary: #2196F3;
            --danger: #f44336;
            --text-main: #e0e0e0;
            --text-dim: #b0b0b0;
        }

        body { 
            font-family: 'Inter', system-ui, sans-serif; 
            background-color: var(--bg-dark); 
            color: var(--text-main);
            margin: 0; padding: 20px;
        }

        .container { 
            max-width: 1100px; margin: 0 auto; 
        }

        /* HEADER & DASHBOARD CARDS */
        .header-top {
            display: flex; justify-content: space-between; align-items: center;
            margin-bottom: 30px;
        }

        .stats-grid {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px; margin-bottom: 30px;
        }

        .stat-card {
            background: var(--card-dark); padding: 20px; border-radius: 12px;
            border: 1px solid #333; text-align: center;
        }

        .stat-card h3 { margin: 0; font-size: 0.8rem; color: var(--text-dim); text-transform: uppercase; }
        .stat-card p { margin: 10px 0 0; font-size: 1.8rem; font-weight: bold; color: var(--primary); }

        /* TABELAS E COMPONENTES */
        .content-section {
            background: var(--card-dark); padding: 25px; border-radius: 12px;
            border: 1px solid #333; margin-bottom: 30px;
        }

        h2 { font-size: 1.2rem; margin-top: 0; display: flex; align-items: center; gap: 10px; }

        .styled-table {
            width: 100%; border-collapse: collapse; margin-top: 15px;
        }

        .styled-table th {
            background: #252525; color: var(--text-dim); font-weight: 500;
            text-align: left; padding: 12px; font-size: 0.85rem;
        }

        .styled-table td { padding: 14px 12px; border-bottom: 1px solid #333; font-size: 0.9rem; }

        /* BOTÕES */
        .btn {
            padding: 8px 16px; border-radius: 6px; text-decoration: none;
            font-weight: 600; font-size: 0.85rem; transition: 0.3s; display: inline-block;
        }

        .btn-primary { background: var(--primary); color: #121212; }
        .btn-outline-danger { border: 1px solid var(--danger); color: var(--danger); }
        .btn-blue { background: var(--secondary); color: white; }

        .btn:hover { opacity: 0.8; transform: translateY(-1px); }

        /* BADGES */
        .badge {
            padding: 4px 10px; border-radius: 20px; font-size: 0.75rem; font-weight: bold;
        }
        .bg-pending { background: rgba(255, 193, 7, 0.2); color: #ffc107; }
        .bg-success { background: rgba(76, 175, 80, 0.2); color: #4caf50; }

        .filter-bar {
            display: flex; gap: 15px; background: #252525; padding: 15px;
            border-radius: 8px; margin-bottom: 20px; align-items: center;
        }

        select, input {
            background: #121212; border: 1px solid #444; color: white;
            padding: 8px; border-radius: 4px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header-top">
            <div>
                <h1 style="margin:0;">Dashboard SVD</h1>
                <span style="color: var(--text-dim)">Bem-vindo de volta, <?php echo explode(' ', $nome_logado)[0]; ?>!</span>
            </div>
            <div style="display: flex; gap: 10px;">
                <a href="upload.php" class="btn btn-primary">➕ Novo Documento</a>
                <a href="logout.php" class="btn btn-outline-danger">Sair</a>
            </div>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <h3>Pendentes de Assinatura</h3>
                <p><?php echo $metricas['total_pendente']; ?></p>
            </div>
            <div class="stat-card">
                <h3>Meus Uploads</h3>
                <p style="color: var(--secondary)"><?php echo $metricas['meus_uploads']; ?></p>
            </div>
            <div class="stat-card">
                <h3>Status do Sistema</h3>
                <p style="color: #4caf50; font-size: 1rem;">Operacional</p>
            </div>
        </div>

        <div class="content-section">
            <h2><span style="color: #ffc107;">⚠️</span> Documentos Aguardando Você</h2>
            <table class="styled-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Documento</th>
                        <th>Remetente</th>
                        <th>Data</th>
                        <th>Ação</th>
                    </tr>
                </thead>
                <tbody>
                    <?php /* Loop dos documentos pendentes aqui */ ?>
                    <tr>
                        <td>#001</td>
                        <td>NF_Servico_01.pdf</td>
                        <td>João Silva</td>
                        <td>01/02/2026</td>
                        <td><a href="#" class="btn btn-primary">Revisar</a></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="content-section">
            <h2>🔎 Histórico e Rastreamento</h2>
            
            <form class="filter-bar" method="GET">
                <select name="status_filtro" onchange="this.form.submit()">
                    <option value="TODOS">Todos os Status</option>
                    <option value="VALIDADO">Validados</option>
                    <option value="REJEITADO">Rejeitados</option>
                </select>
                
                <select name="categoria_filtro" onchange="this.form.submit()">
                    <?php foreach($CATEGORIAS_LIST as $val => $label): ?>
                        <option value="<?=$val?>"><?=$label?></option>
                    <?php endforeach; ?>
                </select>
            </form>

            <table class="styled-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Arquivo</th>
                        <th>Status</th>
                        <th>Workflow</th>
                    </tr>
                </thead>
                <tbody>
                    <?php /* Loop do rastreamento aqui */ ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
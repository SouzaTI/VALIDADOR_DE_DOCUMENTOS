<?php
// Arquivo: painel.php - VERSÃO AJUSTADA PARA O ASSINANTE GESTOR COM RASTREAMENTO DE ENVIOS
session_start(); 
require_once 'db.php'; 

if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit;
}

$validador_logado_id = $_SESSION['usuario_id'];
$nome_logado = $_SESSION['usuario_nome'] ?? 'Validador';

// 1. MÉTRICAS AJUSTADAS: 
// Pendentes (o que ele precisa assinar) e Participações (o que ele já assinou ou subiu)
$sql_count = "SELECT 
    (SELECT COUNT(*) FROM workflow_etapas WHERE validador_fk = ? AND status_etapa = 'PENDENTE') as total_pendente,
    (SELECT COUNT(DISTINCT doc_fk) FROM workflow_etapas WHERE validador_fk = ?) as participacoes,
    (SELECT COUNT(*) FROM documentos WHERE validador_fk = ?) as meus_uploads";
$stmt_count = $conn->prepare($sql_count);
$stmt_count->bind_param("iii", $validador_logado_id, $validador_logado_id, $validador_logado_id);
$stmt_count->execute();
$metricas = $stmt_count->get_result()->fetch_assoc();

// 2. BUSCA DOCUMENTOS AGUARDANDO ASSINATURA (IGUAL)
$sql_pendentes = "
    SELECT d.id AS doc_id, d.nome_arquivo, d.data_upload, u.nome AS remetente_nome
    FROM documentos d
    JOIN workflow_etapas w ON d.id = w.doc_fk
    JOIN usuarios u ON d.validador_fk = u.id
    WHERE w.validador_fk = ? AND w.status_etapa = 'PENDENTE'
    ORDER BY d.data_upload ASC";
$stmt_p = $conn->prepare($sql_pendentes);
$stmt_p->bind_param("i", $validador_logado_id);
$stmt_p->execute();
$res_pendentes = $stmt_p->get_result();

// 3. BUSCA HISTÓRICO GLOBAL DO USUÁRIO (O QUE ELE ASSINOU OU SUBIU)
$sql_hist = "
    SELECT DISTINCT d.id, d.nome_arquivo, d.status, d.caminho_carimbado 
    FROM documentos d 
    LEFT JOIN workflow_etapas w ON d.id = w.doc_fk 
    WHERE d.validador_fk = ? OR w.validador_fk = ?
    ORDER BY d.id DESC";
$stmt_h = $conn->prepare($sql_hist);
$stmt_h->bind_param("ii", $validador_logado_id, $validador_logado_id);
$stmt_h->execute();
$res_historico = $stmt_h->get_result();

// 4. NOVO: BUSCA ENVIOS REALIZADOS POR VOCÊ (Acompanhamento de quem deve assinar)
$sql_meus_envios = "
    SELECT d.id, d.nome_arquivo, d.status, u.nome as assinante_atual
    FROM documentos d
    LEFT JOIN workflow_etapas w ON d.id = w.doc_fk AND w.status_etapa = 'PENDENTE'
    LEFT JOIN usuarios u ON w.validador_fk = u.id
    WHERE d.validador_fk = ?
    ORDER BY d.id DESC";
$stmt_me = $conn->prepare($sql_meus_envios);
$stmt_me->bind_param("i", $validador_logado_id);
$stmt_me->execute();
$res_meus_envios = $stmt_me->get_result();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>SVD - Dashboard</title>
    <style>
        :root { --bg-dark: #121212; --card-dark: #1e1e1e; --primary: #66bb6a; --text-main: #e0e0e0; --text-dim: #b0b0b0; --info: #2196F3; }
        body { font-family: 'Inter', sans-serif; background-color: var(--bg-dark); color: var(--text-main); margin: 0; padding: 20px; }
        .container { max-width: 1100px; margin: 0 auto; }
        .header-top { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: var(--card-dark); padding: 20px; border-radius: 12px; border: 1px solid #333; text-align: center; }
        .content-section { background: var(--card-dark); padding: 25px; border-radius: 12px; border: 1px solid #333; margin-bottom: 30px; }
        .styled-table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        .styled-table th { background: #252525; color: var(--text-dim); text-align: left; padding: 12px; }
        .styled-table td { padding: 14px 12px; border-bottom: 1px solid #333; }
        .btn { padding: 8px 16px; border-radius: 6px; text-decoration: none; font-weight: 600; transition: 0.3s; display: inline-block; font-size: 0.9rem; }
        .btn-primary { background: var(--primary); color: #121212; }
        .btn-secondary { background: #333; color: #fff; border: 1px solid #444; margin-right: 5px; }
        .btn-outline-danger { border: 1px solid #f44336; color: #f44336; }
        .status-badge { padding: 4px 8px; border-radius: 4px; font-size: 0.8rem; font-weight: bold; }
        .status-pendente { background: #f57c00; color: #fff; } /* Laranja para pendente */
        .status-validado { background: #2e7d32; color: #fff; } /* Verde para aprovado */
        .status-rejeitado { background: #d32f2f; color: #fff; } /* Vermelho para reprovado */
    </style>
</head>
<body>
    <div class="container">
        <div class="header-top">
            <div>
                <h1>Dashboard SVD</h1>
                <span style="color: var(--text-dim)">Olá, <?php echo htmlspecialchars($nome_logado); ?></span>
            </div>
            <div style="gap: 10px; display: flex;">
                <a href="upload.php" class="btn btn-primary">➕ Novo Documento</a>
                <a href="logout.php" class="btn btn-outline-danger">Sair</a>
            </div>
        </div>

        <div class="stats-grid">
            <div class="stat-card"><h3>Pendentes</h3><p style="font-size:1.8rem; color:var(--primary)"><?php echo $metricas['total_pendente']; ?></p></div>
            <div class="stat-card"><h3>Atuados (Assinados)</h3><p style="font-size:1.8rem; color:var(--info)"><?php echo $metricas['participacoes']; ?></p></div>
            <div class="stat-card"><h3>Meus Uploads</h3><p style="font-size:1.8rem; color:#FFA726"><?php echo $metricas['meus_uploads']; ?></p></div>
            <div class="stat-card"><h3>Status</h3><p style="color: #4caf50;">Operacional</p></div>
        </div>

        <div class="content-section">
            <h2>⚠️ Aguardando Sua Assinatura</h2>
            <table class="styled-table">
                <thead><tr><th>ID</th><th>Documento</th><th>Remetente</th><th>Data</th><th>Ação</th></tr></thead>
                <tbody>
                    <?php if ($res_pendentes->num_rows > 0): ?>
                        <?php while($row = $res_pendentes->fetch_assoc()): ?>
                            <tr>
                                <td>#<?php echo $row['doc_id']; ?></td>
                                <td><?php echo htmlspecialchars($row['nome_arquivo']); ?></td>
                                <td><?php echo htmlspecialchars($row['remetente_nome']); ?></td>
                                <td><?php echo date('d/m/Y', strtotime($row['data_upload'])); ?></td>
                                <td><a href="validar.php?doc_id=<?php echo $row['doc_id']; ?>" class="btn btn-primary">Revisar</a></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="5" style="text-align:center; padding:20px; color:var(--text-dim)">Tudo em dia!</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="content-section">
            <h2>📤 Acompanhamento de Envios (Meus Uploads)</h2>
            <table class="styled-table">
                <thead><tr><th>ID</th><th>Arquivo</th><th>Com quem está?</th><th>Status Atual</th></tr></thead>
                <tbody>
                    <?php if ($res_meus_envios->num_rows > 0): ?>
                        <?php while($envio = $res_meus_envios->fetch_assoc()): ?>
                            <tr>
                                <td>#<?php echo $envio['id']; ?></td>
                                <td><?php echo htmlspecialchars($envio['nome_arquivo']); ?></td>
                                <td><?php echo $envio['assinante_atual'] ?? '<span style="color:#66bb6a">Fluxo Concluído</span>'; ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo strtolower($envio['status']); ?>">
                                        <?php echo $envio['status']; ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="4" style="text-align:center; padding:20px; color:var(--text-dim)">Você ainda não enviou documentos.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="content-section">
            <h2>🔎 Histórico e Rastreamento</h2>
            <table class="styled-table">
                <thead><tr><th>ID</th><th>Arquivo</th><th>Status</th><th>Ações</th></tr></thead>
                <tbody>
                    <?php if ($res_historico->num_rows > 0): ?>
                        <?php while($row = $res_historico->fetch_assoc()): ?>
                            <tr>
                                <td>#<?php echo $row['id']; ?></td>
                                <td><?php echo htmlspecialchars($row['nome_arquivo']); ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo strtolower($row['status']); ?>">
                                        <?php echo $row['status']; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if (!empty($row['caminho_carimbado'])): ?>
                                        <a href="visualizar.php?doc_id=<?php echo $row['id']; ?>" target="_blank" class="btn btn-secondary">👁️ Ver</a>
                                        <a href="download.php?doc_id=<?php echo $row['id']; ?>" class="btn btn-primary">📥 Baixar</a>
                                    <?php else: ?>
                                        <span style="color:var(--text-dim); font-size:0.8rem;">Aguardando Carimbo</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="4" style="text-align:center; padding:20px; color:var(--text-dim)">Nenhum registro no histórico.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        // Alerta de sucesso se vier do upload.php
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('upload') && urlParams.get('upload') === 'success') {
            alert("✅ Nota Fiscal enviada com sucesso para o gestor!");
        }
    </script>
</body>
</html>
<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['usuario_id'])) { header("Location: login.php"); exit; }

$doc_id = $_GET['doc_id'] ?? die('ID ausente');

// 1. Busca dados do documento
$stmt = $conn->prepare("SELECT nome_arquivo, caminho_carimbado, status FROM documentos WHERE id = ?");
$stmt->bind_param("i", $doc_id);
$stmt->execute();
$doc = $stmt->get_result()->fetch_assoc();

if (!$doc) die("Documento não encontrado.");

// 2. Busca o histórico de auditoria (quem validou ou rejeitou e por que)
$sql_audit = "SELECT v.*, u.nome as validador_nome 
              FROM validacoes v 
              JOIN usuarios u ON v.validador_fk = u.id 
              WHERE v.doc_fk = ? 
              ORDER BY v.data_hora DESC";
$stmt_audit = $conn->prepare($sql_audit);
$stmt_audit->bind_param("i", $doc_id);
$stmt_audit->execute();
$historico = $stmt_audit->get_result();

// Prepara a URL para o visualizador de PDF (basenome para segurança)
$url_pdf = "arquivos/" . basename($doc['caminho_carimbado']);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>SVD - Auditoria do Documento</title>
    <style>
        :root { --bg: #121212; --card: #1e1e1e; --primary: #66bb6a; --danger: #f44336; }
        body { font-family: 'Inter', sans-serif; background: var(--bg); color: #eee; margin: 0; display: flex; height: 100vh; }
        
        .sidebar { width: 350px; background: var(--card); border-right: 2px solid #333; display: flex; flex-direction: column; }
        .main-content { flex: 1; background: #333; position: relative; }
        
        .header { padding: 20px; border-bottom: 1px solid #333; }
        .audit-list { flex: 1; overflow-y: auto; padding: 20px; }
        
        .audit-item { 
            background: #252525; padding: 15px; border-radius: 8px; margin-bottom: 15px; 
            border-left: 4px solid #444; font-size: 0.85rem;
        }
        .audit-item.validar { border-left-color: var(--primary); }
        .audit-item.rejeitar { border-left-color: var(--danger); }
        
        .badge { padding: 3px 8px; border-radius: 4px; font-weight: bold; font-size: 0.7rem; text-transform: uppercase; }
        .badge-validar { background: rgba(102, 187, 106, 0.2); color: var(--primary); }
        .badge-rejeitar { background: rgba(244, 67, 54, 0.2); color: var(--danger); }
        
        iframe { width: 100%; height: 100%; border: none; }
        .btn-voltar { color: #aaa; text-decoration: none; font-size: 0.8rem; display: block; margin-bottom: 10px; }
    </style>
</head>
<body>

<div class="sidebar">
    <div class="header">
        <a href="painel.php" class="btn-voltar">← Voltar ao Painel</a>
        <h3>Auditoria Digital</h3>
        <small style="color: #888;">Doc ID: #<?= $doc_id ?></small><br>
        <strong><?= htmlspecialchars($doc['nome_arquivo']) ?></strong>
    </div>

    <div class="audit-list">
        <h4>Linha do Tempo</h4>
        <?php if ($historico->num_rows > 0): ?>
            <?php while($v = $historico->fetch_assoc()): ?>
                <div class="audit-item <?= strtolower($v['acao']) ?>">
                    <span class="badge badge-<?= strtolower($v['acao']) ?>"><?= $v['acao'] ?></span>
                    <p style="margin: 8px 0;"><strong>Por:</strong> <?= htmlspecialchars($v['validador_nome']) ?></p>
                    <p style="margin: 5px 0; color: #bbb;"><small>📅 <?= date('d/m/Y H:i', strtotime($v['data_hora'])) ?></small></p>
                    
                    <?php if(!empty($v['comentario'])): ?>
                        <div style="background: #111; padding: 8px; border-radius: 4px; margin-top: 10px; font-style: italic;">
                            "<?= htmlspecialchars($v['comentario']) ?>"
                        </div>
                    <?php endif; ?>
                    
                    <p style="margin-top: 10px; font-size: 0.7rem; color: #666;">
                        IP: <?= $v['ip_origem'] ?><br>
                        Dispositivo: <?= substr($v['user_agent'], 0, 40) ?>...
                    </p>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <p style="color: #666;">Nenhum histórico encontrado.</p>
        <?php endif; ?>
    </div>
</div>

<div class="main-content">
    <iframe src="<?= $url_pdf ?>#toolbar=1"></iframe>
</div>

</body>
</html>
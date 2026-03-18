<?php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);
set_time_limit(120); // Aumenta o tempo para processar vários arquivos

require_once 'db.php'; 
require_once 'notificar.php'; 

if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit;
}

$usuarios_sistema = $conn->query("SELECT id, nome FROM usuarios ORDER BY nome ASC")->fetch_all(MYSQLI_ASSOC);
$CATEGORIAS_LIST = ['NF_USO_CONSUMO' => 'NF Uso e Consumo', 'NF_MANUTENCAO_PREDIAL' => 'NF Manutenção Predial', 'NF_OBRAS' => 'NF Obras'];

$mensagem = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['arquivos_lote'])) {
    $arquivos = $_FILES['arquivos_lote'];
    $assinante_id = $_POST['assinante_id'];
    $categoria = $_POST['categoria'];
    $emails_post = $_POST['notificar_emails'] ?? [];
    $emails_str = is_array($emails_post) ? implode(', ', $emails_post) : $emails_post;

    $sucesso_count = 0;
    
    for ($i = 0; $i < count($arquivos['name']); $i++) {
        if ($arquivos['error'][$i] === 0 && $arquivos['type'][$i] === 'application/pdf') {
            
            $nome_original = $arquivos['name'][$i];
            $nome_final = time() . "_" . $i . "_" . preg_replace("/[^a-zA-Z0-9.]/", "_", $nome_original);
            $destino = __DIR__ . DIRECTORY_SEPARATOR . 'arquivos' . DIRECTORY_SEPARATOR . $nome_final;

            if (move_uploaded_file($arquivos['tmp_name'][$i], $destino)) {
                $conn->begin_transaction();
                try {
                    $stmt = $conn->prepare("INSERT INTO documentos (nome_arquivo, caminho_original, validador_fk, status, data_upload, categoria, notificar_emails) VALUES (?, ?, ?, 'PENDENTE', NOW(), ?, ?)");
                    $stmt->bind_param("ssiss", $nome_original, $destino, $_SESSION['usuario_id'], $categoria, $emails_str);
                    $stmt->execute();
                    $doc_id = $conn->insert_id;

                    $stmt_wf = $conn->prepare("INSERT INTO workflow_etapas (doc_fk, validador_fk, ordem, status_etapa) VALUES (?, ?, 1, 'PENDENTE')");
                    $stmt_wf->bind_param("ii", $doc_id, $assinante_id);
                    $stmt_wf->execute();

                    $conn->commit();
                    $sucesso_count++;
                } catch (Exception $e) {
                    $conn->rollback();
                }
            }
        }
    }
    header("Location: painel.php?upload=success&count=$sucesso_count");
    exit;
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>SVD - Upload em Lote</title>
    <style>
        body { font-family: sans-serif; background: #121212; color: #eee; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0;}
        .card { background: #1e1e1e; padding: 2rem; border-radius: 10px; border: 1px solid #333; width: 450px; }
        input, select, textarea { width: 100%; padding: 10px; margin: 10px 0; background: #2c2c2c; border: 1px solid #444; color: #fff; border-radius: 5px; box-sizing: border-box; }
        .btn-submit { background: #2196F3; color: #fff; font-weight: bold; cursor: pointer; border: none; margin-top: 15px; padding: 12px; border-radius: 5px; width: 100%;}
        label { font-size: 0.85rem; color: #b0b0b0; }
        .back-link { display: block; text-align: center; margin-top: 15px; color: #888; text-decoration: none; font-size: 0.9rem;}
    </style>
    <link href="https://cdn.jsdelivr.net/npm/tom-select/dist/css/tom-select.css" rel="stylesheet">
</head>
<body>
    <div class="card">
        <h2>📦 Upload em Lote</h2>
        <form method="POST" enctype="multipart/form-data">
            <label>Selecione as Notas Fiscais (Múltiplos PDFs):</label>
            <input type="file" name="arquivos_lote[]" accept="application/pdf" multiple required>
            
            <label>Gestor Assinante (Para todos os arquivos):</label>
            <select name="assinante_id" required>
                <option value="">-- Escolha quem vai assinar --</option>
                <?php foreach($usuarios_sistema as $user): ?>
                    <option value="<?= $user['id'] ?>"><?= htmlspecialchars($user['nome']) ?></option>
                <?php endforeach; ?>
            </select>

            <label>Notificar Setores (Matriz de Comunicação):</label>
            <select name="notificar_emails[]" id="select-emails" multiple></select>

            <label>Categoria:</label>
            <select name="categoria">
                <?php foreach($CATEGORIAS_LIST as $k => $v) echo "<option value='$k'>$v</option>"; ?>
            </select>
            
            <button type="submit" class="btn-submit">Enviar Lote para o Gestor</button>
            <a href="painel.php" class="back-link">Voltar ao Painel</a>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/tom-select/dist/js/tom-select.complete.min.js"></script>
    <script>
        new TomSelect('#select-emails', {
            plugins: ['remove_button'],
            valueField: 'id',
            labelField: 'text',
            searchField: 'text',
            load: function(query, callback) {
                var url = 'buscar_contatos.php?q=' + encodeURIComponent(query);
                fetch(url).then(response => response.json()).then(json => { callback(json); }).catch(()=>{ callback(); });
            },
            placeholder: "Busque setores ou e-mails..."
        });
    </script>
</body>
</html>
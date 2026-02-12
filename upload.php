<?php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'db.php'; 
require_once 'notificar.php'; // Importante para carregar a função de alerta

if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit;
}

$validador_logado_id = $_SESSION['usuario_id'];
$mensagem = "";
$sucesso_js = false; // Flag para disparar o alerta

$usuarios_sistema = $conn->query("SELECT id, nome FROM usuarios ORDER BY nome ASC")->fetch_all(MYSQLI_ASSOC);

$CATEGORIAS_LIST = [
    'NF_USO_CONSUMO' => 'NF Uso e Consumo',
    'NF_MANUTENCAO_PREDIAL' => 'NF Manutenção Predial', 
    'NF_OBRAS' => 'NF Obras'
];

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['pdf_upload'])) {
    $arquivo = $_FILES['pdf_upload'];
    $nome_arquivo_original = $arquivo['name'];
    $categoria_selecionada = $_POST['categoria'] ?? 'GERAL';
    $assinante_escolhido_id = $_POST['assinante_id']; 
    $emails_notificacao = $_POST['notificar_emails'] ?? '';
    $data_upload_mysql = date('Y-m-d H:i:s');
    $nome_final = time() . "_" . preg_replace("/[^a-zA-Z0-9.]/", "_", $nome_arquivo_original);
    
    if ($arquivo['error'] === 0 && $arquivo['type'] === 'application/pdf') {
        $diretorio_destino = __DIR__ . DIRECTORY_SEPARATOR . 'arquivos' . DIRECTORY_SEPARATOR;
        if (!is_dir($diretorio_destino)) mkdir($diretorio_destino, 0777, true);
        $caminho_completo = $diretorio_destino . $nome_final;
        
        $conn->begin_transaction(); 
        try {
            if (!move_uploaded_file($arquivo['tmp_name'], $caminho_completo)) {
                throw new Exception("Falha ao mover arquivo.");
            }

            $sql_doc = "INSERT INTO documentos (nome_arquivo, caminho_original, validador_fk, status, data_upload, categoria, notificar_emails) VALUES (?, ?, ?, 'PENDENTE', ?, ?, ?)";
            $stmt_doc = $conn->prepare($sql_doc);
            $stmt_doc->bind_param("ssisss", 
                $nome_arquivo_original, 
                $caminho_completo, 
                $validador_logado_id, 
                $data_upload_mysql, 
                $categoria_selecionada, 
                $emails_notificacao
            );
            $stmt_doc->execute();
            $novo_doc_id = $conn->insert_id;

            $sql_wf = "INSERT INTO workflow_etapas (doc_fk, validador_fk, ordem, status_etapa) VALUES (?, ?, 1, 'PENDENTE')";
            $stmt_wf = $conn->prepare($sql_wf);
            $stmt_wf->bind_param("ii", $novo_doc_id, $assinante_escolhido_id); 
            $stmt_wf->execute();
            
            $conn->commit(); 

            // --- NOVO: ALERTA AUTOMÁTICO AO GESTOR SELECIONADO ---
            $stmt_g = $conn->prepare("SELECT nome, email FROM usuarios WHERE id = ?");
            $stmt_g->bind_param("i", $assinante_escolhido_id);
            $stmt_g->execute();
            $res_gestor = $stmt_g->get_result()->fetch_assoc();

            if ($res_gestor && !empty($res_gestor['email'])) {
                enviar_alerta_pendencia_gestor(
                    $res_gestor['email'], 
                    $res_gestor['nome'], 
                    $novo_doc_id, 
                    $nome_arquivo_original, 
                    $_SESSION['usuario_nome'] ?? 'Remetente'
                );
            }
            // ---------------------------------------------------
            
            // Em vez de redirecionar direto pelo PHP, avisamos o JS que deu certo
            $sucesso_js = true; 

        } catch (Exception $e) {
            $conn->rollback(); 
            $mensagem = "Erro: " . $e->getMessage();
        }
    } else {
        $mensagem = "Arquivo inválido. Certifique-se de que é um PDF.";
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>SVD - Novo Upload</title>
    <style>
        body { font-family: sans-serif; background: #121212; color: #eee; display: flex; justify-content: center; align-items: center; min-height: 100vh; }
        .card { background: #1e1e1e; padding: 2rem; border-radius: 10px; border: 1px solid #333; width: 450px; }
        input, select, textarea { width: 100%; padding: 10px; margin: 10px 0; background: #2c2c2c; border: 1px solid #444; color: #fff; border-radius: 5px; box-sizing: border-box; }
        input[type="submit"] { background: #66bb6a; color: #000; font-weight: bold; cursor: pointer; border: none; margin-top: 15px; }
        label { font-size: 0.85rem; color: #b0b0b0; }
    </style>
</head>
<body>
    <div class="card">
        <h2>📄 Novo Upload</h2>
        
        <?php if($mensagem) echo "<p style='color:#ff5252; background:rgba(255,82,82,0.1); padding:10px; border-radius:5px;'>$mensagem</p>"; ?>

        <form id="uploadForm" method="POST" enctype="multipart/form-data" onsubmit="btnSubmit.disabled=true; btnSubmit.value='Enviando...';">
            <label>Nota Fiscal (PDF):</label>
            <input type="file" name="pdf_upload" accept="application/pdf" required>
            
            <label>Assinante (Gestor):</label>
            <select name="assinante_id" required>
                <option value="">-- Escolha quem vai assinar --</option>
                <?php foreach($usuarios_sistema as $user): ?>
                    <option value="<?= $user['id'] ?>"><?= htmlspecialchars($user['nome']) ?></option>
                <?php endforeach; ?>
            </select>

            <label>E-mails para notificar após assinatura:</label>
            <textarea name="notificar_emails" rows="2" placeholder="fiscal@empresa.com, financeiro@empresa.com"></textarea>

            <label>Categoria:</label>
            <select name="categoria">
                <?php foreach($CATEGORIAS_LIST as $k => $v) echo "<option value='$k'>$v</option>"; ?>
            </select>
            
            <input type="submit" name="btnSubmit" value="Enviar para o Gestor">
        </form>
    </div>

    <script>
        <?php if($sucesso_js): ?>
            alert("✅ NF enviada com sucesso para o gestor!\nVocê poderá acompanhar o status no seu painel.");
            window.location.href = "painel.php";
        <?php endif; ?>
    </script>
</body>
</html>
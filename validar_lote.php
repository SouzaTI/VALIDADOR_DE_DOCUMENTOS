<?php
session_start();
require_once 'db.php';
require_once 'notificar.php';

// 1. Defesa: Verifica se o cara tá logado e se tem algo para processar
if (!isset($_SESSION['usuario_id'])) { header("Location: login.php"); exit; }

$validador_id = $_SESSION['usuario_id'];
$assinante_real = $_SESSION['usuario_nome'] ?? 'Gestor';
$documentos_ids = $_POST['docs'] ?? []; // Array vindo dos checkboxes do painel

if (empty($documentos_ids)) {
    echo "<script>alert('Nenhum documento selecionado!'); window.location.href='painel.php';</script>";
    exit;
}

// Se o PIN ainda não foi digitado, mostramos o formulário de confirmação em lote
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
?>
    <!DOCTYPE html>
    <html lang="pt-BR">
    <head>
        <meta charset="UTF-8">
        <title>SVD - Assinatura em Lote</title>
        <style>
            body { font-family: sans-serif; background: #121212; color: #eee; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
            .card { background: #1e1e1e; padding: 30px; border-radius: 12px; border: 1px solid #333; width: 400px; text-align: center; }
            input, button { width: 100%; padding: 12px; margin: 10px 0; border-radius: 6px; border: 1px solid #444; box-sizing: border-box; }
            input { background: #2c2c2c; color: #fff; }
            button { background: #66bb6a; color: #000; font-weight: bold; cursor: pointer; border: none; }
            .info { background: #252525; padding: 10px; border-radius: 6px; margin-bottom: 20px; border-left: 4px solid #2196F3; font-size: 0.9rem; }
        </style>
    </head>
    <body>
        <div class="card">
            <h2>🖋️ Assinar em Lote</h2>
            <div class="info">
                Você selecionou <strong><?php echo count($documentos_ids); ?></strong> documentos para assinar de uma vez.
            </div>
            <form method="POST">
                <?php foreach($documentos_ids as $id): ?>
                    <input type="hidden" name="docs[]" value="<?php echo $id; ?>">
                <?php endforeach; ?>
                
                <label>Digite seu PIN de Transação:</label>
                <input type="password" name="pin" required autofocus>
                
                <button type="submit">Confirmar Assinatura Digital</button>
                <a href="painel.php" style="color: #bbb; text-decoration: none; font-size: 0.8rem;">Cancelar e voltar</a>
            </form>
        </div>
    </body>
    </html>
<?php
    exit;
}

// 2. Processamento do Lote (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pin'])) {
    $pin_digitado = $_POST['pin'];
    
    // Valida o PIN uma única vez antes de entrar no loop
    $stmt_pin = $conn->prepare("SELECT pin_validacao FROM usuarios WHERE id = ?");
    $stmt_pin->bind_param("i", $validador_id);
    $stmt_pin->execute();
    $stmt_pin->bind_result($pin_hash_banco);
    $stmt_pin->fetch();
    $stmt_pin->close();

    if (!password_verify($pin_digitado, $pin_hash_banco)) {
        echo "<script>alert('PIN Inválido! O lote foi cancelado.'); window.location.href='painel.php';</script>";
        exit;
    }

    $sucessos = 0;
    $erros = 0;

    // Coordenadas padrão para lote (Canto inferior direito)
    $pos_x = 350;
    $pos_y = 50;

    foreach ($documentos_ids as $doc_id) {
        // Busca dados necessários de cada documento no loop
        $stmt = $conn->prepare("SELECT d.nome_arquivo, d.notificar_emails, w.ordem FROM documentos d JOIN workflow_etapas w ON d.id = w.doc_fk WHERE d.id = ? AND w.validador_fk = ? AND w.status_etapa = 'PENDENTE'");
        $stmt->bind_param("ii", $doc_id, $validador_id);
        $stmt->execute();
        $stmt->bind_result($nome_arquivo, $notificar_emails, $ordem_atual);
        
        if ($stmt->fetch()) {
            $stmt->close();

            // Chama a API Python
            $url_api = 'http://127.0.0.1:8050/api/carimbar';
            $data_api = [
                'documento_id' => $doc_id,
                'validador_id' => $validador_id,
                'pin_digitado' => $pin_digitado,
                'acao' => 'VALIDAR',
                'motivo' => '',
                'ordem_etapa' => $ordem_atual,
                'x' => $pos_x,
                'y' => $pos_y,
                'user_agent' => $_SERVER['HTTP_USER_AGENT'],
                'hostname' => gethostbyaddr($_SERVER['REMOTE_ADDR'])
            ];

            $ch = curl_init($url_api);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data_api));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            $res_api = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $res = json_decode($res_api, true);
            curl_close($ch);

            if ($http_code === 200 && isset($res['status']) && $res['status'] === 'sucesso') {
                // Atualiza banco de dados
                $conn->query("UPDATE workflow_etapas SET status_etapa = 'VALIDADO', data_conclusao = NOW() WHERE doc_fk = $doc_id AND validador_fk = $validador_id");
                $conn->query("UPDATE documentos SET status = 'VALIDADO' WHERE id = $doc_id");

                // Dispara notificações da Matriz de Comunicação
                if (!empty($notificar_emails)) {
                    $lista = explode(',', $notificar_emails);
                    foreach ($lista as $email) {
                        enviar_notificacao_email(trim($email), $doc_id, $res['caminho'], $assinante_real, $nome_arquivo);
                    }
                }
                $sucessos++;
            } else {
                $erros++;
            }
        } else {
            $stmt->close();
        }
    }

    echo "<script>alert('Lote processado! Sucessos: $sucessos | Erros: $erros'); window.location.href='painel.php';</script>";
}
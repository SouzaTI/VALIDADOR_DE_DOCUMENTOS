<?php
session_start();
require_once 'db.php';
require_once 'notificar.php';

if (!isset($_SESSION['usuario_id'])) { header("Location: login.php"); exit; }

$validador_id = $_SESSION['usuario_id'];
// Captura o nome do assinante da sessão para o e-mail
$assinante_real = $_SESSION['usuario_nome'] ?? 'Validador Identificado'; 

$documento_id = $_GET['doc_id'] ?? die('ID ausente.');

$stmt = $conn->prepare("SELECT d.nome_arquivo, d.caminho_original, d.caminho_carimbado, d.notificar_emails, w.ordem FROM documentos d JOIN workflow_etapas w ON d.id = w.doc_fk WHERE d.id = ? AND w.validador_fk = ? AND w.status_etapa = 'PENDENTE'");
$stmt->bind_param("ii", $documento_id, $validador_id);
$stmt->execute();
$stmt->bind_result($nome_arquivo, $caminho_orig, $caminho_carim, $notificar_emails, $ordem_atual);
if (!$stmt->fetch()) { die("Documento não encontrado ou já processado."); }
$stmt->close();

$url_pdf = "arquivos/" . basename(!empty($caminho_carim) ? $caminho_carim : $caminho_orig);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'];
    $pin_digitado = $_POST['pin'];
    $pagina = $_POST['pagina'] ?? 1;
    $x = $_POST['pos_x'] ?? 400; 
    $y = $_POST['pos_y'] ?? 50;

    $user_agent = $_SERVER['HTTP_USER_AGENT'];
    $hostname = gethostbyaddr($_SERVER['REMOTE_ADDR']);

    $url_api = 'http://127.0.0.1:8050/api/carimbar';
    $data_api = [
        'documento_id' => $documento_id,
        'validador_id' => $validador_id,
        'pin_digitado' => $pin_digitado,
        'acao' => $acao,
        'ordem_etapa' => $ordem_atual,
        'pagina' => $pagina,
        'x' => $x,
        'y' => $y,
        'user_agent' => $user_agent,
        'hostname' => $hostname
    ];

    $ch = curl_init($url_api);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data_api));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    $res_api = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE); 
    $res = json_decode($res_api, true);
    curl_close($ch);

    if ($http_code === 200 && isset($res['status']) && $res['status'] === 'sucesso') {
        $conn->begin_transaction();
        try {
            // Define o status final com base na ação do botão (VALIDAR ou REJEITAR)
            $status_final = ($acao === 'VALIDAR') ? 'VALIDADO' : 'REJEITADO';

            // 1. Atualiza a etapa do workflow
            $stmt_wf = $conn->prepare("UPDATE workflow_etapas SET status_etapa = ?, data_conclusao = NOW() WHERE doc_fk = ? AND validador_fk = ?");
            $stmt_wf->bind_param("sii", $status_final, $documento_id, $validador_id);
            $stmt_wf->execute();

            // 2. Atualiza o status geral do documento
            $stmt_doc = $conn->prepare("UPDATE documentos SET status = ? WHERE id = ?");
            $stmt_doc->bind_param("si", $status_final, $documento_id);
            $stmt_doc->execute();

            $conn->commit();

            if ($acao === 'VALIDAR') {
                $caminho_final = $res['caminho']; 
                if (!empty($notificar_emails)) {
                    $lista = explode(',', $notificar_emails);
                    foreach ($lista as $email) {
                        $email_limpo = trim($email);
                        if (filter_var($email_limpo, FILTER_VALIDATE_EMAIL)) {
                            try {
                                enviar_notificacao_email($email_limpo, $documento_id, $caminho_final, $assinante_real, $nome_arquivo);
                            } catch (Exception $e_mail) {
                                write_log("Erro e-mail $email_limpo: " . $e_mail->getMessage(), 'email_erro.log');
                            }
                        }
                    }
                }
                echo "<script>alert('Documento Validado com Sucesso!'); window.location.href='painel.php';</script>";
            } else {
                // CORREÇÃO: Busca robusta de quem fez o upload (Autor)
                $stmt_autor = $conn->prepare("SELECT u.email, u.nome FROM usuarios u JOIN documentos d ON u.id = d.validador_fk WHERE d.id = ?");
                $stmt_autor->bind_param("i", $documento_id);
                $stmt_autor->execute();
                $res_autor = $stmt_autor->get_result()->fetch_assoc();

                if($res_autor && !empty($res_autor['email'])) {
                    // Aqui garantimos o envio para o e-mail do autor (Matheus) 
                    // O $assinante_real (Jefferson) aparece apenas como o validador que recusou
                    enviar_notificacao_email_rejeicao(
                        $res_autor['email'], 
                        $res_autor['nome'], 
                        $documento_id, 
                        $assinante_real, 
                        "O documento não foi aprovado pelo gestor e precisa de correções."
                    );
                }
                echo "<script>alert('Documento REJEITADO com sucesso! O autor foi notificado.'); window.location.href='painel.php';</script>";
            }
            exit;

        } catch (Exception $e) { 
            $conn->rollback(); 
            die("Erro banco: " . $e->getMessage());
        }
    } else {
        $msg = isset($res['mensagem']) ? $res['mensagem'] : "Erro na API (HTTP $http_code)";
        echo "<script>alert('Erro: " . addslashes($msg) . "');</script>";
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>SVD - Assinar Documento</title>
    <style>
        body { font-family: sans-serif; background: #121212; color: #eee; margin: 0; display: flex; height: 100vh; overflow: hidden; }
        .visualizador { flex: 2; background: #333; position: relative; overflow-y: auto; }
        .formulario { flex: 1; padding: 25px; background: #1e1e1e; overflow-y: auto; border-left: 2px solid #333; z-index: 100; }
        
        /* Ajuste do iframe para permitir rolagem interna ou externa */
        #pdf-frame { width: 100%; height: 100%; border: none; }
        
        input, select, button { width: 100%; padding: 12px; margin: 8px 0; border-radius: 6px; border: 1px solid #444; background: #2c2c2c; color: #fff; box-sizing: border-box; }
        .grid-posicao { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 10px; }
        button { background: #66bb6a; color: #000; font-weight: bold; cursor: pointer; border: none; font-size: 1rem; }
        
        /* Overlay agora é ativado por um botão para não travar o scroll */
        #capa-clique { position: absolute; top: 0; left: 0; width: 100%; height: 2000px; z-index: 99; cursor: crosshair; display: none; }
        #marcador-carimbo { 
            position: absolute; 
            /* 210px é o equivalente a 35% de uma página A4 padrão (595pts) */
            width: 210px; 
            height: 60px; 
            border: 2px dashed #66bb6a; 
            background: rgba(102, 187, 106, 0.3); 
            pointer-events: none; 
            display: none; 
            z-index: 101; 
            /* O translate -100% faz o carimbo ficar ACIMA do ponto onde você clicou */
            transform: translate(0, -100%); 
        }
                
        .instrucoes { background: #252525; padding: 10px; border-radius: 5px; margin-bottom: 15px; border-left: 4px solid #2196F3; }
    </style>
</head>
<body>
    <div class="visualizador" id="container-pdf">
        <div id="capa-clique"></div>
        <div id="marcador-carimbo"></div>
        <iframe id="pdf-frame" src="<?php echo $url_pdf; ?>#toolbar=1&navpanes=0"></iframe>
    </div>
    
    <div class="formulario">
        <h2>Assinar NF: <?php echo htmlspecialchars($nome_arquivo); ?></h2>
        
        <div class="instrucoes">
            <small>1. Role o PDF e encontre a página.<br>
            2. Clique no botão azul abaixo.<br>
            3. Clique no local do PDF para marcar.</small>
        </div>

        <button type="button" id="btn-marcar" style="background: #2196F3; color: white; margin-bottom: 20px;">🎯 Mapear Posição no Clique</button>

        <form method="POST">
            <label>PIN de Transação:</label>
            <input type="password" name="pin" required>

            <fieldset style="border: 1px solid #444; padding: 10px; border-radius: 8px;">
                <legend style="color: #66bb6a; padding: 0 5px;">📍 Dados da Assinatura</legend>
                <div class="grid-posicao">
                    <div><label>Página</label><input type="number" name="pagina" id="inp_pag" value="1"></div>
                    <div><label>Eixo X</label><input type="number" name="pos_x" id="inp_x" value="350"></div>
                    <div><label>Eixo Y</label><input type="number" name="pos_y" id="inp_y" value="50"></div>
                </div>
            </fieldset>

            <label>Ação Final:</label>
            <select name="acao">
                <option value="VALIDAR">VALIDAR E NOTIFICAR SETORES</option>
                <option value="REJEITAR">REJEITAR</option>
            </select>
            <button type="submit">Confirmar e Finalizar Fluxo</button>
        </form>
    </div>

    <script>
        const capa = document.getElementById('capa-clique');
        const marcador = document.getElementById('marcador-carimbo');
        const btnMarcar = document.getElementById('btn-marcar');
        const inpX = document.getElementById('inp_x');
        const inpY = document.getElementById('inp_y');
        const container = document.getElementById('container-pdf');

        // Ativa o modo de seleção de clique
        btnMarcar.addEventListener('click', function() {
            capa.style.display = 'block';
            btnMarcar.innerText = '📌 Clique agora no PDF...';
            btnMarcar.style.background = '#ff9800';
        });

        capa.addEventListener('click', function(e) {
            const rect = container.getBoundingClientRect();
            
            // Posição do clique relativa ao container que tem o scroll
            const x_px = e.clientX - rect.left;
            const y_px = e.clientY - rect.top;

            // Tradução para pontos PDF
            const x_pdf = Math.round((x_px / rect.width) * 595);
            // O Y no PDF conta de baixo para cima. 
            // Como não sabemos a altura exata de todas as páginas juntas, 
            // estimamos pela altura visual do container.
            const y_pdf = Math.round((1 - (y_px / rect.height)) * 842);

            inpX.value = x_pdf;
            inpY.value = y_pdf;

            // UI feedback
            marcador.style.display = 'block';
            marcador.style.left = x_px + 'px';
            marcador.style.top = y_px + 'px';
            
            // Desativa a capa para permitir scroll novamente
            capa.style.display = 'none';
            btnMarcar.innerText = '🎯 Mapear Posição no Clique';
            btnMarcar.style.background = '#2196F3';
        });
    </script>
</body>
</html>
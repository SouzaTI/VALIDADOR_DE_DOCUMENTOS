<?php
// Arquivo: validar.php - DARK MODE E VISUALIZAÇÃO LADO A LADO COM LÓGICA DE WORKFLOW COMPLETA

session_start();
// Se não estiver logado, redireciona para o login
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit;
}

// Inclui as dependências criadas
require_once 'log.php'; 
require_once 'notificar.php'; // Mock de notificação

// --- CONFIGURAÇÃO DE CONEXÃO E API ---
$host = '127.0.0.1';
$port = 3307; 
$db = 'db_svd';
$user = 'root';
$pass = '';
$url_api_carimbo = 'http://127.0.0.1:5000/api/carimbar'; // URL da sua API Flask (Python)
$url_base = 'http://192.168.0.63:8080/validador_documentos'; // Ajuste conforme sua URL base do XAMPP

$conn = new mysqli($host, $user, $pass, $db, $port);
if ($conn->connect_error) {
    die("Falha na Conexão com o Banco de Dados: " . $conn->connect_error);
}

// 1. DADOS DE WORKFLOW (Dinâmicos)
$validador_id = $_SESSION['usuario_id']; // ID do usuário LOGADO
$documento_id = $_GET['doc_id'] ?? die('ID do documento não especificado. Use o painel.'); 
$nome_validador_doc = $_SESSION['usuario_nome'] ?? 'Validador Desconhecido';

$erro = '';
$mensagem_sucesso = '';
$resultado_api = null; // Para guardar a resposta da API Python

// 2. BUSCA O CAMINHO E DADOS DO DOCUMENTO E ETAPA ATUAL
$caminho_doc = '';
$nome_arquivo_doc = 'Não Encontrado';
$ordem_etapa_atual = 0;

// Busca informações do documento e da etapa PENDENTE atual
$sql_info = "SELECT 
                d.nome_arquivo, 
                d.caminho_original, 
                d.caminho_carimbado,
                w.ordem,
                u.pin_validacao
            FROM Documentos d
            JOIN Workflow_Etapas w ON d.id = w.doc_fk
            JOIN Usuarios u ON w.validador_fk = u.id
            WHERE d.id = ? 
            AND w.validador_fk = ?
            AND w.status_etapa = 'PENDENTE'";

$stmt_info = $conn->prepare($sql_info);
$stmt_info->bind_param("ii", $documento_id, $validador_id);
$stmt_info->execute();
$stmt_info->bind_result($nome_arquivo_doc, $caminho_original, $caminho_carimbado, $ordem_etapa_atual, $pin_hash_validador);
$stmt_info->fetch();
$stmt_info->close();

// Define o caminho para a pré-visualização. Se já foi carimbado, usa o carimbado (mais recente).
$caminho_doc_visualizacao = empty($caminho_carimbado) ? $caminho_original : $caminho_carimbado;

// Verifica se o validador logado é o validador da etapa atual PENDENTE
if ($ordem_etapa_atual == 0) {
    $erro = "Você não é o validador responsável ou esta etapa já foi concluída/rejeitada.";
}

// ----------------------------------------------------------------------
// --- LÓGICA DE PROCESSAMENTO (CHAMADA DA API) ---
// ----------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($erro)) {
    $pin_digitado = $_POST['pin'] ?? '';
    $acao = $_POST['acao'] ?? '';

    $comentario_rejeicao = ($acao === 'REJEITAR') ? ($_POST['comentario_rejeicao'] ?? '') : '';

    // Validação de Comentário (Se REJEITAR, mas o comentário está vazio)
    if ($acao === 'REJEITAR' && empty($comentario_rejeicao)) {
         $erro = "O comentário de rejeição é obrigatório para a ação REJEITAR.";
    }

    
    if (empty($erro)) { // Continua se não houver erro de comentário
    
        // 1. Preparar dados para a API
        $data_api = [
            'documento_id' => $documento_id,
            'validador_id' => $validador_id,
            'pin_digitado' => $pin_digitado,
            'acao' => $acao,
            'ordem_etapa' => $ordem_etapa_atual, // Ordem atual
            'comentario' => $comentario_rejeicao // FIX: Comentário adicionado
        ];

        // 2. Chamada da API Python (POST)
        $ch = curl_init($url_api_carimbo);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data_api));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Content-Length: ' . strlen(json_encode($data_api))
        ]);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $resultado_api = json_decode($response, true);
        
        // 3. Processamento da Resposta da API
        if ($resultado_api && $resultado_api['status'] === 'sucesso') {
            
            $conn->begin_transaction(); // Inicia transação para segurança no DB
            try {
                
                // --- A. ATUALIZA STATUS DA ETAPA ATUAL (WORKFLOW_ETAPAS) ---
                $sql_update_wf_atual = "UPDATE Workflow_Etapas 
                                        SET status_etapa = ?, 
                                        data_conclusao = NOW() 
                                        WHERE doc_fk = ? 
                                        AND validador_fk = ? 
                                        AND ordem = ?";
                
                $status_final_etapa = ($acao === 'VALIDAR') ? 'VALIDADO' : 'REJEITADO';
                $stmt_wf_atual = $conn->prepare($sql_update_wf_atual);
                $stmt_wf_atual->bind_param("siii", $status_final_etapa, $documento_id, $validador_id, $ordem_etapa_atual);
                $stmt_wf_atual->execute();
                $stmt_wf_atual->close();

                // --- B. LÓGICA DE WORKFLOW SEQUENCIAL ---
                if ($acao === 'VALIDAR') {
                    
                    // 1. Tenta encontrar a próxima etapa (ordem + 1)
                    $proxima_ordem = $ordem_etapa_atual + 1;
                    $sql_proxima = "SELECT validador_fk, u.email, u.nome 
                                    FROM Workflow_Etapas w 
                                    JOIN Usuarios u ON w.validador_fk = u.id
                                    WHERE doc_fk = ? AND ordem = ?";
                    $stmt_proxima = $conn->prepare($sql_proxima);
                    $stmt_proxima->bind_param("ii", $documento_id, $proxima_ordem);
                    $stmt_proxima->execute();
                    $resultado_proxima = $stmt_proxima->get_result();
                    $proxima_etapa = $resultado_proxima->fetch_assoc();
                    $stmt_proxima->close();

                    if ($proxima_etapa) {


                        // 2. Existe próxima etapa: Mudar status para PENDENTE e Notificar
                        $sql_update_proxima = "UPDATE Workflow_Etapas 
                                               SET status_etapa = 'PENDENTE' 
                                               WHERE doc_fk = ? AND ordem = ?";
                        $stmt_update_proxima = $conn->prepare($sql_update_proxima);
                        $stmt_update_proxima->bind_param("ii", $documento_id, $proxima_ordem);
                        $stmt_update_proxima->execute();
                        $stmt_update_proxima->close();
                        
                        
                        write_log("Workflow Etapa {$proxima_ordem} iniciada para {$proxima_etapa['nome']}.", 'workflow.log');

                        $mensagem_sucesso = "✅ Documento validado (Etapa {$ordem_etapa_atual}) e **próxima etapa iniciada** para {$proxima_etapa['nome']}!";

                        // Armazena dados para notificação PÓS-COMMIT
                        $dados_proxima_notificacao = [
                            'email' => $proxima_etapa['email'],
                            'nome' => $proxima_etapa['nome'],
                            'doc_id' => $documento_id
                        ];

                    } else {
                        // 3. Não existe próxima etapa: FINALIZAR O DOCUMENTO
                        $sql_finalizar = "UPDATE Documentos SET status = 'VALIDADO' WHERE id = ?";
                        $stmt_finalizar = $conn->prepare($sql_finalizar);
                        $stmt_finalizar->bind_param("i", $documento_id);
                        $stmt_finalizar->execute();
                        $stmt_finalizar->close();
                        
                        write_log("Documento {$documento_id} **FINALIZADO** (VALIDADO).", 'workflow.log');
                        $mensagem_sucesso = "🎉 Documento validado (Etapa {$ordem_etapa_atual}) e **FINALIZADO**! Workflow concluído.";
                    }

                } elseif ($acao === 'REJEITAR') {
                    // --- C. LÓGICA DE REJEIÇÃO PROFISSIONAL (Rollback para Autor) ---
                    
                    // 1. Atualiza o status do Documento Principal para PENDENTE
                    $sql_finalizar = "UPDATE Documentos SET status = 'PENDENTE' WHERE id = ?";
                    $stmt_finalizar = $conn->prepare($sql_finalizar);
                    $stmt_finalizar->bind_param("i", $documento_id);
                    $stmt_finalizar->execute();
                    $stmt_finalizar->close();
                    
                    // 2. Marca todas as etapas restantes como CANCELADAS
                    $sql_cancelar = "UPDATE Workflow_Etapas 
                                     SET status_etapa = 'CANCELADO', data_conclusao = NOW()
                                     WHERE doc_fk = ? 
                                     AND ordem > ?";
                    $stmt_cancelar = $conn->prepare($sql_cancelar);
                    $stmt_cancelar->bind_param("ii", $documento_id, $ordem_etapa_atual);
                    $stmt_cancelar->execute();
                    $stmt_cancelar->close();

                    // 3. Busca o Autor Original (o validador_fk da tabela Documentos é o autor)
                    $sql_autor = "SELECT u.email, u.nome, d.validador_fk FROM Documentos d JOIN Usuarios u ON d.validador_fk = u.id WHERE d.id = ?";
                    $stmt_autor = $conn->prepare($sql_autor);
                    $stmt_autor->bind_param("i", $documento_id);
                    $stmt_autor->execute();
                    $resultado_autor = $stmt_autor->get_result();
                    $autor_doc = $resultado_autor->fetch_assoc();
                    $stmt_autor->close();

                    // 4. Armazenar dados do Autor para notificar PÓS-COMMIT
                    $dados_notificacao_autor = [
                        'email' => $autor_doc['email'],
                        'nome' => $autor_doc['nome'],
                        'doc_id' => $documento_id,
                        'validador_nome' => $_SESSION['usuario_nome'] ?? $validador_id,
                        'motivo' => $comentario_rejeicao
                    ];
                    
                    write_log("Documento {$documento_id} **REJEITADO (PENDENTE)** na Etapa {$ordem_etapa_atual}. Motivo: '{$comentario_rejeicao}'", 'workflow.log');
                    $mensagem_sucesso = "❌ Documento rejeitado e enviado para correção do autor. Motivo: {$comentario_rejeicao}";
                }

                $conn->commit(); // Confirma as alterações no banco

                // --- NOTIFICAÇÃO PÓS-COMMIT (FIX 31) ---
                if ($acao === 'VALIDAR' && isset($dados_proxima_notificacao)) {
                    enviar_notificacao_email($dados_proxima_notificacao['email'], $dados_proxima_notificacao['nome'], $dados_proxima_notificacao['doc_id']);
                } elseif ($acao === 'REJEITAR' && isset($dados_notificacao_autor)) { // CORRIGIDO: Agora é um elseif válido
                    enviar_notificacao_email_rejeicao(
                        $dados_notificacao_autor['email'], 
                        $dados_notificacao_autor['nome'], 
                        $dados_notificacao_autor['doc_id'],
                        $dados_notificacao_autor['validador_nome'],
                        $dados_notificacao_autor['motivo']
                    );
                }

                // 1. Armazena a mensagem de sucesso na sessão para ser exibida no painel.php
                $_SESSION['validation_success'] = $mensagem_sucesso; 
                
                // 2. Redireciona para o painel
                header("Location: painel.php");
                exit; // Interrompe o script para garantir o redirecionamento
                
                // Se foi VALIDAR, atualiza o caminho de visualização para o carimbado
                if ($acao === 'VALIDAR' && isset($resultado_api['caminho_final'])) {
                    $caminho_doc_visualizacao = $resultado_api['caminho_final'];
                }

            } catch (Exception $e) {
                $conn->rollback(); // Desfaz todas as alterações em caso de erro
                $erro = "Erro de transação no banco de dados: " . $e->getMessage();
                write_log("Erro de transação no validar.php: {$erro}", 'erro.log');
            }

        } else {
            // Erro da API (PIN Inválido, Documento não encontrado, etc.)
            $erro = "Falha na validação (API): " . ($resultado_api['mensagem'] ?? "Erro HTTP code: {$http_code}");
        }
    } // FECHA O BLOCO IF(EMPTY($ERRO)) QUE CONTROLA A CHAMADA DA API
}

// ----------------------------------------------------------------------
// --- CÓDIGO HTML (VISUALIZAÇÃO) ---
// ----------------------------------------------------------------------
// Função auxiliar para converter o caminho físico em URL (ajustado para Windows)
function caminho_para_url($caminho_fisico, $url_base) {
    // 1. Padroniza separadores de diretório para barras (Windows para Web)
    $caminho_relativo = str_replace('\\', '/', $caminho_fisico);
    
    // 2. Garante que a URL base não termine com barra
    $url_base = rtrim($url_base, '/');

    // 3. Encontra o início do caminho Web (a partir da pasta 'validador_documentos')
    $pos = strpos($caminho_relativo, 'validador_documentos/'); 
    
    if ($pos !== false) {
        // Extrai o caminho que começa em 'arquivos/...'
        $pos_arquivos = $pos + strlen('validador_documentos/');
        $caminho_http_parcial_bruto = substr($caminho_relativo, $pos_arquivos); 
        
        // 4. Separa diretório e nome do arquivo
        $diretorio = dirname($caminho_http_parcial_bruto);
        $nome_arquivo = basename($caminho_http_parcial_bruto);
        
        // 5. Codifica o nome do arquivo (CRÍTICO para espaços como '%20')
        $nome_arquivo_codificado = rawurlencode($nome_arquivo);
        
        // 6. Remonta o caminho relativo: arquivos/nome_codificado.pdf
        $caminho_http_parcial = "{$diretorio}/{$nome_arquivo_codificado}";
    } else {
        // Fallback
        return 'erro_caminho'; 
    }
    
    // 7. Monta a URL completa.
    $url_final = "{$url_base}/{$caminho_http_parcial}";
    
    return $url_final;
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>SVD - Validar Documento #<?php echo $documento_id; ?></title>
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
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.5);
            border: 1px solid #333;
        }
        h1, h2 { color: #66bb6a; }
        .grid { 
            display: grid; 
            grid-template-columns: 1fr 300px; 
            gap: 20px; 
            margin-top: 20px;
        }
        .pdf-viewer, .validation-panel {
            background: #2c2c2c;
            padding: 15px;
            border-radius: 4px;
        }
        .pdf-viewer iframe {
            width: 100%; 
            height: 600px; 
            border: none;
        }
        /* Painel de Validação */
        label { display: block; margin-top: 10px; font-weight: bold; color: #81c784; }
        input[type="password"], select { 
            width: 100%;
            padding: 8px;
            margin-top: 5px;
            box-sizing: border-box;
            border-radius: 4px;
            border: 1px solid #444;
            background-color: #333;
            color: #e0e0e0;
        }
        input[type="submit"] {
            margin-top: 20px;
            width: 100%;
            padding: 10px;
            background-color: #007bff;
            color: white;
            font-weight: bold;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        input[type="submit"]:hover {
            background-color: #0056b3;
        }
        .message-box { 
            padding: 15px; 
            border-radius: 4px; 
            margin-bottom: 20px;
            font-weight: bold;
        }
        .error { background: #ff52521a; color: #ff5252; border: 1px solid #ff5252; }
        .success { background: #66bb6a1a; color: #66bb6a; border: 1px solid #66bb6a; }
        .back-link { display: block; margin-top: 20px; text-align: center; color: #81c784; text-decoration: none;}
    </style>
</head>
<body>
    <div class="container">
        <h1>Validação de Documento: <?php echo htmlspecialchars($nome_arquivo_doc); ?></h1>
        <p>Validador Logado: **<?php echo htmlspecialchars($nome_validador_doc); ?>** | Etapa Atual: **<?php echo $ordem_etapa_atual; ?>**</p>

        <?php if (!empty($erro)): ?>
            <div class="message-box error">❌ Erro: <?php echo htmlspecialchars($erro); ?></div>
        <?php endif; ?>

        <?php if (!empty($mensagem_sucesso)): ?>
            <div class="message-box success"><?php echo $mensagem_sucesso; ?></div>
        <?php endif; ?>
        
        <?php if ($ordem_etapa_atual > 0 && empty($erro)): ?>
            <div class="grid">
                <div class="pdf-viewer">
                    <h2>Pré-visualização (PDF Mais Recente)</h2>
                    <?php
                    // Cria a URL para o iframe
                    $url_visualizacao = caminho_para_url($caminho_doc_visualizacao, $url_base);
                    ?>
                    <iframe src="<?php echo htmlspecialchars($url_visualizacao); ?>" frameborder="0"></iframe>
                    <p style="font-size: 0.8em; color: #999;">Caminho Físico: `<?php echo htmlspecialchars($caminho_doc_visualizacao); ?>`</p>
                </div>

                <div class="validation-panel">
                    <h2>Executar Ação</h2>
    
                    <form method="POST" action="">
                        <label for="pin">PIN de Transação:</label>
                        <input type="password" id="pin" name="pin" required maxlength="50" placeholder="Digite seu PIN de transação">
                        
                        <label for="acao">Ação:</label>
                        <select id="acao" name="acao">
                            <option value="VALIDAR">VALIDAR (Carimbar)</option>
                            <option value="REJEITAR">REJEITAR (Com Comentário)</option>
                        </select>
                        
                        <div id="comentario-field" style="display:none; margin-top: 15px;">
                            <label for="comentario_rejeicao">Comentário da Rejeição (Obrigatório):</label>
                            <textarea id="comentario_rejeicao" name="comentario_rejeicao" rows="3" maxlength="500" placeholder="Motivo da Rejeição: Seja claro e objetivo."></textarea>
                        </div>
                        <input type="submit" value="Executar Ação e Chamar API">
                    </form>
                </div>
            </div>
        <?php else: ?>
            <p>Não foi possível exibir o painel de validação. Verifique a etapa ou a conexão.</p>
        <?php endif; ?>
        
        <a href="painel.php" class="back-link">← Voltar para o Painel</a>
    </div>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const acaoSelect = document.getElementById('acao');
        const comentarioField = document.getElementById('comentario-field');
        const comentarioTextarea = document.getElementById('comentario_rejeicao');
        
        function toggleComentario() {
            if (acaoSelect.value === 'REJEITAR') {
                comentarioField.style.display = 'block';
                comentarioTextarea.setAttribute('required', 'required'); // Torna obrigatório
            } else {
                comentarioField.style.display = 'none';
                comentarioTextarea.removeAttribute('required'); // Remove obrigatório
            }
        }

        // Inicializa no carregamento e adiciona listener
        toggleComentario();
        acaoSelect.addEventListener('change', toggleComentario);
    });
</script>
</body>
</html>
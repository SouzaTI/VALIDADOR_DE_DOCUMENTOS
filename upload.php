<?php
// Arquivo: upload.php - VERSÃO FINAL COM WORKFLOW E RENOMEAÇÃO (Dark Mode)

session_start(); // OBRIGATÓRIO para usar $_SESSION

$host = '127.0.0.1';
$port = 3307; // CONFIRME SUA PORTA!
$db = 'db_svd';
$user = 'root';
$pass = '';

require 'log.php';
require 'notificar.php';

$conn = new mysqli($host, $user, $pass, $db, $port);
if ($conn->connect_error) {
    die("Falha na Conexão com o Banco de Dados: " . $conn->connect_error);
}

// Verifica se o usuário está logado para saber quem está fazendo o upload
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit;
}
$validador_logado_id = $_SESSION['usuario_id'];
$mensagem = "";

// Busca todos os usuários (exceto o próprio uploader, opcional)
$sql_users = "SELECT id, nome FROM Usuarios ORDER BY nome ASC"; 
$stmt_users = $conn->prepare($sql_users);
$stmt_users->execute();
$validadores_list = $stmt_users->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_users->close();

$CATEGORIAS_LIST = [
    'NF_USO_CONSUMO' => 'NF Uso e Consumo',
    'NF_MANUTENCAO_PREDIAL' => 'NF Manutenção Predial', 
    'NF_OBRAS' => 'NF Obras',
];

$revisa_doc_id = $_GET['revisa_doc_id'] ?? null; // Necessário definir aqui, fora do POST.

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['pdf_upload'])) {
    
    $arquivo = $_FILES['pdf_upload'];
    $nome_arquivo_original = $arquivo['name'];
    $validadores_selecionados = $_POST['validadores'] ?? [];
    $categoria_selecionada = $_POST['categoria'] ?? 'GERAL'; // JÁ ESTÁ CORRETO
    
    // 1. Checagem básica do workflow
    $validadores_efetivos = array_filter($validadores_selecionados, function($id) {
        return !empty($id);
    });

    // 2. Coloca o ID do autor do upload no início do array
    array_unshift($validadores_efetivos, $validador_logado_id);

    // Gerar o timestamp no formato MySQL
    $data_upload_mysql = date('Y-m-d H:i:s');
    
    // Define o ID do documento. Se for revisão, usa o ID do GET.
    $novo_doc_id = $revisa_doc_id; // Se for revisão, já define o ID
    
    // Define o nome do arquivo no disco
    if ($revisa_doc_id) {
        $nome_final = time() . "_REV{$novo_doc_id}_" . $nome_arquivo_original;
    } else {
        $nome_final = time() . "_" . $nome_arquivo_original;
    }
    
    // FIM FIX 60/61
    
    if (empty($validadores_efetivos)) {
        $mensagem = "<span style='color:red;'>Erro: O workflow precisa de pelo menos o autor como revisor.</span>";
    } else if ($arquivo['error'] === 0 && $arquivo['type'] === 'application/pdf') {
        
        $diretorio_destino = __DIR__ . DIRECTORY_SEPARATOR . 'arquivos' . DIRECTORY_SEPARATOR;
        $caminho_completo = $diretorio_destino . $nome_final;
        
        $conn->begin_transaction(); 

        try {
            // 1. Move o arquivo para a pasta 'arquivos'
            if (!move_uploaded_file($arquivo['tmp_name'], $caminho_completo)) {
                throw new Exception("Erro ao mover arquivo para o diretório de destino.");
            }

            // 2. Registra na tabela Documentos (INSERT ou UPDATE)
            $status_inicial = 'EM_FLUXO';

            // START FIX 62: Lógica de INSERT vs. UPDATE (Reenvio)
            if ($revisa_doc_id) {
                // Se for REENVIO (UPDATE): Atualiza caminho, categoria e reseta carimbo
                $sql_doc = "UPDATE Documentos 
                            SET nome_arquivo=?, caminho_original=?, caminho_carimbado=NULL, status=?, data_upload=?, categoria=? 
                            WHERE id=?";
                $stmt_doc = $conn->prepare($sql_doc);
                // Bind Param: sssssi (nome, caminho, status, data, categoria, id)
                $stmt_doc->bind_param("sssssi", 
                    $nome_arquivo_original, 
                    $caminho_completo, 
                    $status_inicial, 
                    $data_upload_mysql,
                    $categoria_selecionada, 
                    $novo_doc_id
                );
            } else {
                // Se for NOVO UPLOAD (INSERT)
                $sql_doc = "INSERT INTO Documentos (nome_arquivo, caminho_original, validador_fk, status, data_upload, categoria) VALUES (?, ?, ?, ?, ?, ?)";
                $stmt_doc = $conn->prepare($sql_doc);
                // Bind Param: ssissi (nome, caminho, validador_fk, status, data, categoria)
                $stmt_doc->bind_param("ssisss", 
                    $nome_arquivo_original, 
                    $caminho_completo, 
                    $validador_logado_id, // INT (i)
                    $status_inicial, 
                    $data_upload_mysql,
                    $categoria_selecionada // STRING (s)
                );
            }
            // FIM FIX 62

            if (!$stmt_doc->execute()) {
                throw new Exception("Erro ao salvar/atualizar no banco (Documentos).");
            }
            
            // Pega o ID (se foi INSERT)
            if (!$revisa_doc_id) {
                $novo_doc_id = $conn->insert_id;
            }
            $stmt_doc->close();

            // 3. INSERÇÃO DO WORKFLOW DINÂMICO
            // START FIX 63: Se for REENVIO, DELETAR o workflow antigo
            if ($revisa_doc_id) {
                 $sql_delete_wf = "DELETE FROM Workflow_Etapas WHERE doc_fk = ?";
                 $stmt_delete_wf = $conn->prepare($sql_delete_wf);
                 $stmt_delete_wf->bind_param("i", $novo_doc_id);
                 $stmt_delete_wf->execute();
                 $stmt_delete_wf->close();
            }
            // FIM FIX 63
            
            $sql_wf = "INSERT INTO Workflow_Etapas (doc_fk, validador_fk, ordem, status_etapa) VALUES (?, ?, ?, ?)";
            $stmt_wf = $conn->prepare($sql_wf);
            
            $ordem = 1;
            $count_etapas = 0;

            foreach ($validadores_efetivos as $validador_id) {
                // A primeira etapa (autor) é PENDENTE, as seguintes são FUTURO
                $status_etapa = ($ordem == 1) ? 'PENDENTE' : 'FUTURO';

                $stmt_wf->bind_param("iiis", $novo_doc_id, $validador_id, $ordem, $status_etapa);
                
                if (!$stmt_wf->execute()) {
                    throw new Exception("Erro ao iniciar workflow (Etapa {$ordem}).");
                }
                $ordem++;
                $count_etapas++;
            }
            $stmt_wf->close();
            
            $conn->commit(); 

            // 1. Busca o e-mail e nome do autor logado
            $sql_autor_email = "SELECT email, nome FROM Usuarios WHERE id = ?";
            $stmt_autor = $conn->prepare($sql_autor_email);
            $stmt_autor->bind_param("i", $validador_logado_id);
            $stmt_autor->execute();
            $autor_data = $stmt_autor->get_result()->fetch_assoc();
            $stmt_autor->close();
            
            // 2. Notifica o autor/validador da Etapa 1
            if ($autor_data) {
                enviar_notificacao_email($autor_data['email'], $autor_data['nome'], $novo_doc_id);
                write_log("Notificação de Etapa 1 enviada para o autor ({$autor_data['email']}).", 'email_log.log');
            }
            
            // --- REDIRECIONAMENTO DE SUCESSO ---
            $mensagem_sucesso = "Documento ID **{$novo_doc_id}** ('{$nome_arquivo_original}') enviado e workflow de **{$count_etapas} etapas** iniciado.";
            
            $_SESSION['upload_success'] = $mensagem_sucesso;
            header("Location: painel.php");
            exit; 
            
        } catch (Exception $e) {
            $conn->rollback(); 
            @unlink($caminho_completo); 
            $mensagem = "<span style='color:red;'>Erro na transação: {$e->getMessage()}</span>";
        }
        
    } else {
        $mensagem = "<span style='color:red;'>Erro no upload ou arquivo não é um PDF válido.</span>";
    }
}
$conn->close();

?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>SVD - Upload de Documentos</title>
    <style>
        /* DARK MODE STYLES */
        body { 
            font-family: Arial, sans-serif; 
            padding: 20px; 
            background-color: #121212; 
            display: flex; 
            justify-content: center; 
            align-items: center; 
            min-height: 100vh; 
            color: #e0e0e0; 
        }
        .container { 
            background: #1e1e1e; 
            padding: 30px; 
            border-radius: 8px; 
            max-width: 500px; 
            width: 100%; 
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.5);
            border: 1px solid #333;
        }
        h1, h3 { color: #66bb6a; margin-top: 20px; }
        label, p { margin-top: 10px; display: block; }
        
        /* Layout de Workflow (para o visual ficar lado a lado) */
        .workflow-container {
            display: grid;
            grid-template-columns: 1fr 2fr; 
            gap: 10px; 
            align-items: center;
            margin-bottom: 5px;
        }
        .workflow-container label {
            display: inline;
            margin-top: 0;
            color: #81c784;
        }
        
        input[type="file"], input[type="submit"], select { 
            width: 100%;
            padding: 10px;
            margin-top: 8px; 
            box-sizing: border-box;
            border-radius: 4px;
            background-color: #2c2c2c;
            color: #e0e0e0;
            border: 1px solid #444;
        }
        input[type="submit"] {
            background-color: #007bff;
            color: white;
            font-weight: bold;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        input[type="submit"]:hover {
            background-color: #0056b3;
        }
        .message { display: block; margin-bottom: 15px; font-weight: bold; padding: 10px; border-radius: 4px; }
        .error { color: #ff5252; background: #ff52521a; }
        .back-link { display: block; margin-top: 20px; text-align: center; color: #81c784; text-decoration: none;}
        .step-hidden { display: none !important; }
    </style>
    </head>
<body>
    <div class="container">
        <h1>Novo Upload de Documento</h1>
        <p>Este documento será vinculado ao seu usuário (ID: <?php echo $validador_logado_id; ?>) como o **Autor**.</p>
        
        <?php if (!empty($mensagem)): ?>
            <p class="message error">❌ <?php echo $mensagem; ?></p>
        <?php endif; ?>

        <form method="POST" action="" enctype="multipart/form-data">
            <label for="pdf_upload">Selecione o arquivo PDF:</label>
            <input type="file" id="pdf_upload" name="pdf_upload" accept="application/pdf" required>

            <label for="categoria">Selecione a Categoria do Documento:</label>
            <select id="categoria" name="categoria" required 
                    style="width: 100%; padding: 10px; margin-top: 8px; box-sizing: border-box; border-radius: 4px; background-color: #2c2c2c; color: #e0e0e0; border: 1px solid #444;">
                
                <?php foreach ($CATEGORIAS_LIST as $key => $label): ?>
                    <option value="<?php echo htmlspecialchars($key); ?>"><?php echo htmlspecialchars($label); ?></option>
                <?php endforeach; ?>
                
            </select>
            
            <p>Este documento será vinculado ao seu usuário (ID: <?php echo $validador_logado_id; ?>) como o **Autor**.</p>

            
            <h3>Definir Workflow de Assinatura</h3>
                <p style="color: #999; font-size: 0.9em;">Selecione os validadores na ordem. Novos campos aparecerão automaticamente.</p>

                <div id="workflow-steps">
                    <?php 
                    // Gera 5 campos de seleção. Aumentamos para 5 para uma UX melhor.
                    for ($i = 1; $i <= 5; $i++): 
                        $required = ($i == 1) ? 'required' : ''; 
                        $label = "Etapa {$i}" . (($i == 1) ? ' (Obrigatória):' : ' (Opcional):');
                        $hidden_class = ($i > 2) ? 'step-hidden' : ''; // Esconde Etapa 3 em diante
                    ?>
                        <div class="workflow-container step-<?php echo $i; ?> <?php echo $hidden_class; ?>">
                            <label for="validador_<?php echo $i; ?>"><?php echo $label; ?></label>
                            <select id="validador_<?php echo $i; ?>" name="validadores[]" <?php echo $required; ?>>
                                <option value="">-- Selecione o <?php echo $i; ?>º Validador --</option>
                                <?php foreach ($validadores_list as $user): ?>
                                    <option value="<?php echo $user['id']; ?>"><?php echo htmlspecialchars($user['nome']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endfor; ?>
                </div>

                <input type="submit" value="Fazer Upload e Iniciar Workflow">
        </form>
        
        <a href="painel.php" class="back-link">Voltar para o Painel</a>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
$(document).ready(function() {
    let max_steps_shown = 2; // Começa mostrando Etapa 1 e 2
    let total_steps = 5;     // Total de campos gerados inicialmente no PHP
    
    // Função que lida com a visibilidade e adiciona novo campo
    function checkAndAddStep(currentStep) {
        const currentSelect = $(`.step-${currentStep} select`);
        const nextStep = currentStep + 1;

        if (currentSelect.val() !== '') {
            // A. Se preenchido, mostra a próxima etapa (se existir no HTML)
            $(`.step-${nextStep}`).removeClass('step-hidden');

            // B. Se for a penúltima etapa gerada, adiciona mais uma dinamicamente
            if (currentStep === total_steps) {
                total_steps++;
                
                const newStepContainer = $(`<div class="workflow-container step-${total_steps} step-hidden"></div>`);
                const newLabel = $(`<label for="validador_${total_steps}">Etapa ${total_steps} (Opcional):</label>`);
                const newSelect = $(`<select id="validador_${total_steps}" name="validadores[]"></select>`);
                
                // Clona as opções de usuário do 1º select
                newSelect.append($('#validador_1 option').clone());
                newSelect.find('option:first').text(`-- Selecione o ${total_steps}º Validador --`);
                
                newStepContainer.append(newLabel, newSelect);
                $('#workflow-steps').append(newStepContainer);

                // Adiciona o listener para o novo campo
                newSelect.on('change', function() {
                    checkAndAddStep(total_steps);
                });
            }
        } else {
            // C. Se o campo for limpo/desselecionado, limpa e oculta os subsequentes
            for (let i = nextStep; i <= total_steps; i++) {
                $(`.step-${i}`).addClass('step-hidden');
                $(`.step-${i} select`).val('');
            }
        }
    }

    // Inicializa listeners para todos os campos gerados
    for (let i = 1; i <= total_steps; i++) {
        $(`.step-${i} select`).on('change', function() {
            checkAndAddStep(i);
        });
    }

    // Oculta Etapa 3 em diante no carregamento (o CSS já faz, mas garante a lógica)
    for (let i = max_steps_shown + 1; i <= total_steps; i++) {
        $(`.step-${i}`).addClass('step-hidden');
    }
});
</script>
    </body>
</html>
    
 
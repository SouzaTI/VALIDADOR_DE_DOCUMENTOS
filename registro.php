<?php
/**
 * SVD - Validador de Documentos
 * Arquivo: registro.php (Versão Administrativa com Níveis e Correção de PIN)
 */

session_start();
require_once 'db.php'; 

// TRAVA DE SEGURANÇA: Só ADMIN acessa. Se não for, volta pro painel.
if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_nivel'] !== 'ADMIN') {
    header("Location: painel.php");
    exit;
}

$mensagem = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome    = trim($_POST['nome'] ?? '');
    $usuario = trim($_POST['usuario'] ?? '');
    $email   = trim($_POST['email'] ?? '');
    $celular = trim($_POST['celular'] ?? '');
    $nivel   = $_POST['nivel'] ?? 'BASIC'; // Captura o nível selecionado

    if (empty($nome) || empty($usuario) || empty($email)) {
        $mensagem = "<div class='alert error'>Por favor, preencha Nome, Usuário e E-mail.</div>";
    } else {
        $sql_check = "SELECT id FROM usuarios WHERE usuario = ? OR email = ?";
        $stmt_check = $conn->prepare($sql_check);
        $stmt_check->bind_param("ss", $usuario, $email);
        $stmt_check->execute();
        
        if ($stmt_check->get_result()->num_rows > 0) {
            $mensagem = "<div class='alert error'>Erro: Usuário ou E-mail já cadastrado.</div>";
        } else {
            // Senha padrão 123456
            $senha_hash = password_hash('123456', PASSWORD_BCRYPT);
            
            // NOVO: PIN padrão 123456 para evitar erro de 'Column cannot be null'
            // O usuário trocará este valor obrigatoriamente no onboarding.php
            $pin_hash = password_hash('123456', PASSWORD_BCRYPT);

            // Inserção incluindo a coluna NIVEL e o PIN inicial
            $sql_insert = "INSERT INTO usuarios 
                           (nome, usuario, email, celular, senha_login, primeiro_login, pin_validacao, nivel) 
                           VALUES (?, ?, ?, ?, ?, 1, ?, ?)";
            
            $stmt_insert = $conn->prepare($sql_insert);
            // Ajustado bind_param para os 7 parâmetros (sssssss)
            $stmt_insert->bind_param("sssssss", $nome, $usuario, $email, $celular, $senha_hash, $pin_hash, $nivel);
            
            if ($stmt_insert->execute()) {
                $mensagem = "<div class='alert success'>✅ Usuário <b>$usuario</b> ($nivel) criado!<br>Senha e PIN padrão: 123456</div>";
            } else {
                $mensagem = "<div class='alert error'>Erro ao registrar: " . $stmt_insert->error . "</div>";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>SVD - Cadastrar Usuário</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background-color: #121212; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; color: #e0e0e0; }
        .container { background: #1e1e1e; padding: 30px; border-radius: 12px; max-width: 450px; width: 90%; border: 1px solid #333; }
        h1 { text-align: center; color: #66bb6a; }
        label { display: block; margin-bottom: 5px; font-size: 0.9rem; color: #bbb; }
        input, select { width: 100%; padding: 12px; margin-bottom: 15px; border-radius: 6px; border: 1px solid #444; background: #2c2c2c; color: #fff; box-sizing: border-box; }
        input[type="submit"] { background: #66bb6a; color: #121212; font-weight: bold; cursor: pointer; border: none; padding: 15px; transition: 0.3s; width: 100%; }
        input[type="submit"]:hover { background: #4caf50; }
        .alert { padding: 15px; border-radius: 6px; margin-bottom: 20px; text-align: center; font-weight: bold; }
        .error { background: rgba(255, 82, 82, 0.1); border: 1px solid #ff5252; color: #ff5252; }
        .success { background: rgba(102, 187, 106, 0.1); border: 1px solid #66bb6a; color: #66bb6a; }
        .footer-link { display:block; text-align:center; color:#888; text-decoration:none; margin-top: 10px; font-size: 0.9rem; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Novo Usuário</h1>
        <?= $mensagem; ?>
        <form method="POST">
            <label>Nome Completo:</label>
            <input type="text" name="nome" placeholder="Ex: Matheus Cabral" required>
            
            <label>Usuário de Acesso (Login):</label>
            <input type="text" name="usuario" placeholder="Ex: matheus.cabral" required>
            
            <label>E-mail de Notificação:</label>
            <input type="email" name="email" placeholder="email@empresa.com" required>

            <label>Celular (Opcional):</label>
            <input type="text" name="celular" placeholder="(11) 99999-9999">
            
            <label>Nível de Permissão:</label>
            <select name="nivel">
                <option value="BASIC">👤 BASIC (Usuário Padrão)</option>
                <option value="ADMIN">🛡️ ADMIN (Administrador)</option>
            </select>
            
            <input type="submit" value="Criar Acesso">
        </form>
        <a href="usuarios_config.php" class="footer-link">Voltar para Gestão</a>
    </div>
</body>
</html>
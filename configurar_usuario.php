<?php
/**
 * SVD - Validador de Documentos
 * Arquivo: configurar_usuario.php
 */

session_start();
require_once 'db.php';

// 1. Bloqueio de segurança: se não tá logado, volta pro login
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit;
}

// 2. Se o cara já configurou (flag 0), manda direto pro painel, não tem o que fazer aqui
if ($_SESSION['primeiro_login'] == 0) {
    header("Location: painel.php");
    exit;
}

$mensagem = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nova_senha = $_POST['nova_senha'] ?? '';
    $confirma_senha = $_POST['confirma_senha'] ?? '';

    // Validações básicas
    if (strlen($nova_senha) < 8) {
        $mensagem = "<div class='alert error'>A nova senha deve ter pelo menos 8 caracteres.</div>";
    } elseif ($nova_senha !== $confirma_senha) {
        $mensagem = "<div class='alert error'>As senhas não conferem.</div>";
    } else {
        // Tudo certo! Vamos atualizar o banco
        $senha_hash = password_hash($nova_senha, PASSWORD_DEFAULT);
        $user_id = $_SESSION['usuario_id'];

        // Update: Nova senha e desativa a flag de primeiro login
        $sql = "UPDATE Usuarios SET senha_login = ?, primeiro_login = 0 WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("si", $senha_hash, $user_id);

        if ($stmt->execute()) {
            // Atualiza a variável de sessão para o cara não cair mais aqui
            $_SESSION['primeiro_login'] = 0;
            
            // Redireciona com um delay ou link, ou direto
            header("Location: painel.php?msg=sucesso");
            exit;
        } else {
            $mensagem = "<div class='alert error'>Erro ao atualizar configurações.</div>";
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>SVD - Configuração Inicial</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background-color: #121212; color: #e0e0e0; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; }
        .container { background: #1e1e1e; padding: 30px; border-radius: 12px; max-width: 400px; width: 90%; border: 1px solid #333; box-shadow: 0 8px 20px rgba(0,0,0,0.5); }
        h2 { color: #66bb6a; text-align: center; }
        p { font-size: 0.9rem; color: #aaa; text-align: center; margin-bottom: 25px; }
        label { display: block; margin-bottom: 5px; font-size: 0.85rem; }
        input { width: 100%; padding: 12px; margin-bottom: 20px; border-radius: 6px; border: 1px solid #444; background: #2c2c2c; color: #fff; box-sizing: border-box; }
        input[type="submit"] { background: #66bb6a; color: #121212; border: none; font-weight: bold; cursor: pointer; transition: 0.3s; }
        input[type="submit"]:hover { background: #4caf50; }
        .alert { padding: 10px; border-radius: 6px; margin-bottom: 15px; text-align: center; font-size: 0.9rem; }
        .error { background: rgba(255, 82, 82, 0.1); border: 1px solid #ff5252; color: #ff5252; }
    </style>
</head>
<body>
    <div class="container">
        <h2>Primeiro Acesso</h2>
        <p>Olá, <strong><?php echo htmlspecialchars($_SESSION['usuario_nome']); ?></strong>! Por segurança, você precisa definir uma nova senha pessoal antes de continuar.</p>

        <?php echo $mensagem; ?>

        <form method="POST" action="">
            <label>Nova Senha:</label>
            <input type="password" name="nova_senha" placeholder="Mínimo 8 caracteres" required>

            <label>Confirme a Nova Senha:</label>
            <input type="password" name="confirma_senha" placeholder="Repita a senha" required>

            <input type="submit" value="Salvar e Acessar Painel">
        </form>
    </div>
</body>
</html>
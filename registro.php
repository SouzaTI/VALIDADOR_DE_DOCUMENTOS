<?php
/**
 * SVD - Validador de Documentos
 * Arquivo: registro.php (Refatorado)
 */

require_once 'db.php'; // Usa a conexão centralizada

$mensagem = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $nome = trim($_POST['nome'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $celular = trim($_POST['celular'] ?? '');
    $senha = $_POST['senha'] ?? '';
    $pin = $_POST['pin'] ?? '';

    // 1. VALIDAÇÃO BÁSICA
    if (empty($nome) || empty($email) || empty($senha) || empty($pin)) {
        $mensagem = "<div class='alert error'>Por favor, preencha todos os campos.</div>";
    } elseif (strlen($senha) < 8) {
        $mensagem = "<div class='alert error'>A senha deve ter pelo menos 8 caracteres.</div>";
    } elseif (strlen($pin) < 5 || !is_numeric($pin)) {
        $mensagem = "<div class='alert error'>O PIN deve ser numérico e ter pelo menos 5 dígitos.</div>";
    } else {
        
        // 2. VERIFICA SE O E-MAIL JÁ EXISTE
        $sql_check = "SELECT id FROM Usuarios WHERE email = ?";
        $stmt_check = $conn->prepare($sql_check);
        $stmt_check->bind_param("s", $email);
        $stmt_check->execute();
        
        if ($stmt_check->get_result()->num_rows > 0) {
            $mensagem = "<div class='alert error'>Erro: Este e-mail já está em uso.</div>";
            $stmt_check->close();
        } else {
            $stmt_check->close();

            // 3. CRIPTOGRAFIA (HASH) - Nível Engenharia
            $senha_hash = password_hash($senha, PASSWORD_DEFAULT);
            $pin_hash = password_hash($pin, PASSWORD_DEFAULT);

            // 4. INSERÇÃO NO BANCO DE DADOS
            // Nota: Garantimos que primeiro_login comece como 1
            $sql_insert = "INSERT INTO Usuarios 
                           (nome, email, celular, senha_login, pin_validacao, primeiro_login) 
                           VALUES (?, ?, ?, ?, ?, 1)";
            
            $stmt_insert = $conn->prepare($sql_insert);
            $stmt_insert->bind_param("sssss", $nome, $email, $celular, $senha_hash, $pin_hash);
            
            if ($stmt_insert->execute()) {
                $mensagem = "<div class='alert success'>✅ Sucesso! Usuário registrado. <a href='login.php' style='color:inherit;'>Faça o login agora.</a></div>";
                // Limpa o POST para não repetir os dados no formulário
                $_POST = array();
            } else {
                $mensagem = "<div class='alert error'>Erro ao registrar: " . $stmt_insert->error . "</div>";
            }
            $stmt_insert->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SVD - Novo Usuário</title>
    <style>
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            background-color: #121212; 
            display: flex; 
            justify-content: center; 
            align-items: center; 
            min-height: 100vh; 
            margin: 0;
            color: #e0e0e0; 
        }
        .container { 
            background: #1e1e1e; 
            padding: 30px; 
            border-radius: 12px; 
            max-width: 450px; 
            width: 90%; 
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.5);
            border: 1px solid #333; 
        }
        h1 { text-align: center; color: #66bb6a; margin-bottom: 25px; }
        label { display: block; margin-bottom: 5px; font-size: 0.9rem; color: #bbb; }
        input { 
            width: 100%; padding: 10px; margin-bottom: 15px; 
            box-sizing: border-box; border: 1px solid #444; 
            border-radius: 6px; background-color: #2c2c2c; color: #fff; 
        }
        input:focus { border-color: #66bb6a; outline: none; }
        input[type="submit"] { 
            background-color: #66bb6a; color: #121212; 
            border: none; font-weight: bold; cursor: pointer; 
            margin-top: 10px; transition: 0.3s;
        }
        input[type="submit"]:hover { background-color: #4caf50; }
        .alert { padding: 15px; border-radius: 6px; margin-bottom: 20px; text-align: center; font-weight: bold; }
        .error { background: #ff52521a; border: 1px solid #ff5252; color: #ff5252; }
        .success { background: #66bb6a1a; border: 1px solid #66bb6a; color: #66bb6a; }
        .link-login { display: block; text-align: center; margin-top: 20px; color: #81c784; text-decoration: none; font-size: 0.9rem; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Novo Registro</h1>
        
        <?php echo $mensagem; ?>

        <form method="POST" action="">
            <label>Nome Completo:</label>
            <input type="text" name="nome" value="<?php echo htmlspecialchars($_POST['nome'] ?? ''); ?>" required>
            
            <label>E-mail (Será seu login):</label>
            <input type="email" name="email" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>

            <label>Celular:</label>
            <input type="text" name="celular" value="<?php echo htmlspecialchars($_POST['celular'] ?? ''); ?>" placeholder="(00) 00000-0000">
            
            <label>Senha de Login (Mín. 8 caracteres):</label>
            <input type="password" name="senha" required>
            
            <label>PIN de Segurança (5+ dígitos):</label>
            <input type="password" name="pin" required>

            <input type="submit" value="Criar Minha Conta">
        </form>
        
        <a href="login.php" class="link-login">Já possui uma conta? Entrar</a>
    </div>
</body>
</html>
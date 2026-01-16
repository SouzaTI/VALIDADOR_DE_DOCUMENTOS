<?php
// Arquivo: registro.php
// Objetivo: Permitir que novos usuários se registrem e gerar os hashes de senha/PIN.

// --- CONFIGURAÇÃO DE CONEXÃO ---
$host = '127.0.0.1';
$port = 3307; // CONFIRME SUA PORTA!
$db = 'db_svd';
$user = 'root';
$pass = '';

$conn = new mysqli($host, $user, $pass, $db, $port);
if ($conn->connect_error) {
    $mensagem = "<span style='color:#ff5252;'>Falha na Conexão com o Banco de Dados.</span>";
} else {
    $mensagem = "";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $nome = trim($_POST['nome'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $celular = trim($_POST['celular'] ?? '');
    $senha = $_POST['senha'] ?? '';
    $pin = $_POST['pin'] ?? '';

    // 1. VALIDAÇÃO BÁSICA
    if (empty($nome) || empty($email) || empty($senha) || empty($pin)) {
        $mensagem = "<span style='color:#ff5252;'>Por favor, preencha todos os campos.</span>";
    } elseif (strlen($senha) < 8) {
        $mensagem = "<span style='color:#ff5252;'>A senha deve ter pelo menos 8 caracteres.</span>";
    } elseif (strlen($pin) < 5 || !is_numeric($pin)) {
        $mensagem = "<span style='color:#ff5252;'>O PIN deve ser numérico e ter pelo menos 5 dígitos.</span>";
    } else {
        
        // 2. CRIPTOGRAFIA (HASH)
        $senha_hash = password_hash($senha, PASSWORD_DEFAULT);
        $pin_hash = password_hash($pin, PASSWORD_DEFAULT); // O PIN também é um segredo!

        // 3. VERIFICA SE O E-MAIL JÁ EXISTE
        $sql_check = "SELECT id FROM Usuarios WHERE email = ?";
        $stmt_check = $conn->prepare($sql_check);
        $stmt_check->bind_param("s", $email);
        $stmt_check->execute();
        if ($stmt_check->get_result()->num_rows > 0) {
            $mensagem = "<span style='color:#ff5252;'>Erro: Este e-mail já está em uso.</span>";
            $stmt_check->close();
        } else {
            $stmt_check->close();

            // 4. INSERÇÃO NO BANCO DE DADOS
            $sql_insert = "INSERT INTO Usuarios 
                           (nome, email, celular, senha_login, pin_validacao) 
                           VALUES (?, ?, ?, ?, ?)";
            
            $stmt_insert = $conn->prepare($sql_insert);
            $stmt_insert->bind_param("sssss", $nome, $email, $celular, $senha_hash, $pin_hash);
            
            if ($stmt_insert->execute()) {
                $novo_id = $conn->insert_id;
                $mensagem = "<span style='color:#66bb6a;'>Sucesso! Usuário {$nome} registrado com ID {$novo_id}. Você pode fazer o login agora.</span>";
                // Limpar os campos do formulário após o sucesso (opcional)
                unset($_POST);
            } else {
                $mensagem = "<span style='color:#ff5252;'>Erro ao registrar usuário: " . $stmt_insert->error . "</span>";
            }
            $stmt_insert->close();
        }
    }
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>SVD - Novo Usuário</title>
    <style>
        /* DARK MODE STYLES */
        body { 
            font-family: Arial, sans-serif; 
            padding: 20px; 
            background-color: #121212; /* Fundo escuro */
            display: flex; 
            justify-content: center; 
            align-items: center; 
            min-height: 100vh; 
            color: #e0e0e0; /* Texto claro */
        }
        .container { 
            background: #1e1e1e; /* Card escuro */
            padding: 30px; 
            border-radius: 8px; 
            max-width: 450px; 
            width: 100%; 
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.5);
            border: 1px solid #333; 
        }
        h1 { 
            text-align: center; 
            color: #66bb6a; /* Cor de sucesso */
            margin-bottom: 20px;
        }
        input[type="text"], input[type="email"], input[type="password"] { 
            width: 100%; 
            padding: 10px; 
            margin-top: 5px; 
            margin-bottom: 15px; 
            box-sizing: border-box; 
            border: 1px solid #444; 
            border-radius: 4px; 
            background-color: #2c2c2c; /* Fundo do campo */
            color: #e0e0e0; 
        }
        input[type="submit"] { 
            background-color: #66bb6a; 
            color: #1e1e1e; 
            padding: 12px; 
            border: none; 
            border-radius: 4px; 
            cursor: pointer; 
            width: 100%; 
            font-weight: bold;
            transition: background-color 0.3s;
        }
        input[type="submit"]:hover {
            background-color: #4caf50;
        }
        span { display: block; margin-bottom: 15px; font-weight: bold; text-align: center;}
        .link-login { 
            display: block; 
            text-align: center; 
            margin-top: 15px; 
            font-size: 14px; 
            color: #81c784;
            text-decoration: none;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Novo Registro de Validador</h1>
        
        <?php echo $mensagem; ?>

        <form method="POST" action="">
            <label for="nome">Nome Completo:</label>
            <input type="text" id="nome" name="nome" value="<?php echo htmlspecialchars($_POST['nome'] ?? ''); ?>" required>
            
            <label for="email">E-mail (Login):</label>
            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>

            <label for="celular">Celular:</label>
            <input type="text" id="celular" name="celular" value="<?php echo htmlspecialchars($_POST['celular'] ?? ''); ?>">
            
            <label for="senha">Senha de Login (Mín. 8 chars):</label>
            <input type="password" id="senha" name="senha" required>
            
            <label for="pin">PIN de Transação (Mín. 5 dígitos numéricos):</label>
            <input type="password" id="pin" name="pin" required>

            <input type="submit" value="Registrar Novo Usuário">
        </form>
        
        <a href="login.php" class="link-login">Já tem conta? Faça o Login</a>
    </div>
</body>
</html>
<?php
// Arquivo: login.php - DARK MODE

session_start();
// --- CONFIGURAÇÃO DE CONEXÃO ---
$host = '127.0.0.1';
$port = 3307; 
$db = 'db_svd';
$user = 'root';
$pass = '';

$conn = new mysqli($host, $user, $pass, $db, $port);
if ($conn->connect_error) {
    $erro = "Falha na Conexão com o Banco de Dados.";
} else {
    $erro = "";
}

if (isset($_SESSION['usuario_id'])) {
    header("Location: painel.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $senha_digitada = $_POST['senha'] ?? '';

    if (empty($email) || empty($senha_digitada)) {
        $erro = "Preencha e-mail e senha.";
    } else {
        // MUDANÇA 1: Incluir 'primeiro_login' na seleção SQL
        $sql = "SELECT id, nome, senha_login, primeiro_login FROM Usuarios WHERE email = ?"; 
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $usuario = $result->fetch_assoc();
        $stmt->close();
        
        if ($usuario && password_verify($senha_digitada, $usuario['senha_login'])) {
            
            // Credenciais OK. Vamos iniciar a sessão
            $_SESSION['usuario_id'] = $usuario['id'];
            $_SESSION['usuario_nome'] = $usuario['nome'];
            // Guardamos o status de primeiro login na sessão
            $_SESSION['primeiro_login'] = (int)$usuario['primeiro_login']; 
            
            // MUDANÇA 2: Lógica Condicional de Redirecionamento
            if ($usuario['primeiro_login'] == 1) {
                // Se a flag estiver ativa (1), manda pra tela de configuração
                header("Location: configurar_usuario.php"); 
            } else {
                // Se a flag for 0 (já configurou), manda pro painel normal
                header("Location: painel.php"); 
            }
            exit;
            
        } else {
            $erro = "Credenciais inválidas.";
        }
    }
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>SVD - Login</title>
    <style>
        /* DARK MODE STYLES */
        body { 
            font-family: Arial, sans-serif; 
            background-color: #121212; /* Fundo muito escuro */
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
            max-width: 350px; 
            width: 100%; 
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.5); 
            border: 1px solid #333;
        }
        h1 { 
            text-align: center; 
            color: #66bb6a; /* Verde/Sucesso */
            margin-bottom: 20px;
        }
        input[type="email"], input[type="password"] { 
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
        .error { 
            color: #ff5252; /* Vermelho Erro */
            text-align: center; 
            margin-bottom: 15px; 
            font-weight: bold; 
            border: 1px solid #ff525255;
            padding: 10px;
            background: #ff52521a;
            border-radius: 4px;
        }
        .link-registro {
            display: block;
            text-align: center;
            margin-top: 15px;
            color: #81c784;
            text-decoration: none;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>SVD Login</h1>
        <?php if (!empty($erro)): ?>
            <p class="error">❌ <?php echo htmlspecialchars($erro); ?></p>
        <?php endif; ?>
        <form method="POST" action="">
            <label for="email">E-mail:</label>
            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
            
            <label for="senha">Senha:</label>
            <input type="password" id="senha" name="senha" required>
            
            <input type="submit" value="Entrar no Sistema">
        </form>
        <a href="registro.php" class="link-registro">Ainda não tem conta? Crie sua conta aqui.</a>
    </div>
</body>
</html>
<?php
/**
 * SVD - Validador de Documentos
 * Arquivo: login.php (Refatorado)
 */

session_start();
require_once 'db.php'; // Puxa as configurações do banco

// Se o cara já tiver uma sessão ativa, manda direto pro painel
if (isset($_SESSION['usuario_id'])) {
    header("Location: painel.php");
    exit;
}

$erro = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitização básica de entrada
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $senha_digitada = $_POST['senha'] ?? '';

    if (empty($email) || empty($senha_digitada)) {
        $erro = "Por favor, preencha todos os campos.";
    } else {
        // 1. SQL Ajustado para o banco db_svd e tabela usuarios
        $sql = "SELECT id, nome, senha_login, primeiro_login FROM usuarios WHERE email = ?"; 
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $usuario = $result->fetch_assoc();
        $stmt->close();
        
        // 2. Verificação de senha usando os nomes de coluna do print: senha_login
        if ($usuario && password_verify($senha_digitada, $usuario['senha_login'])) {
            
            session_regenerate_id(true);

            $_SESSION['usuario_id'] = $usuario['id'];
            $_SESSION['usuario_nome'] = $usuario['nome'];
            $_SESSION['primeiro_login'] = (int)$usuario['primeiro_login']; 
            
            // 3. Lógica baseada na coluna: primeiro_login
            // Se for 1 (como o Jefferson Carvalho no print), vai para configurar_usuario.php
            $destino = ($usuario['primeiro_login'] == 1) ? "configurar_usuario.php" : "painel.php";
            
            header("Location: $destino"); 
            exit;
        } else {
            $erro = "E-mail ou senha inválidos.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SVD - Login</title>
    <style>
        /* DARK MODE UI */
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
            padding: 40px; 
            border-radius: 12px; 
            max-width: 400px; 
            width: 90%; 
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.7); 
            border: 1px solid #333;
        }
        h1 { 
            text-align: center; 
            color: #66bb6a; 
            margin-bottom: 30px;
            font-size: 1.8rem;
        }
        label {
            display: block;
            margin-bottom: 8px;
            font-size: 0.9rem;
            color: #bbb;
        }
        input[type="email"], input[type="password"] { 
            width: 100%; 
            padding: 12px; 
            margin-bottom: 20px; 
            box-sizing: border-box; 
            border: 1px solid #444; 
            border-radius: 6px; 
            background-color: #2c2c2c; 
            color: #fff; 
            outline: none;
            transition: border-color 0.3s;
        }
        input[type="email"]:focus, input[type="password"]:focus {
            border-color: #66bb6a;
        }
        input[type="submit"] { 
            background-color: #66bb6a; 
            color: #121212; 
            padding: 14px; 
            border: none; 
            border-radius: 6px; 
            cursor: pointer; 
            width: 100%; 
            font-weight: bold;
            font-size: 1rem;
            transition: transform 0.2s, background-color 0.3s;
        }
        input[type="submit"]:hover {
            background-color: #4caf50;
            transform: translateY(-2px);
        }
        input[type="submit"]:active {
            transform: translateY(0);
        }
        .error { 
            color: #ff5252; 
            text-align: center; 
            margin-bottom: 20px; 
            font-size: 0.9rem;
            border: 1px solid #ff525255;
            padding: 12px;
            background: #ff52521a;
            border-radius: 6px;
        }
        .link-registro {
            display: block;
            text-align: center;
            margin-top: 25px;
            color: #81c784;
            text-decoration: none;
            font-size: 0.9rem;
        }
        .link-registro:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>SVD Validador</h1>
        
        <?php if (!empty($erro)): ?>
            <div class="error">❌ <?php echo htmlspecialchars($erro); ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <label for="email">E-mail Corporativo</label>
            <input type="email" id="email" name="email" 
                   value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" 
                   placeholder="exemplo@dominio.com" required>
            
            <label for="senha">Senha de Acesso</label>
            <input type="password" id="senha" name="senha" 
                   placeholder="••••••••" required>
            
            <input type="submit" value="Entrar no Sistema">
        </form>
        
        <a href="registro.php" class="link-registro">Solicitar acesso ao sistema</a>
    </div>
</body>
</html>
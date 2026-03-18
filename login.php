<?php
/**
 * SVD - Validador de Documentos
 * Arquivo: login.php (Versão Atualizada com Nível de Acesso)
 */

session_start();
require_once 'db.php'; 

// Se o usuário já tiver uma sessão ativa, manda direto pro painel
if (isset($_SESSION['usuario_id'])) {
    header("Location: painel.php");
    exit;
}

$erro = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario_input = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_SPECIAL_CHARS);
    $senha_digitada = $_POST['senha'] ?? '';

    if (empty($usuario_input) || empty($senha_digitada)) {
        $erro = "Por favor, preencha todos os campos.";
    } else {
        // 1. SQL busca dados incluindo a nova coluna NIVEL
        $sql = "SELECT id, nome, senha_login, primeiro_login, pin_validacao, nivel FROM usuarios WHERE usuario = ?"; 
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $usuario_input);
        $stmt->execute();
        $result = $stmt->get_result();
        $usuario = $result->fetch_assoc();
        $stmt->close();
        
        // 2. Verificação de senha
        if ($usuario && password_verify($senha_digitada, $usuario['senha_login'])) {
            
            session_regenerate_id(true);

            // Grava os dados essenciais na SESSÃO
            $_SESSION['usuario_id'] = $usuario['id'];
            $_SESSION['usuario_nome'] = $usuario['nome'];
            $_SESSION['usuario_nivel'] = $usuario['nivel']; // Importante para as travas de ADMIN/BASIC
            
            // 3. Lógica de Redirecionamento
            // Se for o primeiro acesso ou não tiver PIN, vai para onboarding
            if ($usuario['primeiro_login'] == 1 || empty($usuario['pin_validacao'])) {
                $destino = "onboarding.php"; 
            } else {
                $destino = "painel.php";
            }
            
            header("Location: $destino"); 
            exit;
        } else {
            $erro = "Usuário ou senha inválidos.";
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
        body { font-family: 'Segoe UI', sans-serif; background-color: #121212; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; color: #e0e0e0; }
        .container { background: #1e1e1e; padding: 40px; border-radius: 12px; max-width: 400px; width: 90%; box-shadow: 0 10px 30px rgba(0, 0, 0, 0.7); border: 1px solid #333; }
        h1 { text-align: center; color: #66bb6a; margin-bottom: 30px; font-size: 1.8rem; }
        label { display: block; margin-bottom: 8px; font-size: 0.9rem; color: #bbb; }
        input[type="text"], input[type="password"] { width: 100%; padding: 12px; margin-bottom: 20px; box-sizing: border-box; border: 1px solid #444; border-radius: 6px; background-color: #2c2c2c; color: #fff; outline: none; transition: border-color 0.3s; }
        input[type="text"]:focus, input[type="password"]:focus { border-color: #66bb6a; }
        input[type="submit"] { background-color: #66bb6a; color: #121212; padding: 14px; border: none; border-radius: 6px; cursor: pointer; width: 100%; font-weight: bold; font-size: 1rem; transition: transform 0.2s, background-color 0.3s; }
        input[type="submit"]:hover { background-color: #4caf50; transform: translateY(-2px); }
        .error { color: #ff5252; text-align: center; margin-bottom: 20px; font-size: 0.9rem; border: 1px solid #ff525255; padding: 12px; background: #ff52521a; border-radius: 6px; }
        .link-registro { display: block; text-align: center; margin-top: 25px; color: #81c784; text-decoration: none; font-size: 0.9rem; }
    </style>
</head>
<body>
    <div class="container">
        <h1>SVD Validador</h1>
        
        <?php if (!empty($erro)): ?>
            <div class="error">❌ <?php echo htmlspecialchars($erro); ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <label for="email">Usuário</label>
            <input type="text" id="email" name="email" 
                   value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" 
                   placeholder="Ex: alex.cunha" required>
            
            <label for="senha">Senha de Acesso</label>
            <input type="password" id="senha" name="senha" 
                   placeholder="••••••••" required>
            
            <input type="submit" value="Entrar no Sistema">
        </form>
        
        <?php if (isset($_SESSION['usuario_nivel']) && $_SESSION['usuario_nivel'] === 'ADMIN'): ?>
            <a href="registro.php" class="link-registro">Cadastrar novos usuários</a>
        <?php else: ?>
            <a href="#" class="link-registro" onclick="alert('Procure o administrador para solicitar acesso.')">Solicitar acesso ao sistema</a>
        <?php endif; ?>
    </div>
</body>
</html>
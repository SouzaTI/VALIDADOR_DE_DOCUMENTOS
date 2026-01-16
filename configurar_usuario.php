<?php
// ATENÇÃO: Adicionado para FORÇAR a exibição de erros fatais de PHP (Remover em produção)
error_reporting(E_ALL); 
ini_set('display_errors', 1);

session_start();

// 1. Verifica se o usuário está logado e se precisa configurar
if (!isset($_SESSION['usuario_id']) || ($_SESSION['primeiro_login'] ?? 0) != 1) {
    // Se não está logado ou já configurou, manda pro painel
    header("Location: painel.php");
    exit;
}

// --- CONFIGURAÇÃO DE CONEXÃO ---
// OBS: Use os mesmos dados de conexão do seu login.php
$host = '127.0.0.1';
$port = 3307; 
$db = 'db_svd';
$user = 'root';
$pass = '';

$conn = new mysqli($host, $user, $pass, $db, $port);
if ($conn->connect_error) {
    $mensagem = "<span style='color:#ff5252;'>Falha na Conexão com o Banco de Dados: " . $conn->connect_error . "</span>";
} else {
    $mensagem = "";
}

// --- PROCESSAMENTO DO FORMULÁRIO ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $nova_senha = $_POST['nova_senha'] ?? '';
    $pin = $_POST['pin'] ?? '';
    $id_usuario = $_SESSION['usuario_id'];

    // 2. VALIDAÇÃO BÁSICA
    if (empty($nova_senha) || empty($pin)) {
        $mensagem = "<span style='color:#ff5252;'>Por favor, preencha todos os campos.</span>";
    } elseif (strlen($nova_senha) < 8) {
        $mensagem = "<span style='color:#ff5252;'>A nova senha deve ter pelo menos 8 caracteres.</span>";
    } elseif (strlen($pin) < 5 || !is_numeric($pin)) {
        $mensagem = "<span style='color:#ff5252;'>O PIN deve ser numérico e ter pelo menos 5 dígitos.</span>";
    } else {
        
        // 3. CRIPTOGRAFIA (HASH)
        $nova_senha_hash = password_hash($nova_senha, PASSWORD_DEFAULT);
        $novo_pin_hash = password_hash($pin, PASSWORD_DEFAULT); 
        
        // 4. ATUALIZAÇÃO NO BANCO
        // Atualiza Senha, PIN e desativa a flag 'primeiro_login' (0)
        $sql_update = "UPDATE Usuarios 
                       SET senha_login = ?, pin_validacao = ?, primeiro_login = 0
                       WHERE id = ?";
        
        $stmt_update = $conn->prepare($sql_update);
        
        // >>>>> AQUI ESTÁ O FIX PRINCIPAL: Checagem se o prepare falhou (retorna false) <<<<<
        if ($stmt_update === false) {
            // Se falhou, provavelmente é erro de sintaxe SQL ou nome de coluna inexistente
            $mensagem = "<span style='color:#ff5252;'>❌ Erro fatal ao preparar SQL: " . $conn->error . "</span>";
        } else {
            // Se a preparação foi OK, a gente prossegue com o bind e execute
            $stmt_update->bind_param("ssi", $nova_senha_hash, $novo_pin_hash, $id_usuario);
            
            if ($stmt_update->execute()) {
                // Sucesso! Atualiza a sessão e redireciona.
                $_SESSION['primeiro_login'] = 0; // Marca como configurado
                $mensagem = "<span style='color:#66bb6a;'>✅ Sucesso! Credenciais atualizadas. Você será redirecionado em breve.</span>";
                $stmt_update->close(); // Fechar o statement
                $conn->close(); // Fechar a conexão
                
                // Redireciona após 3 segundos
                header("Refresh: 3; url=painel.php"); 
                exit; // >>>>> SAÍDA CRÍTICA: Termina a execução aqui.
            } else {
                $mensagem = "<span style='color:#ff5252;'>❌ Erro ao executar UPDATE: " . $stmt_update->error . "</span>";
                $stmt_update->close(); // Fechar o statement no caso de erro de execução
            }
        }
    }
}
// Se a execução falhou (prepare ou execute), a conexão ainda estará aberta e será fechada aqui.
// Se a execução foi bem sucedida, ela foi fechada dentro do 'if' antes do exit.
$conn->close();
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>SVD - Configuração Inicial</title>
    <style>
        /* CSS DARK MODE COPIADO DO LOGIN.PHP */
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
            max-width: 450px; /* Um pouco mais largo que o login */
            width: 100%; 
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.5); 
            border: 1px solid #333;
        }
        h1 { 
            text-align: center; 
            color: #66bb6a; /* Verde/Sucesso */
            margin-bottom: 20px;
        }
        input[type="password"] { 
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
        span { 
            display: block; 
            margin-bottom: 15px; 
            font-weight: bold; 
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🛠️ Configuração Inicial Obrigatória</h1>
        
        <?php echo $mensagem; ?>
        
        <p style="text-align: center; margin-bottom: 20px; color: #bdbdbd;">
            Olá, **<?php echo htmlspecialchars($_SESSION['usuario_nome'] ?? 'usuário'); ?>**!
            <br>
            A senha atual é provisória. Por favor, defina sua **nova senha pessoal** e um **PIN de segurança** para prosseguir.
        </p>

        <form method="POST" action="">
            <label for="nova_senha">Nova Senha de Login (Mín. 8 chars):</label>
            <input type="password" id="nova_senha" name="nova_senha" required>
            
            <label for="pin">PIN de Transação (Mín. 5 dígitos numéricos):</label>
            <input type="password" id="pin" name="pin" required>

            <input type="submit" value="Salvar e Acessar o Sistema">
        </form>
    </div>
</body>
</html>
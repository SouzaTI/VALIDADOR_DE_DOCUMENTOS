<?php
/**
 * SVD - Validador de Documentos
 * Arquivo: onboarding.php (Visual Dark Mode Refatorado)
 */

session_start();
require_once 'db.php';

if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nova_senha = password_hash($_POST['nova_senha'], PASSWORD_BCRYPT);
    $novo_pin = password_hash($_POST['novo_pin'], PASSWORD_BCRYPT);
    $user_id = $_SESSION['usuario_id'];

    // Atualiza senha, pin e desmarca o primeiro login
    $stmt = $conn->prepare("UPDATE usuarios SET senha_login = ?, pin_validacao = ?, primeiro_login = 0 WHERE id = ?");
    $stmt->bind_param("ssi", $nova_senha, $novo_pin, $user_id);
    
    if ($stmt->execute()) {
        echo "<script>alert('Configuração finalizada! Use seus novos dados para assinar.'); window.location.href='painel.php';</script>";
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SVD - Configuração Obrigatória</title>
    <style>
        /* Padronização com o Visual do login.php */
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
            max-width: 450px; 
            width: 90%; 
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.7); 
            border: 1px solid #333;
        }
        h1 { 
            text-align: center; 
            color: #66bb6a; 
            margin-bottom: 20px;
            font-size: 1.6rem;
        }
        .info {
            text-align: center;
            font-size: 0.95rem;
            color: #bbb;
            margin-bottom: 30px;
            line-height: 1.5;
        }
        .info b { color: #fff; }
        
        label {
            display: block;
            margin-bottom: 8px;
            font-size: 0.85rem;
            color: #66bb6a;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        input[type="password"], input[type="text"] { 
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
            font-size: 1rem;
        }
        input:focus {
            border-color: #66bb6a;
        }

        .help-text {
            font-size: 0.75rem;
            color: #888;
            margin-top: -15px;
            margin-bottom: 20px;
            display: block;
        }

        button[type="submit"] { 
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
            margin-top: 10px;
        }
        button[type="submit"]:hover {
            background-color: #4caf50;
            transform: translateY(-2px);
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔒 Configuração Obrigatória</h1>
        <div class="info">
            Olá, <b><?= htmlspecialchars($_SESSION['usuario_nome']) ?></b>!<br>
            Para garantir a segurança das suas assinaturas, defina sua senha pessoal e seu PIN numérico.
        </div>
        
        <form method="POST">
            <label for="nova_senha">Nova Senha de Login</label>
            <input type="password" id="nova_senha" name="nova_senha" placeholder="Digite sua nova senha" required>
            
            <label for="novo_pin">Definir PIN de Assinatura</label>
            <input type="password" id="novo_pin" name="novo_pin" placeholder="Ex: 123456" pattern="\d*" maxlength="6" required>
            <span class="help-text">Este PIN numérico será solicitado em todas as suas validações.</span>
            
            <button type="submit">Gravar e Acessar Painel</button>
        </form>
    </div>
</body>
</html>
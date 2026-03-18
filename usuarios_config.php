<?php
/**
 * SVD - Validador de Documentos
 * Arquivo: usuarios_config.php (Gestão e Reset de Acesso)
 */

session_start();
require_once 'db.php';

// Só ADMIN entra.
if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_nivel'] !== 'ADMIN') {
    die("Acesso negado. Página restrita a administradores.");
}

// LÓGICA DE RESET: Volta para 123456 e limpa o PIN
if (isset($_GET['reset_id'])) {
    $id_reset = $_GET['reset_id'];
    $senha_padrao = password_hash('123456', PASSWORD_BCRYPT);
    
    $stmt = $conn->prepare("UPDATE usuarios SET senha_login = ?, pin_validacao = NULL, primeiro_login = 1 WHERE id = ?");
    $stmt->bind_param("si", $senha_padrao, $id_reset);
    
    if ($stmt->execute()) {
        header("Location: usuarios_config.php?msg=reset_ok");
        exit;
    }
}

$res_usuarios = $conn->query("SELECT id, nome, usuario, email, nivel, primeiro_login FROM usuarios ORDER BY nome ASC");
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>SVD - Gestão de Usuários</title>
    <style>
        :root { --bg: #121212; --card: #1e1e1e; --primary: #66bb6a; --text: #e0e0e0; }
        body { font-family: sans-serif; background: var(--bg); color: var(--text); padding: 40px; }
        .container { max-width: 1000px; margin: 0 auto; }
        .card { background: var(--card); padding: 25px; border-radius: 12px; border: 1px solid #333; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #333; }
        .badge { padding: 4px 8px; border-radius: 4px; font-size: 0.75rem; font-weight: bold; }
        .badge-admin { background: rgba(33, 150, 243, 0.2); color: #2196F3; }
        .badge-basic { background: rgba(158, 158, 158, 0.2); color: #bbb; }
        .btn-reset { background: #ffa726; color: #000; padding: 6px 10px; border-radius: 4px; text-decoration: none; font-size: 0.8rem; font-weight: bold; }
    </style>
</head>
<body>

<div class="container">
    <div style="display:flex; justify-content:space-between; align-items:center;">
        <h1>Gestão de Usuários</h1>
        <a href="registro.php" style="background:var(--primary); color:#000; padding:10px; text-decoration:none; border-radius:6px; font-weight:bold;">+ Novo Usuário</a>
    </div>

    <?php if(isset($_GET['msg'])) echo "<p style='color:#66bb6a;'>✅ Acesso resetado com sucesso!</p>"; ?>

    <div class="card">
        <table>
            <thead>
                <tr>
                    <th>Nome</th>
                    <th>Nível</th>
                    <th>Status</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php while($user = $res_usuarios->fetch_assoc()): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($user['nome']) ?></strong><br><small><?= $user['usuario'] ?></small></td>
                    <td><span class="badge badge-<?= strtolower($user['nivel']) ?>"><?= $user['nivel'] ?></span></td>
                    <td><?= ($user['primeiro_login'] == 1) ? "🟡 Pendente" : "🟢 Ativo" ?></td>
                    <td>
                        <a href="?reset_id=<?= $user['id'] ?>" class="btn-reset" onclick="return confirm('Deseja resetar a senha deste usuário para 123456?')">🔄 Resetar</a>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
    <br><a href="painel.php" style="color:#888;">← Voltar ao Dashboard</a>
</div>
</body>
</html>
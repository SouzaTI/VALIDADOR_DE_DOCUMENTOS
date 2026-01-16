<?php
// Arquivo: notificar.php - AGORA COM PHPMailer (Implementação REAL de E-mail)

// 1. Inclui o autoloader do Composer (essencial para usar o PHPMailer)
require 'vendor/autoload.php'; 

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP; // Para usar SMTP::DEBUG_SERVER, se necessário

require_once 'log.php'; 

/**
 * Envia e-mail de notificação usando PHPMailer e credenciais SMTP.
 * @return bool Retorna TRUE para sucesso, FALSE para falha.
 */
function enviar_notificacao_email($validador_email, $validador_nome, $doc_id) {
    
    // As credenciais de DB/API não devem ficar em funções, 
    // mas vamos colocá-las aqui por enquanto para um teste rápido.
    // O ideal é usar variáveis de ambiente ou um arquivo de config.
    $SMTP_CONFIG = [
        'host' => 'email-ssl.com.br',
        'username' => 'helpdesk@comercialsouzaatacado.com.br',
        'password' => '@So1311@',
        'port' => 465, 
        'secure' => \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS
    ];

    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
    $status_envio = false;

    try {
        $mail->CharSet = 'UTF-8';
        
        // $mail->SMTPDebug = SMTP::DEBUG_SERVER; // Descomente para debug (MUITA informação)
        $mail->isSMTP();
        $mail->Host       = $SMTP_CONFIG['host'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $SMTP_CONFIG['username'];
        $mail->Password   = $SMTP_CONFIG['password'];
        $mail->SMTPSecure = $SMTP_CONFIG['secure']; 
        $mail->Port       = $SMTP_CONFIG['port'];
        
        // Remetente (De)
        $mail->setFrom($SMTP_CONFIG['username'], 'Sistema SVD (Não Responder)');

        // Destinatário (Para)
        $mail->addAddress($validador_email, $validador_nome);

        // Conteúdo
        $mail->isHTML(true);
        $mail->Subject = "⚠️ Documento Pendente: Ação Necessária (#{$doc_id})";
        
        // Mensagem HTML
        $body_html = "
            <div style='font-family: Arial, sans-serif; line-height: 1.6; color: #333; background-color: #f0f0f0; padding: 20px;'>
                
                <div style='max-width: 550px; margin: 0 auto; background-color: #ffffff; padding: 30px; border: 1px solid #e0e0e0; border-radius: 8px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);'>
                    
                    <h1 style='color: #4CAF50; font-size: 24px; margin-top: 0; margin-bottom: 20px;'>
                        🚨 AÇÃO NECESSÁRIA!
                    </h1>
                    
                    <p style='font-size: 16px; margin-bottom: 15px;'>
                        👋 Olá, <strong>{$validador_nome}</strong>!
                    </p>
                    
                    <p style='font-size: 16px; margin-bottom: 30px; padding: 10px; background-color: #f7fff7; border-left: 4px solid #66bb6a;'>
                        O documento de ID <strong>#{$doc_id}</strong> aguarda a sua 
                        <strong style='color: #388e3c;'>REVISÃO E ASSINATURA</strong> na sua Etapa do Workflow.
                    </p>
                    
                    <a href='http://192.168.0.63:8080/validador_documentos/painel.php' 
                       style='background-color: #66bb6a; 
                              color: white; 
                              padding: 15px 25px; 
                              text-decoration: none; 
                              border-radius: 5px; 
                              font-weight: bold; 
                              font-size: 18px;
                              display: inline-block;
                              box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);'>
                        IR PARA O PAINEL DE APROVAÇÕES
                    </a>
                    
                    <p style='margin-top: 30px; font-size: 13px; color: #777;'>
                        Este link é seguro e direciona você diretamente para a fila de documentos pendentes.
                    </p>
                    
                </div>
                
                <div style='text-align: center; margin-top: 20px; font-size: 12px; color: #999;'>
                    <hr style='border: none; border-top: 1px solid #e0e0e0; margin-bottom: 10px;'>
                    Mensagem automática do Sistema SVD (Suporte: helpdesk@comercialsouzaatacado.com.br).
                </div>
                
            </div>
        ";
        
        $mail->Body    = $body_html;
        $mail->AltBody = "Documento {$doc_id} aguardando revisão. Acesse o painel: http://192.168.0.63:8080/validador_documentos/painel.php";

        $mail->send();
        $status_envio = true;
        
    } catch (PHPMailer\PHPMailer\Exception $e) {
        // Em caso de erro, logamos a informação e retornamos FALSE
        write_log("ERRO SMTP PHPMailer para {$validador_email}: {$mail->ErrorInfo}", 'email_erro.log');
        $status_envio = false;
    }

    // Logamos o resultado da notificação
    $log_msg = "NOTIFICACAO E-MAIL: Documento {$doc_id} para {$validador_nome} ({$validador_email}). Status: " . ($status_envio ? 'SUCESSO' : 'FALHA');
    write_log($log_msg, 'email_log.log'); 
    
    return $status_envio; 
}

/**
 * Envia e-mail de notificação de REJEIÇÃO para o autor.
 * @return bool Retorna TRUE para sucesso, FALSE para falha.
 */
function enviar_notificacao_email_rejeicao($autor_email, $autor_nome, $doc_id, $validador_nome, $motivo_rejeicao) {
    
    // --- Configurações SMTP REAIS (COPIAR E COLAR DE enviar_notificacao_email) ---
    $SMTP_CONFIG = [
        'host' => 'email-ssl.com.br',
        'username' => 'helpdesk@comercialsouzaatacado.com.br',
        'password' => '@So1311@', // ATENÇÃO: Verifique a senha
        'port' => 465, 
        'secure' => \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS
    ];

    // FIX 32A: Usar a referência completa para resolver o Fatal Error
    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
    $status_envio = false;

    try {
        $mail->CharSet = 'UTF-8'; // CRÍTICO para acentuação
        $mail->isSMTP();
        $mail->Host       = $SMTP_CONFIG['host'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $SMTP_CONFIG['username'];
        $mail->Password   = $SMTP_CONFIG['password'];
        $mail->SMTPSecure = $SMTP_CONFIG['secure']; 
        $mail->Port       = $SMTP_CONFIG['port'];
        
        $mail->setFrom($SMTP_CONFIG['username'], 'Sistema SVD (NÃO Responder)');
        $mail->addAddress($autor_email, $autor_nome);

        $mail->isHTML(true);
        $mail->Subject = "❌ REJEIÇÃO: Documento #{$doc_id} Rejeitado (Correção Necessária)";
        
        // Mensagem HTML de REJEIÇÃO (Cor Vermelha e Box para o Comentário)
        $body_html = "
            <div style='font-family: Arial, sans-serif; line-height: 1.6; color: #333; background-color: #f0f0f0; padding: 20px;'>
                
                <div style='max-width: 550px; margin: 0 auto; background-color: #ffffff; padding: 30px; border: 1px solid #e0e0e0; border-radius: 8px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);'>
                    
                    <h1 style='color: #F44336; font-size: 24px; margin-top: 0; margin-bottom: 20px;'>
                        DOCUMENTO REJEITADO
                    </h1>
                    
                    <p style='font-size: 16px; margin-bottom: 15px;'>
                        👋 Olá, <strong>{$autor_nome}</strong>!
                    </p>
                    
                    <p style='font-size: 16px; margin-bottom: 10px; padding: 10px; background-color: #ffeaea; border-left: 4px solid #F44336;'>
                        Seu documento <strong>#{$doc_id}</strong> foi 
                        <strong style='color: #D32F2F;'>REJEITADO</strong> pelo validador <strong>{$validador_nome}</strong>. O fluxo foi interrompido e o documento aguarda sua correção.
                    </p>
                    
                    <h3 style='color: #333; margin-top: 20px;'>Motivo da Rejeição:</h3>
                    <div style='padding: 15px; border: 1px dashed #F44336; background-color: #fff8f8; border-radius: 4px; margin-bottom: 30px;'>
                        <p style='margin: 0; white-space: pre-wrap; font-style: italic;'>".htmlspecialchars($motivo_rejeicao)."</p>
                    </div>
                    
                    <a href='[http://192.168.0.63:8080/validador_documentos/upload.php](http://192.168.0.63:8080/validador_documentos/upload.php)' 
                       style='background-color: #007bff; 
                              color: white; 
                              padding: 15px 25px; 
                              text-decoration: none; 
                              border-radius: 5px; 
                              font-weight: bold; 
                              font-size: 18px;
                              display: inline-block;
                              box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);'>
                        FAZER NOVO UPLOAD (CORREÇÃO)
                    </a>
                    
                </div>
                
                <div style='text-align: center; margin-top: 20px; font-size: 12px; color: #999;'>
                    <hr style='border: none; border-top: 1px solid #e0e0e0; margin-bottom: 10px;'>
                    Mensagem automática do Sistema SVD (Suporte: helpdesk@comercialsouzaatacado.com.br).
                </div>
                
            </div>
        ";
        
        $mail->Body    = $body_html;
        $mail->AltBody = "Documento {$doc_id} rejeitado. Motivo: {$motivo_rejeicao}. Faça o upload da correção em: [http://192.168.0.63:8080/validador_documentos/upload.php](http://192.168.0.63:8080/validador_documentos/upload.php)";

        $mail->send();
        $status_envio = true;
        
    } catch (\PHPMailer\PHPMailer\Exception $e) { // FIX 32B: Corrigir o catch
        // Usa o namespace correto para Exception
        write_log("ERRO SMTP PHPMailer REJEIÇÃO para {$autor_email}: {$mail->ErrorInfo}", 'email_erro.log');
        $status_envio = false;
    }

    $log_msg = "NOTIFICACAO REJEIÇÃO: Documento {$doc_id} enviado para o autor ({$autor_email}). Status: " . ($status_envio ? 'SUCESSO' : 'FALHA');
    write_log($log_msg, 'email_log.log'); 
    
    return $status_envio; 
}


?>
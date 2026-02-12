<?php
// Arquivo: notificar.php - Versão Final com Alerta de Pendência
require 'vendor/autoload.php'; 

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once 'log.php'; 

/**
 * Envia e-mail de notificação de SUCESSO com detalhes dinâmicos.
 */
function enviar_notificacao_email($validador_email, $doc_id, $caminho_arquivo, $assinante_nome, $nome_arquivo) {
    
    $SMTP_CONFIG = [
        'host' => 'email-ssl.com.br',
        'username' => 'helpdesk@comercialsouzaatacado.com.br',
        'password' => '@So1311@',
        'port' => 465, 
        'secure' => PHPMailer::ENCRYPTION_SMTPS
    ];

    $mail = new PHPMailer(true);
    $status_envio = false;

    try {
        $mail->CharSet = 'UTF-8';
        $mail->isSMTP();
        $mail->Host       = $SMTP_CONFIG['host'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $SMTP_CONFIG['username'];
        $mail->Password   = $SMTP_CONFIG['password'];
        $mail->SMTPSecure = $SMTP_CONFIG['secure']; 
        $mail->Port       = $SMTP_CONFIG['port'];
        
        $mail->setFrom($SMTP_CONFIG['username'], 'Sistema SVD (Não Responder)');
        $mail->addAddress($validador_email);

        if ($caminho_arquivo && file_exists($caminho_arquivo)) {
            $mail->addAttachment($caminho_arquivo, "Assinado_#{$doc_id}_{$nome_arquivo}");
        }

        $mail->isHTML(true);
        $mail->Subject = "✅ Documento Assinado: {$nome_arquivo} (#{$doc_id})";
        
        $body_html = "
            <div style='font-family: Arial, sans-serif; line-height: 1.6; color: #333; background-color: #f0f0f0; padding: 20px;'>
                <div style='max-width: 550px; margin: 0 auto; background-color: #ffffff; padding: 30px; border: 1px solid #e0e0e0; border-radius: 8px;'>
                    <h1 style='color: #4CAF50; font-size: 24px; margin-top: 0;'>🚨 DOCUMENTO FINALIZADO!</h1>
                    <p style='font-size: 16px;'>Prezado(a),</p>
                    <p style='font-size: 16px; padding: 15px; background-color: #f7fff7; border-left: 4px solid #66bb6a;'>
                        Informamos que o documento <strong>" . htmlspecialchars($nome_arquivo) . "</strong> (ID #{$doc_id}) acaba de ser assinado digitalmente por <strong>" . htmlspecialchars($assinante_nome) . "</strong>.<br><br>
                        O arquivo final assinado <b>segue em anexo</b> para os devidos processos internos.
                    </p>
                    <div style='text-align: center; margin: 30px 0;'>
                        <a href='http://192.168.0.63:8080/validador_documentos/painel.php' 
                            style='background-color: #66bb6a; color: white; padding: 15px 25px; text-decoration: none; border-radius: 5px; font-weight: bold;'>
                            ACESSAR HISTÓRICO NO SISTEMA
                        </a>
                    </div>
                </div>
            </div>";
        
        $mail->Body = $body_html;
        $mail->AltBody = "O documento {$nome_arquivo} foi assinado por {$assinante_nome}. O PDF assinado segue em anexo.";

        $mail->send();
        $status_envio = true;
        
    } catch (Exception $e) {
        write_log("ERRO SMTP: {$mail->ErrorInfo}", 'email_erro.log');
        $status_envio = false;
    }

    return $status_envio; 
}

/**
 * Envia e-mail de notificação de REJEIÇÃO para quem fez o upload.
 */
function enviar_notificacao_email_rejeicao($autor_email, $autor_nome, $doc_id, $validador_nome, $motivo_rejeicao) {
    
    $SMTP_CONFIG = [
        'host' => 'email-ssl.com.br',
        'username' => 'helpdesk@comercialsouzaatacado.com.br',
        'password' => '@So1311@',
        'port' => 465, 
        'secure' => PHPMailer::ENCRYPTION_SMTPS
    ];

    $mail = new PHPMailer(true);
    $status_envio = false;

    try {
        $mail->CharSet = 'UTF-8';
        $mail->isSMTP();
        $mail->Host       = $SMTP_CONFIG['host'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $SMTP_CONFIG['username'];
        $mail->Password   = $SMTP_CONFIG['password'];
        $mail->SMTPSecure = $SMTP_CONFIG['secure']; 
        $mail->Port       = $SMTP_CONFIG['port'];
        
        $mail->setFrom($SMTP_CONFIG['username'], 'Sistema SVD (Não Responder)');
        $mail->addAddress($autor_email, $autor_nome);

        $mail->isHTML(true);
        $mail->Subject = "❌ REJEIÇÃO: Documento #{$doc_id} (Correção Necessária)";
        
        $body_html = "
            <div style='font-family: Arial, sans-serif; line-height: 1.6; color: #333; background-color: #f0f0f0; padding: 20px;'>
                <div style='max-width: 550px; margin: 0 auto; background-color: #ffffff; padding: 30px; border: 1px solid #e0e0e0; border-radius: 8px;'>
                    <h1 style='color: #F44336; font-size: 24px; margin-top: 0;'>DOCUMENTO REJEITADO</h1>
                    <p>Olá, <strong>{$autor_nome}</strong>!</p>
                    <p>Seu documento <strong>#{$doc_id}</strong> foi rejeitado por <strong>{$validador_nome}</strong>.</p>
                    <div style='padding: 15px; border: 1px dashed #F44336; background-color: #fff8f8; margin: 20px 0;'>
                        <strong>Motivo:</strong><br>
                        <i>" . htmlspecialchars($motivo_rejeicao) . "</i>
                    </div>
                    <a href='http://192.168.0.63:8080/validador_documentos/upload.php' 
                       style='background-color: #007bff; color: white; padding: 15px 25px; text-decoration: none; border-radius: 5px; font-weight: bold; display: inline-block;'>
                        FAZER NOVO UPLOAD
                    </a>
                </div>
            </div>";
        
        $mail->Body = $body_html;
        $mail->send();
        $status_envio = true;
        
    } catch (Exception $e) {
        write_log("ERRO SMTP REJEIÇÃO: {$mail->ErrorInfo}", 'email_erro.log');
        $status_envio = false;
    }

    return $status_envio; 
}

/**
 * Notifica o GESTOR que há um novo documento aguardando assinatura.
 */
function enviar_alerta_pendencia_gestor($gestor_email, $gestor_nome, $doc_id, $nome_arquivo, $remetente_nome) {
    
    $SMTP_CONFIG = [
        'host' => 'email-ssl.com.br',
        'username' => 'helpdesk@comercialsouzaatacado.com.br',
        'password' => '@So1311@',
        'port' => 465, 
        'secure' => PHPMailer::ENCRYPTION_SMTPS
    ];

    $mail = new PHPMailer(true);

    try {
        $mail->CharSet = 'UTF-8';
        $mail->isSMTP();
        $mail->Host       = $SMTP_CONFIG['host'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $SMTP_CONFIG['username'];
        $mail->Password   = $SMTP_CONFIG['password'];
        $mail->SMTPSecure = $SMTP_CONFIG['secure']; 
        $mail->Port       = $SMTP_CONFIG['port'];
        
        $mail->setFrom($SMTP_CONFIG['username'], 'Sistema SVD (Não Responder)');
        $mail->addAddress($gestor_email, $gestor_nome);

        $mail->isHTML(true);
        $mail->Subject = "⏳ Pendência de Assinatura: {$nome_arquivo} (#{$doc_id})";
        
        $body_html = "
            <div style='font-family: Arial, sans-serif; line-height: 1.6; color: #333; background-color: #f0f0f0; padding: 20px;'>
                <div style='max-width: 550px; margin: 0 auto; background-color: #ffffff; padding: 30px; border: 1px solid #e0e0e0; border-radius: 8px;'>
                    <h1 style='color: #f57c00; font-size: 22px; margin-top: 0;'>⚠️ NOVO DOCUMENTO PENDENTE</h1>
                    <p>Olá, <strong>" . htmlspecialchars($gestor_nome) . "</strong>,</p>
                    <p>Informamos que um novo documento foi enviado para sua análise e assinatura.</p>
                    <div style='padding: 15px; background-color: #fff8f1; border-left: 4px solid #f57c00; margin: 20px 0;'>
                        <strong>Documento:</strong> " . htmlspecialchars($nome_arquivo) . "<br>
                        <strong>Enviado por:</strong> " . htmlspecialchars($remetente_nome) . "<br>
                        <strong>ID:</strong> #{$doc_id}
                    </div>
                    <div style='text-align: center; margin: 30px 0;'>
                        <a href='http://192.168.0.63:8080/validador_documentos/validar.php?doc_id={$doc_id}' 
                           style='background-color: #66bb6a; color: #000; padding: 15px 25px; text-decoration: none; border-radius: 5px; font-weight: bold;'>
                           ACESSAR PARA REVISAR E ASSINAR
                        </a>
                    </div>
                </div>
            </div>";
        
        $mail->Body = $body_html;
        $mail->send();
        return true;
        
    } catch (Exception $e) {
        write_log("ERRO SMTP ALERTA GESTOR: {$mail->ErrorInfo}", 'email_erro.log');
        return false;
    }
}
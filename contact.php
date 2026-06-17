<?php
header('Content-Type: application/json; charset=utf-8');

// ── Configurações SMTP OVH ─────────────────────────────────
$smtp_host = 'ssl0.ovh.net';
$smtp_port = 465;
$smtp_user = 'geral@hncn.pt';
$smtp_pass = 'PALAVRA_PASSE_EMAIL';   // ← alterar para a password do email

$destinatario = 'geral@hncn.pt';

// ── Recolher e sanitizar campos ────────────────────────────
$nome     = isset($_POST['nome'])     ? trim(strip_tags($_POST['nome']))     : '';
$email    = isset($_POST['email'])    ? trim(strip_tags($_POST['email']))    : '';
$telefone = isset($_POST['telefone']) ? trim(strip_tags($_POST['telefone'])) : '';
$mensagem = isset($_POST['mensagem']) ? trim(strip_tags($_POST['mensagem'])) : '';

if (!$nome || !$email || !$mensagem) {
    echo json_encode(['success' => false, 'error' => 'Campos obrigatórios em falta.']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'error' => 'Email inválido.']);
    exit;
}

// ── Função SMTP ────────────────────────────────────────────
function smtp_send($host, $port, $user, $pass, $from, $from_name, $to, $subject, $body) {
    $socket = @fsockopen("ssl://{$host}", $port, $errno, $errstr, 30);
    if (!$socket) return false;

    $read = function() use ($socket) { return fgets($socket, 512); };
    $write = function($cmd) use ($socket) { fputs($socket, $cmd . "\r\n"); };

    $read(); // 220 greeting

    $write("EHLO hncn.pt");
    while ($line = $read()) { if (substr($line, 3, 1) === ' ') break; }

    $write("AUTH LOGIN");
    $read();
    $write(base64_encode($user));
    $read();
    $write(base64_encode($pass));
    $r = $read();
    if (strpos($r, '235') === false) { fclose($socket); return false; }

    $write("MAIL FROM:<{$from}>");
    $read();
    $write("RCPT TO:<{$to}>");
    $read();
    $write("DATA");
    $read();

    $subj_enc = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    $from_enc = '=?UTF-8?B?' . base64_encode($from_name) . '?=';
    $headers  = "From: {$from_enc} <{$from}>\r\n";
    $headers .= "To: {$to}\r\n";
    $headers .= "Subject: {$subj_enc}\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $headers .= "Content-Transfer-Encoding: 8bit\r\n";

    $write($headers . "\r\n" . $body . "\r\n.");
    $r = $read();
    $write("QUIT");
    fclose($socket);

    return strpos($r, '250') !== false;
}

// ── Email para a HNCN ──────────────────────────────────────
$corpo_hncn  = "Nova mensagem recebida através do formulário do site hncn.pt\r\n\r\n";
$corpo_hncn .= "Nome:      {$nome}\r\n";
$corpo_hncn .= "Email:     {$email}\r\n";
$corpo_hncn .= "Telefone:  " . ($telefone ?: '-') . "\r\n\r\n";
$corpo_hncn .= "Mensagem:\r\n{$mensagem}\r\n";

$enviado = smtp_send(
    $smtp_host, $smtp_port, $smtp_user, $smtp_pass,
    $smtp_user, 'HNCN Construções',
    $destinatario,
    'Novo contacto via site HNCN - ' . $nome,
    $corpo_hncn
);

// ── Email de confirmação para o remetente ──────────────────
if ($enviado) {
    $corpo_conf  = "Olá {$nome},\r\n\r\n";
    $corpo_conf .= "A sua mensagem foi recebida com sucesso pela HNCN Construções Lda.\r\n";
    $corpo_conf .= "Entraremos em contacto brevemente.\r\n\r\n";
    $corpo_conf .= "---\r\n";
    $corpo_conf .= "Resumo da sua mensagem:\r\n\r\n";
    $corpo_conf .= "{$mensagem}\r\n\r\n";
    $corpo_conf .= "---\r\n";
    $corpo_conf .= "HNCN Construções Lda\r\n";
    $corpo_conf .= "geral@hncn.pt\r\n";
    $corpo_conf .= "www.hncn.pt\r\n";

    smtp_send(
        $smtp_host, $smtp_port, $smtp_user, $smtp_pass,
        $smtp_user, 'HNCN Construções',
        $email,
        'A sua mensagem foi recebida - HNCN Construções',
        $corpo_conf
    );

    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Não foi possível enviar o email. Tente novamente.']);
}

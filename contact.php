<?php
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método não permitido.']);
    exit;
}

// ── Configurações SMTP (ficheiro fora do Git — ver smtp-config.example.php) ──
$config_file = __DIR__ . '/smtp-config.php';
if (!file_exists($config_file)) {
    error_log('contact.php: smtp-config.php em falta');
    echo json_encode(['success' => false, 'error' => 'Não foi possível enviar o email. Tente novamente.']);
    exit;
}
require_once $config_file;

// ── Anti-spam: campo escondido que só os bots preenchem ──────────────────────
if (!empty($_POST['website'])) {
    echo json_encode(['success' => true]); // fingir sucesso para não dar pistas ao bot
    exit;
}

// ── Recolher e sanitizar campos ──────────────────────────────────────────────
function campo($nome, $max) {
    $v = isset($_POST[$nome]) ? trim(strip_tags($_POST[$nome])) : '';
    return mb_substr($v, 0, $max, 'UTF-8');
}
// Remover quebras de linha em campos de uma só linha
$nome     = str_replace(["\r", "\n"], ' ', campo('nome', 100));
$email    = str_replace(["\r", "\n"], '',  campo('email', 254));
$telefone = str_replace(["\r", "\n"], ' ', campo('telefone', 30));
$mensagem = campo('mensagem', 5000);

if (!$nome || !$email || !$mensagem) {
    echo json_encode(['success' => false, 'error' => 'Campos obrigatórios em falta.']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'error' => 'Email inválido.']);
    exit;
}

// ── Função SMTP ──────────────────────────────────────────────────────────────
function smtp_send($host, $port, $user, $pass, $from, $from_name, $to, $subject, $body, $reply_to = null) {
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
    if ($reply_to) $headers .= "Reply-To: {$reply_to}\r\n";
    $headers .= "Subject: {$subj_enc}\r\n";
    $headers .= "Date: " . date('r') . "\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $headers .= "Content-Transfer-Encoding: 8bit\r\n";

    // Normalizar quebras de linha e aplicar "dot-stuffing" (uma linha só com
    // "." terminaria a mensagem a meio)
    $body = preg_replace("/\r\n|\r|\n/", "\r\n", $body);
    $body = preg_replace('/^\./m', '..', $body);

    $write($headers . "\r\n" . $body . "\r\n.");
    $r = $read();
    $write("QUIT");
    fclose($socket);

    return strpos($r, '250') !== false;
}

// ── Email para a HNCN ────────────────────────────────────────────────────────
$corpo_hncn  = "Nova mensagem recebida através do formulário do site hncn.pt\r\n\r\n";
$corpo_hncn .= "Nome:      {$nome}\r\n";
$corpo_hncn .= "Email:     {$email}\r\n";
$corpo_hncn .= "Telefone:  " . ($telefone ?: '-') . "\r\n\r\n";
$corpo_hncn .= "Mensagem:\r\n{$mensagem}\r\n";

$enviado = smtp_send(
    SMTP_HOST, SMTP_PORT, SMTP_USER, SMTP_PASS,
    SMTP_USER, 'HNCN Construções',
    CONTACT_TO,
    'Novo contacto via site HNCN - ' . $nome,
    $corpo_hncn,
    $email   // "Responder" vai diretamente para quem escreveu
);

// ── Email de confirmação para o remetente ────────────────────────────────────
// Texto fixo, sem repetir o que foi escrito no formulário: assim o formulário
// não pode ser usado para enviar mensagens arbitrárias a terceiros a partir
// de geral@hncn.pt.
if ($enviado) {
    $corpo_conf  = "Olá,\r\n\r\n";
    $corpo_conf .= "A sua mensagem foi recebida com sucesso pela HNCN Construções Lda.\r\n";
    $corpo_conf .= "Entraremos em contacto brevemente.\r\n\r\n";
    $corpo_conf .= "Se não enviou nenhuma mensagem através do site hncn.pt, pode ignorar este email.\r\n\r\n";
    $corpo_conf .= "---\r\n";
    $corpo_conf .= "HNCN Construções Lda\r\n";
    $corpo_conf .= "geral@hncn.pt\r\n";
    $corpo_conf .= "www.hncn.pt\r\n";

    smtp_send(
        SMTP_HOST, SMTP_PORT, SMTP_USER, SMTP_PASS,
        SMTP_USER, 'HNCN Construções',
        $email,
        'A sua mensagem foi recebida - HNCN Construções',
        $corpo_conf
    );

    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Não foi possível enviar o email. Tente novamente.']);
}

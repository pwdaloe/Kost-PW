<?php

/**
 * Kirim email via PHP mail() bawaan cPanel.
 * From harus dari domain sendiri agar tidak masuk spam.
 */
function sendMail(string $to, string $subject, string $body): bool {
    $fromName    = 'Kost PW';
    $fromAddress = 'noreply@kost.purwandaru.com';

    $headers = implode("\r\n", [
        'From: ' . $fromName . ' <' . $fromAddress . '>',
        'Reply-To: ' . $fromAddress,
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8',
        'X-Mailer: PHP/' . phpversion(),
    ]);

    return mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $body, $headers);
}

function mailTemplate(string $title, string $body): string {
    return '<!DOCTYPE html>
<html lang="id">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<style>
  body{font-family:Segoe UI,Arial,sans-serif;background:#f0f2f5;margin:0;padding:24px}
  .wrap{max-width:520px;margin:0 auto;background:#fff;border-radius:10px;overflow:hidden;box-shadow:0 2px 12px rgba(0,0,0,.08)}
  .hdr{background:#003087;color:#fff;padding:24px 32px;text-align:center}
  .hdr h2{margin:0;font-size:1.2rem;letter-spacing:.3px}
  .body{padding:28px 32px;color:#374151;line-height:1.7;font-size:.95rem}
  .btn{display:inline-block;background:#003087;color:#fff;padding:12px 28px;border-radius:7px;text-decoration:none;font-weight:700;margin:16px 0}
  .footer{padding:16px 32px;background:#f8f9fa;color:#9ca3af;font-size:.8rem;text-align:center;border-top:1px solid #e5e7eb}
  .alert-box{background:#fef3c7;border-left:4px solid #f59e0b;padding:12px 16px;border-radius:4px;margin:12px 0;font-size:.875rem}
</style>
</head>
<body>
<div class="wrap">
  <div class="hdr"><h2>🏠 Kost PW — ' . htmlspecialchars($title) . '</h2></div>
  <div class="body">' . $body . '</div>
  <div class="footer">Email ini dikirim otomatis oleh sistem Kost PW · kost.purwandaru.com</div>
</div>
</body></html>';
}

<?php
declare(strict_types=1);

namespace PIZ\Services;

use PIZ\Settings;

final class Mailer
{
    public static function sendPasswordReset(array $user, string $link): bool
    {
        $name = (string) ($user['name'] ?? 'пользователь');
        $store = (string) Settings::get('store_name', 'КИОСК ДОНЕР');
        $subject = 'Восстановление пароля — ' . $store;
        $safeName = htmlspecialchars($name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $safeLink = htmlspecialchars($link, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $html = '<!doctype html><html lang="ru"><body style="margin:0;background:#f4f6f9;font-family:Arial,sans-serif;color:#1f2937">'
            . '<div style="max-width:560px;margin:0 auto;padding:28px 16px">'
            . '<div style="background:#141821;border-radius:16px 16px 0 0;padding:22px 24px;color:#fff">'
            . '<div style="font-size:22px;font-weight:700">' . htmlspecialchars($store, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</div>'
            . '<div style="font-size:12px;color:#aeb7c5;margin-top:4px">учёт продаж</div></div>'
            . '<div style="background:#fff;padding:26px 24px;border-radius:0 0 16px 16px">'
            . '<h1 style="font-size:20px;margin:0 0 14px">Восстановление пароля</h1>'
            . '<p style="font-size:14px;line-height:1.6">Здравствуйте, <strong>' . $safeName . '</strong>!</p>'
            . '<p style="font-size:14px;line-height:1.6">Вы запросили новый пароль для входа. Ссылка действует <strong>60 минут</strong>.</p>'
            . '<p style="margin:22px 0"><a href="' . $safeLink . '" style="display:inline-block;background:#f26b21;color:#fff;text-decoration:none;padding:13px 22px;border-radius:10px;font-weight:700">Сбросить пароль</a></p>'
            . '<p style="font-size:12px;color:#6b7280;line-height:1.6">Если кнопка не работает, скопируйте ссылку:<br><a href="' . $safeLink . '" style="color:#c2410c;word-break:break-all">' . $safeLink . '</a></p>'
            . '<p style="font-size:12px;color:#6b7280;line-height:1.6;margin-top:18px">Если вы не запрашивали восстановление, просто проигнорируйте это письмо.</p>'
            . '</div></div></body></html>';
        $text = "Восстановление пароля — {$store}\n\n"
            . "Здравствуйте, {$name}!\n\n"
            . "Ссылка для сброса пароля действует 60 минут:\n{$link}\n\n"
            . "Если вы не запрашивали восстановление, проигнорируйте письмо.\n";
        return self::send((string) $user['email'], $subject, $html, $text);
    }

    public static function send(string $to, string $subject, string $html, string $text): bool
    {
        if (!filter_var($to, FILTER_VALIDATE_EMAIL) || !function_exists('mail')) {
            return false;
        }
        $fromEmail = (string) Settings::get('mail_from_email', 'no-reply@piz.itop7.ru');
        if (!filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
            $fromEmail = 'no-reply@piz.itop7.ru';
        }
        $store = (string) Settings::get('store_name', 'КИОСК ДОНЕР');
        $fromName = '=?UTF-8?B?' . base64_encode($store) . '?=';
        $boundary = 'piz-' . bin2hex(random_bytes(12));
        $body = '--' . $boundary . "\r\n"
            . "Content-Type: text/plain; charset=UTF-8\r\n"
            . "Content-Transfer-Encoding: base64\r\n\r\n"
            . chunk_split(base64_encode($text)) . "\r\n"
            . '--' . $boundary . "\r\n"
            . "Content-Type: text/html; charset=UTF-8\r\n"
            . "Content-Transfer-Encoding: base64\r\n\r\n"
            . chunk_split(base64_encode($html)) . "\r\n"
            . '--' . $boundary . "--\r\n";
        $headers = [
            'MIME-Version: 1.0',
            'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
            'From: ' . $fromName . ' <' . $fromEmail . '>',
            'Reply-To: ' . $fromEmail,
            'X-Mailer: PIZ',
        ];
        $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
        $sent = @mail($to, $encodedSubject, $body, implode("\r\n", $headers), '-f' . $fromEmail);
        if (!$sent) {
            $sent = @mail($to, $encodedSubject, $body, implode("\r\n", $headers));
        }
        if (!$sent) {
            error_log('[PIZ] mail failed for ' . $to);
        }
        return (bool) $sent;
    }
}

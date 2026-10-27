<?php
/**
 * Yardımcı Fonksiyonlar, Güvenlik ve Erişilebilirlik Destekleri
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

// HTML Güvenli Çıktı (XSS Önleme)
function e(?string $str): string {
    return htmlspecialchars((string)($str ?? ''), ENT_QUOTES, 'UTF-8');
}

// Para Birimi Formatı
function format_currency(float|int|string $amount, string $symbol = DEFAULT_CURRENCY): string {
    $num = (float)$amount;
    return number_format($num, 2, ',', '.') . ' ' . $symbol;
}

// Sayı Formatı
function format_number(float|int|string $number, int $decimals = 2): string {
    $num = (float)$number;
    // Eğer tamsayı ise ve ondalık kısmı sıfırsa tamsayı gibi gösterebiliriz
    if (floor($num) == $num) {
        return number_format($num, 0, ',', '.');
    }
    return number_format($num, $decimals, ',', '.');
}

// Tarih Formatı
function format_date(?string $date, string $format = 'd.m.Y'): string {
    if (!$date) return '-';
    $time = strtotime($date);
    return $time ? date($format, $time) : '-';
}

// Tarih Saat Formatı
function format_datetime(?string $datetime, string $format = 'd.m.Y H:i'): string {
    if (!$datetime) return '-';
    $time = strtotime($datetime);
    return $time ? date($format, $time) : '-';
}

// Stok Hareket Tipi Rozeti (Erişilebilir ve Anlaşılır)
function get_movement_badge(string $type): string {
    switch ($type) {
        case 'in':
            return '<span class="badge badge-success" role="status" aria-label="Stok Girişi: Depoya ürün eklendi"><span class="badge-icon" aria-hidden="true">↓</span> Giriş</span>';
        case 'out':
            return '<span class="badge badge-danger" role="status" aria-label="Stok Çıkışı: Depodan ürün sevk edildi"><span class="badge-icon" aria-hidden="true">↑</span> Çıkış</span>';
        case 'transfer':
            return '<span class="badge badge-info" role="status" aria-label="Depo Transferi: Konumlar arası aktarım"><span class="badge-icon" aria-hidden="true">⇄</span> Transfer</span>';
        case 'adjustment':
            return '<span class="badge badge-warning" role="status" aria-label="Stok Düzeltme / Sayım"><span class="badge-icon" aria-hidden="true">⚙</span> Sayım/Düzeltme</span>';
        default:
            return '<span class="badge badge-secondary">' . e($type) . '</span>';
    }
}

// Stok Durumu Rozeti
function get_stock_badge(float $current, float $min): string {
    if ($current <= 0) {
        return '<span class="badge badge-danger" role="status" aria-label="Tükendi: Stokta ürün kalmadı"><span class="badge-dot" aria-hidden="true"></span> Tükendi (0)</span>';
    } elseif ($current <= $min) {
        return '<span class="badge badge-warning" role="status" aria-label="Kritik Stok: ' . format_number($current) . ' kaldı, minimum ' . format_number($min) . '"><span class="badge-dot" aria-hidden="true"></span> Kritik (' . format_number($current) . ')</span>';
    } else {
        return '<span class="badge badge-success" role="status" aria-label="Yeterli Stok: ' . format_number($current) . ' adet mevcut"><span class="badge-dot" aria-hidden="true"></span> Yeterli (' . format_number($current) . ')</span>';
    }
}

// Sistem Günlüğü (İşlem Denetim İzi)
function log_activity(string $action, ?string $details = null): void {
    try {
        $db = get_db();
        $user = current_user();
        $userId = $user ? $user['id'] : null;
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

        $stmt = $db->prepare('INSERT INTO activity_logs (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)');
        $stmt->execute([$userId, $action, $details, $ip]);
    } catch (Exception $e) {
        error_log("Log yazma hatası: " . $e->getMessage());
    }
}

// Sistem Ayarını Çekme
function get_setting(string $key, string $default = ''): string {
    try {
        $db = get_db();
        $stmt = $db->prepare('SELECT setting_value FROM settings WHERE setting_key = ?');
        $stmt->execute([$key]);
        $val = $stmt->fetchColumn();
        return $val !== false ? (string)$val : $default;
    } catch (Exception $e) {
        return $default;
    }
}

// Telegram Bildirim ve 2FA Gönderimi
function send_telegram_message(string $bot_token, string $chat_id, string $text): array {
    if (empty($bot_token) || empty($chat_id)) {
        return ['success' => false, 'error' => 'Telegram Bot Token veya Chat ID eksik.'];
    }

    $url = "https://api.telegram.org/bot{$bot_token}/sendMessage";
    $post_data = http_build_query([
        'chat_id' => $chat_id,
        'text' => $text,
        'parse_mode' => 'HTML'
    ]);

    $opts = [
        'http' => [
            'method'  => 'POST',
            'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
            'content' => $post_data,
            'timeout' => 8,
            'ignore_errors' => true
        ]
    ];

    $context = stream_context_create($opts);
    $result = @file_get_contents($url, false, $context);

    if ($result === false) {
        return ['success' => false, 'error' => 'Telegram API sunucusuna ulaşılamadı.'];
    }

    $response = json_decode($result, true);
    if (!empty($response['ok'])) {
        return ['success' => true];
    } else {
        return ['success' => false, 'error' => $response['description'] ?? 'Telegram hatası'];
    }
}

// Ayar Güncelleme
function set_setting(string $key, string $value): bool {
    try {
        $db = get_db();
        $stmt = $db->prepare('INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) 
                              ON CONFLICT(setting_key) DO UPDATE SET setting_value = excluded.setting_value');
        return $stmt->execute([$key, $value]);
    } catch (Exception $e) {
        // MySQL ON DUPLICATE KEY UPDATE alternatifi
        try {
            $stmt = $db->prepare('INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) 
                                  ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');
            return $stmt->execute([$key, $value]);
        } catch (Exception $ex) {
            return false;
        }
    }
}

// Otomatik Benzersiz SKU / Ürün Kodu Üretici
function generate_sku(): string {
    return 'PRD-' . date('Ym') . '-' . strtoupper(bin2hex(random_bytes(2)));
}

// Erişilebilir Sayfalama HTML Bileşeni
function render_pagination(int $total_items, int $per_page, int $current_page, string $base_url): string {
    $total_pages = (int)ceil($total_items / $per_page);
    if ($total_pages <= 1) {
        return '';
    }

    $separator = (str_contains($base_url, '?')) ? '&' : '?';

    $html = '<nav class="pagination-nav" aria-label="Sayfalama Gezintisi">';
    $html .= '<ul class="pagination">';

    // Önceki Butonu
    if ($current_page > 1) {
        $prev_url = e($base_url . $separator . 'page=' . ($current_page - 1));
        $html .= '<li class="page-item"><a class="page-link" href="' . $prev_url . '" aria-label="Önceki Sayfa">&laquo; Önceki</a></li>';
    } else {
        $html .= '<li class="page-item disabled"><span class="page-link" aria-hidden="true">&laquo; Önceki</span></li>';
    }

    // Sayfa Numaraları
    for ($i = 1; $i <= $total_pages; $i++) {
        if ($i === 1 || $i === $total_pages || ($i >= $current_page - 2 && $i <= $current_page + 2)) {
            $page_url = e($base_url . $separator . 'page=' . $i);
            if ($i === $current_page) {
                $html .= '<li class="page-item active" aria-current="page"><span class="page-link">' . $i . ' <span class="sr-only">(Geçerli Sayfa)</span></span></li>';
            } else {
                $html .= '<li class="page-item"><a class="page-link" href="' . $page_url . '" aria-label="Sayfa ' . $i . '">' . $i . '</a></li>';
            }
        } elseif ($i === $current_page - 3 || $i === $current_page + 3) {
            $html .= '<li class="page-item disabled"><span class="page-link">&hellip;</span></li>';
        }
    }

    // Sonraki Butonu
    if ($current_page < $total_pages) {
        $next_url = e($base_url . $separator . 'page=' . ($current_page + 1));
        $html .= '<li class="page-item"><a class="page-link" href="' . $next_url . '" aria-label="Sonraki Sayfa">Sonraki &raquo;</a></li>';
    } else {
        $html .= '<li class="page-item disabled"><span class="page-link" aria-hidden="true">Sonraki &raquo;</span></li>';
    }

    $html .= '</ul>';
    $html .= '<div class="pagination-info" aria-live="polite">Toplam ' . $total_items . ' kayıttan ' . (($current_page - 1) * $per_page + 1) . ' - ' . min($current_page * $per_page, $total_items) . ' arası gösteriliyor.</div>';
    $html .= '</nav>';

    return $html;
}

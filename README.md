# Süper Depo Scripti (PHP & MySQL)

Süper Depo Scripti; modern, tamamen erişilebilir (WCAG 2.1 AA uyumlu), güvenli, PHP ve MySQL tabanlı bir depo ve stok yönetim sistemidir. Depoya giren (mal kabul) ve çıkan (sevkiyat) tüm ürünlerin kayıt altına alınmasını, kritik stok uyarılarını, depo içi raf transferlerini, WordPress/WooCommerce mağaza senkronizasyonunu ve Telegram 2FA güvenlik doğrulamasını eksiksiz olarak sunar.

---

## 🌟 Öne Çıkan Özellikler

### 1. Kapsamlı Stok ve Depo Yönetimi
- **Ürün Yönetimi:** SKU, barkod, ürün adı, birim (Adet, Kg, Koli, Litre vb.), alış ve satış fiyatları, minimum kritik stok eşikleri.
- **Depoya Ürün Girişi (Mal Kabul):** Tedarikçi seçimi, irsaliye/fatura numarası, birim alış maliyeti, miktar ve parti notları.
- **Depodan Ürün Çıkışı (Sevkiyat):** Müşteri seçimi, çıkış nedeni (Satış, Mağaza Sevkiyatı, Fire/Hasar, İade), negatif stok önleme koruması.
- **Stok Hareket Geçmişi:** Tarih, işlem tipi, miktar, işlem öncesi/sonrası stok, belge no ve işlemi yapan kullanıcı detayları.
- **Sayım & Konum Transferi:** Fiziksel sayım sonrası mutabakat düzeltmeleri ve depolar/raflar arası ürün transferi.
- **Barkod & Raf Etiketi:** Ürün bazlı barkod okuyucu desteği ve doğrudan yazdırılabilir (`window.print`) raf etiketleri.

### 2. Tam Erişilebilirlik (Accessibility - WCAG 2.1 AA)
- **Ekran Okuyucu Desteği:** Semantik HTML5, `aria-label`, `aria-live`, `aria-current`, `role` ve durum rozetleri.
- **Atlama Bağlantısı (Skip Link):** Klavye kullanıcıları için doğrudan ana içeriğe atlama olanağı.
- **Görsel Tercihler:** 
  - 🌓 Aydınlık ve Karanlık mod geçişi
  - ◐ Yüksek Kontrast modu
  - 🔠 Yazı boyutu ayarlama (A- / A / A+)
- **Klavye Kısayolları:**
  - `Alt + N` : Yeni Ürün Ekle
  - `Alt + G` : Ürün Girişi (Mal Kabul)
  - `Alt + C` : Ürün Çıkışı (Sevkiyat)
  - `Alt + H` : Stok Hareket Geçmişi
  - `Alt + S` : Arama Kutusuna Odaklan

### 3. Gelişmiş Giriş Güvenliği & Telegram 2FA
- **Telegram İki Adımlı Doğrulama (2FA):** Ayarlar ekranından girilen Telegram Bot Token ve Chat ID ile her girişte tek kullanımlık 6 haneli güvenlik kodu gönderimi.
- **Güvenlik Mimarisi:** Oturum sabitleme koruması (`session_regenerate_id`), CSRF belirteç doğrulaması, XSS sanitizasyonu ve BCrypt şifreleme.

### 4. WordPress & WooCommerce Entegrasyonu
- **Webhook Entegrasyonu:** WooCommerce mağazanızda yeni bir sipariş verildiğinde deponuzdaki stokların otomatik olarak düşülmesi.
- **Çift Yönlü Eşitleme:** Depo stoklarını mağazaya aktarma ve WooCommerce ürünlerini depoya içe aktarma desteği.
- **REST API:** Barkod okuyucular ve dış entegrasyonlar için JSON API (`/api.php?action=get_stock&sku=...`).

### 5. Raporlama ve Dışa Aktarım
- Kategori bazlı envanter değer analizi (maliyet, satış değeri, kâr marjı).
- Kritik seviyedeki ve tükenen ürünler raporu ve sipariş tavsiyeleri.
- Tek tıkla Türkçe Excel uyumlu UTF-8 BOM destekli CSV dışa aktarımı.

---

## 🚀 Kurulum

### Gereksinimler
- PHP 8.1 veya üzeri
- `pdo_mysql` (üretim sunucuları) veya `pdo_sqlite` (taşınabilir/yerel)
- `mbstring`, `openssl`, `curl`

### 1. Adım: Veritabanını Hazırlayın
MySQL sunucunuzda bir veritabanı oluşturun ve `database.sql` dosyasını içe aktarın:
```bash
mysql -u root -p super_depo < database.sql
```

### 2. Adım: Yapılandırma
`config.php` dosyasındaki veritabanı bilgilerini düzenleyin:
```php
define('DB_DRIVER', 'mysql'); // veya 'sqlite'
define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'super_depo');
define('DB_USER', 'root');
define('DB_PASS', 'parolaniz');
```

### 3. Adım: Web Sunucusunu Başlatın
Geliştirme için PHP dahili sunucusunu kullanabilirsiniz:
```bash
php -S 127.0.0.1:8000
```
Tarayıcınızdan `http://127.0.0.1:8000/login.php` adresine gidin.

### Varsayılan Giriş Bilgileri:
- **Yönetici:** `admin` / Şifre: `admin123`
- **Depocu:** `depocu` / Şifre: `admin123`

---

## 🧪 Testler
Otomatik test süitini çalıştırmak için:
```bash
php tests/test_system.php
```

---

## 📄 Lisans
Bu proje MIT lisansı ile lisanslanmıştır.

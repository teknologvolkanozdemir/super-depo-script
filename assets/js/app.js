/**
 * Süper Depo Scripti - Erişilebilirlik ve Etkileşim Scripti
 */

document.addEventListener('DOMContentLoaded', () => {
    // 1. Canlı Duyuru Alanı (Screen Reader Announcement Region)
    let liveRegion = document.getElementById('a11y-live-region');
    if (!liveRegion) {
        liveRegion = document.createElement('div');
        liveRegion.id = 'a11y-live-region';
        liveRegion.className = 'sr-only';
        liveRegion.setAttribute('role', 'status');
        liveRegion.setAttribute('aria-live', 'polite');
        liveRegion.setAttribute('aria-atomic', 'true');
        document.body.appendChild(liveRegion);
    }

    window.announceA11y = function(message) {
        if (liveRegion) {
            liveRegion.textContent = '';
            setTimeout(() => {
                liveRegion.textContent = message;
            }, 100);
        }
    };

    // 2. Tema ve Yüksek Kontrast Yönetimi
    const root = document.documentElement;
    const themeToggleBtn = document.getElementById('theme-toggle-btn');
    const contrastToggleBtn = document.getElementById('contrast-toggle-btn');

    // Kayıtlı tercihleri uygula
    const savedTheme = localStorage.getItem('super_depo_theme') || 
        (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
    const savedContrast = localStorage.getItem('super_depo_contrast') === 'true';

    root.setAttribute('data-theme', savedTheme);
    if (savedContrast) {
        root.setAttribute('data-high-contrast', 'true');
    }

    if (themeToggleBtn) {
        themeToggleBtn.setAttribute('aria-pressed', savedTheme === 'dark');
        themeToggleBtn.addEventListener('click', () => {
            const currentTheme = root.getAttribute('data-theme') || 'light';
            const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
            root.setAttribute('data-theme', newTheme);
            localStorage.setItem('super_depo_theme', newTheme);
            themeToggleBtn.setAttribute('aria-pressed', newTheme === 'dark');
            announceA11y(newTheme === 'dark' ? 'Karanlık tema etkinleştirildi.' : 'Aydınlık tema etkinleştirildi.');
        });
    }

    if (contrastToggleBtn) {
        contrastToggleBtn.setAttribute('aria-pressed', savedContrast);
        contrastToggleBtn.addEventListener('click', () => {
            const isHigh = root.getAttribute('data-high-contrast') === 'true';
            const newStatus = !isHigh;
            if (newStatus) {
                root.setAttribute('data-high-contrast', 'true');
            } else {
                root.removeAttribute('data-high-contrast');
            }
            localStorage.setItem('super_depo_contrast', newStatus);
            contrastToggleBtn.setAttribute('aria-pressed', newStatus);
            announceA11y(newStatus ? 'Yüksek kontrast modu açıldı.' : 'Yüksek kontrast modu kapatıldı.');
        });
    }

    // 3. Yazı Boyutu Ayarları (Font Resizing)
    const fontIncBtn = document.getElementById('font-inc-btn');
    const fontDecBtn = document.getElementById('font-dec-btn');
    const fontResetBtn = document.getElementById('font-reset-btn');

    let currentFontSize = parseFloat(localStorage.getItem('super_depo_font_size') || '16');

    function applyFontSize(size) {
        currentFontSize = Math.min(Math.max(size, 14), 24); // 14px ile 24px arası
        root.style.fontSize = currentFontSize + 'px';
        localStorage.setItem('super_depo_font_size', currentFontSize);
    }

    if (currentFontSize !== 16) {
        applyFontSize(currentFontSize);
    }

    if (fontIncBtn) {
        fontIncBtn.addEventListener('click', () => {
            applyFontSize(currentFontSize + 2);
            announceA11y(`Yazı boyutu büyütüldü: ${currentFontSize} piksel.`);
        });
    }

    if (fontDecBtn) {
        fontDecBtn.addEventListener('click', () => {
            applyFontSize(currentFontSize - 2);
            announceA11y(`Yazı boyutu küçültüldü: ${currentFontSize} piksel.`);
        });
    }

    if (fontResetBtn) {
        fontResetBtn.addEventListener('click', () => {
            applyFontSize(16);
            announceA11y('Yazı boyutu varsayılana sıfırlandı: 16 piksel.');
        });
    }

    // 4. Klavye Kısayolları (Hızlı Depo İşlemleri)
    document.addEventListener('keydown', (e) => {
        // Form alanlarında yazı yazarken kısayolların çakışmaması için
        const isEditing = ['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement.tagName);

        // Alt + N -> Yeni Ürün
        if (e.altKey && (e.key === 'n' || e.key === 'N')) {
            e.preventDefault();
            window.location.href = 'product-add.php';
        }
        // Alt + G -> Ürün Girişi
        else if (e.altKey && (e.key === 'g' || e.key === 'G')) {
            e.preventDefault();
            window.location.href = 'stock-in.php';
        }
        // Alt + C -> Ürün Çıkışı
        else if (e.altKey && (e.key === 'c' || e.key === 'C')) {
            e.preventDefault();
            window.location.href = 'stock-out.php';
        }
        // Alt + H -> Stok Hareketleri
        else if (e.altKey && (e.key === 'h' || e.key === 'H')) {
            e.preventDefault();
            window.location.href = 'stock-movements.php';
        }
        // Alt + S -> Arama Kutusuna Odaklan
        else if (e.altKey && (e.key === 's' || e.key === 'S')) {
            e.preventDefault();
            const searchInput = document.getElementById('search-input') || document.querySelector('input[type="search"]');
            if (searchInput) {
                searchInput.focus();
                searchInput.select();
                announceA11y('Arama kutusuna odaklanıldı.');
            }
        }
    });

    // 5. Barkod Okuyucu Girişi & Otomatik Ürün Seçimi
    const productSelect = document.getElementById('product_id');
    const barcodeInput = document.getElementById('barcode_search');

    if (barcodeInput && productSelect) {
        barcodeInput.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                const code = barcodeInput.value.trim().toLowerCase();
                let found = false;

                for (let option of productSelect.options) {
                    const optBarcode = (option.dataset.barcode || '').toLowerCase();
                    const optSku = (option.dataset.sku || '').toLowerCase();
                    if (optBarcode === code || optSku === code) {
                        productSelect.value = option.value;
                        productSelect.dispatchEvent(new Event('change'));
                        barcodeInput.value = '';
                        announceA11y(`Ürün bulundu: ${option.text}`);
                        found = true;
                        break;
                    }
                }

                if (!found && code) {
                    announceA11y(`Barkod veya kod bulunamadı: ${code}`);
                    alert(`"${code}" barkoduna veya koduna ait ürün listede bulunamadı.`);
                }
            }
        });
    }

    // 6. Ürün Seçildiğinde Birim ve Mevcut Stok Bilgisini Güncelleme
    if (productSelect) {
        const updateProductInfo = () => {
            const selectedOpt = productSelect.options[productSelect.selectedIndex];
            const stockDisplay = document.getElementById('current_stock_display');
            const unitDisplay = document.getElementById('unit_display');
            const priceInput = document.getElementById('unit_price');

            if (selectedOpt && selectedOpt.value) {
                const stock = selectedOpt.dataset.stock || '0';
                const unit = selectedOpt.dataset.unit || 'Adet';
                const price = selectedOpt.dataset.price || '';

                if (stockDisplay) stockDisplay.textContent = `${stock} ${unit}`;
                if (unitDisplay) unitDisplay.textContent = unit;
                if (priceInput && (!priceInput.value || priceInput.value === '0.00' || priceInput.value === '0')) {
                    priceInput.value = price;
                }
            }
        };

        productSelect.addEventListener('change', updateProductInfo);
        // Sayfa yüklendiğinde de varsa çalıştır
        if (productSelect.value) {
            updateProductInfo();
        }
    }

    // 7. Otomatik Tutar Hesaplayıcı (Miktar * Birim Fiyat)
    const qtyInput = document.getElementById('quantity');
    const priceInput = document.getElementById('unit_price');
    const totalDisplay = document.getElementById('total_amount_display');

    function calculateTotal() {
        if (!qtyInput || !priceInput || !totalDisplay) return;
        const qty = parseFloat(qtyInput.value) || 0;
        const price = parseFloat(priceInput.value) || 0;
        const total = qty * price;
        totalDisplay.textContent = total.toLocaleString('tr-TR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' ₺';
    }

    if (qtyInput && priceInput) {
        qtyInput.addEventListener('input', calculateTotal);
        priceInput.addEventListener('input', calculateTotal);
        calculateTotal();
    }
});

# ThinkOrbit AI - PHP Version

Bu proje, ThinkOrbit AI quiz platformunun PHP versiyonudur. Bu uygulama, kullanıcıların herhangi bir konuda çoktan seçmeli sorular oluşturmasına, bu soruları çözmesine ve sonuçlarına göre kişiselleştirilmiş çalışma planı almasına olanak tanır.

## Özellikler

- Google Gemini API kullanarak dinamik quiz oluşturma
- Kullanıcı kayıt ve giriş sistemi
- Quiz sonuçlarının detaylı analizi
- Kişiselleştirilmiş çalışma planı oluşturma
- Yapay zeka asistanı ile canlı sohbet
- Geri bildirim sistemi

## Teknik Detaylar

- PHP 7.4+
- PostgreSQL veritabanı
- Google Gemini AI API entegrasyonu
- JWT ile kimlik doğrulama
- Responsive modern arayüz

## Kurulum

### Gereksinimler

- PHP 7.4 veya üzeri
- PostgreSQL 12 veya üzeri
- Apache web sunucusu (mod_rewrite etkin)
- Composer (bağımlılıklar için)

### Adımlar

1. Projeyi klonlayın:
   ```
   git clone https://github.com/yourusername/thinkorbit-ai.git
   cd thinkorbit-ai/php
   ```

2. Veritabanını oluşturun:
   - PostgreSQL veritabanınıza bağlanın
   - `database.sql` dosyasındaki SQL komutlarını çalıştırın

3. Konfigürasyon:
   - `config.php` dosyasındaki veritabanı bağlantı bilgilerini düzenleyin
   - Gemini API anahtarınızı `gemini.php` dosyasında güncelleyin
   - `$base_path` değişkenini kurulum yolunuza göre ayarlayın

4. Web sunucusu yapılandırması:
   - Uygulamayı bir web sunucusuna (Apache önerilir) konumlandırın
   - `.htaccess` dosyasının çalıştığından emin olun (mod_rewrite gereklidir)

## Kullanım

1. Web tarayıcınızdan uygulamaya erişin
2. Hesap oluşturup giriş yapın
3. Ana sayfadan yeni bir quiz oluşturun:
   - Konu seçin
   - Zorluk seviyesi belirleyin
   - Soru sayısı seçin
4. Quiz'i çözün ve sonuçları görüntüleyin
5. Kişiselleştirilmiş çalışma planınıza göz atın
6. İsterseniz AI asistanla sohbet edin

## Notlar

- Gemini API ücretsiz sürümünde kullanım sınırları vardır
- Veritabanı tablo yapısı için `database.sql` dosyasına bakın
- Hata durumunda `php` dizininde `error.log` dosyasını kontrol edin

## Lisans

Bu proje MIT lisansı altında lisanslanmıştır. Detaylar için `LICENSE` dosyasına bakın. 
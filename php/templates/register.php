<?php 
// Set page title
$title = "Kayıt Ol";
// Include the header
include 'header.php';
?>

<div class="register-container">
    <h1 class="mb-4">Kayıt Ol</h1>
    
    <div class="form-container">
        <div id="registerError" class="alert alert-danger" style="display: none;"></div>
        <div id="registerSuccess" class="alert alert-success" style="display: none;">Kayıt başarılı! Giriş sayfasına yönlendiriliyorsunuz...</div>
        
        <form id="registerForm" method="POST" action="/thinkorbit-ai/php/register">
            <div class="form-group">
                <label for="username">Kullanıcı Adı</label>
                <input type="text" id="username" name="username" class="form-control" required>
            </div>
            
            <div class="form-group">
                <label for="email">E-posta</label>
                <input type="email" id="email" name="email" class="form-control" required>
            </div>
            
            <div class="form-group">
                <label for="password">Şifre</label>
                <input type="password" id="password" name="password" class="form-control" required>
                <small style="color: var(--text-muted);">Şifreniz en az 8 karakter uzunluğunda olmalıdır.</small>
            </div>
            
            <button type="submit" class="btn btn-primary btn-block">
                <i class="fas fa-user-plus"></i> Kayıt Ol
            </button>
        </form>
        
        <div style="text-align: center; margin-top: 20px;">
            <p>Zaten bir hesabınız var mı? <a href="/thinkorbit-ai/php/login" style="color: var(--accent-color);">Giriş Yapın</a></p>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const registerForm = document.getElementById('registerForm');
    const registerError = document.getElementById('registerError');
    const registerSuccess = document.getElementById('registerSuccess');
    
    registerForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        // Form verilerini topla
        const formData = new FormData(registerForm);
        
        // Şifre kontrolü
        const password = formData.get('password');
        if (password.length < 8) {
            registerError.textContent = 'Şifre en az 8 karakter uzunluğunda olmalıdır.';
            registerError.style.display = 'block';
            return;
        }
        
        // AJAX isteği gönder
        fetch('/thinkorbit-ai/php/register', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            console.log("Response status:", response.status);
            // Tüm response içeriğini göster
            return response.text().then(text => {
                try {
                    // JSON olarak parse etmeyi dene
                    const data = JSON.parse(text);
                    console.log("Response data:", data);
                    return data;
                } catch (e) {
                    // Parse hatası varsa
                    console.error("JSON parse error:", e);
                    console.error("Raw response:", text);
                    throw new Error("Invalid JSON response: " + text);
                }
            });
        })
        .then(data => {
            if (data.status === 'error') {
                // Hata mesajını göster
                registerError.textContent = data.message;
                registerError.style.display = 'block';
                registerSuccess.style.display = 'none';
            } else {
                // Başarı mesajını göster
                registerSuccess.style.display = 'block';
                registerError.style.display = 'none';
                
                // Form'u temizle
                registerForm.reset();
                
                // Giriş sayfasına yönlendir
                setTimeout(function() {
                    window.location.href = '/thinkorbit-ai/php/login';
                }, 2000);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            registerError.textContent = 'Bir hata oluştu. Lütfen tekrar deneyin.';
            registerError.style.display = 'block';
            registerSuccess.style.display = 'none';
        });
    });
});
</script>

<?php 
// Include footer
include 'footer.php'; 
?> 
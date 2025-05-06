<?php 
// Set page title
$title = "Giriş Yap";
// Include the header
include 'header.php';
?>

<div class="login-container">
    <h1 class="mb-4">Giriş Yap</h1>
    
    <div class="form-container">
        <div id="loginError" class="alert alert-danger" style="display: none;"></div>
        
        <form id="loginForm" method="POST" action="/thinkorbit-ai/php/login">
            <div class="form-group">
                <label for="username">Kullanıcı Adı</label>
                <input type="text" id="username" name="username" class="form-control" required>
            </div>
            
            <div class="form-group">
                <label for="password">Şifre</label>
                <input type="password" id="password" name="password" class="form-control" required>
            </div>
            
            <button type="submit" class="btn btn-primary btn-block">
                <i class="fas fa-sign-in-alt"></i> Giriş Yap
            </button>
        </form>
        
        <div style="text-align: center; margin-top: 20px;">
            <p>Hesabınız yok mu? <a href="/thinkorbit-ai/php/register" style="color: var(--accent-color);">Kayıt Olun</a></p>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const loginForm = document.getElementById('loginForm');
    const loginError = document.getElementById('loginError');
    
    // Token saklama fonksiyonu
    function storeToken(token) {
        localStorage.setItem('access_token', token);
        console.log('Token başarıyla saklandı.');
    }
    
    loginForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        // Form verilerini topla
        const formData = new FormData(loginForm);
        
        // AJAX isteği gönder
        fetch('/thinkorbit-ai/php/login', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'error') {
                // Hata mesajını göster
                loginError.textContent = data.message;
                loginError.style.display = 'block';
            } else {
                // Token'ı sakla
                storeToken(data.access_token);
                
                // Kullanıcı adını da sakla
                if (data.user && data.user.username) {
                    localStorage.setItem('username', data.user.username);
                }
                
                // Ana sayfaya yönlendir
                window.location.href = '/thinkorbit-ai/php';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            loginError.textContent = 'Bir hata oluştu. Lütfen tekrar deneyin.';
            loginError.style.display = 'block';
        });
    });
});
</script>

<?php 
// Include footer
include 'footer.php'; 
?> 
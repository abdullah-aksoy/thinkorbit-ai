        </div><!-- /.container -->
    </div><!-- /.main-content -->

    <script>
    // DOM yüklendiğinde tüm formları kontrol et
    document.addEventListener('DOMContentLoaded', function() {
        // Tüm formları bul
        const forms = document.querySelectorAll('form');
        
        forms.forEach(form => {
            // Form gönderilmeden önce
            form.addEventListener('submit', function(e) {
                // Token input field'ı var mı kontrol et
                let tokenInput = form.querySelector('input[name="token"]');
                
                // Token field yoksa oluştur
                if (!tokenInput) {
                    tokenInput = document.createElement('input');
                    tokenInput.type = 'hidden';
                    tokenInput.name = 'token';
                    form.appendChild(tokenInput);
                }
                
                // Token'ı localStorage'dan al
                const token = localStorage.getItem('access_token');
                if (token) {
                    tokenInput.value = token;
                    console.log('Form gönderilirken token eklendi: ' + form.action);
                } else {
                    console.log('Token bulunamadı!');
                }
            });
        });
        
        // Debug için token durumunu konsola yaz
        const token = localStorage.getItem('access_token');
        if (token) {
            console.log('Token mevcut: ' + token.substring(0, 20) + '...');
        } else {
            console.log('Token bulunamadı');
        }
    });
    </script>

    <!-- JavaScript -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/2.9.2/umd/popper.min.js"></script>
</body>
</html> 
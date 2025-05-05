# feedback.py
import os
# Google Generative AI kütüphanesini eklemeyi unutma!
# pip install google-generativeai
import google.generativeai as genai

# API anahtarını ve modeli modül seviyesinde okumayı/yapılandırmayı kaldırıyoruz.

def get_feedback_from_gemini(topic: str, questions_text: str) -> str:
    """
    Gemini Pro modelini kullanarak quiz sonuçları için geri bildirim üretir.
    API anahtarını okur, modeli yapılandırır ve çağrıyı yapar.
    """
    api_key = os.environ.get("GEMINI_API_KEY")
    if not api_key:
        # Bu uyarıyı artık fonksiyon çağrıldığında göreceğiz (eğer anahtar yoksa)
        print("Uyarı: GEMINI_API_KEY ortam değişkeni bulunamadı.")
        return "Geri bildirim hizmeti yapılandırılamadı (API Anahtarı eksik)."

    try:
        # API anahtarı varsa, yapılandırmayı ve model örneğini burada yap
        genai.configure(api_key=api_key)
        model = genai.GenerativeModel('gemini-pro')
    except Exception as e:
        print(f"Gemini modeli yapılandırılırken/yüklenirken hata oluştu: {e}")
        return "Geri bildirim hizmeti şu anda kullanılamıyor (Model yapılandırma hatası)."

    # Prompt yapısı aynı kalıyor
    prompt = f"""
Rol: Sen deneyimli ve yardımsever bir öğretmensin. Görevin, bir öğrencinin belirli bir konudaki quiz sonuçlarını analiz ederek ona özel, yapıcı ve teşvik edici geri bildirimler sunmaktır.

Konu: {topic}

Quiz Soruları ve Cevapları:
{questions_text}

Görevin:
1.  **Genel Değerlendirme:** Öğrencinin bu konudaki genel başarı düzeyini (örneğin, çok iyi, iyi, geliştirilmesi gerekiyor, zayıf) belirle ve kısaca açıkla.
2.  **Hata Analizi:** Yanlış cevaplanan soruları incele. Hataların olası nedenlerini (kavram yanılgısı, dikkat hatası, bilgi eksikliği vb.) belirlemeye çalış. Belirgin örüntüler varsa vurgula.
3.  **Eksik Konular:** Özellikle hangi alt konularda veya soru tiplerinde zorlandığını tespit et.
4.  **Gelişim Önerileri:** Öğrencinin eksiklerini gidermesi ve konuyu daha iyi anlaması için somut ve uygulanabilir öneriler sun (örneğin, tekrar edilmesi gereken konular, ek çalışma kaynakları, farklı soru tipleri çözme vb.).
5.  **Ton ve Üslup:** Geri bildirimin motive edici, anlaşılır, samimi ve bir öğretmenin öğrenciye yol gösteren tavrında olsun. Emir kipleri yerine tavsiye ve yönlendirme cümleleri kullan. Eleştirileri yapıcı bir dille ifade et.

Çıktı Formatı: Akıcı bir metin halinde, yukarıdaki tüm adımları kapsayan bir geri bildirim oluştur.
"""
    response = None # Hata durumunda response'a erişebilmek için
    try:
        response = model.generate_content(prompt)
        # Güvenlik ayarları nedeniyle içerik engellenirse kontrol et
        if hasattr(response, 'prompt_feedback') and response.prompt_feedback.block_reason:
             print(f"Uyarı: Gemini yanıtı içerik filtrelemesi nedeniyle engellendi. Neden: {response.prompt_feedback.block_reason}")
             return "Üzgünüm, geri bildirim üretirken bir içerik kısıtlamasıyla karşılaşıldı. Lütfen sorularınızı veya konuyu gözden geçirin."

        if hasattr(response, 'text'):
             feedback = response.text
             return feedback
        else:
             print(f"Hata: Gemini yanıtında 'text' alanı bulunamadı. Yanıt: {response}")
             return "Geri bildirim alınırken beklenmedik bir formatla karşılaşıldı."

    except Exception as e:
        print(f"Gemini API çağrısında hata oluştu: {e}")
        print(f"Gemini Response: {response if response is not None else 'Yok'}")
        return "Geri bildirim alınırken bir sunucu hatası oluştu. Lütfen daha sonra tekrar deneyin."

# Örnek kullanım (test için):
if __name__ == "__main__":
    # Test kodu artık doğrudan fonksiyonu çağıracak ve API anahtarı kontrolü içeride yapılacak.
    print("Test amaçlı geri bildirim üretiliyor...")
    test_topic = "Basit Kesirler"
    test_questions = """Soru: 1/2 + 1/4 işleminin sonucu kaçtır?
Verdiği Cevap: 2/6 | Doğru Cevap: 3/4
Soru: 5/3 kesri bileşik kesir midir basit kesir mi?
Verdiği Cevap: Basit Kesir | Doğru Cevap: Bileşik Kesir
Soru: 1/5 kesrinin ondalık gösterimi nedir?
Verdiği Cevap: 0.2 | Doğru Cevap: 0.2"""
    # Test çalıştırmadan önce .env dosyasının ve anahtarın var olduğundan emin olunmalı
    # Veya test için doğrudan anahtarı buraya girmek (güvenli değil!)
    # load_dotenv() çağrısını buraya da eklemek gerekebilir eğer doğrudan çalıştırılırsa
    from dotenv import load_dotenv
    load_dotenv() 
    
    feedback = get_feedback_from_gemini(test_topic, test_questions)
    print("--- Örnek Geri Bildirim ---")
    print(feedback)
    print("---------------------------")

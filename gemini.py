import google.generativeai as genai
from typing import List, Dict, Any
import re
import os

# API anahtarını doğrudan ayarla
api_key = "AIzaSyAhkVO9Mo96-7tCAkIeIaR3l8s-otnh9rY"  # Bu anahtarı yeni bir anahtarla değiştirin

try:
    print("Configuring Gemini API...")
    genai.configure(api_key=api_key)
    print("Gemini API configured successfully")
except Exception as e:
    print(f"Error configuring Gemini API: {type(e).__name__} - {str(e)}")
    raise

# Model yapılandırması
generation_config = {
    "temperature": 0.9,
    "top_p": 1,
    "top_k": 1,
    "max_output_tokens": 2048,
}

safety_settings = [
    {
        "category": "HARM_CATEGORY_HARASSMENT",
        "threshold": "BLOCK_MEDIUM_AND_ABOVE"
    },
    {
        "category": "HARM_CATEGORY_HATE_SPEECH",
        "threshold": "BLOCK_MEDIUM_AND_ABOVE"
    },
    {
        "category": "HARM_CATEGORY_SEXUALLY_EXPLICIT",
        "threshold": "BLOCK_MEDIUM_AND_ABOVE"
    },
    {
        "category": "HARM_CATEGORY_DANGEROUS_CONTENT",
        "threshold": "BLOCK_MEDIUM_AND_ABOVE"
    },
]

try:
    print("Initializing Gemini model...")
    model = genai.GenerativeModel(
        model_name="gemini-2.0-flash",
        generation_config=generation_config,
        safety_settings=safety_settings
    )
    print("Gemini model initialized successfully")
except Exception as e:
    print(f"Error initializing Gemini model: {type(e).__name__} - {str(e)}")
    raise

def generate_explanation_with_gemini(question: str, correct_answer: str, topic: str) -> str:
    prompt = f"""Lütfen aşağıdaki sorunun çözümünü detaylı bir şekilde açıkla.
    
Konu: {topic}
Soru: {question}
Doğru Cevap: {correct_answer}

Lütfen adım adım, anlaşılır bir şekilde açıkla ve neden bu cevabın doğru olduğunu belirt."""

    try:
        response = model.generate_content(prompt)
        if not hasattr(response, 'text'):
            return "Açıklama üretilemedi."
        return response.text.strip()
    except Exception as e:
        print(f"Açıklama üretilirken hata: {str(e)}")
        return "Açıklama üretilemedi."

def generate_quiz_questions_with_gemini(level: str, topic: str, num_questions: int) -> List[Dict[str, Any]]:
    prompt = f"""Lütfen {level} düzeyinde {topic} konusunda {num_questions} adet çoktan seçmeli soru üret.
Her soru için tam olarak aşağıdaki formatı kullan:

1. Soru metni buraya yazılacak?
A) Birinci şık
B) İkinci şık
C) Üçüncü şık
D) Dördüncü şık
Doğru Cevap: B
Açıklama: Sorunun detaylı çözüm açıklaması buraya yazılacak.

2. İkinci soru metni?
A) Şık
B) Şık
C) Şık
D) Şık
Doğru Cevap: A
Açıklama: İkinci sorunun detaylı çözüm açıklaması buraya yazılacak.

Lütfen her soruyu bu formatta yaz ve aralarında boş satır bırak. Her soru için mutlaka bir açıklama ekle."""

    try:
        print(f"Gemini API'ye gönderilen prompt:\n{prompt}")
        response = model.generate_content(prompt)
        
        if not hasattr(response, 'text'):
            print("Hata: API yanıtında text özelliği yok!")
            return []
            
        text_response = response.text
        print("\nGemini API'den gelen yanıt:\n", text_response)
        
        if not text_response or text_response.strip() == "":
            print("Hata: API boş yanıt döndü!")
            return []

        questions = _parse_gemini_output(text_response)
        print(f"\nİşlenmiş sorular ({len(questions)} adet):")
        for i, q in enumerate(questions, 1):
            print(f"\nSoru {i}:")
            print(f"Soru metni: {q['question']}")
            print("Şıklar:")
            for key, value in q['options'].items():
                print(f"{key}) {value}")
            print(f"Doğru cevap: {q['correct_option']}")
            print(f"Açıklama: {q.get('explanation', 'Açıklama yok')}")

        return questions
    except Exception as e:
        print(f"Gemini API hatası: {str(e)}")
        print(f"Hata türü: {type(e).__name__}")
        return []

def _parse_gemini_output(response_text: str) -> List[Dict[str, Any]]:
    questions = []
    blocks = response_text.strip().split("\n\n")
    print(f"\nToplam {len(blocks)} blok bulundu.")

    for i, block in enumerate(blocks, 1):
        try:
            print(f"\nBlok {i} işleniyor:")
            print(block)
            
            lines = block.strip().split("\n")
            if len(lines) < 6:  # En az 6 satır olmalı (soru + 4 şık + doğru cevap + açıklama)
                print(f"Blok {i} yeterli satır içermiyor, atlanıyor...")
                continue

            question_line = lines[0].strip()
            question_text = re.sub(r"^\d+\.\s*", "", question_line)
            options = {}
            correct_option = ""
            explanation = ""

            for j, line in enumerate(lines[1:]):
                if re.match(r"^[A-Da-d]\)", line):
                    opt_key = line[0].upper()
                    opt_value = line[2:].strip()
                    options[opt_key] = opt_value
                elif "Doğru Cevap" in line:
                    correct_option = line.split(":")[-1].strip().upper()
                elif "Açıklama:" in line:
                    # Açıklamayı al ve sonraki satırları da dahil et
                    explanation_parts = [line.split(":", 1)[1].strip()]
                    for next_line in lines[j+2:]:  # j+2 çünkü lines[1:]'den iterasyon yapıyoruz
                        if not next_line.startswith(("A)", "B)", "C)", "D)", "Doğru Cevap:", "Açıklama:")):
                            explanation_parts.append(next_line.strip())
                        else:
                            break
                    explanation = " ".join(explanation_parts)

            if question_text and options and correct_option in options:
                questions.append({
                    "question": question_text,
                    "options": options,
                    "correct_option": correct_option,
                    "explanation": explanation
                })
                print(f"Soru başarıyla eklendi: {question_text[:50]}...")
            else:
                print("Soru eklenmedi çünkü:")
                print(f"- Soru metni var mı: {bool(question_text)}")
                print(f"- Şıklar var mı: {bool(options)}")
                print(f"- Doğru cevap şıklar arasında mı: {correct_option in options}")
        except Exception as e:
            print(f"Blok {i} işlenirken hata: {str(e)}")
            continue

    return questions

def analyze_quiz_results_and_create_plan(topic: str, correct_answers: List[str], wrong_answers: List[str], total_questions: int) -> Dict[str, Any]:
    if total_questions < 20:
        warning = "Not: Daha sağlıklı bir çalışma planı için en az 20 soru çözmenizi öneririz."
    else:
        warning = ""

    prompt = f"""Lütfen aşağıdaki quiz sonuçlarını analiz ederek kişiselleştirilmiş bir çalışma planı oluştur.
Yanıtını düz metin formatında ver ve emojiler kullan.
Her bölüm başlığı için uygun bir emoji seç.

Konu: {topic}
Toplam Soru Sayısı: {total_questions}
Doğru Cevaplanan Sorular: {len(correct_answers)}
Yanlış Cevaplanan Sorular: {len(wrong_answers)}

Yanlış Yapılan Sorular:
{', '.join(wrong_answers)}

Lütfen:
1. Sonuçların kısa bir analizini yap
2. Zayıf noktaları belirt
3. Gelişim için özel öneriler sun
4. Günlük çalışma planı oluştur
5. Önerilen kaynaklar listele

Yanıtını aşağıdaki formatta ver:

📊 1. Sonuçların Analizi
[Detaylı analiz metni]

❗ 2. Zayıf Noktalar
 [Konu başlığı]: [Detaylı açıklama]

💡 3. Gelişim Önerileri
 [Öneri metni]

📅 4. Günlük Çalışma Planı
 [Plan detayları]

📚 5. Önerilen Kaynaklar
 [Kaynak listesi]

Her bölümü yeni satırda başlat ve uygun emojilerle destekle.
Pozitif ve motive edici bir dil kullan."""

    try:
        response = model.generate_content(prompt)
        if not hasattr(response, 'text'):
            return {
                "success": False,
                "message": "Çalışma planı oluşturulamadı.",
                "warning": warning
            }
        
        # HTML etiketlerini temizle ve formatı düzelt
        plan_text = response.text.strip()
        plan_text = plan_text.replace('```html', '').replace('```', '')  # Remove code blocks
        plan_text = plan_text.replace('<h3>', '').replace('</h3>', '')
        plan_text = plan_text.replace('<p>', '').replace('</p>', '\n')
        plan_text = plan_text.replace('<ul>', '').replace('</ul>', '')
        plan_text = plan_text.replace('<li>', '➡️ ').replace('</li>', '')
        plan_text = plan_text.replace('*', '')
        
        return {
            "success": True,
            "plan": plan_text,
            "warning": warning
        }
    except Exception as e:
        print(f"Çalışma planı oluşturulurken hata: {str(e)}")
        return {
            "success": False,
            "message": "Çalışma planı oluşturulurken bir hata oluştu.",
            "warning": warning
        }

def chat_with_ai(user_message: str) -> str:
    try:
        if not user_message or user_message.strip() == "":
            return "Üzgünüm, boş bir mesaj aldım. Lütfen bir şeyler yazın. 😊"

        print(f"Preparing prompt for message: {user_message[:50]}...")
        prompt = f"""Kullanıcı mesajı: {user_message}

Lütfen ThinkOrbit AI eğitim asistanı olarak, eğitimle ilgili konularda yardımcı ol.
Cevabın eğitici, yardımcı ve arkadaşça olsun.

Yanıt verirken:
Önemli noktaları vurgulamak için uygun emojiler ekle fakat çok kullanma(📚, 💡, ✨, 🎯, 📝 gibi)
Madde işaretleri için emoji kullan fakat çok kullanma(• yerine ➡️ veya 📌)
Pozitif ve motive edici bir dil kullan
Paragraflar arasında yeterli boşluk bırak
Önemli kelimeleri veya cümleleri emojilerle vurgula
"""

        print("Sending request to Gemini API...")
        try:
            response = model.generate_content(prompt)
            print("Received response from Gemini API")
        except Exception as api_error:
            print(f"Gemini API error: {type(api_error).__name__} - {str(api_error)}")
            return f"Üzgünüm, API çağrısında bir hata oluştu: {type(api_error).__name__}. Lütfen tekrar deneyin. 😔"
        
        if not response:
            print("Error: Null response from Gemini API")
            return "Üzgünüm, şu anda yanıt veremiyorum. Lütfen tekrar deneyin. 😔"
            
        if not hasattr(response, 'text'):
            print("Error: Response has no 'text' attribute")
            print(f"Response type: {type(response)}")
            print(f"Response content: {response}")
            return "Üzgünüm, şu anda yanıt veremiyorum. Lütfen tekrar deneyin. 😔"
        
        response_text = response.text.strip()
        if not response_text:
            print("Error: Empty response text")
            return "Üzgünüm, boş bir yanıt aldım. Lütfen tekrar deneyin. 😔"
            
        print(f"Successfully generated response: {response_text[:100]}...")
        return response_text
        
    except Exception as e:
        print(f"Unexpected error in chat_with_ai: {type(e).__name__} - {str(e)}")
        return f"Üzgünüm, beklenmeyen bir hata oluştu: {type(e).__name__}. Lütfen tekrar deneyin. 😔"
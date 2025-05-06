from fastapi import FastAPI, Form, Request, HTTPException, Depends, File, UploadFile
from fastapi.responses import HTMLResponse, JSONResponse, RedirectResponse
from fastapi.staticfiles import StaticFiles
from fastapi.templating import Jinja2Templates
from fastapi.middleware.cors import CORSMiddleware
from fastapi.security import OAuth2PasswordRequestForm, OAuth2PasswordBearer
from pydantic import BaseModel, Field, validator
from gemini import generate_quiz_questions_with_gemini, analyze_quiz_results_and_create_plan, chat_with_ai
from auth import authenticate_user, create_user, get_current_user, create_access_token, ACCESS_TOKEN_EXPIRE_MINUTES
from typing import List, Dict, Optional, Any
import uvicorn
import json
from datetime import datetime, timedelta
import os
from dotenv import load_dotenv
import psycopg2
from psycopg2.extras import RealDictCursor
from passlib.context import CryptContext
import jwt
from jose import JWTError
import functools
# Load environment variables
load_dotenv()

# Request Models
class ChatMessage(BaseModel):
    message: str

class QuizForm(BaseModel):
    topic: str
    level: str
    numQuestions: int

class QuizAnalysisForm(BaseModel):
    topic: Optional[str] = None
    level: Optional[str] = None
    token: Optional[str] = None
    
    # Dinamik soru alanları için alan
    questions: Optional[Dict[str, Any]] = Field(default_factory=dict)
    
    class Config:
        extra = 'allow'  # Ekstra alanları kabul et
    
    @validator('*', pre=True)
    def empty_str_to_none(cls, v):
        """
        Boş stringleri None'a çevir
        """
        if isinstance(v, str) and v.strip() == '':
            return None
        return v
    
    def __init__(self, **data):
        """
        Gelen verileri daha esnek şekilde işle
        """
        # Tüm form verilerini topla
        processed_data = {}
        questions = {}
        
        for key, value in data.items():
            # Soru verilerini ayrı topla
            if key.startswith('q_') or key.startswith('c_') or key.startswith('text_') or key.startswith('opt_') or key.startswith('exp_'):
                parts = key.split('_')
                if len(parts) >= 2:
                    index = parts[1]
                    if index not in questions:
                        questions[index] = {}
                    
                    if parts[0] == 'q':
                        questions[index]['selected'] = value
                    elif parts[0] == 'c':
                        questions[index]['correct'] = value
                    elif parts[0] == 'text':
                        questions[index]['text'] = value
                    elif parts[0] == 'opt':
                        letter = parts[2]
                        if 'options' not in questions[index]:
                            questions[index]['options'] = {}
                        questions[index]['options'][letter] = value
                    elif parts[0] == 'exp':
                        questions[index]['explanation'] = value
            
            # Diğer alanları işle
            elif key in ['topic', 'level', 'token']:
                processed_data[key] = value
        
        # Soruları işlenmiş veriye ekle
        processed_data['questions'] = questions
        
        # Debug bilgisi
        print("DEBUG: Processed form data:")
        for k, v in processed_data.items():
            print(f"{k}: {v}")
        
        super().__init__(**processed_data)

# JWT Configuration
SECRET_KEY = os.getenv("SECRET_KEY", "b6977034537ca13eb4f9aad8e521782d20225e7be98dda5e28da2eadc08cbb2c")
ALGORITHM = "HS256"
ACCESS_TOKEN_EXPIRE_MINUTES = 30

# Password hashing
pwd_context = CryptContext(schemes=["bcrypt"], deprecated="auto")

# Database configuration
DB_HOST = os.getenv("DB_HOST", "localhost")
DB_PORT = os.getenv("DB_PORT", "5432")
DB_NAME = os.getenv("DB_NAME", "thinkorbit")
DB_USER = os.getenv("DB_USER", "postgres")
DB_PASSWORD = os.getenv("DB_PASSWORD", "241324")

# OAuth2 scheme
oauth2_scheme = OAuth2PasswordBearer(tokenUrl="login")

# Database connection function
def get_db_connection():
    try:
        conn = psycopg2.connect(
            host=DB_HOST,
            port=DB_PORT,
            database=DB_NAME,
            user=DB_USER,
            password=DB_PASSWORD,
            cursor_factory=RealDictCursor
        )
        return conn
    except Exception as e:
        print(f"Database connection error: {e}")
        raise HTTPException(status_code=500, detail="Database connection error")

app = FastAPI()

# Statik dosyalar ve HTML şablonları
app.mount("/static", StaticFiles(directory="static"), name="static")
templates = Jinja2Templates(directory="templates")

# Geri bildirimleri saklamak için klasör oluştur
FEEDBACK_DIR = "feedback"
if not os.path.exists(FEEDBACK_DIR):
    os.makedirs(FEEDBACK_DIR)

# CORS ayarı
app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

# Auth gerektiren endpoint'ler için middleware
def auth_required(func):
    @functools.wraps(func)
    async def wrapper(*args, **kwargs):
        request = kwargs.get('request')
        if not request:
            for arg in args:
                if isinstance(arg, Request):
                    request = arg
                    break
        
        if not request:
            print("Error: Request object not found in auth_required decorator") 
            raise HTTPException(status_code=500, detail="Internal server error: Request object missing")

        try:
            print("Checking authorization...")
            
            # Authorization header'ını kontrol et
            auth_header = request.headers.get('Authorization')
            
            # Form verilerinden token'ı al
            form_data = await request.form()
            form_token = form_data.get('token')
            
            # Token'ı belirle (önce header, sonra form)
            token = None
            if auth_header:
                print(f"Authorization header found: {auth_header[:20]}...")
                try:
                    scheme, token = auth_header.split()
                    if scheme.lower() != 'bearer':
                        token = None
                except ValueError:
                    token = None
            
            # Eğer header'da token yoksa, form verilerinden al
            if not token and form_token:
                print("Token found in form data")
                token = form_token
            
            if not token:
                print("No token found")
                raise HTTPException(
                    status_code=401,
                    detail="Token bulunamadı",
                    headers={"WWW-Authenticate": "Bearer"},
                )
            
            print("Validating token...")
            current_user = await get_current_user(token)
            print(f"Token validated for user: {current_user['username']}")
            
            kwargs['current_user'] = current_user
            return await func(*args, **kwargs)
            
        except HTTPException as e:
            print(f"HTTP Exception in auth decorator: {e.detail}")
            raise e
        except Exception as e:
            print(f"Unexpected error in auth decorator: {type(e).__name__} - {str(e)}")
            raise HTTPException(
                status_code=401,
                detail=f"Kimlik doğrulama hatası: {str(e)}",
                headers={"WWW-Authenticate": "Bearer"}
            )
    
    return wrapper

# Ana sayfa
@app.get("/", response_class=HTMLResponse)
async def home(request: Request):
    return templates.TemplateResponse("index.html", {"request": request})

# Giriş sayfası
@app.get("/login", response_class=HTMLResponse)
async def login_page(request: Request):
    return templates.TemplateResponse("login.html", {"request": request})

# Kayıt sayfası
@app.get("/register", response_class=HTMLResponse)
async def register_page(request: Request):
    return templates.TemplateResponse("register.html", {"request": request})

# Quiz üretimi - auth kontrolü ekle
@app.post("/generate", response_class=HTMLResponse)
@auth_required
async def generate(
    request: Request,
    topic: str = Form(...),
    level: str = Form(...),
    numQuestions: str = Form(...),
    current_user: dict = None
):
    try:
        print(f"Received form data - Topic: {topic}, Level: {level}, NumQuestions: {numQuestions}")

        # Veri doğrulama
        if not topic or not level or not numQuestions:
            return JSONResponse(
                status_code=400,
                content={"error": "Tüm alanları doldurunuz."}
            )

        # Level kontrolü
        if level not in ["kolay", "orta", "zor"]:
            return JSONResponse(
                status_code=400,
                content={"error": "Geçersiz zorluk seviyesi."}
            )

        # Soru sayısı kontrolü
        try:
            num_questions_int = int(numQuestions)
            if num_questions_int not in [5, 10, 15, 20]:
                return JSONResponse(
                    status_code=400,
                    content={"error": "Geçersiz soru sayısı. Lütfen 5, 10, 15 veya 20 seçin."}
                )
        except ValueError:
            return JSONResponse(
                status_code=400,
                content={"error": "Soru sayısı geçerli bir sayı olmalıdır."}
            )

        # Quiz oluştur
        questions = generate_quiz_questions_with_gemini(
            topic=topic,
            num_questions=num_questions_int,
            level=level
        )

        if not questions or not isinstance(questions, list):
            return JSONResponse(
                status_code=500,
                content={"error": "Quiz üretilemedi. Lütfen tekrar deneyin."}
            )

        # Soruları işle
        formatted_questions = []
        for i, q in enumerate(questions):
            try:
                formatted_question = {
                    "id": i + 1,
                    "question": q.get("question", ""),
                    "options": {
                        "A": q.get("options", {}).get("A", ""),
                        "B": q.get("options", {}).get("B", ""),
                        "C": q.get("options", {}).get("C", ""),
                        "D": q.get("options", {}).get("D", "")
                    },
                    "correct_answer": q.get("correct_answer", ""),
                    "explanation": q.get("explanation", "")
                }
                formatted_questions.append(formatted_question)
            except Exception as e:
                print(f"Error formatting question {i + 1}: {str(e)}")
                continue

        if not formatted_questions:
            return JSONResponse(
                status_code=500,
                content={"error": "Sorular işlenirken hata oluştu. Lütfen tekrar deneyin."}
            )

        return templates.TemplateResponse(
            "quiz.html",
            {
                "request": request,
                "questions": formatted_questions,
                "topic": topic,
                "level": level,
                "user": current_user
            }
        )

    except Exception as e:
        print(f"Error in generate endpoint: {str(e)}")
        return JSONResponse(
            status_code=500,
            content={"error": f"Quiz oluşturulurken bir hata oluştu: {str(e)}"}
        )

# Sonuç analizi - auth kontrolü ekle
@app.post("/analyze", response_class=HTMLResponse)
@auth_required
async def analyze(
    request: Request, 
    current_user: dict = None
):
    try:
        # Form verilerini al
        form_data = await request.form()
        form_dict = dict(form_data)
        
        # Detaylı log ekle
        print("\n--- ANALYZE ENDPOINT DEBUG START ---")
        print("Gelen form verileri:")
        for key, value in form_dict.items():
            print(f"{key}: {value}")
        
        # Tüm form verilerini doğrudan kullan, Pydantic validation'ı atla
        try:
            # Toplam soru sayısını dinamik olarak bul
            question_indices = set()
            for key in form_dict.keys():
                if key.startswith("q_"):
                    index = key.split("_")[1]
                    question_indices.add(index)
            
            total_questions = len(question_indices)
            print(f"DEBUG: Toplam soru sayısı: {total_questions}")
            print(f"DEBUG: Soru indeksleri: {question_indices}")

            # Token kontrolü
            token = request.headers.get('Authorization', '').split(' ')[-1] if 'Authorization' in request.headers else form_dict.get('token')
            if not token:
                print("DEBUG: Token bulunamadı")
                return JSONResponse(
                    status_code=401,
                    content={
                        "status": "error", 
                        "message": "Geçersiz veya eksik token",
                        "debug_info": "Token bulunamadı"
                    }
                )

            # Form verilerini doğrudan kullan, Pydantic validation'ı atla
            correct = 0
            user_answers = {}
            question_data = []
            correct_questions = []
            wrong_questions = []

            # Her soru için verileri işle
            for i in range(total_questions):
                str_i = str(i)
                selected = form_dict.get(f"q_{str_i}")
                question_text = form_dict.get(f"text_{str_i}", "")
                
                # Doğru cevabı form verilerinden al
                correct_answer = form_dict.get(f"c_{str_i}")
                
                # Seçenekleri topla
                options = {
                    "A": form_dict.get(f"opt_{str_i}_A", ""),
                    "B": form_dict.get(f"opt_{str_i}_B", ""),
                    "C": form_dict.get(f"opt_{str_i}_C", ""),
                    "D": form_dict.get(f"opt_{str_i}_D", "")
                }
                
                # Eğer doğru cevap bulunamazsa, ilk seçeneği varsay
                if not correct_answer:
                    correct_answer = "A"
                    print(f"WARNING: Doğru cevap belirlenemedi. Soru {i + 1} için varsayılan olarak A seçildi.")
                
                print(f"\nSoru {i + 1} verileri:")
                print(f"- Seçilen cevap: {selected}")
                print(f"- Doğru cevap: {correct_answer}")
                print(f"- Soru metni: {question_text[:50]}...")
                
                if not selected:
                    print(f"Uyarı: Soru {i + 1} için cevap seçilmemiş!")
                    continue

                is_correct = selected == correct_answer
                print(f"- Doğru mu? {is_correct}")
                
                if is_correct:
                    correct += 1
                    correct_questions.append(question_text)
                else:
                    wrong_questions.append(question_text)

                explanation = form_dict.get(f"exp_{str_i}", "")

                user_answers[i] = selected
                question_data.append({
                    "question": question_text,
                    "selected": selected,
                    "correct": correct_answer,
                    "is_correct": is_correct,
                    "options": options,
                    "explanation": explanation
                })

            total = len(question_data)
            if total == 0:
                raise ValueError("Hiç soru cevaplanmamış!")

            wrong = total - correct
            percent = round((correct / total) * 100, 2)
            topic = form_dict.get("topic", "")

            print(f"\nSonuç özeti:")
            print(f"- Toplam soru: {total}")
            print(f"- Doğru sayısı: {correct}")
            print(f"- Yanlış sayısı: {wrong}")
            print(f"- Başarı yüzdesi: {percent}%")
            print("--- ANALYZE ENDPOINT DEBUG END ---\n")

            # Çalışma planı oluştur
            study_plan = analyze_quiz_results_and_create_plan(
                topic=topic,
                correct_answers=correct_questions,
                wrong_answers=wrong_questions,
                total_questions=total
            )

            return templates.TemplateResponse("result.html", {
                "request": request,
                "correct": correct,
                "wrong": wrong,
                "percent": percent,
                "total": total,
                "questions": question_data,
                "topic": topic,
                "level": form_dict.get("level", ""),
                "study_plan": study_plan,
                "user": current_user
            })
        except Exception as validation_error:
            print(f"Validation Error: {type(validation_error).__name__} - {str(validation_error)}")
            import traceback
            print(traceback.format_exc())
            
            # Detaylı hata bilgisi
            error_details = {
                "error_type": type(validation_error).__name__,
                "error_message": str(validation_error),
                "traceback": traceback.format_exc(),
                "form_data_keys": list(form_dict.keys())
            }
            
            return JSONResponse(
                status_code=422,
                content={
                    "status": "error",
                    "message": "Form verilerini işlerken hata oluştu",
                    "debug_info": error_details
                }
            )
    except Exception as e:
        print(f"Analiz hatası: {str(e)}")
        print(f"Hata türü: {type(e).__name__}")
        import traceback
        print(f"Hata detayı:\n{traceback.format_exc()}")
        return JSONResponse(
            status_code=500,
            content={
                "status": "error",
                "message": f"Sonuçlar analiz edilirken bir hata oluştu: {str(e)}",
                "error_type": type(e).__name__
            }
        )

# Geri bildirim endpoint'i
@app.post("/feedback")
@auth_required
async def submit_feedback(
    request: Request,
    question_number: str = Form(...),
    feedback_text: str = Form(...),
    topic: str = Form(None),
    level: str = Form(None),
    current_user: dict = None
):
    try:
        # Validate input
        if not feedback_text or len(feedback_text.strip()) == 0:
            return JSONResponse(
                status_code=400,
                content={
                    "status": "error", 
                    "message": "Geri bildirim boş olamaz"
                }
            )

        print(f"Gelen form verileri: question={question_number}, feedback={feedback_text}, topic={topic}, level={level}, user={current_user['username']}")
        
        # int'e çevir, hata olursa 0 yap
        try:
            question_number_int = int(question_number)
        except Exception:
            question_number_int = 0

        feedback = {
            "timestamp": datetime.now().isoformat(),
            "question_number": question_number_int,
            "feedback": feedback_text,
            "topic": topic or "Belirtilmemiş",
            "level": level or "Belirtilmemiş",
            "username": current_user['username']  # Add username to feedback
        }
        
        # Geri bildirimi JSON dosyasına kaydet
        try:
            if not os.path.exists(FEEDBACK_DIR):
                os.makedirs(FEEDBACK_DIR)
                
            filename = f"{FEEDBACK_DIR}/feedback_{current_user['username']}_{datetime.now().strftime('%Y%m%d_%H%M%S')}.json"
            
            with open(filename, "w", encoding="utf-8") as f:
                json.dump(feedback, f, ensure_ascii=False, indent=2)
        except Exception as file_error:
            print(f"Dosya kaydetme hatası: {str(file_error)}")
            return JSONResponse(
                status_code=500,
                content={
                    "status": "error",
                    "message": "Geri bildirim kaydedilemedi",
                    "detail": str(file_error)
                }
            )
        
        return JSONResponse(
            status_code=200,
            content={
                "status": "success",
                "message": "Geri bildiriminiz için teşekkürler!"
            }
        )
    except Exception as e:
        print(f"Geri bildirim hatası: {str(e)}")
        return JSONResponse(
            status_code=500,
            content={
                "status": "error",
                "message": "Geri bildirim kaydedilirken bir hata oluştu",
                "detail": str(e)
            }
        )

# Hata sayfası
@app.get("/error", response_class=HTMLResponse)
async def error(request: Request, message: str = "Bir hata oluştu"):
    return templates.TemplateResponse("error.html", {
        "request": request,
        "error_message": message
    })

@app.get("/chat", response_class=HTMLResponse)
async def chat_page(request: Request):
    return templates.TemplateResponse("chat.html", {"request": request})

@app.post("/chat/send")
@auth_required
async def chat_send(
    request: Request,
    message: str = Form(...),
    current_user: dict = None
):
    try:
        print(f"Received chat request from user: {current_user['username'] if current_user else 'unknown'}")
        print(f"Message content: {message}")
        
        if not message:
            print("Empty message received")
            return JSONResponse(
                status_code=400,
                content={"status": "error", "message": "Mesaj boş olamaz"}
            )
            
        print("Calling Gemini API...")
        response = chat_with_ai(message)
        print(f"Gemini API response received")
        
        if not response:
            print("Empty response from Gemini API")
            return JSONResponse(
                status_code=500,
                content={"status": "error", "message": "API'den yanıt alınamadı"}
            )
            
        print("Sending successful response back to client")
        return JSONResponse(
            status_code=200,
            content={"status": "success", "response": response}
        )
    except Exception as e:
        print(f"Error in chat_send: {type(e).__name__} - {str(e)}")
        return JSONResponse(
            status_code=500,
            content={"status": "error", "message": str(e)}
        )

# Auth endpoints
@app.post("/register")
async def register(
    username: str = Form(...),
    email: str = Form(...),
    password: str = Form(...)
):
    try:
        # Şifre hash'leme
        password_hash = pwd_context.hash(password)
        
        conn = get_db_connection()
        cur = conn.cursor()
        
        # Kullanıcı ve email kontrolü
        cur.execute("SELECT * FROM users WHERE username = %s OR email = %s", (username, email))
        if cur.fetchone():
            raise HTTPException(status_code=400, detail="Username or email already exists")
        
        # Yeni kullanıcı oluşturma
        cur.execute(
            "INSERT INTO users (username, email, password_hash) VALUES (%s, %s, %s) RETURNING id, username, email",
            (username, email, password_hash)
        )
        new_user = cur.fetchone()
        conn.commit()
        
        return JSONResponse(
            status_code=201,
            content={"status": "success", "user": {
                "id": new_user["id"],
                "username": new_user["username"],
                "email": new_user["email"]
            }}
        )
    except HTTPException as e:
        raise e
    except Exception as e:
        print(f"Registration error: {str(e)}")
        raise HTTPException(status_code=500, detail=str(e))
    finally:
        if 'cur' in locals():
            cur.close()
        if 'conn' in locals():
            conn.close()

@app.post("/login")
async def login(form_data: OAuth2PasswordRequestForm = Depends()):
    try:
        print(f"Login attempt for username: {form_data.username}")
        conn = get_db_connection()
        cur = conn.cursor()
        
        # Kullanıcıyı bul
        cur.execute(
            "SELECT id, username, email, password_hash FROM users WHERE username = %s",
            (form_data.username,)
        )
        user = cur.fetchone()
        print(f"Database query result: {user}")
        
        if not user:
            print("User not found in database")
            raise HTTPException(
                status_code=401,
                detail="Incorrect username or password",
                headers={"WWW-Authenticate": "Bearer"},
            )
            
        # Şifre doğrulama
        is_password_correct = pwd_context.verify(form_data.password, user["password_hash"])
        print(f"Password verification result: {is_password_correct}")
        
        if not is_password_correct:
            print("Password verification failed")
            raise HTTPException(
                status_code=401,
                detail="Incorrect username or password",
                headers={"WWW-Authenticate": "Bearer"},
            )
        
        # Token oluştur
        access_token_expires = timedelta(minutes=ACCESS_TOKEN_EXPIRE_MINUTES)
        access_token = create_access_token(
            data={"sub": user["username"]}, expires_delta=access_token_expires
        )
        
        # Son giriş zamanını güncelle
        cur.execute(
            "UPDATE users SET last_login = CURRENT_TIMESTAMP WHERE id = %s",
            (user["id"],)
        )
        conn.commit()
        
        return {
            "access_token": access_token,
            "token_type": "bearer",
            "user": {
                "id": user["id"],
                "username": user["username"],
                "email": user["email"]
            }
        }
    except Exception as e:
        print(f"Login error: {str(e)}")
        raise HTTPException(status_code=500, detail=str(e))
    finally:
        if 'cur' in locals():
            cur.close()
        if 'conn' in locals():
            conn.close()

@app.get("/me")
async def read_users_me(current_user: dict = Depends(get_current_user)):
    return current_user

@app.post("/logout")
async def logout():
    try:
        print("Processing logout request...")
        response = JSONResponse(
            content={"status": "success", "message": "Başarıyla çıkış yapıldı"},
            status_code=200
        )
        return response
    except Exception as e:
        print(f"Error during logout: {str(e)}")
        return JSONResponse(
            content={"status": "error", "message": "Çıkış yapılırken bir hata oluştu"},
            status_code=500
        )

@app.get("/test-db")
async def test_db():
    try:
        conn = get_db_connection()
        cur = conn.cursor()
        cur.execute('SELECT version();')
        version = cur.fetchone()
        cur.close()
        conn.close()
        return {"status": "success", "postgresql_version": version}
    except Exception as e:
        return {"status": "error", "detail": str(e)}

# JWT token oluşturma fonksiyonu
def create_access_token(data: dict, expires_delta: timedelta = None):
    to_encode = data.copy()
    if expires_delta:
        expire = datetime.utcnow() + expires_delta
    else:
        expire = datetime.utcnow() + timedelta(minutes=15)
    to_encode.update({"exp": expire})
    encoded_jwt = jwt.encode(to_encode, SECRET_KEY, algorithm=ALGORITHM)
    return encoded_jwt

# Token doğrulama fonksiyonu
async def get_current_user(token: str):
    credentials_exception = HTTPException(
        status_code=401,
        detail="Could not validate credentials",
        headers={"WWW-Authenticate": "Bearer"},
    )
    
    try:
        # Token'ı temizle (başında veya sonunda boşluk varsa)
        token = token.strip()
        
        print(f"Decoding token: {token[:20]}...")
        payload = jwt.decode(token, SECRET_KEY, algorithms=[ALGORITHM])
        username: str = payload.get("sub")
        
        if username is None:
            print("No username found in token")
            raise credentials_exception
            
        print(f"Username from token: {username}")
        
        conn = get_db_connection()
        cur = conn.cursor()
        
        print("Querying database for user...")
        cur.execute(
            "SELECT id, username, email FROM users WHERE username = %s",
            (username,)
        )
        user = cur.fetchone()
        
        if user is None:
            print("User not found in database")
            raise credentials_exception
            
        print(f"User found: {user['username']}")
        return user
        
    except jwt.ExpiredSignatureError:
        print("Token has expired")
        raise HTTPException(
            status_code=401,
            detail="Token süresi dolmuş",
            headers={"WWW-Authenticate": "Bearer"}
        )
    except jwt.InvalidTokenError:
        print("Invalid token")
        raise HTTPException(
            status_code=401,
            detail="Geçersiz token",
            headers={"WWW-Authenticate": "Bearer"}
        )
    except Exception as e:
        print(f"Unexpected error in token validation: {type(e).__name__} - {str(e)}")
        raise credentials_exception
    finally:
        if 'cur' in locals():
            cur.close()
        if 'conn' in locals():
            conn.close()

if __name__ == "__main__":
    uvicorn.run("main:app", host="0.0.0.0", port=8000, reload=True)

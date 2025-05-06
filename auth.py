from passlib.hash import bcrypt
from fastapi import HTTPException, Depends
from fastapi.security import OAuth2PasswordBearer
from jose import JWTError, jwt
from datetime import datetime, timedelta
from typing import Optional
from database import Database
import os
from dotenv import load_dotenv

load_dotenv()

# JWT ayarları
SECRET_KEY = os.getenv("JWT_SECRET_KEY", "your-secret-key")
ALGORITHM = "HS256"
ACCESS_TOKEN_EXPIRE_MINUTES = 30

oauth2_scheme = OAuth2PasswordBearer(tokenUrl="login")
db = Database()

def verify_password(plain_password: str, hashed_password: str) -> bool:
    return bcrypt.verify(plain_password, hashed_password)

def get_password_hash(password: str) -> str:
    return bcrypt.hash(password)

def create_access_token(data: dict, expires_delta: Optional[timedelta] = None):
    """
    JWT token oluşturma fonksiyonu
    Token süresi ve içeriği için gelişmiş kontroller ekle
    """
    to_encode = data.copy()
    
    # Token süresi için kontrol
    if expires_delta:
        expire = datetime.utcnow() + expires_delta
    else:
        # Varsayılan süre 30 dakika
        expire = datetime.utcnow() + timedelta(minutes=30)
    
    # Ek güvenlik için token oluşturma zamanını ekle
    to_encode.update({
        "exp": expire,  # Token bitiş zamanı
        "iat": datetime.utcnow(),  # Token oluşturma zamanı
        "nbf": datetime.utcnow()   # Token geçerlilik başlangıç zamanı
    })
    
    # Daha güvenli token oluşturma
    try:
        encoded_jwt = jwt.encode(to_encode, SECRET_KEY, algorithm=ALGORITHM)
        return encoded_jwt
    except Exception as e:
        # Token oluşturma hatalarını yakala
        print(f"Token oluşturma hatası: {e}")
        raise

async def get_current_user(token: str = Depends(oauth2_scheme)):
    credentials_exception = HTTPException(
        status_code=401,
        detail="Kimlik doğrulama başarısız",
        headers={"WWW-Authenticate": "Bearer"},
    )
    try:
        payload = jwt.decode(token, SECRET_KEY, algorithms=[ALGORITHM])
        username: str = payload.get("sub")
        if username is None:
            raise credentials_exception
    except JWTError:
        raise credentials_exception
    
    user = get_user(username)
    if user is None:
        raise credentials_exception
    return user

def create_user(username: str, email: str, password: str) -> dict:
    try:
        # Kullanıcı adı veya email zaten var mı kontrol et
        query = "SELECT username, email FROM users WHERE username = %s OR email = %s"
        db.cursor.execute(query, (username, email))
        existing_user = db.cursor.fetchone()
        
        if existing_user:
            if existing_user['username'] == username:
                raise HTTPException(status_code=400, detail="Bu kullanıcı adı zaten kullanımda")
            else:
                raise HTTPException(status_code=400, detail="Bu email adresi zaten kullanımda")
        
        # Yeni kullanıcı oluştur
        hashed_password = get_password_hash(password)
        query = """
            INSERT INTO users (username, email, password_hash)
            VALUES (%s, %s, %s)
            RETURNING id, username, email, created_at
        """
        db.cursor.execute(query, (username, email, hashed_password))
        db.connection.commit()
        
        new_user = db.cursor.fetchone()
        return {
            "id": new_user['id'],
            "username": new_user['username'],
            "email": new_user['email'],
            "created_at": new_user['created_at']
        }
    except Exception as e:
        db.connection.rollback()
        raise HTTPException(status_code=500, detail=str(e))

def authenticate_user(username: str, password: str) -> Optional[dict]:
    try:
        query = """
            SELECT id, username, email, password_hash, is_active
            FROM users
            WHERE username = %s
        """
        db.cursor.execute(query, (username,))
        user = db.cursor.fetchone()
        
        if not user:
            return None
        if not verify_password(password, user['password_hash']):
            return None
        if not user['is_active']:
            raise HTTPException(status_code=400, detail="Kullanıcı hesabı devre dışı")
        
        # Son giriş zamanını güncelle
        update_query = "UPDATE users SET last_login = CURRENT_TIMESTAMP WHERE id = %s"
        db.cursor.execute(update_query, (user['id'],))
        db.connection.commit()
        
        return {
            "id": user['id'],
            "username": user['username'],
            "email": user['email']
        }
    except Exception as e:
        db.connection.rollback()
        raise HTTPException(status_code=500, detail=str(e))

def get_user(username: str) -> Optional[dict]:
    try:
        query = """
            SELECT id, username, email, is_active, is_admin, created_at, last_login
            FROM users
            WHERE username = %s
        """
        db.cursor.execute(query, (username,))
        user = db.cursor.fetchone()
        
        if user:
            return {
                "id": user['id'],
                "username": user['username'],
                "email": user['email'],
                "is_active": user['is_active'],
                "is_admin": user['is_admin'],
                "created_at": user['created_at'],
                "last_login": user['last_login']
            }
        return None
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))

def update_user(user_id: int, data: dict) -> dict:
    try:
        allowed_fields = ['email', 'password']
        update_fields = []
        values = []
        
        for field, value in data.items():
            if field in allowed_fields:
                if field == 'password':
                    update_fields.append("password_hash = %s")
                    values.append(get_password_hash(value))
                else:
                    update_fields.append(f"{field} = %s")
                    values.append(value)
        
        if not update_fields:
            raise HTTPException(status_code=400, detail="Güncellenecek alan bulunamadı")
        
        values.append(user_id)
        query = f"""
            UPDATE users
            SET {", ".join(update_fields)}
            WHERE id = %s
            RETURNING id, username, email, is_active, created_at, last_login
        """
        
        db.cursor.execute(query, values)
        db.connection.commit()
        updated_user = db.cursor.fetchone()
        
        return {
            "id": updated_user['id'],
            "username": updated_user['username'],
            "email": updated_user['email'],
            "is_active": updated_user['is_active'],
            "created_at": updated_user['created_at'],
            "last_login": updated_user['last_login']
        }
    except Exception as e:
        db.connection.rollback()
        raise HTTPException(status_code=500, detail=str(e))

def deactivate_user(user_id: int):
    try:
        query = "UPDATE users SET is_active = false WHERE id = %s"
        db.cursor.execute(query, (user_id,))
        db.connection.commit()
    except Exception as e:
        db.connection.rollback()
        raise HTTPException(status_code=500, detail=str(e)) 
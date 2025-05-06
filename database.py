import psycopg2
from psycopg2 import Error
from typing import Dict, List, Any, Optional
import os
from dotenv import load_dotenv

# .env dosyasından veritabanı bilgilerini yükle
load_dotenv()

class Database:
    def __init__(self):
        try:
            self.connection = psycopg2.connect(
                host=os.getenv("DB_HOST", "localhost"),
                user=os.getenv("DB_USER", "postgres"),
                password=os.getenv("DB_PASSWORD", "241324"),
                database=os.getenv("DB_NAME", "thinkorbit"),
                port=os.getenv("DB_PORT", "5432")
            )
            self.cursor = self.connection.cursor()
        except Error as e:
            print(f"Veritabanı bağlantı hatası: {e}")
            raise

    def close(self):
        if self.connection:
            self.cursor.close()
            self.connection.close()

    # Quiz ile ilgili fonksiyonlar
    def save_quiz_result(self, user_id: int, topic: str, difficulty: str, 
                        total_questions: int, correct_answers: int, score: float) -> int:
        try:
            query = """
                INSERT INTO quiz_results 
                (user_id, topic, difficulty, total_questions, correct_answers, score)
                VALUES (%s, %s, %s, %s, %s, %s)
                RETURNING id
            """
            self.cursor.execute(query, (user_id, topic, difficulty, 
                                      total_questions, correct_answers, score))
            quiz_id = self.cursor.fetchone()[0]
            self.connection.commit()
            return quiz_id
        except Error as e:
            print(f"Quiz sonucu kaydedilirken hata: {e}")
            self.connection.rollback()
            raise

    def save_quiz_questions(self, quiz_id: int, questions: List[Dict[str, Any]]):
        try:
            query = """
                INSERT INTO quiz_questions 
                (quiz_id, question_text, correct_answer, option_a, option_b, option_c, option_d, explanation)
                VALUES (%s, %s, %s, %s, %s, %s, %s, %s)
            """
            for question in questions:
                self.cursor.execute(query, (
                    quiz_id,
                    question['question'],
                    question['correct_option'],
                    question['options']['A'],
                    question['options']['B'],
                    question['options']['C'],
                    question['options']['D'],
                    question.get('explanation', '')
                ))
            self.connection.commit()
        except Error as e:
            print(f"Quiz soruları kaydedilirken hata: {e}")
            self.connection.rollback()
            raise

    def save_study_plan(self, quiz_id: int, plan_content: str):
        try:
            query = "INSERT INTO study_plans (quiz_id, plan_content) VALUES (%s, %s)"
            self.cursor.execute(query, (quiz_id, plan_content))
            self.connection.commit()
        except Error as e:
            print(f"Çalışma planı kaydedilirken hata: {e}")
            self.connection.rollback()
            raise

    # Sohbet ile ilgili fonksiyonlar
    def save_chat_message(self, user_id: int, message: str, response: str):
        try:
            query = """
                INSERT INTO chat_history (user_id, message, response)
                VALUES (%s, %s, %s)
            """
            self.cursor.execute(query, (user_id, message, response))
            self.connection.commit()
        except Error as e:
            print(f"Sohbet mesajı kaydedilirken hata: {e}")
            self.connection.rollback()
            raise

    def get_user_chat_history(self, user_id: int, limit: int = 10) -> List[Dict[str, Any]]:
        try:
            query = """
                SELECT message, response, created_at
                FROM chat_history
                WHERE user_id = %s
                ORDER BY created_at DESC
                LIMIT %s
            """
            self.cursor.execute(query, (user_id, limit))
            columns = [desc[0] for desc in self.cursor.description]
            return [dict(zip(columns, row)) for row in self.cursor.fetchall()]
        except Error as e:
            print(f"Sohbet geçmişi alınırken hata: {e}")
            raise

    def get_user_quiz_history(self, user_id: int, limit: int = 10) -> List[Dict[str, Any]]:
        try:
            query = """
                SELECT topic, difficulty, total_questions, correct_answers, score, created_at
                FROM quiz_results
                WHERE user_id = %s
                ORDER BY created_at DESC
                LIMIT %s
            """
            self.cursor.execute(query, (user_id, limit))
            columns = [desc[0] for desc in self.cursor.description]
            return [dict(zip(columns, row)) for row in self.cursor.fetchall()]
        except Error as e:
            print(f"Quiz geçmişi alınırken hata: {e}")
            raise

# Veritabanı bağlantısı için singleton instance
db = Database()

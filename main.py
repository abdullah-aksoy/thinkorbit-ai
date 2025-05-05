# main.py
from fastapi import FastAPI
from pydantic import BaseModel
from feedback import get_feedback_from_gemini

app = FastAPI()

class QuizQuestion(BaseModel):
    question: str
    user_answer: str
    correct_answer: str

class FeedbackRequest(BaseModel):
    topic: str
    questions: list[QuizQuestion]

@app.post("/feedback")
def generate_feedback(data: FeedbackRequest):
    questions_text = "\n".join(
        [f"Soru: {q.question}\nVerdiği Cevap: {q.user_answer} | Doğru Cevap: {q.correct_answer}" for q in data.questions]
    )
    feedback_text = get_feedback_from_gemini(data.topic, questions_text)
    return {"feedback": feedback_text}

"""
rag_server.py
=============
FastAPI 상시 서버 — 모델을 메모리에 올려두고 HTTP로 검색 요청을 처리

실행:
    python scripts/rag/rag_server.py

PHP에서 호출:
    POST http://localhost:8001/search
    {"query": "이상사례 보고 기한", "top_k": 5}

엔드포인트:
    POST /search  → 법령 검색
    GET  /health  → 서버 상태 확인
"""

import os
import sys
from contextlib import asynccontextmanager
from dotenv import load_dotenv
from fastapi import FastAPI, HTTPException
from pydantic import BaseModel
from pinecone import Pinecone
from sentence_transformers import SentenceTransformer, CrossEncoder

load_dotenv()

PINECONE_API_KEY = os.getenv("PINECONE_API_KEY")
PINECONE_INDEX   = os.getenv("PINECONE_INDEX_NAME", "gpsp-laws")

# =============================================
# 전역 모델 — 서버 시작 시 1회만 로드
# =============================================

_embedder = None
_reranker = None
_index    = None


@asynccontextmanager
async def lifespan(app: FastAPI):
    """서버 시작 시 모델 로드, 종료 시 정리"""
    global _embedder, _reranker, _index

    print("=" * 50)
    print("AI-PMS RAG 서버 시작 중...")
    print("=" * 50)

    print("[1/3] 임베딩 모델 로드 중...")
    _embedder = SentenceTransformer("paraphrase-multilingual-MiniLM-L12-v2")
    print("      ✅ paraphrase-multilingual-MiniLM-L12-v2 완료")

    print("[2/3] Reranker 모델 로드 중...")
    _reranker = CrossEncoder("cross-encoder/mmarco-mMiniLMv2-L12-H384-v1")
    print("      ✅ mmarco-mMiniLMv2 완료")

    print("[3/3] Pinecone 연결 중...")
    pc     = Pinecone(api_key=PINECONE_API_KEY)
    _index = pc.Index(PINECONE_INDEX)
    print(f"      ✅ {PINECONE_INDEX} 인덱스 연결 완료")

    print("=" * 50)
    print("🚀 서버 준비 완료! http://localhost:8001")
    print("=" * 50)

    yield  # 서버 실행

    print("서버 종료 중...")


app = FastAPI(
    title="AI-PMS RAG Server",
    description="GPSP / GCP / ICH E2A 벡터 검색 + Reranking API",
    version="1.0.0",
    lifespan=lifespan,
)


# =============================================
# Query Expansion
# =============================================

QUERY_EXPANSION = {
    "이상사례":  "有害事象 adverse event AE",
    "유해사례":  "有害事象 adverse event AE",
    "부작용":    "副作用 adverse drug reaction ADR",
    "중대":      "重篤 serious SAE",
    "SAE":       "重篤有害事象 serious adverse event 중대한 이상사례",
    "AE":        "有害事象 adverse event 이상사례",
    "보고":      "報告 report reporting 신고",
    "기한":      "期限 deadline 기간 days",
    "7일":       "七日 7 days",
    "15일":      "十五日 15 days",
    "신속":      "速やか expedited 즉시",
    "계약":      "契約 contract agreement",
    "위탁":      "委託 outsourcing delegation",
    "의무":      "義務 obligation 준수 compliance",
    "개인정보":  "個人情報 personal data privacy",
    "동의":      "同意 consent 승인",
    "익명":      "匿名 anonymization 비식별",
    "시판후":    "製造販売後 post-marketing PMS",
    "임상시험":  "臨床試験 clinical trial 치험",
    "프로토콜":  "実施計画書 protocol",
    "모니터링":  "モニタリング monitoring",
    "有害事象":  "이상사례 adverse event AE",
    "副作用":    "부작용 ADR adverse drug reaction",
    "重篤":      "중대한 serious SAE",
    "報告":      "보고 report reporting",
    "契約":      "계약 contract",
    "委託":      "위탁 outsourcing",
}


def expand_query(query: str) -> str:
    expansions = []
    for keyword, synonyms in QUERY_EXPANSION.items():
        if keyword in query:
            expansions.append(synonyms)
    if expansions:
        return query + " " + " ".join(expansions)
    return query


# =============================================
# 요청/응답 스키마
# =============================================

class SearchRequest(BaseModel):
    query: str
    top_k: int = 5


class LawResult(BaseModel):
    clause:       str
    text:         str
    source:       str
    score:        float
    rerank_score: float
    vector_score: float


class SearchResponse(BaseModel):
    query:    str
    expanded: str
    results:  list[LawResult]


# =============================================
# 엔드포인트
# =============================================

@app.get("/health")
def health():
    """서버 상태 확인 — Laravel에서 서버가 살아있는지 체크할 때 사용"""
    return {
        "status":   "ok",
        "embedder": _embedder is not None,
        "reranker": _reranker is not None,
        "index":    _index is not None,
    }


@app.post("/search", response_model=SearchResponse)
def search(req: SearchRequest):
    """
    법령 벡터 검색 + Reranking

    PHP ContractService에서 호출:
        POST http://localhost:8001/search
        {"query": "이상사례 보고 기한", "top_k": 5}
    """
    if _embedder is None or _reranker is None or _index is None:
        raise HTTPException(status_code=503, detail="모델이 아직 로드되지 않았습니다")

    # 1단계: Query Expansion + 벡터 검색
    expanded = expand_query(req.query)
    vector   = _embedder.encode(expanded).tolist()

    candidates = _index.query(
        vector           = vector,
        top_k            = req.top_k * 4,
        include_metadata = True,
    )

    if not candidates["matches"]:
        return SearchResponse(query=req.query, expanded=expanded, results=[])

    # 2단계: Cross-Encoder Reranking
    pairs = [
        [req.query, match["metadata"].get("text", "")]
        for match in candidates["matches"]
    ]
    rerank_scores = _reranker.predict(pairs)

    reranked = []
    for i, match in enumerate(candidates["matches"]):
        reranked.append(LawResult(
            clause       = match["metadata"].get("clause", ""),
            text         = match["metadata"].get("text", ""),
            source       = match["metadata"].get("source", ""),
            vector_score = round(float(match["score"]), 4),
            rerank_score = round(float(rerank_scores[i]), 4),
            score        = round(float(rerank_scores[i]), 4),
        ))

    reranked.sort(key=lambda x: x.rerank_score, reverse=True)

    return SearchResponse(
        query    = req.query,
        expanded = expanded,
        results  = reranked[:req.top_k],
    )

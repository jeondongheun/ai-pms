"""
search.py
=========
계약서 텍스트 → 쿼리 확장 → 벡터 검색 (top 20) → Reranking (top 5) → 반환

2단계 검색 파이프라인:
  1단계: Pinecone 벡터 검색으로 후보 20개 추출 (빠름, 대략적)
  2단계: Cross-Encoder Reranker로 정밀 재순위 (느리지만 정확)
"""

import os
import sys
import json
from dotenv import load_dotenv
from pinecone import Pinecone
from sentence_transformers import SentenceTransformer, CrossEncoder

load_dotenv()

PINECONE_API_KEY = os.getenv("PINECONE_API_KEY")
PINECONE_INDEX   = os.getenv("PINECONE_INDEX_NAME", "gpsp-laws")

_embedder  = None  # 벡터 임베딩 모델 (1단계)
_reranker  = None  # Cross-Encoder 재순위 모델 (2단계)
_index     = None  # Pinecone 인덱스

def get_embedder():
    global _embedder
    if _embedder is None:
        _embedder = SentenceTransformer("paraphrase-multilingual-MiniLM-L12-v2")
    return _embedder

def get_reranker():
    global _reranker
    if _reranker is None:
        # 다국어 Cross-Encoder: 한/일/영 모두 지원
        _reranker = CrossEncoder("cross-encoder/mmarco-mMiniLMv2-L12-H384-v1")
    return _reranker

def get_index():
    global _index
    if _index is None:
        pc     = Pinecone(api_key=PINECONE_API_KEY)
        _index = pc.Index(PINECONE_INDEX)
    return _index


# =============================================
# Query Expansion
# =============================================

QUERY_EXPANSION = {
    # 이상사례
    "이상사례":   "有害事象 adverse event AE",
    "유해사례":   "有害事象 adverse event AE",
    "부작용":     "副作用 adverse drug reaction ADR",
    "중대":       "重篤 serious SAE",
    "SAE":        "重篤有害事象 serious adverse event 중대한 이상사례",
    "AE":         "有害事象 adverse event 이상사례",

    # 보고
    "보고":       "報告 report reporting 신고",
    "기한":       "期限 deadline 기간 days",
    "7일":        "七日 7 days",
    "15일":       "十五日 15 days",
    "신속":       "速やか expedited 즉시",

    # 계약
    "계약":       "契約 contract agreement",
    "위탁":       "委託 outsourcing delegation",
    "의무":       "義務 obligation 준수 compliance",

    # 개인정보
    "개인정보":   "個人情報 personal data privacy",
    "동의":       "同意 consent 승인",
    "익명":       "匿名 anonymization 비식별",

    # 조사
    "시판후":     "製造販売後 post-marketing PMS",
    "임상시험":   "臨床試験 clinical trial 치험",
    "프로토콜":   "実施計画書 protocol",
    "모니터링":   "モニタリング monitoring",

    # 역방향 (일본어 → 한국어/영어)
    "有害事象":   "이상사례 adverse event AE",
    "副作用":     "부작용 ADR adverse drug reaction",
    "重篤":       "중대한 serious SAE",
    "報告":       "보고 report reporting",
    "契約":       "계약 contract",
    "委託":       "위탁 outsourcing",
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
# 2단계 검색 파이프라인
# =============================================

def search_laws(query: str, top_k: int = 5) -> list:
    """
    1단계: 벡터 검색으로 후보 20개 추출
    2단계: Cross-Encoder로 재순위 후 top_k 반환

    Args:
        query:  검색 쿼리 (한/일/영 모두 가능)
        top_k:  최종 반환 수 (기본 5)
    """

    # ── 1단계: 벡터 검색 ──────────────────────────
    expanded_query = expand_query(query)
    embedder = get_embedder()
    vector   = embedder.encode(expanded_query).tolist()

    index   = get_index()
    # 재순위 후보를 위해 top_k * 4개 추출
    candidates = index.query(
        vector           = vector,
        top_k            = top_k * 4,
        include_metadata = True,
    )

    if not candidates["matches"]:
        return []

    # ── 2단계: Cross-Encoder Reranking ────────────
    reranker = get_reranker()

    # [쿼리, 문서] 쌍 생성
    pairs = [
        [query, match["metadata"].get("text", "")]
        for match in candidates["matches"]
    ]

    # Cross-Encoder가 각 쌍의 관련성 점수 계산
    rerank_scores = reranker.predict(pairs)

    # 원본 후보에 rerank 점수 추가
    reranked = []
    for i, match in enumerate(candidates["matches"]):
        reranked.append({
            "clause":        match["metadata"].get("clause", ""),
            "text":          match["metadata"].get("text", ""),
            "source":        match["metadata"].get("source", ""),
            "vector_score":  round(float(match["score"]), 4),
            "rerank_score":  round(float(rerank_scores[i]), 4),
            # 최종 점수: rerank 점수 기준으로 정렬
            "score":         round(float(rerank_scores[i]), 4),
        })

    # rerank_score 기준 내림차순 정렬
    reranked.sort(key=lambda x: x["rerank_score"], reverse=True)

    # 상위 top_k만 반환
    return reranked[:top_k]


# =============================================
# 커맨드라인 실행
# =============================================

if __name__ == "__main__":
    if len(sys.argv) < 2:
        print("테스트 모드: 샘플 쿼리 실행\n")
        queries = [
            "이상사례 보고 기한",
            "개인정보 보호 계약",
            "위탁 계약 의무사항",
            "중대한 이상사례 SAE 7일",
        ]
        for q in queries:
            print(f"🔍 쿼리: {q}")
            results = search_laws(q, top_k=3)
            for r in results:
                print(
                    f"  [rerank:{r['rerank_score']:+.2f} / vec:{r['vector_score']}]"
                    f" [{r['source'][:10]}] {r['clause'][:35]}"
                )
            print()
    else:
        query   = sys.argv[1]
        top_k   = int(sys.argv[2]) if len(sys.argv) > 2 else 5
        results = search_laws(query, top_k=top_k)
        print(json.dumps(results, ensure_ascii=False))

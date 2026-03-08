"""
ingest.py
=========
GPSP 성령 + GCP 성령 + ICH E2A + 일본 개인정보보호법 수집 → 청크 분리 → 한국어 키워드 보강 → 임베딩 → Pinecone 저장

텍스트 소스 파일 위치:
  scripts/rag/data/ich_e2a.txt  - ICH E2A 가이드라인
  scripts/rag/data/pipa.txt     - 일본 개인정보보호법
"""

import os
import re
import time
from pathlib import Path
from dotenv import load_dotenv
from pinecone import Pinecone, ServerlessSpec
from sentence_transformers import SentenceTransformer
import requests
from bs4 import BeautifulSoup

load_dotenv()

PINECONE_API_KEY = os.getenv("PINECONE_API_KEY")
PINECONE_INDEX   = os.getenv("PINECONE_INDEX_NAME", "gpsp-laws")

# 텍스트 파일 기준 경로 (ingest.py 기준 상대경로)
DATA_DIR = Path(__file__).parent / "data"

# =============================================
# 한국어 ↔ 일본어 키워드 매핑
# =============================================

KO_JA_KEYWORD_MAP = {
    "有害事象":       "이상사례 유해사례 AE adverse event",
    "副作用":         "부작용 약물유해반응 ADR",
    "重篤":           "중대한 SAE 심각한",
    "予期しない":     "예상치 못한 unexpected",
    "死亡":           "사망 death fatal",
    "入院":           "입원 hospitalisation",
    "報告":           "보고 report 신고",
    "期限":           "기한 deadline 기간",
    "速やか":         "신속 즉시 빠르게",
    "七日":           "7일 7 days",
    "十五日":         "15일 15 days",
    "二十四時間":     "24시간",
    "契約":           "계약 contract",
    "委託":           "위탁 위임 outsourcing",
    "医療機関":       "의료기관 병원 hospital",
    "製造販売業者":   "제약회사 제조판매업자 sponsor",
    "個人情報":       "개인정보 personal data privacy 프라이버시",
    "個人データ":     "개인데이터 개인정보 personal data",
    "匿名":           "익명화 비식별화 anonymization",
    "秘密":           "기밀 비밀 confidential",
    "同意":           "동의 consent 승인",
    "第三者提供":     "제3자 제공 third party disclosure",
    "目的外利用":     "목적 외 이용 secondary use",
    "安全管理":       "안전관리 보안 security",
    "漏洩":           "유출 leak breach 침해",
    "開示":           "공개 disclosure 열람",
    "削除":           "삭제 erasure 파기",
    "保有個人データ": "보유개인데이터 보유정보",
    "プライバシー":   "프라이버시 privacy 개인정보보호",
    "使用成績調査":   "사용성적조사 시판후조사 PMS",
    "製造販売後":     "시판후 제조판매후 post-marketing",
    "調査":           "조사 investigation survey",
    "実施計画書":     "실시계획서 프로토콜 protocol",
    "記録":           "기록 문서 document record",
    "保存":           "보존 보관 storage retention",
    "治験責任医師":   "치험책임의사 주임연구자 principal investigator",
    "依頼者":         "의뢰자 스폰서 sponsor",
    "モニタリング":   "모니터링 monitoring",
    "省令":           "성령 법령 regulation",
    "基準":           "기준 표준 standard",
    "遵守":           "준수 compliance",
}


def enrich_with_korean(text: str) -> str:
    found_keywords = []
    for ja_word, ko_words in KO_JA_KEYWORD_MAP.items():
        if ja_word in text:
            found_keywords.append(ko_words)
    if found_keywords:
        keyword_str = " ".join(found_keywords)
        return f"{text}\n[관련 키워드: {keyword_str}]"
    return text


# =============================================
# 데이터 소스 정의
# =============================================

SOURCES = [
    {
        "id":   "gpsp",
        "name": "일본 GPSP 성령 (厚生労働省令第171号)",
        "type": "html",
        "url":  "https://www.mhlw.go.jp/web/t_doc?dataId=81aa6623&dataType=0&pageNo=1",
        "lang": "ja",
    },
    {
        "id":   "gcp",
        "name": "일본 GCP 성령 (厚生省令第28号)",
        "type": "html",
        "url":  "https://www.mhlw.go.jp/web/t_doc?dataId=81997396&dataType=0&pageNo=1",
        "lang": "ja",
    },
    {
        "id":   "ich_e2a",
        "name": "ICH E2A Clinical Safety Data Management Guideline",
        "type": "file",
        "file": DATA_DIR / "ich_e2a.txt",
        "lang": "en",
    },
    {
        "id":   "pipa",
        "name": "일본 개인정보보호법 (個人情報の保護に関する法律)",
        "type": "file",
        "file": DATA_DIR / "pipa.txt",
        "lang": "ja",
    },
]


# =============================================
# 1. 원문 수집
# =============================================

def fetch_html(url: str) -> str:
    headers = {"User-Agent": "Mozilla/5.0"}
    res = requests.get(url, headers=headers, timeout=30)
    res.encoding = "utf-8"
    soup = BeautifulSoup(res.text, "html.parser")
    for tag in soup(["script", "style", "img", "nav", "footer"]):
        tag.decompose()
    text  = soup.get_text(separator="\n")
    lines = [l.strip() for l in text.splitlines() if l.strip()]
    return "\n".join(lines)


def fetch_file(filepath: Path) -> str:
    if not filepath.exists():
        raise FileNotFoundError(f"파일을 찾을 수 없습니다: {filepath}")
    return filepath.read_text(encoding="utf-8")


def fetch_source(source: dict) -> str:
    print(f"  → {source['name']} 수집 중...")
    try:
        if source["type"] == "html":
            text = fetch_html(source["url"])
        elif source["type"] == "file":
            text = fetch_file(source["file"])
        else:
            text = ""
        print(f"  → {len(text)}자 수집 완료")
        return text
    except Exception as e:
        print(f"  → 수집 실패: {e}")
        return ""


# =============================================
# 2. 청크 분리
# =============================================

def kanji_to_ascii(text: str) -> str:
    kanji_map = {
        "一": "1", "二": "2", "三": "3", "四": "4", "五": "5",
        "六": "6", "七": "7", "八": "8", "九": "9", "十": "10",
        "百": "100",
    }
    result = text
    for k, v in kanji_map.items():
        result = result.replace(k, v)
    result = result.replace("第", "article-").replace("条", "").replace("の", "-")
    result = re.sub(r'[^\x00-\x7F]', '', result)
    return result.strip() or "unknown"


def split_japanese_law(text: str, source_id: str, source_name: str) -> list:
    pattern = r"(第[一二三四五六七八九十百]+条(?:の[一二三四五六七八九十百]+)?)"
    parts   = re.split(pattern, text)
    chunks  = []
    i = 1
    while i < len(parts) - 1:
        title = parts[i].strip()
        body  = parts[i + 1].strip() if i + 1 < len(parts) else ""

        NOISE_PATTERNS = ["の例による", "準用する規定", "読み替えて準用"]
        is_noise = len(body) < 20 or (
            len(body) < 100 and any(p in body for p in NOISE_PATTERNS)
        )
        if is_noise:
            i += 2
            continue

        enriched_text = enrich_with_korean(f"{title}\n{body[:800]}")
        chunks.append({
            "id":     f"{source_id}-{kanji_to_ascii(title)}",
            "clause": title,
            "text":   enriched_text,
            "source": source_name,
        })
        i += 2
    return chunks


def split_english_doc(text: str, source_id: str, source_name: str) -> list:
    pattern = r"((?:Section\s+[IVX]+[:\-\s]|[A-Z]{2,}[:\s])[^\n]{3,60})"
    parts   = re.split(pattern, text)
    chunks  = []
    i = 1
    while i < len(parts) - 1:
        title = parts[i].strip()
        body  = parts[i + 1].strip() if i + 1 < len(parts) else ""
        if len(body) < 30:
            i += 2
            continue
        section_id = str(i // 2)
        chunks.append({
            "id":     f"{source_id}-{section_id}",
            "clause": title,
            "text":   f"{title}\n{body[:800]}",
            "source": source_name,
        })
        i += 2
    return chunks


def split_fallback(text: str, source_id: str, source_name: str) -> list:
    chunks = []
    for idx, start in enumerate(range(0, len(text), 500)):
        chunk_text = text[start:start + 500]
        if len(chunk_text) < 50:
            continue
        enriched = enrich_with_korean(chunk_text) if source_id != "ich_e2a" else chunk_text
        chunks.append({
            "id":     f"{source_id}-chunk-{idx}",
            "clause": f"Section {idx}",
            "text":   enriched,
            "source": source_name,
        })
    return chunks


def split_into_chunks(text: str, source: dict) -> list:
    sid  = source["id"]
    name = source["name"]
    lang = source["lang"]

    if lang == "ja":
        chunks = split_japanese_law(text, sid, name)
    elif lang == "en":
        chunks = split_english_doc(text, sid, name)
    else:
        chunks = []

    if not chunks:
        print(f"  → 패턴 미발견. 500자 단위로 분리합니다.")
        chunks = split_fallback(text, sid, name)

    return chunks


# =============================================
# 3. 임베딩
# =============================================

def create_embeddings(chunks: list) -> list:
    print(f"[3/4] 임베딩 생성 중... (총 {len(chunks)}개 청크)")
    model   = SentenceTransformer("paraphrase-multilingual-MiniLM-L12-v2")
    texts   = [c["text"] for c in chunks]
    vectors = model.encode(texts, show_progress_bar=True, batch_size=16)
    for i, c in enumerate(chunks):
        c["vector"] = vectors[i].tolist()
    print(f"  → 완료. 벡터 차원: {len(chunks[0]['vector'])}")
    return chunks


# =============================================
# 4. Pinecone 저장
# =============================================

def upsert_to_pinecone(chunks: list) -> None:
    print(f"[4/4] Pinecone에 저장 중... (총 {len(chunks)}개)")
    pc  = Pinecone(api_key=PINECONE_API_KEY)
    dim = len(chunks[0]["vector"])

    existing = [idx.name for idx in pc.list_indexes()]
    if PINECONE_INDEX not in existing:
        print(f"  → 인덱스 '{PINECONE_INDEX}' 생성 중...")
        pc.create_index(
            name      = PINECONE_INDEX,
            dimension = dim,
            metric    = "cosine",
            spec      = ServerlessSpec(cloud="aws", region="us-east-1"),
        )
        time.sleep(10)

    index = pc.Index(PINECONE_INDEX)

    print("  → 기존 벡터 초기화 중...")
    index.delete(delete_all=True)
    time.sleep(3)

    for i in range(0, len(chunks), 100):
        batch   = chunks[i:i + 100]
        vectors = [
            {
                "id":       c["id"],
                "values":   c["vector"],
                "metadata": {
                    "clause": c["clause"],
                    "text":   c["text"][:1000],
                    "source": c["source"],
                },
            }
            for c in batch
        ]
        index.upsert(vectors=vectors)
        print(f"  → {i + len(batch)}/{len(chunks)} 저장 완료")

    time.sleep(3)
    stats = index.describe_index_stats()
    print(f"\n✅ 완료! Pinecone에 총 {stats['total_vector_count']}개 벡터 저장됨")


# =============================================
# 메인
# =============================================

if __name__ == "__main__":
    print("=" * 60)
    print("AI-PMS RAG 멀티소스 파이프라인 (한국어 키워드 보강)")
    print("소스: GPSP 성령 + GCP 성령 + ICH E2A + 개인정보보호법")
    print("=" * 60)

    all_chunks = []

    print("\n[1-2/4] 법령 원문 수집 및 청크 분리")
    for source in SOURCES:
        print(f"\n📄 {source['name']}")
        text = fetch_source(source)
        if not text:
            print("  → 건너뜀")
            continue
        chunks = split_into_chunks(text, source)
        print(f"  → {len(chunks)}개 청크 생성")
        all_chunks.extend(chunks)

    print(f"\n총 {len(all_chunks)}개 청크 수집 완료")

    if not all_chunks:
        print("❌ 수집된 데이터가 없습니다.")
        exit(1)

    print()
    all_chunks = create_embeddings(all_chunks)

    print()
    upsert_to_pinecone(all_chunks)

    print(f"\n🎉 완료! 총 {len(all_chunks)}개 법령 조항 벡터 DB 저장")
    print("소스별 통계:")
    for source in SOURCES:
        count = sum(1 for c in all_chunks if source["id"] in c["id"])
        print(f"  - {source['name']}: {count}개")

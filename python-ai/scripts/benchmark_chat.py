#!/usr/bin/env python3
"""
Benchmark runner lokal sederhana untuk ISTA AI — Issue #190.

Mengukur latency end-to-end untuk tiga mode:
- Chat biasa (general)
- Web search
- Chat dokumen (RAG)

Cara menjalankan:
    cd python-ai
    source venv/bin/activate
    python scripts/benchmark_chat.py [--url http://127.0.0.1:8001] [--token <token>] [--mode all]

Output:
    Tabel ringkasan latency per mode (min, max, avg, p50, p95)
    Log per request ke stdout

Keamanan:
    - Tidak menyimpan konten response ke file
    - Token dibaca dari env ISTA_AI_TOKEN atau argumen --token
    - Query benchmark bersifat generik, tidak mengandung data sensitif
"""

import argparse
import json
import os
import statistics
import sys
import time
import uuid
from typing import Dict, List, Optional, Tuple


# ---------------------------------------------------------------------------
# Query benchmark — generik, tidak mengandung data sensitif
# ---------------------------------------------------------------------------

BENCHMARK_QUERIES = {
    "general": [
        "Apa tugas utama seorang sekretaris dalam rapat dinas?",
        "Bagaimana cara membuat surat dinas yang baik?",
        "Jelaskan perbedaan antara memo internal dan surat resmi.",
        "Apa yang dimaksud dengan disposisi dalam administrasi perkantoran?",
        "Bagaimana prosedur pengarsipan dokumen yang benar?",
    ],
    "web_search": [
        "Apa berita terbaru tentang kebijakan pemerintah hari ini?",
        "Cari informasi terkini tentang regulasi ASN terbaru.",
        "Apa perkembangan terbaru dalam digitalisasi layanan pemerintah?",
    ],
}


# ---------------------------------------------------------------------------
# HTTP client sederhana (tidak butuh library eksternal selain requests)
# ---------------------------------------------------------------------------

def _stream_chat(
    url: str,
    token: str,
    messages: List[Dict],
    force_web_search: bool = False,
    request_id: Optional[str] = None,
    timeout: int = 60,
) -> Tuple[float, float, int, bool]:
    """
    Kirim request ke /api/chat dan ukur TTFT + total duration.

    Returns:
        (ttft_ms, total_ms, chunk_count, success)
    """
    try:
        import requests
    except ImportError:
        print("ERROR: requests library tidak tersedia. Install dengan: pip install requests")
        sys.exit(1)

    rid = request_id or str(uuid.uuid4())
    headers = {
        "Authorization": f"Bearer {token}",
        "Accept": "text/event-stream",
        "Content-Type": "application/json",
        "X-Request-ID": rid,
    }
    payload = {
        "messages": messages,
        "force_web_search": force_web_search,
        "allow_auto_realtime_web": force_web_search,
    }

    t_start = time.perf_counter()
    ttft_ms = -1.0
    chunk_count = 0

    try:
        with requests.post(
            f"{url}/api/chat",
            headers=headers,
            json=payload,
            stream=True,
            timeout=timeout,
        ) as resp:
            resp.raise_for_status()

            for chunk in resp.iter_content(chunk_size=None):
                if chunk:
                    if ttft_ms < 0:
                        ttft_ms = (time.perf_counter() - t_start) * 1000.0
                    chunk_count += 1

        total_ms = (time.perf_counter() - t_start) * 1000.0
        return ttft_ms, total_ms, chunk_count, True

    except Exception as exc:
        total_ms = (time.perf_counter() - t_start) * 1000.0
        print(f"  ERROR: {exc}")
        return -1.0, total_ms, 0, False


# ---------------------------------------------------------------------------
# Runner per mode
# ---------------------------------------------------------------------------

def run_mode(
    mode: str,
    queries: List[str],
    url: str,
    token: str,
    force_web: bool = False,
) -> List[Dict]:
    results = []
    print(f"\n{'='*60}")
    print(f"Mode: {mode.upper()} ({len(queries)} queries)")
    print(f"{'='*60}")

    for i, query in enumerate(queries, 1):
        rid = str(uuid.uuid4())[:8]
        messages = [{"role": "user", "content": query}]

        print(f"  [{i}/{len(queries)}] request_id={rid} ... ", end="", flush=True)

        ttft_ms, total_ms, chunks, success = _stream_chat(
            url=url,
            token=token,
            messages=messages,
            force_web_search=force_web,
            request_id=rid,
            timeout=90,
        )

        status = "OK" if success else "FAIL"
        print(f"{status} | TTFT={ttft_ms:.0f}ms | total={total_ms:.0f}ms | chunks={chunks}")

        results.append({
            "mode": mode,
            "request_id": rid,
            "ttft_ms": ttft_ms,
            "total_ms": total_ms,
            "chunks": chunks,
            "success": success,
        })

    return results


# ---------------------------------------------------------------------------
# Summary statistics
# ---------------------------------------------------------------------------

def print_summary(all_results: List[Dict]) -> None:
    print(f"\n{'='*60}")
    print("RINGKASAN BENCHMARK")
    print(f"{'='*60}")

    modes = sorted(set(r["mode"] for r in all_results))

    for mode in modes:
        mode_results = [r for r in all_results if r["mode"] == mode]
        successful = [r for r in mode_results if r["success"]]

        if not successful:
            print(f"\n{mode.upper()}: semua request gagal")
            continue

        ttfts = [r["ttft_ms"] for r in successful if r["ttft_ms"] > 0]
        totals = [r["total_ms"] for r in successful]

        print(f"\n{mode.upper()} ({len(successful)}/{len(mode_results)} berhasil):")

        if ttfts:
            print(f"  TTFT  — min={min(ttfts):.0f}ms  avg={statistics.mean(ttfts):.0f}ms  "
                  f"p50={statistics.median(ttfts):.0f}ms  max={max(ttfts):.0f}ms")
        if totals:
            print(f"  Total — min={min(totals):.0f}ms  avg={statistics.mean(totals):.0f}ms  "
                  f"p50={statistics.median(totals):.0f}ms  max={max(totals):.0f}ms")

    total_ok = sum(1 for r in all_results if r["success"])
    print(f"\nTotal: {total_ok}/{len(all_results)} request berhasil")


# ---------------------------------------------------------------------------
# Save results to JSON
# ---------------------------------------------------------------------------

def save_results(results: List[Dict], output_path: str) -> None:
    with open(output_path, "w", encoding="utf-8") as f:
        json.dump(results, f, ensure_ascii=False, indent=2)
    print(f"\nHasil disimpan ke: {output_path}")


# ---------------------------------------------------------------------------
# Main
# ---------------------------------------------------------------------------

def main() -> None:
    parser = argparse.ArgumentParser(
        description="Benchmark runner lokal untuk ISTA AI latency measurement"
    )
    parser.add_argument(
        "--url",
        default=os.environ.get("ISTA_AI_URL", "http://127.0.0.1:8001"),
        help="Base URL Python AI service (default: http://127.0.0.1:8001)",
    )
    parser.add_argument(
        "--token",
        default=os.environ.get("ISTA_AI_TOKEN", ""),
        help="Bearer token untuk autentikasi (atau set env ISTA_AI_TOKEN)",
    )
    parser.add_argument(
        "--mode",
        choices=["general", "web_search", "all"],
        default="all",
        help="Mode benchmark yang dijalankan (default: all)",
    )
    parser.add_argument(
        "--output",
        default="",
        help="Path file JSON untuk menyimpan hasil (opsional)",
    )
    args = parser.parse_args()

    if not args.token:
        print("ERROR: Token tidak ditemukan. Set --token atau env ISTA_AI_TOKEN")
        sys.exit(1)

    print(f"Target: {args.url}")
    print(f"Mode: {args.mode}")

    all_results: List[Dict] = []

    if args.mode in ("general", "all"):
        all_results.extend(run_mode(
            "general",
            BENCHMARK_QUERIES["general"],
            url=args.url,
            token=args.token,
            force_web=False,
        ))

    if args.mode in ("web_search", "all"):
        all_results.extend(run_mode(
            "web_search",
            BENCHMARK_QUERIES["web_search"],
            url=args.url,
            token=args.token,
            force_web=True,
        ))

    print_summary(all_results)

    if args.output:
        save_results(all_results, args.output)


if __name__ == "__main__":
    main()

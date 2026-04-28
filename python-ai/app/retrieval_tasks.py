import argparse
import json
import logging

logging.basicConfig(level=logging.INFO)


def run_search_task(query: str, filenames_json: str, top_k: int, user_id: str | None) -> int:
    from app.services.rag_retrieval import search_relevant_chunks

    filenames = json.loads(filenames_json) if filenames_json else None
    chunks, success = search_relevant_chunks(
        query,
        filenames,
        top_k=top_k,
        user_id=user_id or None,
    )
    print(
        json.dumps(
            {
                "success": bool(success),
                "chunks": chunks,
            },
            ensure_ascii=False,
        ),
        flush=True,
    )
    return 0 if success else 1


def main() -> int:
    parser = argparse.ArgumentParser(description="ISTA AI retrieval task runner")
    subparsers = parser.add_subparsers(dest="command", required=True)

    search_parser = subparsers.add_parser("search", help="Search relevant chunks for a document chat query")
    search_parser.add_argument("query")
    search_parser.add_argument("filenames_json")
    search_parser.add_argument("top_k", type=int)
    search_parser.add_argument("user_id", nargs="?")

    args = parser.parse_args()

    if args.command == "search":
        return run_search_task(args.query, args.filenames_json, args.top_k, args.user_id)

    parser.error("Unknown command")
    return 2


if __name__ == "__main__":
    raise SystemExit(main())

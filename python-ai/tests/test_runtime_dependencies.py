from pathlib import Path


def test_runtime_import_dependencies_are_listed_in_requirements():
    requirements = (
        Path(__file__).resolve().parents[1] / "requirements.txt"
    ).read_text(encoding="utf-8")

    normalized_requirements = {
        line.strip().lower().split("==", 1)[0].split(">=", 1)[0]
        for line in requirements.splitlines()
        if line.strip() and not line.lstrip().startswith("#")
    }

    assert "langchain-community" in normalized_requirements
    assert "langchain-text-splitters" in normalized_requirements

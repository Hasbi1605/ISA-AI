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

    assert "langchain-core" in normalized_requirements
    assert "langchain-chroma" in normalized_requirements
    assert "pdfplumber" in normalized_requirements
    assert "python-docx" in normalized_requirements
    assert "openpyxl" in normalized_requirements
    assert "weasyprint" in normalized_requirements


def test_heavy_ml_dependencies_are_not_part_of_runtime_requirements():
    requirements = (
        Path(__file__).resolve().parents[1] / "requirements.txt"
    ).read_text(encoding="utf-8")

    normalized_requirements = {
        line.strip().lower().split("[", 1)[0].split("==", 1)[0].split(">=", 1)[0]
        for line in requirements.splitlines()
        if line.strip() and not line.lstrip().startswith("#")
    }

    heavy_packages = {
        "accelerate",
        "langchain-community",
        "opencv-python",
        "onnx",
        "onnxruntime",
        "spacy",
        "timm",
        "torch",
        "torchvision",
        "transformers",
        "unstructured",
        "unstructured-client",
        "unstructured_inference",
    }

    assert normalized_requirements.isdisjoint(heavy_packages)

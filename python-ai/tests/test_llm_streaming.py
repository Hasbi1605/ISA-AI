import os
import sys

import pytest

sys.path.insert(0, os.path.join(os.path.dirname(__file__), ".."))


@pytest.mark.parametrize(
    ("model", "expected_marker"),
    [
        (
            {
                "label": "GPT-4.1 (Primary)",
                "provider": "litellm",
                "model_name": "openai/gpt-4.1",
                "api_key_env": "GITHUB_TOKEN",
            },
            "[MODEL:GPT-4.1 (Primary)]\n",
        ),
        (
            {
                "provider": "litellm",
                "model_name": "openai/gpt-4.1",
                "api_key_env": "GITHUB_TOKEN",
            },
            "[MODEL:litellm:openai/gpt-4.1]\n",
        ),
    ],
)
def test_stream_with_cascade_prefers_configured_model_label(monkeypatch, model, expected_marker):
    from app.services import llm_streaming

    def fake_run_model(_model, _messages):
        if False:
            yield ""

    monkeypatch.setattr(llm_streaming, "_run_model", fake_run_model)

    output = list(
        llm_streaming.stream_with_cascade(
            messages=[{"role": "user", "content": "Halo"}],
            model_list=[model],
        )
    )

    assert output[0] == expected_marker

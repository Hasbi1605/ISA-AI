"""
Test untuk tuning RAG dokumen — Issue #192.

Verifikasi:
1. HyDE smart mode aktif untuk query konseptual, skip untuk query sederhana
2. HyDE max_attempts = 1 (failfast)
3. Config top_k, doc_candidates sesuai nilai tuning
4. Rollback config bisa dikembalikan ke nilai sebelumnya
"""
import os
import sys

sys.path.insert(0, os.path.join(os.path.dirname(__file__), ".."))

import pytest


# ---------------------------------------------------------------------------
# HyDE smart mode — _should_use_hyde()
# ---------------------------------------------------------------------------

class TestHydeSmartMode:
    """Verifikasi logika _should_use_hyde() untuk mode smart."""

    @pytest.mark.parametrize("query,expected_use", [
        # Query konseptual — HyDE harus aktif
        ("mengapa kebijakan ini diterapkan di lingkungan kantor?", True),
        ("bagaimana cara kerja sistem pengarsipan dokumen ini?", True),
        ("apa hubungan antara prosedur A dan prosedur B dalam dokumen?", True),
        ("jelaskan konsep manajemen risiko yang disebutkan dalam laporan", True),
        ("analisis dampak perubahan kebijakan terhadap pegawai", True),
        ("evaluasi efektivitas program yang dijelaskan dalam dokumen ini?", True),
        ("apa yang dimaksud dengan istilah teknis dalam bagian ketiga?", True),
        ("bagaimana pengaruh regulasi baru terhadap prosedur yang ada?", True),
        # Query sederhana — HyDE harus skip
        ("ringkaskan dokumen ini", False),
        ("buat ringkasan dari laporan", False),
        ("apa isi dokumen?", False),
        ("tampilkan poin utama", False),
        ("halo", False),
        ("hi apa kabar", False),
        ("baca isi dokumen ini", False),
        ("jelaskan isi dokumen", False),
    ])
    def test_should_use_hyde_smart_mode(self, query, expected_use):
        from app.services.rag_hybrid import _should_use_hyde
        result, reason = _should_use_hyde(query)
        assert result == expected_use, (
            f"Query '{query}': expected HyDE={expected_use}, got HyDE={result} (reason: {reason})"
        )

    def test_short_query_always_skips_hyde(self):
        """Query pendek (< 5 kata) selalu skip HyDE."""
        from app.services.rag_hybrid import _should_use_hyde
        result, reason = _should_use_hyde("apa itu")
        assert result is False
        assert "pendek" in reason

    def test_long_question_with_mark_uses_hyde(self):
        """Query panjang (>= 8 kata) dengan tanda tanya menggunakan HyDE."""
        from app.services.rag_hybrid import _should_use_hyde
        query = "apakah dokumen ini membahas prosedur pengadaan barang dan jasa?"
        result, reason = _should_use_hyde(query)
        assert result is True

    def test_hyde_skip_patterns_take_priority(self):
        """Pattern skip harus diprioritaskan di atas pattern use."""
        from app.services.rag_hybrid import _should_use_hyde
        # "rangkum" adalah skip pattern, meski query panjang
        result, reason = _should_use_hyde("rangkum dan analisis dokumen ini secara mendalam")
        assert result is False


# ---------------------------------------------------------------------------
# HyDE max_attempts = 1 (failfast tuning #192)
# ---------------------------------------------------------------------------

class TestHydeMaxAttempts:
    """Verifikasi HyDE hanya mencoba 1 model sebelum fallback."""

    def test_hyde_stops_after_one_failed_attempt(self, monkeypatch):
        """Jika model pertama gagal, HyDE langsung fallback ke query asli."""
        import app.services.rag_hybrid as rh

        attempt_count = {"count": 0}

        def fake_completion(**kwargs):
            attempt_count["count"] += 1
            raise RuntimeError("model timeout")

        monkeypatch.setattr("app.services.rag_hybrid.get_env", lambda k, d=None: "fake-key")

        import litellm
        monkeypatch.setattr(litellm, "completion", fake_completion)

        fake_models = [
            {"label": "Model A", "model_name": "groq/llama", "api_key_env": "KEY_A", "provider": "litellm"},
            {"label": "Model B", "model_name": "groq/llama2", "api_key_env": "KEY_B", "provider": "litellm"},
        ]

        import app.config_loader as cl
        monkeypatch.setattr(cl, "get_chat_models", lambda: fake_models)

        result = rh._generate_hyde_query("mengapa kebijakan ini diterapkan?")

        # Harus fallback ke query asli
        assert result == "mengapa kebijakan ini diterapkan?"
        # Hanya 1 attempt (max_attempts=1)
        assert attempt_count["count"] == 1, (
            f"HyDE harus berhenti setelah 1 attempt, tapi mencoba {attempt_count['count']} kali"
        )

    def test_hyde_returns_original_query_on_timeout(self, monkeypatch):
        """Jika HyDE timeout, kembalikan query asli tanpa error."""
        import app.services.rag_hybrid as rh

        monkeypatch.setattr("app.services.rag_hybrid.get_env", lambda k, d=None: "fake-key")

        import litellm
        monkeypatch.setattr(litellm, "completion", lambda **kwargs: (_ for _ in ()).throw(
            Exception("Request timed out")
        ))

        import app.config_loader as cl
        monkeypatch.setattr(cl, "get_chat_models", lambda: [
            {"label": "Model A", "model_name": "groq/llama", "api_key_env": "KEY_A", "provider": "litellm"},
        ])

        original = "bagaimana cara kerja sistem ini?"
        result = rh._generate_hyde_query(original)
        assert result == original


# ---------------------------------------------------------------------------
# Config values tuning #192
# ---------------------------------------------------------------------------

class TestConfigTuning:
    """Verifikasi nilai config setelah tuning #192."""

    def test_hyde_mode_is_smart(self):
        """HyDE mode harus 'smart' setelah tuning."""
        from app.config_loader import reload_config, get_hyde_config
        reload_config()
        hyde_cfg = get_hyde_config()
        assert hyde_cfg.get("mode") == "smart", (
            f"HyDE mode harus 'smart', tapi saat ini '{hyde_cfg.get('mode')}'"
        )

    def test_hyde_enabled_is_true(self):
        """HyDE harus tetap enabled."""
        from app.config_loader import reload_config, get_hyde_config
        reload_config()
        hyde_cfg = get_hyde_config()
        assert hyde_cfg.get("enabled") is True

    def test_top_k_is_five(self):
        """top_k harus 5 setelah tuning."""
        from app.config_loader import reload_config, get_rag_top_k
        reload_config()
        assert get_rag_top_k() == 5, f"top_k harus 5, tapi saat ini {get_rag_top_k()}"

    def test_top_n_is_five(self):
        """top_n harus 5 setelah tuning."""
        from app.config_loader import reload_config, get_rag_top_n
        reload_config()
        assert get_rag_top_n() == 5, f"top_n harus 5, tapi saat ini {get_rag_top_n()}"

    def test_doc_candidates_is_twenty(self):
        """doc_candidates harus 20 setelah tuning."""
        from app.config_loader import reload_config, get_rag_doc_candidates
        reload_config()
        assert get_rag_doc_candidates() == 20, (
            f"doc_candidates harus 20, tapi saat ini {get_rag_doc_candidates()}"
        )

    def test_doc_candidates_gte_top_n(self):
        """doc_candidates harus >= top_n (invariant wajib)."""
        from app.config_loader import reload_config, get_rag_doc_candidates, get_rag_top_n
        reload_config()
        assert get_rag_doc_candidates() >= get_rag_top_n(), (
            f"doc_candidates ({get_rag_doc_candidates()}) harus >= top_n ({get_rag_top_n()})"
        )

    def test_hyde_timeout_is_three(self):
        """HyDE timeout harus 3 detik."""
        from app.config_loader import reload_config, get_hyde_config
        reload_config()
        hyde_cfg = get_hyde_config()
        assert int(hyde_cfg.get("timeout", 0)) == 3

    def test_web_top_n_unchanged(self):
        """web_top_n tidak berubah (tetap 5)."""
        from app.config_loader import reload_config, get_rerank_config
        reload_config()
        rerank_cfg = get_rerank_config()
        assert int(rerank_cfg.get("web_top_n", 0)) == 5


# ---------------------------------------------------------------------------
# Rollback scenario
# ---------------------------------------------------------------------------

class TestRollbackScenario:
    """Verifikasi bahwa config bisa di-override untuk rollback."""

    def test_hyde_mode_can_be_overridden_to_always(self, monkeypatch):
        """Simulasi rollback: mode bisa diubah ke 'always' via config override."""
        from app.config_loader import reload_config, get_hyde_config
        import app.config_loader as cl

        # Simulasi config dengan mode always (rollback)
        original_load = cl.load_config

        def patched_load():
            cfg = original_load()
            cfg_copy = dict(cfg)
            cfg_copy.setdefault("retrieval", {})
            cfg_copy["retrieval"] = dict(cfg_copy.get("retrieval", {}))
            cfg_copy["retrieval"]["hyde"] = {"enabled": True, "mode": "always", "timeout": 3, "max_tokens": 100}
            return cfg_copy

        monkeypatch.setattr(cl, "load_config", patched_load)
        monkeypatch.setattr(cl, "_config_cache", None)

        # Dengan patch, mode harus always
        hyde_cfg = get_hyde_config()
        assert hyde_cfg.get("mode") == "always"

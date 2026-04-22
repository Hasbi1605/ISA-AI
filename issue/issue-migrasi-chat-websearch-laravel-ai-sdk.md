# Issue Plan: Migrasi Chat Umum dan Realtime Web Search ke Laravel AI SDK

## Latar Belakang
Issue ini adalah turunan capability pertama dari issue `#67` setelah foundation dan boundary tersedia. Chat umum tanpa dokumen adalah area paling rendah risiko untuk mulai memindahkan runtime dari Python ke Laravel AI SDK. Selain itu, policy realtime/web search saat tidak ada dokumen aktif juga bisa dipisahkan lebih awal dari RAG dokumen.

Saat ini perilaku ini tersebar di:

- `python-ai/app/main.py`
- `python-ai/app/llm_manager.py`
- `python-ai/app/services/rag_policy.py`
- `laravel/app/Services/AIService.php`
- `laravel/app/Services/ChatOrchestrationService.php`

## Tujuan
- Memindahkan chat umum non-dokumen ke Laravel AI SDK tanpa menurunkan kualitas.
- Memigrasikan realtime web search ke provider tool resmi atau mekanisme Laravel AI yang setara.
- Menjaga perilaku toggle web, source rendering, dan gaya respons tetap konsisten.

## Ruang Lingkup
- Implementasi runtime Laravel baru untuk chat tanpa dokumen.
- Implementasi web search untuk pertanyaan realtime/non-dokumen.
- Porting policy dasar:
  - explicit web request
  - realtime auto routing
  - no-web default untuk query non-realtime
- Menjaga source formatting di layer Laravel agar user-facing output tetap familiar.
- Menjaga model/failover strategy setara secara fungsional pada level Laravel runtime.

## Di Luar Scope
- Chat dengan dokumen aktif.
- Upload/ingest/retrieval dokumen.
- Summarization berbasis dokumen.
- Decommission Python runtime untuk capability lain.

## Area / File Terkait
- `laravel/app/Services/ChatOrchestrationService.php`
- `laravel/app/Livewire/Chat/ChatIndex.php`
- `laravel/app/Services/AIService.php` atau penggantinya di boundary baru
- `python-ai/app/main.py`
- `python-ai/app/llm_manager.py`
- `python-ai/app/services/rag_policy.py`
- `python-ai/tests/test_prompt_contracts.py`
- `python-ai/tests/test_prompt_eval_scenarios.py`

## Risiko
- Routing realtime/web berubah dan menghasilkan jawaban yang terasa berbeda.
- Source web dari SDK/provider memiliki shape berbeda dibanding runtime Python lama.
- Marker internal lama `[MODEL]` / `[SOURCES]` tidak lagi relevan dan bisa memicu drift di parsing Laravel.
- Failover provider tidak setara jika tidak didesain eksplisit.

## Langkah Implementasi
1. Petakan flow chat non-dokumen existing ke agent/service Laravel baru.
2. Pindahkan system prompt dan aturan gaya respons ke boundary Laravel tanpa mengubah persona.
3. Implementasikan realtime routing dan explicit web request di Laravel.
4. Implementasikan web search dengan provider tool yang kompatibel, termasuk batas domain/location bila diperlukan.
5. Standarkan source/result metadata ke format yang bisa dipakai `ChatOrchestrationService`.
6. Integrasikan capability ini melalui feature flag dan shadow mode.
7. Bandingkan hasil runtime lama vs baru terhadap acceptance matrix sebelum enable sebagian traffic.

## Rencana Test
- Port atau adaptasi test prompt/policy non-dokumen yang saat ini ada di Python.
- Tambahkan test Laravel untuk:
  - explicit web request
  - realtime auto web
  - query non-realtime tetap non-web
  - source rendering web
  - streaming/metadata integration
- Jalankan full test Laravel setelah capability ini aktif di jalur baru.

## Kriteria Selesai
- Chat umum tanpa dokumen bisa berjalan lewat Laravel AI SDK.
- Routing realtime/web parity terhadap kontrak kualitas sudah memadai.
- Source web tetap tampil konsisten di UI Laravel.
- Capability ini bisa dinyalakan bertahap via feature flag tanpa mengganggu chat berbasis dokumen.

# OPAS-0101 [AUTO CODING][STORY][AUDIO CAPTURE] Build browser and system audio capture service

File này dùng để giữ định hướng triển khai cho story:
`OPAS-0101 [AUTO CODING][STORY][AUDIO CAPTURE] Build browser and system audio capture service`

Story này thuộc epic:
`OPAS-0100 [AUTO CODING][EPIC][REALTIME TRANSLATION][MULTI-PLATFORM] Build realtime speech capture and translation overlay system`

Mục tiêu của file này:

- ghi rõ 5 phase/sub-issue chính của epic OPAS-0100
- chốt rõ goal, scope, acceptance criteria của từng sub-issue
- chốt rõ phạm vi OPAS-0101 audio capture trước khi code
- tránh nhầm LibreTranslate là speech-to-text engine
- tách audio capture khỏi STT, translation, overlay để dễ làm POC
- ghi lại các quyết định kỹ thuật cho Chrome, Cốc Cốc, Brave, Meet, YouTube, Zoom Web, Teams Web
- tạo contract đầu ra để các story sau nối vào STT và dịch được ngay

## Tầm nhìn tổng quát của OPAS-0100

Hệ thống realtime translation cần đi theo luồng:

```text
Browser/System Audio
  -> Capture Service
  -> Speech-to-Text Engine
  -> Translation Service
  -> Overlay UI
  -> Session History / Export / Automation
```

Trong đó:

- audio capture lấy tiếng đang phát ra từ browser hoặc hệ thống
- STT chuyển âm thanh thành transcript realtime
- LibreTranslate là translation engine mặc định cho bước dịch text
- overlay hiển thị transcript gốc, bản dịch, gợi ý trả lời, cách phát âm
- Laravel quản lý cấu hình, session, history, provider, settings
- Python service xử lý audio stream, STT, realtime processing
- n8n chỉ nên tham gia orchestration, notification, automation, monitoring
- desktop client hoặc browser extension chịu trách nhiệm tương tác với nguồn audio và overlay

## 5 Phase Theo Sub-Issue

Epic OPAS-0100 hiện chia thành 5 sub-issue chính.

```text
OPAS-0101 Audio Capture
  -> OPAS-0102 Speech-to-Text
  -> OPAS-0103 Translation
  -> OPAS-0104 AI Copilot
  -> OPAS-0105 Pronunciation Assist
```

Mỗi phase phải có output rõ ràng cho phase sau. Không nên trộn toàn bộ vào một story lớn vì realtime audio, STT, dịch, AI gợi ý trả lời, và phát âm là 5 lớp kỹ thuật khác nhau.

### Phase 1 - OPAS-0101 Audio Capture

Issue:
`#115`

Goal:

- build service lấy audio realtime từ browser tab và system audio
- cho phép người dùng xem YouTube, họp Google Meet, Zoom Web, Teams Web, hoặc dùng browser Chromium bất kỳ và vẫn capture được tiếng
- tạo audio stream ổn định cho downstream STT service

Business Goal:

- người dùng có thể tham gia meeting, xem video, nghe livestream, hoặc dùng app browser có phát tiếng
- hệ thống liên tục lấy audio để chuẩn bị chuyển thành text ở phase STT

Scope:

- browser audio capture từ Chrome, Cốc Cốc, Brave, Edge và các Chromium-based browser
- support Google Meet, Microsoft Teams, Zoom Web, YouTube, browser media playback
- system audio capture từ desktop audio, speaker output, loopback audio
- multiple audio device/source selection
- start/stop capture
- volume monitoring
- audio stream forwarding
- long-running session support
- low latency streaming

Acceptance Criteria:

- capture Chrome tab audio thành công
- capture meeting audio thành công
- capture YouTube audio thành công
- audio stream available cho STT service
- long-running session supported
- average latency dưới 500 ms

Output Contract:

- audio chunk stream
- source metadata
- sample rate/channel/format rõ ràng
- session id và timestamp để STT xử lý realtime

### Phase 2 - OPAS-0102 Speech-to-Text

Issue:
`#116`

Goal:

- chuyển audio stream từ OPAS-0101 thành transcript realtime
- hiển thị đúng nội dung speaker đang nói trong meeting, video, interview, livestream
- tạo text transcript ổn định cho translation service

Business Goal:

- người dùng nhìn được chính xác người khác đang nói gì
- đây là lớp bắt buộc trước khi dịch, AI copilot, và pronunciation assist hoạt động

Scope:

- realtime transcription
- streaming recognition
- automatic punctuation
- sentence segmentation
- auto language detection
- confidence score support
- support phase 1 languages: English, Japanese, Chinese, Vietnamese
- support long meetings và multi-hour sessions

Example:

```text
Detected Speech:
How are you today?

Transcript:
How are you today?
```

Acceptance Criteria:

- average latency dưới 2 giây
- continuous transcription
- supports long meetings
- supports multiple languages
- stable transcription during multi-hour sessions

Output Contract:

- transcript text
- detected language
- sentence boundary
- confidence score nếu engine hỗ trợ
- timestamp để translation/overlay sync được

### Phase 3 - OPAS-0103 Translation

Issue:
`#117`

Goal:

- dịch transcript realtime sang ngôn ngữ người dùng chọn
- dùng LibreTranslate làm translation engine mặc định trong phase 1
- giữ provider abstraction để sau này thay OpenAI, DeepL, Azure Translator

Business Goal:

- người dùng hiểu được cuộc trò chuyện bằng ngôn ngữ khác mà không cần thông thạo ngôn ngữ đó
- ví dụ nghe tiếng Anh/Nhật/Trung và xem bản dịch tiếng Việt gần realtime

Scope:

- translation toggle
- source language configurable
- auto language detection từ STT hoặc translation provider
- target language selection
- supported languages: English, Japanese, Chinese, Vietnamese
- provider phase 1: LibreTranslate
- provider phase 2: OpenAI, DeepL, Azure Translator

Example:

```text
Original:
How are you?

Translation:
Bạn khỏe không?
```

Acceptance Criteria:

- realtime translation
- multi-language support
- translation toggle available
- language auto detection supported
- translation latency dưới 2 giây

Output Contract:

- original transcript
- translated text
- source language
- target language
- provider name
- timestamp/session id

### Phase 4 - OPAS-0104 AI Copilot

Issue:
`#118`

Goal:

- build realtime conversation assistant hiểu ngữ cảnh cuộc trò chuyện
- tạo suggested reply để người dùng trả lời tự nhiên trong meeting
- hỗ trợ trả lời cùng ngôn ngữ với speaker và có bản nghĩa tiếng Việt

Business Goal:

- người dùng Việt có thể tham gia meeting tiếng Anh, Nhật, Trung dù chưa thông thạo
- hệ thống đóng vai trò realtime communication copilot, không chỉ là subtitle/dictionary

Scope:

- conversation understanding
- detect conversation context
- detect intent, question, request, greeting
- context-aware suggested responses
- language-aware responses
- meeting-aware responses
- professional tone và casual tone
- quick actions: copy, pin, regenerate, shorten, formalize

Example:

```text
Speaker:
How are you?

Translation:
Bạn khỏe không?

Suggested Reply:
I'm good, thank you.

Vietnamese Meaning:
Tôi ổn, cảm ơn bạn.
```

Technical Question Example:

```text
Speaker:
What is one plus one?

Translation:
Một cộng một bằng mấy?

Suggested Reply:
One plus one equals two.

Vietnamese Meaning:
Một cộng một bằng hai.
```

Meeting Opening Example:

```text
Good afternoon everyone.
Nice to meet you all.
Thank you for joining today's meeting.
```

Acceptance Criteria:

- suggested replies generated within 2 seconds
- context-aware suggestions
- multi-language support
- meeting workflow support
- supports professional communication scenarios

Output Contract:

- suggested reply in speaker language
- Vietnamese meaning
- tone metadata
- source transcript context
- action metadata for copy/pin/regenerate/shorten/formalize

### Phase 5 - OPAS-0105 Pronunciation Assist

Issue:
`#119`

Goal:

- tạo hướng dẫn phát âm cho suggested replies
- giúp người dùng đọc được câu trả lời tiếng Anh, Nhật, Trung trong meeting
- support nhiều mode phát âm, bao gồm Vietnamese-friendly pronunciation

Business Goal:

- người dùng không đọc tiếng Anh/Nhật/Trung tốt vẫn có thể nói lại câu được AI gợi ý
- giảm rào cản khi phải phản hồi trực tiếp bằng ngoại ngữ

Scope:

- automatic pronunciation generation
- native pronunciation mode
- romanized pronunciation mode
- Vietnamese-friendly pronunciation mode
- language-specific pronunciation support
- toggle enable/disable
- realtime overlay display support
- supported languages: English, Japanese, Chinese

Examples:

```text
Original Reply:
I'm good, thank you.

Vietnamese Pronunciation:
Am gút, then kiu.

Vietnamese Meaning:
Tôi ổn, cảm ơn bạn.
```

```text
Original Reply:
Thank you for your explanation.

Vietnamese Pronunciation:
Then kiu pho diu ích-xờ-pờ-lây-sần.

Vietnamese Meaning:
Cảm ơn bạn đã giải thích.
```

```text
Original Reply:
ありがとうございます。

Vietnamese Pronunciation:
A ri ga tô gô zai ma sư.

Vietnamese Meaning:
Xin cảm ơn.
```

```text
Original Reply:
你好

Pinyin:
Nǐ hǎo

Vietnamese Pronunciation:
Nỉ hảo

Vietnamese Meaning:
Xin chào.
```

Overlay Display Example:

```text
Speaker:
How are you?

Translation:
Bạn khỏe không?

Suggested Reply:
I'm good, thank you.

Pronunciation:
Am gút, then kiu.

Vietnamese Meaning:
Tôi ổn, cảm ơn bạn.
```

Acceptance Criteria:

- automatic pronunciation generation
- language-specific pronunciation support
- toggle enable/disable
- realtime display support

Output Contract:

- original reply
- romanized pronunciation nếu có
- Vietnamese-friendly pronunciation
- Vietnamese meaning
- pronunciation mode
- language code

## Phạm vi riêng của OPAS-0101

OPAS-0101 chỉ tập trung vào audio capture.

Trạng thái triển khai hiện tại:

- đã tạo Python module `services/python/tool_realtime_audio_capture`
- đã expose API prefix `/realtime-audio`
- đã có source discovery skeleton qua `GET /realtime-audio/sources`
- đã có browser tab discovery skeleton qua `GET /realtime-audio/browser-tabs`
- đã có start/stop session lifecycle skeleton qua `POST /realtime-audio/sessions` và `POST /realtime-audio/sessions/{session_id}/stop`
- đã có transcript polling contract qua `GET /realtime-audio/sessions/{session_id}/transcript`
- đã có transcript realtime qua `WS /realtime-audio/sessions/{session_id}/transcript/ws`
- đã verify standalone FastAPI server chạy được tại `http://127.0.0.1:5010/realtime-audio`
- popup extension hiện theo flow chính: chọn tab, mặc định tab đang active, bấm `Connect`, xem realtime transcript dạng waterfall
- đã thêm Chromium extension tại `apps/realtime-audio-extension` để capture audio thật từ current tab bằng `chrome.tabCapture`
- backend đã có endpoint nhận tab audio chunk qua `POST /realtime-audio/sessions/{session_id}/audio-chunk`
- popup hiển thị audio chunks/bytes/STT status để kiểm tra tab audio thật đang được gửi về backend
- do giới hạn bảo mật Chrome, extension phải là bên khởi động tab audio capture
- nếu Chrome báo thiếu quyền `activeTab`, extension focus tab đã chọn; người dùng click icon extension lại trên tab đó và bấm `Connect`
- UI không còn tạo session `capturing` cho macOS discovery fallback vì nguồn này chỉ đọc được title/url tab, không lấy được audio; real capture phải đi qua source `extension-tab:*`
- dấu hiệu real capture đúng: popup hiển thị `Chunks` và `Audio` tăng
- extension đã chuyển từ MediaRecorder WebM/Opus sang Web Audio PCM 16 kHz mono để STT realtime đọc ổn định hơn
- session card/API đã có audio RMS, STT status, STT attempt count, và last STT error để debug nhanh khi capture có bytes nhưng chưa có transcript
- đã thêm `GET /realtime-audio/sessions/{session_id}/debug` cho troubleshooting capture/STT
- đã thêm `GET /realtime-audio/sessions/{session_id}/audio-buffer` và link `Download audio buffer` để nghe chính xác audio backend đang đưa vào STT
- STT có ngưỡng RMS tối thiểu `OPAS_REALTIME_STT_MIN_RMS` để tránh gọi Whisper khi tab gần như im lặng
- đã thêm `POST /realtime-audio/stt/warmup` để preload model trước khi họp/video bắt đầu
- đã thêm `POST /realtime-audio/sessions/{session_id}/transcribe-now` để ép xử lý audio buffer hiện tại khi cần debug
- extension dùng background/offscreen document để giữ Web Audio capture chạy ổn định sau khi popup đóng
- đã thêm optional local STT adapter qua `faster-whisper`; `scripts/start-realtime-audio-capture.sh` cài STT mặc định, dùng `--capture-only` nếu chỉ test capture
- health/UI hiển thị trạng thái STT installed/not installed để tránh nhầm capture chạy nhưng transcript chưa có engine
- STT adapter hiện là bridge/POC để Live Text hiện transcript từ audio chunks, còn story OPAS-0102 vẫn cần tách riêng để tối ưu streaming, language detection, latency, diarization, và provider abstraction
- audio/STT runtime đã chuyển sang rolling buffer để không giữ file WebM phình vô hạn; UI hiển thị `Buffered bytes` thay vì tổng bytes
- transcript endpoint giữ tối đa 10 dòng gần nhất để Live Text chạy lâu không nặng
- đã verify browser tab discovery đọc được tab Chrome/Brave trên macOS khi server có quyền Automation
- browser tab audio capture adapter đã có POC qua extension; system audio adapter vẫn là step tiếp theo
- popup hiện dùng audio trực tiếp từ selected tab qua extension/offscreen document, không dùng Web Speech API microphone test mode
- transcript tab audio thật chỉ hoạt động khi optional STT dependency/model đã cài; nếu chưa cài thì UI chỉ báo audio captured waiting for STT
- chưa phân biệt được 2 người nói; speaker diarization cần story/adapter riêng sau STT, ví dụ diarization model hoặc meeting platform speaker metadata

Story này cần tạo được một service hoặc POC đủ để:

- chọn nguồn audio
- start capture
- stop capture
- theo dõi volume
- stream audio ra cho STT service
- chạy ổn định trong phiên dài
- có log/debug đủ để biết nguồn nào đang thu, có tiếng hay không, latency ra sao

Story này chưa cần:

- chạy Whisper thật hoàn chỉnh
- dịch bằng LibreTranslate
- render overlay production
- phân biệt speaker
- tạo meeting summary
- lưu full transcript
- phát âm text-to-speech

Những phần đó thuộc các story sau của OPAS-0100.

## User Workflow Mục Tiêu

Người dùng mở một cuộc họp hoặc video, ví dụ:

- Google Meet trên Chrome
- YouTube trên Brave
- Zoom Web trên Cốc Cốc
- Microsoft Teams Web trên Edge hoặc Chrome

Sau đó người dùng mở OPAS realtime translation tool và:

- chọn nguồn audio là browser tab hoặc system output
- bấm Start
- thấy volume meter nhảy khi người trong meeting/video nói
- service forward audio stream cho STT layer
- nếu tắt meeting, đổi tab, hoặc mất audio tạm thời thì service không crash
- bấm Stop thì capture dừng sạch, session đóng có log rõ ràng

## Capture Strategy

Audio capture có hai hướng chính.

### Browser Tab Audio

Mục tiêu:

- lấy audio từ một tab browser cụ thể
- ưu tiên các trình duyệt Chromium như Chrome, Cốc Cốc, Brave, Edge
- phù hợp với YouTube, Meet, Zoom Web, Teams Web

Hướng triển khai khả thi:

- browser extension dùng Chrome Extension API để capture tab audio
- Electron desktop app dùng `desktopCapturer` hoặc quyền screen/audio capture của Chromium
- browser automation chỉ dùng để hỗ trợ chọn tab/debug, không nên là capture engine chính

Ghi chú:

- tab audio capture thường cần user gesture hoặc permission rõ ràng
- Google Meet và các app họp có thể thay đổi behavior theo permission, device, browser policy
- Cốc Cốc và Brave là Chromium-based nhưng vẫn phải verify permission thực tế

### System Audio Capture

Mục tiêu:

- lấy speaker output hoặc desktop audio chung
- dùng được khi không capture được tab riêng
- phù hợp với mọi app phát tiếng, bao gồm browser, native Zoom, media player

Hướng triển khai theo OS:

- Windows: WASAPI loopback
- macOS: ScreenCaptureKit nếu phù hợp, hoặc virtual audio device như BlackHole cho POC
- Linux: PulseAudio/PipeWire monitor source

Ghi chú:

- system audio trên macOS thường khó hơn Windows
- nếu POC chạy trên macOS local, nên ghi rõ dependency như BlackHole nếu cần
- không nên hard-code một OS duy nhất vào domain contract

## Kiến Trúc Đề Xuất Cho OPAS-0101

```text
Capture Client
  -> Audio Source Selector
  -> Capture Adapter
  -> Audio Normalizer
  -> Volume Meter
  -> Stream Publisher
  -> STT Input Contract
```

### Capture Client

Có thể là một trong các lựa chọn:

- Electron POC
- Tauri POC
- Chrome extension POC
- Python desktop helper cho system audio

Khuyến nghị cho POC đầu tiên:

- ưu tiên đường system audio nếu cần chứng minh nhanh end-to-end với YouTube/meeting
- ưu tiên browser tab capture nếu muốn kiểm soát chính xác từng tab ngay từ đầu
- không trộn cả hai vào một commit lớn nếu chưa có contract rõ

### Audio Source Selector

Trách nhiệm:

- list nguồn audio khả dụng
- phân loại nguồn: browser tab, desktop audio, microphone, virtual device
- lưu metadata tối thiểu: source id, label, kind, browser/process nếu có, OS
- cho phép switch source trước khi start session

### Capture Adapter

Trách nhiệm:

- start/stop capture theo source đã chọn
- đọc audio chunk realtime
- handle permission error
- handle source disconnected
- expose status: idle, starting, capturing, interrupted, stopping, stopped, failed

### Audio Normalizer

Đầu ra nên chuẩn hóa trước khi đưa vào STT:

- sample rate: 16 kHz hoặc 48 kHz tùy STT target, nhưng contract phải ghi rõ
- channel: mono cho STT nếu không cần stereo
- format: PCM 16-bit little-endian hoặc float32, chọn một format POC
- chunk duration: 100 ms đến 500 ms
- timestamp: monotonic timestamp cho mỗi chunk

### Volume Meter

Trách nhiệm:

- tính RMS/peak đơn giản trên mỗi chunk
- emit volume event cho UI/debug
- giúp acceptance test xác minh đang có tiếng

### Stream Publisher

Trách nhiệm:

- forward audio chunk cho STT service qua WebSocket, local TCP, gRPC, hoặc HTTP streaming
- POC nên ưu tiên WebSocket vì dễ debug và phù hợp realtime
- khi STT chưa có, có thể ghi chunk vào file WAV rolling buffer để verify

## Contract Đầu Ra Cho STT

Audio capture service cần xuất stream event có cấu trúc ổn định.

Ví dụ event metadata:

```json
{
  "session_id": "rt-20260604-001",
  "source": {
    "type": "browser_tab",
    "label": "YouTube - Chrome",
    "browser": "chrome"
  },
  "audio": {
    "format": "pcm_s16le",
    "sample_rate": 16000,
    "channels": 1,
    "chunk_ms": 250
  },
  "timestamp_ms": 123456,
  "sequence": 42
}
```

Binary audio payload nên đi riêng với metadata nếu dùng WebSocket để tránh payload JSON quá lớn.

## POC Breakdown Riêng Cho OPAS-0101

Phần này chỉ là breakdown kỹ thuật nội bộ của OPAS-0101 audio capture. Không nhầm với 5 phase/sub-issue của epic OPAS-0100 ở đầu file.

### Step 1 - Source Discovery Và Capture Skeleton

Mục tiêu:

- tạo service/app POC có start/stop rõ ràng
- list được audio source khả dụng
- chọn được một source
- log được lifecycle của capture session
- chưa cần stream audio thật nếu discovery còn blocker

Khi xong step này phải có:

- command hoặc UI nhỏ để xem source list
- trạng thái session rõ ràng
- error message rõ nếu thiếu permission hoặc thiếu device

### Step 2 - Capture System Audio

Mục tiêu:

- capture được desktop/speaker output
- verify được với YouTube hoặc meeting audio
- có volume meter realtime
- ghi được short WAV sample để kiểm tra

Khi xong step này phải có:

- capture YouTube audio thành công
- capture meeting audio thành công nếu meeting phát qua speaker output
- latency capture trung bình dưới 500 ms ở mức audio chunk
- chạy tối thiểu 30 phút không crash trong POC

### Step 3 - Capture Browser Tab Audio

Mục tiêu:

- capture được tab audio từ Chromium-based browser
- ưu tiên Chrome trước, sau đó verify Brave/Cốc Cốc/Edge
- tránh thu lẫn tiếng từ app khác khi đã chọn tab cụ thể

Khi xong step này phải có:

- capture Chrome tab audio thành công
- capture YouTube tab thành công
- capture Google Meet hoặc Zoom Web tab thành công
- có fallback rõ nếu browser không cho permission

### Step 4 - Stream Forwarding Contract

Mục tiêu:

- stream audio chunk ra endpoint local cho STT service
- metadata đầy đủ session/source/audio format
- reconnect hoặc stop sạch khi consumer mất kết nối

Khi xong step này phải có:

- WebSocket hoặc equivalent stream endpoint
- STT mock consumer nhận được audio chunk
- metrics cơ bản: chunks sent, dropped chunks, average chunk latency, current volume

### Step 5 - Long-Running Hardening

Mục tiêu:

- hỗ trợ session dài như meeting/video/livestream
- tự recover khi audio source bị gián đoạn ngắn
- giữ memory/CPU ổn định

Khi xong step này phải có:

- chạy test tối thiểu 2 giờ với audio liên tục hoặc mô phỏng audio
- log không tăng vô hạn
- không giữ file temp không kiểm soát
- stop session giải phóng resource

## Acceptance Criteria Cụ Thể

Story được xem là đạt POC khi:

- capture Chrome tab audio thành công
- capture YouTube audio thành công
- capture meeting audio thành công qua Google Meet hoặc Zoom Web
- audio stream available cho STT mock service
- start/stop capture nhiều lần không crash
- có volume monitoring realtime
- average audio chunk latency dưới 500 ms
- session dài tối thiểu 30 phút chạy ổn trong POC
- log đủ để debug source, permission, device disconnect, stream disconnect

Story được xem là đủ nền để nối sang STT khi:

- audio output format đã được ghi rõ
- STT consumer không cần biết capture source là tab hay system
- source metadata đủ để hiển thị/debug trong UI
- capture service không phụ thuộc trực tiếp vào LibreTranslate

## Non-Goals Và Ranh Giới Kỹ Thuật

Không dùng LibreTranslate để nhận diện giọng nói.

LibreTranslate chỉ nhận text và trả text dịch. Với realtime speech translation, pipeline đúng là:

```text
Audio -> STT -> Text -> LibreTranslate -> Translated Text
```

Không build overlay trong OPAS-0101.

Overlay cần story riêng vì nó liên quan:

- always-on-top window
- drag/resize/opacity/font settings
- transcript layout
- reply suggestion controls
- text-to-speech hoặc pronunciation helper

Không build reply suggestion trong OPAS-0101.

Reply suggestion cần AI/text layer riêng:

- transcript context
- user language preference
- suggested response in Vietnamese
- translation to English
- pronunciation hint like "hai, hao a du"

## Technical Risks

### Browser Permission Risk

Browser tab capture có thể bị giới hạn bởi permission hoặc chính sách của từng browser.

Mitigation:

- bắt đầu với Chrome stable
- ghi rõ browser/version trong test log
- có fallback system audio

### macOS System Audio Risk

macOS không luôn có loopback audio đơn giản như Windows.

Mitigation:

- dùng BlackHole cho POC nếu cần
- nghiên cứu ScreenCaptureKit cho hướng native hơn
- giữ interface capture adapter không phụ thuộc BlackHole

### Latency Risk

STT realtime cần chunk nhỏ, nhưng chunk quá nhỏ làm tăng overhead.

Mitigation:

- bắt đầu với chunk 250 ms
- đo latency capture riêng, STT riêng, translation riêng
- không gộp performance của các layer vào OPAS-0101

### Long-Running Resource Risk

Meeting dài dễ lộ memory leak, file temp leak, connection leak.

Mitigation:

- rolling metrics
- bounded logs
- explicit cleanup khi stop
- long-running smoke test

## Gợi Ý Cấu Trúc Repo Khi Bắt Đầu Code

Chưa bắt buộc tạo ngay, nhưng hướng đặt module nên rõ:

```text
services/
  realtime-translation/
    capture/
      README.md
      pyproject.toml hoặc package.json
      src/
        adapters/
        streams/
        metrics/
        config/
```

Nếu chọn Electron/Tauri cho capture/overlay POC:

```text
apps/
  realtime-overlay/
```

Nếu chọn Python-only POC cho system audio:

```text
services/
  python/
    tool_realtime_audio_capture/
```

Khuyến nghị:

- nếu chỉ làm system audio POC nhanh, đặt trong `services/python/tool_realtime_audio_capture`
- nếu làm browser tab capture và overlay cùng hướng desktop, tạo app riêng dưới `apps/realtime-overlay`
- không nhét capture logic vào Laravel
- Laravel chỉ giữ config/session/history/API khi đến story backend

## Verification Checklist

Trước khi đóng OPAS-0101, cần có checklist:

- source discovery chạy được trên máy mục tiêu
- start capture thành công
- stop capture giải phóng resource
- volume meter phản ánh audio thật
- capture YouTube audio
- capture một meeting web
- stream audio tới mock STT consumer
- log permission error dễ hiểu
- latency đo được và dưới 500 ms trung bình
- long-running smoke test đạt tối thiểu 30 phút
- docs ghi rõ OS/browser đã test

## Notes Cho Các Story Sau

Sau OPAS-0101, các phase tiếp theo của OPAS-0100 là:

- OPAS-0102: realtime STT service với Whisper/faster-whisper/whisper.cpp
- OPAS-0103: realtime translation service, LibreTranslate phase 1, provider abstraction cho OpenAI/DeepL/Azure
- OPAS-0104: AI copilot, conversation understanding, suggested replies, quick actions
- OPAS-0105: pronunciation assist, romanized pronunciation, Vietnamese-friendly pronunciation

Các phần overlay UI, session history, export transcript, Laravel settings, n8n automation, notification, monitoring có thể tách thành story riêng sau khi 5 phase lõi chạy được.

Thứ tự này giúp hệ thống đi từ audio thật đến transcript, rồi mới dịch, gợi ý trả lời, và hỗ trợ phát âm.

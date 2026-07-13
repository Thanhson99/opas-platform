# Douyin Video Subtitle And Translation Backlog

Ghi chu nay luu y tuong tuong lai cho pipeline:

```text
Douyin download
  -> extract/normalize audio
  -> speech-to-text with timestamps
  -> subtitle segmentation
  -> translate subtitle to Vietnamese / English
  -> optional subtitle file or burned-in subtitle
  -> optional YouTube upload metadata
```

## Context

Ngay 2026-07-10, da kiem tra nhanh repo `Huanshere/VideoLingo`.

Ket luan chinh:

- VideoLingo khong phai cong cu bat tieng realtime tu tab web nhu OPAS realtime audio extension.
- VideoLingo manh o huong xu ly video/audio da co san de tao subtitle, dich subtitle, va dubbing.
- Phan dang can ghi nho cho OPAS la cach VideoLingo dung WhisperX cho ASR/subtitle-grade transcript.
- Subtitle chua can lam ngay, nhung neu sau nay download video tu Douyin ve va muon co sub tieng Viet/tieng Anh thi day la huong "ngon an" de nghien cuu.

## Candidate Approach

Khi can lam tinh nang subtitle cho video Douyin da tai ve, uu tien thiet ke mot pipeline batch/offline rieng, khong tron voi realtime audio capture:

1. Lay video tu `services/douyin-worker/storage/videos`.
2. Tim metadata tu `services/douyin-worker/storage/metadata`.
3. Dung FFmpeg de extract audio ve mono 16 kHz.
4. Dung WhisperX de transcribe va align timestamp theo word/segment.
5. Cat subtitle thanh cau ngan, de doc, phu hop Shorts/Reels/TikTok-style video.
6. Dich subtitle sang `vi` hoac `en`.
7. Xuat `.srt`/`.vtt` truoc; burn-in subtitle vao video chi lam sau khi can.
8. Noi voi `services/youtube-uploader` neu can upload video da co subtitle/metadata.

## Why WhisperX / VideoLingo Is Relevant

VideoLingo dung cac y tuong phu hop voi bai toan video da tai ve:

- WhisperX cho transcript co timestamp tot hon cho subtitle.
- FFmpeg de extract/normalize audio.
- Optional Demucs de tach vocal khi video co nhac nen/noise.
- Subtitle segmentation rieng sau ASR, khong dung raw transcript truc tiep.
- Dich va adapt subtitle sau khi da co timeline.

Day co kha nang cho ket qua subtitle tot hon cach realtime STT hien tai vi realtime STT cua OPAS dang toi uu latency, khong toi uu word-level alignment.

## Boundary With Realtime Audio Capture

Khong thay the realtime audio capture bang VideoLingo.

Hai huong nay khac nhau:

- OPAS realtime audio capture: bat tieng tu browser tab/meeting/livestream dang phat, uu tien latency thap.
- Douyin subtitle translation: xu ly video da download, uu tien subtitle chinh xac va dep.

Neu can meeting/livestream realtime thi tiep tuc dung extension `chrome.tabCapture` va `faster-whisper`/streaming STT.

Neu can video Douyin co sub dep de dang lai, dung huong WhisperX/VideoLingo-inspired batch pipeline.

## Future Feature Slice

Ten feature goi y:

```text
Douyin Subtitle Translation Pipeline
```

Scope MVP:

- process mot file `.mp4` da download
- output `.srt` song ngu hoac mot ngon ngu
- support target `vi` va `en`
- luu artifact canh video goc
- khong burn subtitle trong MVP
- khong dubbing trong MVP

Scope later:

- batch process selected Douyin videos tu Laravel UI
- burn-in subtitle voi style rieng cho Shorts
- auto-generate YouTube title/description tu transcript
- choose provider: LibreTranslate, OpenAI, DeepL, Azure
- optional vocal separation bang Demucs cho video nhieu nhac nen

## Implementation Notes

Khong nen dua logic nay vao Laravel controller.

Vi tri phu hop hon:

```text
services/python/tool_video_subtitle_translation
```

Hoac neu muon gan rieng voi Douyin:

```text
services/python/tool_douyin_subtitle_translation
```

Laravel chi nen:

- tao job
- chon video
- hien status
- hien/download artifacts

Worker/Python service nen:

- doc video/audio
- chay ASR
- dich subtitle
- ghi `.srt`, `.vtt`, va JSON artifact

## Do Not Forget

Khi operator noi ve viec "video Douyin download ve co sub tieng Viet/Anh", hay nho lai note nay va xem VideoLingo/WhisperX nhu reference chinh cho subtitle batch pipeline.

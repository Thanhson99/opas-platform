# Runbook AI Local

## Thanh phan

- Ollama container: `ollama`
- Writer model: `qwen2.5:7b`
- Reviewer model: `mistral:7b`
- Translation service: `libretranslate`

## Lenh can nho

Khoi dong stack:

```bash
docker compose up -d
```

Nap model Ollama:

```bash
bash scripts/ollama-pull-models.sh
```

Kiem tra model:

```bash
docker exec -it ollama ollama list
```

## Quy uoc su dung model

### `qwen2.5:7b`

Dung cho:

- viet nhap
- tong hop
- phac thao cau truc
- sinh JSON ke hoach

### `mistral:7b`

Dung cho:

- review
- phat hien loi logic
- rut gon
- chuan hoa output

## Quy uoc prompt

- Moi prompt goc dat trong `ai-local/agents/`.
- Prompt phai ghi ro:
  - vai tro
  - input
  - rang buoc
  - dinh dang output
- Uu tien output JSON khi workflow can xu ly tiep.

## Cach dung trong n8n

1. Tao node HTTP request toi `http://ollama:11434/api/chat`
2. Chon model theo file prompt
3. Nap noi dung system/user tu file `.md` trong repo
4. Bat buoc validate output truoc khi ghi DB hoac publish

## Cach dung trong Laravel/Python

- Laravel nen goi n8n cho workflow dai.
- Python service nen goi Ollama khi can validate, retry, chunking.
- Khong nen de prompt nghiep vu phan tan trong source ma khong co file tai lieu goc.

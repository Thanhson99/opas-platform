# To Chuc Folder

## Nguyen tac

- Khong doi vi tri folder runtime dang duoc `docker-compose.yml` mount.
- Them folder tai lieu va prompt de quan ly luong van hanh ro rang.
- Moi service tu quan ly code cua no; tai lieu chung dat o root.

## Cau truc nen giu

```text
apps/laravel/        Web app va API gateway
services/python/     FastAPI services
services/n8n/        Workflow, node, credential data
services/libretranslate/
  Dockerfile + models ArgosTranslate
docker/              Persistent runtime data
nginx/               Reverse proxy
scripts/             Utility scripts
docs/                Kien truc, runbook, tich hop
ai-local/            Prompt va quy uoc dieu khien LLM local
```

## Y nghia folder moi

### `docs/`

Tai lieu cho nguoi van hanh va nguoi phat trien:

- kien truc
- luong tich hop
- quy uoc folder
- runbook AI local

### `ai-local/`

Bo prompt va quy tac de goi AI local nhat quan:

- prompt cho model viet nhap
- prompt cho model review
- prompt cho orchestration
- prompt cho post-edit translation

## Thu tu uu tien khi them file moi

1. File chay runtime dat trong service tuong ung.
2. File prompt hoac instruction dat trong `ai-local/`.
3. File mo ta luong hoac quy uoc dat trong `docs/`.
4. Script phu tro dat trong `scripts/`.

## Khong nen lam

- Khong dat prompt AI lung tung trong `n8n/workflows/` hoac `laravel/resources/`.
- Khong dat tai lieu van hanh trong README cua tung framework mac dinh.
- Khong doi them folder runtime nua neu chua sua toan bo volume mount va script lien quan.

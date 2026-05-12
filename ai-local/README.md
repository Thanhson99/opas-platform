# AI Local Control Pack

Folder nay chua bo file `.md` de dieu khien AI local nhat quan giua Laravel, n8n va Python services.

## Cac file chinh

- `agents/qwen-writer.md`: prompt cho model tao ban nhap
- `agents/qwen-critic.md`: prompt cho model review, sua va bat loi
- `agents/laravel-n8n-orchestrator.md`: prompt cho tac vu dieu phoi luong
- `agents/libretranslate-postedit.md`: prompt hau xu ly sau dich

## Cach dung

### Neu goi tu n8n

- Su dung noi dung trong file lam `system` hoac `instructions`
- Truyen input that ngan gon, co schema ro rang
- Yeu cau output theo format da ghi trong file

### Neu goi tu Python service

- Tach `system_prompt` tu file `.md`
- Truyen du lieu da preprocess
- Validate JSON truoc khi tra ve caller

### Neu goi tu Laravel

- Uu tien khong goi model truc tiep neu la workflow dai
- Laravel nen trigger n8n hoac Python service thay vi om prompt trong controller

## Nguyen tac

- Mot file prompt = mot vai tro ro rang
- Khong tron writer va reviewer trong cung mot prompt
- Khong de prompt dai nhung mo ho
- Uu tien output co cau truc, de debug va de retry

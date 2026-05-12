# Luong Tich Hop

## 1. Laravel -> n8n

Dung khi:

- can trigger workflow dai
- can retry
- can log tung buoc
- can ket hop AI, translation, upload, scheduling

De nghi:

- Laravel goi vao webhook cua n8n
- Laravel luu `job_id`, `workflow_name`, `payload_hash`, `status`
- n8n tra ve ket qua ngan hoac callback lai Laravel

## 2. Laravel -> Python Services

Dung khi:

- can response nhanh
- can mot utility service don le
- can parsing/scraping/preprocess

Hien tai:

- `apps/laravel/app/Services/Python/PythonService.php` dang la client co ban
- nen mo rong thanh service theo tung domain thay vi mot class chung cho moi use-case

## 3. n8n -> Ollama

Dung khi:

- viet nhap bai
- rewrite
- review output
- generate structured JSON

Quy uoc de nghi:

- `qwen2.5:7b`: writer / planner
- `mistral:7b`: critic / reviewer
- moi workflow luu prompt goc trong `ai-local/agents/*.md`
- n8n chi tham chieu prompt, khong nen viet prompt dai truc tiep trong node neu co the tranh

## 4. n8n -> LibreTranslate

Dung khi:

- dich nhap
- tao phien ban ngon ngu thu hai
- tien xu ly hoac hau xu ly noi dung AI

De nghi:

- Dich thuan bang LibreTranslate
- Neu can do truot, chay them mot buoc AI post-edit bang Ollama

## 5. Python Services -> Ollama

Dung khi:

- can xu ly AI co logic lap trinh phuc tap
- can validate output JSON
- can chunking, retry, fallback

Neu lam theo huong nay, Python service nen dong vai tro adapter:

- nhan payload tu Laravel hoac n8n
- goi Ollama
- validate schema
- tra ket qua sach

## Luong goi y cho bai toan cua ban

1. User nhap yeu cau tai Laravel
2. Laravel trigger n8n workflow
3. n8n goi Python service de lay/lam sach du lieu
4. n8n goi Qwen de tao draft
5. n8n goi LibreTranslate neu can da ngon ngu
6. n8n goi Mistral de review hoac post-edit
7. n8n tra ket qua ve Laravel
8. Laravel hien thi, luu log va lich su

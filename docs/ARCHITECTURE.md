# Kien Truc Tong The

## Muc tieu

Repo nay duoc to chuc theo mo hinh:

- Laravel = giao dien va diem vao nguoi dung
- n8n = workflow engine
- Python services = utility layer va data processing
- Ollama = local LLM runtime
- LibreTranslate = local translation layer
- PostgreSQL = du lieu dung chung

## So do logic

```text
User
  |
  v
Laravel UI/API
  | \
  |  \--> Python Services
  |
  \----> n8n Workflows
             | \
             |  \--> LibreTranslate
             |
             \----> Ollama
```

## Nguyen tac ket noi

- Laravel khong nen goi truc tiep qua nhieu node AI.
- n8n nen giu vai tro orchestration cho cac pipeline dai.
- Python services nen giu cac tac vu:
  - parsing
  - scraping
  - preprocessing
  - validation
  - adapter cho external APIs
- Ollama nen duoc goi qua prompt co cau truc va output JSON khi co the.
- LibreTranslate nen dung cho translation layer, khong gan logic nghiep vu vao service nay.

## Trang thai source hien tai

- Laravel da co mot service client cho Python: `App\Services\Python\PythonService`
- Route Laravel hien chu yeu phuc vu:
  - coin
  - stock
  - video automation
- Python service hien dang expose rat it route tong hop; can bo sung them route nghiep vu neu muon Laravel goi on dinh.
- n8n da co nhieu workflow JSON nhung chua co tai lieu mapping use-case ro rang.

## Dinh huong tiep theo

Nen chia use-case thanh 3 lop:

1. `interactive`
   Laravel goi truc tiep Python service hoac n8n webhook va cho ket qua ngay.
2. `workflow`
   n8n xu ly pipeline dai, co trang thai job.
3. `ai-assisted`
   n8n/Python goi Ollama va LibreTranslate, tra ve ket qua da chuan hoa.

# Laravel App

Day khong con la README mac dinh cua framework. Folder `apps/laravel/` trong repo nay dong vai tro:

- web UI cho nguoi van hanh
- diem trigger workflow
- noi hien thi ket qua tra ve tu n8n va Python services
- lop API/noi bo de quan ly job, log va dashboard sau nay

## Trang thai hien tai

- Da co route cho:
  - coin
  - stock
  - video automation
- Da co `App\Services\Python\PythonService` de goi Python service
- Chua co lop orchestration ro rang cho n8n webhook

## Dinh huong nen lam tiep

1. Tao service rieng cho n8n webhook client.
2. Tach cac use-case AI/translation thanh module thay vi de trong controller.
3. Luu lich su trigger workflow de truy vet.
4. Hien thi job status tren UI Laravel.

## Lien ket tai lieu

- `../docs/ARCHITECTURE.md`
- `../docs/INTEGRATION-FLOW.md`
- `../ai-local/README.md`

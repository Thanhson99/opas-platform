# Laravel App

Folder `apps/laravel/` trong repo nay dong vai tro:

- backend Laravel theo huong RESTful API
- frontend React SPA build bang Vite
- lop ket noi voi n8n, Python services va workflow noi bo
- noi dat validation, service layer, test va rule CI/CD cho app chinh

## Lenh can nho

- local dev: `composer dev`
- local bootstrap nhanh: `../../scripts/start-local.sh` hoac `..\..\scripts\start-local.bat`
- PHP quality gate: `composer check`
- React quality gate: `npm run check`
- full frontend CI gate: `npm run ci`
- full app CI gate: `composer ci`

## Kien truc hien tai

- `routes/api.php`: API cho coins, stocks, alerts, keywords, videos
- `routes/web.php`: SPA entry route
- `resources/js/spa/`: React app chia theo `components`, `pages`, `lib`, `config`
- `resources/js/spa/components/icons/`: icon SVG local, khong phu thuoc CDN hay font icon
- `resources/views/spa.blade.php`: Blade entry duy nhat cho SPA
- `resources/views/errors/`: error views can thiet cho Laravel
- `app/Http/Controllers/Api/`: API controller layer cho frontend
- `app/Services/`: service layer de giu business logic tach khoi controller

## Ghi chu frontend

- Frontend hien tai da bo shell Blade cu, chi giu SPA entry va error views.
- Khong con dung AdminLTE, DataTables, jQuery, Toastr hay font/icon load truc tiep tu CDN.
- Icon duoc viet thanh component SVG local de de dong goi, version control va dung offline.
- Font hien tai dung local/system stack. Neu sau nay can font custom co dinh, nen dat file vao `resources/fonts/` roi import qua Vite.

## Dinh huong nen lam tiep

1. Them auth cho API bang Sanctum neu mo rong ra user that.
2. Chuan hoa response bang Laravel API Resources.
3. Tach them service/client rieng cho n8n webhook va external providers.
4. Dung workflow CI chay `php artisan test`, `pint`, `phpstan`, `eslint`, `prettier`.

## Lien ket tai lieu

- `../docs/architecture.md`
- `../docs/integration-flow.md`
- `../ai-local/README.md`

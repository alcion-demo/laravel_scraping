# スクレイピング学習  

<img alt="Static Badge" src="https://img.shields.io/badge/wsl2-w?style=plastic&logo=linux&logoColor=000000&labelColor=%23FCC624&color=%23FCC624"> <img alt="Static Badge" src="https://img.shields.io/badge/ubuntu-u?style=plastic&logo=ubuntu&logoColor=%23ffffff&labelColor=%23E95420&color=%23E95420"> <img alt="Static Badge" src="https://img.shields.io/badge/alpine-l?style=plastic&logo=alpinelinux&logoColor=%23ffffff&labelColor=%230D597F&color=%230D597F">
<img alt="Static Badge" src="https://img.shields.io/badge/Docker-d?style=plastic&logo=docker&logoColor=%23ffffff&labelColor=%232496ED&color=%232496ED">  
<img alt="Static Badge" src="https://img.shields.io/badge/NGINX-n?style=plastic&logo=nginx&logoColor=%23ffffff">
<img alt="Static Badge" src="https://img.shields.io/badge/MySQL-m?style=plastic&logo=mysql&logoColor=%23ffffff&labelColor=%234479A1&color=%234479A1">
<img alt="Static Badge" src="https://img.shields.io/badge/Laravel13-l?style=plastic&logo=laravel&logoColor=%23ffffff&labelColor=%23FF2D20&color=%23FF2D20">  
<img alt="Static Badge" src="https://img.shields.io/badge/php-p?style=plastic&logo=php&logoColor=%23ffffff&labelColor=%23777BB4&color=%23777BB4">
<img alt="Static Badge" src="https://img.shields.io/badge/React-%20?style=plastic&logo=react&logoColor=%23000000&labelColor=%2361DAFB&color=%2361DAFB">
<img alt="Static Badge" src="https://img.shields.io/badge/TypeScript-%20?style=plastic&logo=typescript&logoColor=%233178C6&labelColor=%23ffffff&color=%23ffffff">  
<img alt="Static Badge" src="https://img.shields.io/badge/tailwind-%20?style=plastic&logo=tailwindcss&logoColor=ffffff&color=%2306B6D4">
<img alt="Static Badge" src="https://img.shields.io/badge/vite-v?style=plastic&logo=vite&logoColor=%23ffffff&labelColor=%23646CFF&color=%23646CFF">
<img alt="Static Badge" src="https://img.shields.io/badge/npm-n?style=plastic&logo=npm&logoColor=%23ffffff&labelColor=%23CB3837&color=%23CB3837">  

## プロジェクト概要
- Laravel 13ベースのバックエンドと、React + TypeScript + Vite のフロントエンドを含む構成。
- backend 側は Laravel アプリケーション、frontend 側は React SPA。
- Docker Compose で `app` / nginx / `node` / `db(mysql)` / `phpmyadmin` を起動する構成。
- 実装済み機能は、Todo リソースのCRUD APIと、それを呼び出す React フロントエンド。

## 学習・検証目的
- 後で書く
- PHPUnitによるAPI・機能テスト

## 主な機能
- 後で書く
  - 後で書く
  - 後で書く
  - 後で書く
- テスト
  - Featureテストを実装し、後で書くを検証

## 使用技術
| カテゴリ | 使用技術 |
| :--- | :--- |
| **Backend** | Laravel 13, PHP `8.5.11` |
| **Frontend** | React `^19.3.1`, Tailwind CSS, Node.js `22.23.4` |
| **Authentication / Authorization** | Fortify |
| **Infrastructure** | Docker Compose (App / Node / MySQL / Nginx) |
| **OS Environment** | WSL2 (Ubuntu / Alpine Linux) |
| **Database** | MySQL 8.x |

## セットアップ手順

### 1. インフラのビルドと起動
```
docker compose build
docker compose up -d
```

### 2. バックエンド初期化
```
docker compose exec app ash
composer create-project laravel/laravel .
chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache
```
#### 3. 認証packcageの導入
```
composer require laravel/sanctum
php artisan install:api
composer require laravel/fortify
php artisan fortify:install
```
### 4. フロントエンド初期化
```
docker compose run --rm node sh
npm create vite@latest . -- --template react-ts
npm install
npm install tailwindcss @tailwindcss/vite
```
#### 5. .env修正・作成  
※ バックエンドはcompose.ymlに設定した内容に修正。

#### 6. マイグレーション
```
php artisan migrate
```
#### 7. その他(バックエンド)
```
composer require --dev "squizlabs/php_codesniffer=*"
composer require --dev barryvdh/laravel-debugbar
composer require laravel-lang/lang:~8.0
php artisan lang:publish
cp ./vendor/laravel-lang/lang/json/ja.json ./lang/
cp -r ./vendor/laravel-lang/lang/src/ja ./lang/
composer require phpoffice/phpspreadsheet
```

#### 8. その他(フロントエンド)
```
docker compose exec node sh
npm install axios
npm list axios
npm install react-router-dom
npm list react-router-dom
npm install @iconify/react
npm install chart.js react-chartjs-2
```

#### 9. 認証の設定
※下記で、`config/core.php`
```
php artisan config:publish cors
```
```
    'allowed_origins' => ['http://localhost:5173'],
    'supports_credentials' => true,
```
## ディレクトリ構成（主要部分）後で修正
- compose.yaml
- php
  - `Dockerfile`
- nginx
  - `default.conf`
- backend
  - `artisan`
  - `composer.json`
  - `package.json`
  - `.env.example`
  - `routes/`
    - `api.php`
    - `console.php`
    - `web.php`
  - `app/`
    - `Actions/`
      - `Fortify/`
    - `Http/`
      - `Controllers/`
        - `Controller.php`
        - `Api/`
          - `DashboardController.php`
          - `ReceiptController.php`
          - `ReceiptExportController.php`
      - `Requests/`
        - `StoreReceiptRequest.php`
        - `UpdateReceiptRequest.php`
    - `Models/`
      - `Receipt.php`
      - `ReceiptItem.php`
      - `User.php`
    - `Providers/`
      - `AppServiceProvider.php`
      - `FortifyServiceProvider.php`
    - `Services/`
      - `DashboardService.php`
      - `ReceiptExcelExportService.php`
  - `resources/`
    - `views/`
      - `welcome.blade.php`
  - `database/`
- frontend
  - `package.json`
  - `README.md`
  - `src/`
    - `auth/`
      - `Auth.css`
      - `components/`
      - `hooks/`
        - `useAuth.ts`
      - `pages/`
        - `LoginPage.tsx`
        - `RegisterPage.tsx`
      - `types/`
    - `lib/`
      - `axios.ts`
    - `pages/`
      - `calendar/`
        - `CalendarGrid.tsx`
        - `CalendarHeader.tsx`
        - `CalendarPage.tsx`
        - `hooks/`
        - `types/`
        - `utils/`
      - `dashboard/`
        - `Dashboard.css`
        - `DashboardPage.tsx`
        - `components/`
        - `hooks/`
        - `types/`
        - `utils/`
      - `receipts/`
        - `ReceiptCreatePage.tsx`
        - `ReceiptEditPage.tsx`
        - `ReceiptsPage.tsx`
        - `components/`
          - `ReceiptForm.tsx`
          - `ReceiptItemRow.tsx`
          - `ReceiptItemsEditor.tsx`
          - `ReceiptSummary.tsx`
        - `hooks/`
        - `types/`
    - `App.css`
    - `App.tsx`
    - `index.css`
    - `main.tsx`
  - `public/`
  - `index.html`
  - `vite.config.ts`
- `data/`
- `mysql/`
  - `my.cnf`
---
## 設計・実装の特徴　後で修正
- API を RESTful に定義
- `index()` は `JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE` 付きの JSON レスポンス
- `App\Models\User` は PHP 8 の属性ベースで `Fillable` / `Hidden` を定義し、`password` を `hashed` にキャスト
- フロントエンドは React Hooks でシンプルに API 連携し、CRUD 操作を実装

---

## 処理の流れ　後で修正
```mermaid
graph TD
    A[React フロントエンド] -->|認証/ログイン| B[Laravel Fortify]
    A -->|API 呼び出し| C[Laravel API routes]
    C --> D[ReceiptController]
    C --> E[DashboardController]
    C --> F[ReceiptExportController]
    D --> G[Receipt Model]
    E --> H[DashboardService]
    F --> I[ReceiptExcelExportService]
    G --> J[(MySQL Database)]
    H --> J
    I --> J
```
## クラス構成図　後で修正
```mermaid
classDiagram
    class User {
        +id
        +name
        +email
        +password
        +receipts()
    }

    class Receipt {
        +id
        +user_id
        +purchase_date
        +category
        +store_name
        +total_amount
        +note
        +user()
        +items()
        +getListByUser(userId)
        +findByUser(userId, receiptId)
        +createWithItems(userId, data)
        +updateWithItems(userId, receiptId, data)
        +deleteByUser(userId, receiptId)
    }

    class ReceiptItem {
        +id
        +receipt_id
        +product_name
        +quantity
        +unit_price
        +receipt()
    }

    class ReceiptController {
        +index(request)
        +store(request)
        +show(request, id)
        +update(request, id)
        +destroy(request, id)
    }

    class DashboardController {
        +index(request, dashboardService)
    }

    class DashboardService {
        +getMonthlySummary(userId, month)
    }

    class ReceiptExportController {
        +export(request, service)
    }

    class ReceiptExcelExportService {
        +generate(userId, month)
        +save(spreadsheet, path)
    }

    User "1" --> "many" Receipt
    Receipt "1" --> "many" ReceiptItem
    ReceiptController --> Receipt
    DashboardController --> DashboardService
    ReceiptExportController --> ReceiptExcelExportService
```
## 今後の改善予定　後で修正
- OCRを追加検討
- Excel出力レイアウトの改善
- 実際の利用を通じたUI・操作性の改善
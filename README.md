# coachtech 勤怠管理アプリ

## 概要

一般ユーザーが出勤・休憩・退勤の打刻、勤怠の確認、勤怠修正申請を行い、管理者ユーザーが全ユーザーの勤怠確認・スタッフ管理・修正申請の承認を行う、勤怠管理を目的とした Web アプリケーションです。

## 使用技術

- PHP 8.5
- Laravel 10.50
- MySQL 8.x
- Laravel Sail（Docker）
- Laravel Fortify（認証）

## 環境構築手順

1. リポジトリを取得する

```bash
   git clone <このリポジトリのURL>
   cd <プロジェクトディレクトリ>
```

2. `.env.example` を `.env` にコピーする

```bash
   cp .env.example .env
```

3. Sail を起動し、依存関係をインストールする

```bash
   ./vendor/bin/sail up -d
   ./vendor/bin/sail composer install
   ./vendor/bin/sail artisan key:generate
```

4. マイグレーション・シーディングを実行する

```bash
   ./vendor/bin/sail artisan migrate --seed
```

5. フロントエンドをビルドする

```bash
   ./vendor/bin/sail npm install
   ./vendor/bin/sail npm run dev
```

6. アクセスする
    - アプリ: http://localhost
    - phpMyAdmin: http://localhost:8080
    - Mailpit: http://localhost:8025

## ログイン情報（シーディングで自動作成）

| 役割           | メールアドレス    | パスワード | 備考                |
| -------------- | ----------------- | ---------- | ------------------- |
| 一般ユーザー   | user1@example.com | password   | メール認証済み      |
| 一般ユーザー   | user2@example.com | password   | メール認証済み      |
| 管理者ユーザー | user3@example.com | password   | admin_status = true |

- 一般ユーザーログイン: http://localhost/login
- 管理者ログイン: http://localhost/admin/login

## 主な機能

### 一般ユーザー

- 会員登録・ログイン・ログアウト
- 出勤・休憩・退勤の打刻（`/attendance`）
- 勤怠一覧の確認（`/attendance/list`）
- 勤怠詳細の確認・修正申請（`/attendance/{id}`）
- 修正申請一覧の確認（`/stamp_correction_request/list`）

### 管理者ユーザー

- 管理者ログイン・ログアウト（`/admin/login`）
- 全ユーザーの当日勤怠一覧（`/admin/attendance/list`）
- 勤怠詳細の確認・直接修正（`/attendance/{id}`）
- スタッフ一覧（`/admin/staff/list`）
- スタッフ別の月次勤怠一覧（`/admin/attendance/staff/{id}`）
- 修正申請一覧の確認・承認（`/stamp_correction_request/list`, `/stamp_correction_request/approve/{id}`）

## テストの実行

```bash
./vendor/bin/sail artisan test
```

## コード整形（Laravel Pint）

```bash
./vendor/bin/sail pint
```

## ディレクトリ構成の補足

- `app/Http/Controllers/AttendanceRecordController.php` : 打刻・勤怠一覧・勤怠詳細表示・修正（申請/直接）を担当。一般ユーザー・管理者共通で使用
- `app/Http/Controllers/ApplicationController.php` : 修正申請の一覧・承認を担当。一般ユーザー・管理者共通で使用
- `app/Http/Controllers/Admin/AdminAuthController.php` : 管理者ログイン・ログアウト
- `app/Http/Controllers/Admin/AdminAttendanceController.php` : 管理者向け・全ユーザーの当日勤怠一覧
- `app/Http/Controllers/Admin/StaffController.php` : スタッフ一覧・スタッフ別月次勤怠一覧
- `app/Actions/Fortify/CreateNewUser.php` : 会員登録処理（`RegisterRequest` のルールを使用）
- `app/Providers/FortifyServiceProvider.php` : ログイン処理のカスタマイズ（一般ユーザーのみ認証）
- `lang/ja/auth.php`, `lang/ja/validation.php` : 日本語バリデーション・認証メッセージ

## 実装状況（2026年時点）

- 基本機能（会員登録・ログイン・打刻・勤怠一覧/詳細・修正申請・管理者機能一式）: 実装済み・テスト済み
- 応用機能（メール認証・マイ勤怠レポート・公開API・CSV出力）: 今後実装予定

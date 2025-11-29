# 子供向けアトリエ実装

## 実装内容

### 1. データベース
- 新しいテーブル `kids_artworks` を作成
- 即時承認（審査なし）
- AIストーリーフィールド付き

### 2. ファイル保存先
- `uploads/kids/` フォルダに保存
- 通常の作品とは別管理

### 3. 新規ファイル
- `/api/upload-kids-artwork.php` - 子供向けアップロードAPI
- `/kids-works.php` - 子供向け作品一覧ページ
- `/kids-work.php` - 子供向け作品詳細ページ
- `/database/kids_artworks_complete.sql` - テーブル作成SQL（統合版）
- `/api/get-floating-materials.php` - 背景素材取得API

### 4. 修正ファイル
- `/compose2/kids.php` - アップロードAPIエンドポイントを変更、背景素材アニメーション追加
- `/includes/header-kids.php` - 子供向けヘッダー（完全版）
- `/includes/header-kids-nav.php` - 子供向けヘッダー（navbar only）

## セットアップ手順

### 1. データベーステーブルの作成

```bash
mysql -u your_username -p your_database < database/kids_artworks_complete.sql
```

または、phpMyAdminなどで `database/kids_artworks_complete.sql` の内容を実行してください。

**注意:** 統合版SQLファイルには以下が含まれています：
- kids_artworksテーブルの作成
- ペンネーム機能の削除（NULL許可）
- 必要なインデックスの作成

### 2. アップロードディレクトリの作成

```bash
mkdir -p uploads/kids
chmod 755 uploads/kids
```

### 3. OpenAI APIキーの設定

`includes/openai.php` に以下の定数が定義されていることを確認：

```php
define('OPENAI_API_KEY', 'your-api-key-here');
```

## 機能

### アップロード処理
1. 子供が作品をアップロード
2. 即時公開（審査なし）
3. OpenAI GPT-4o-miniで画像からストーリーを自動生成
4. `kids_artworks` テーブルに保存
5. `uploads/kids/` フォルダに画像を保存

### ストーリー生成
- Vision APIを使用して画像を解析
- 100文字程度のひらがな多めの優しいお話を生成
- 生成失敗時は空文字列（エラーにはしない）

### 表示
- `kids-works.php`: 作品一覧
- `kids-work.php`: 作品詳細（AIストーリー表示）
- ページネーション: 50件/ページ

## 注意事項

- OpenAI APIの料金が発生します（GPT-4o-mini使用）
- 画像は最大10MBまで
- PNG/JPEG形式のみ
- 自動リサイズ: 最大1200px
- AIストーリー生成は同期処理（アップロード時間が少し長くなる可能性）

## 今後の改善案

- ストーリー生成を非同期処理にする
- 生成されたストーリーの品質チェック
- 不適切な内容のフィルタリング
- 管理画面での作品管理機能

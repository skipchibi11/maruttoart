# Cloudflare R2 アップロード実装（SDK不要版）

community_artworks のアップロードを Cloudflare R2 に変更しました。

## 特徴

✅ **SDK不要** - AWS Signature Version 4 を PHP ネイティブで実装  
✅ **Composer不要** - 外部ライブラリ一切不要  
✅ **Presigned URL 方式** - ブラウザから直接 R2 にアップロード（サーバー負荷軽減）  
✅ **投稿制限対応** - IP ベースで 1日1回制限

## クイックスタート

### 1. .env に R2 設定を追加

```env
R2_ACCOUNT_ID=your_account_id
R2_BUCKET=your_bucket_name
R2_ACCESS_KEY_ID=your_access_key
R2_SECRET_ACCESS_KEY=your_secret_key
R2_PUBLIC_URL=https://your-custom-domain.com
```

### 2. Cloudflare で設定

1. R2 バケットを作成
2. API トークンを作成（Read & Write 権限）
3. **CORS 設定**（必須）:

```json
[
  {
    "AllowedOrigins": ["https://yourdomain.com"],
    "AllowedMethods": ["GET", "PUT", "POST"],
    "AllowedHeaders": ["*"],
    "ExposeHeaders": ["ETag"],
    "MaxAgeSeconds": 3600
  }
]
```

4. パブリックアクセスを有効化

### 3. 動作確認

1. `/compose/` で作品を作成・投稿
2. DB の `community_artworks` に R2 URL が保存されているか確認

## ファイル構成

- **api/get-r2-presigned-url.php** - Presigned URL 生成（PHP ネイティブ署名）
- **api/confirm-r2-upload.php** - アップロード完了・DB登録
- **compose/index.php** - クライアント側実装（3ステップアップロード）
- **config.php** - R2 設定定義

## 詳細ドキュメント

- [R2_IMPLEMENTATION.md](R2_IMPLEMENTATION.md) - 実装概要・セットアップ手順
- [docs/R2_SETUP.md](docs/R2_SETUP.md) - 詳細な設定ガイド

## トラブルシューティング

| エラー | 原因 | 解決方法 |
|--------|------|----------|
| "R2設定が不完全です" | `.env` 未設定 | R2_* 環境変数を設定 |
| CORS エラー | CORS 未設定 | R2 バケットで CORS を設定 |
| "Presigned URL が無効" | 時刻ズレ/設定ミス | システム時刻確認、`.env` 確認 |
| 画像が表示されない | 公開設定ミス | R2 パブリックアクセス確認 |

## セキュリティ

- `.env` は Git にコミットしない（`.gitignore` に追加済み）
- 投稿は IP ベースで 1日1回に制限
- ファイルサイズは 10MB まで
- PNG/JPEG のみ許可

## コスト

Cloudflare R2 は無料枠が大きめ:
- ストレージ: 10GB まで無料
- Class A 操作（PUT）: 100万リクエスト/月まで無料
- データ転送: 無料（Cloudflare 経由）

詳細: https://developers.cloudflare.com/r2/pricing/

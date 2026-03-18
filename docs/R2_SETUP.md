# Cloudflare R2 アップロード設定ガイド

このガイドでは、community_artworks のアップロードを Cloudflare R2 に切り替えるための設定手順を説明します。

## 前提条件

- Cloudflare アカウント
- R2 ストレージが有効化されていること
- PHP 7.4 以上（標準機能のみ使用、外部SDK不要）

## 1. Cloudflare R2 バケットの作成

1. Cloudflare ダッシュボードにログイン
2. **R2** セクションに移動
3. **Create bucket** をクリック
4. バケット名を入力（例: `maruttoart-artworks`）
5. リージョンを選択して作成

## 3. R2 API トークンの作成

1. R2 ダッシュボードで **Manage R2 API Tokens** をクリック
2. **Create API Token** をクリック
3. 権限を設定:
   - **Object Read & Write** を選択
   - 作成したバケットを指定
4. トークンを作成し、以下をメモ:
   - `Access Key ID`
   - `Secret Access Key`
   - `Account ID`（R2 ダッシュボードの右上に表示）

## 4. 環境変数の設定

`.env` ファイルに以下を追加:

```env
# Cloudflare R2 設定
R2_ACCOUNT_ID=your-account-id-here
R2_BUCKET=maruttoart-artworks
R2_ACCESS_KEY_ID=your-access-key-id
R2_3ECRET_ACCESS_KEY=your-secret-access-key
R2_PUBLIC_URL=https://your-custom-domain.com
```

**注意**: `.env` ファイルは `.gitignore` に含まれていることを確認してください。

## 4. R2 バケットの公開設定

### オプション A: R2.dev ドメインを使用（簡単）

1. R2 ダッシュボードでバケットを開く
2. **Settings** タブに移動
3. **Public Access** セクションで **Allow Access** を有効化
4. 表示される `*.r2.dev` URL をメモ
5. `.env` の `R2_PUBLIC_URL` に設定

```env
R2_PUBLIC_URL=https://pub-xxxxxxxxxxxxx.r2.dev
```

### オプション B: カスタムドメインを使用（推奨）

1. R2 バケット設定で **Custom Domains** を選択
2. **Add Custom Domain** をクリック
3. ドメイン名を入力（例: `cdn.maruttoart.com`）
4. DNS レコードを追加:
   - Type: `CNAME`
   - Name: `cdn`（またはサブドメイン）
   - Target: バケットの R2 エンドポイント
5. `.env` に設定:

```env
R2_PUBLIC_URL=https://cdn.maruttoart.com
```

## 5. CORS 設定（必須）

Presigned URL 方式では、ブラウザから直接 R2 にアップロードするため、CORS 設定が必要です。

1. R2 バケット設定で **CORS policy** を選択
2. 以下の JSON を追加:

```json
[
  {
    "AllowedOrigins": [
      "https://yourdomain.com",
      "http://localhost"
    ],
    "AllowedMethods": [
      "GET",
      "PUT",
      "POST"
    ],
    "AllowedHeaders": [
      "*"
    ],
    "ExposeHeaders": [
      "ETag"
    ],
    "MaxAgeSeconds": 3600
  }
]
```

**重要**: `AllowedOrigins` に実際のドメインを設定してください。

## 6. 動作確認

1. ブラウザで `/compose/` にアクセス
2. 作品を作成して投稿
3. デベロッパーツールの Network タブで以下を確認:
   - `/api/get-r2-presigned-url.php` が成功（200）
   - R2 への PUT リクエストが成功（200）
   - `/api/confirm-r2-upload.php` が成功（200）
4. DB の `community_artworks` テーブルに新規レコードが追加されていることを確認
5. `image_path` と `webp_path` に R2 の URL が保存されていることを確認

## トラブルシューティング

### CORS エラーが発生する場合

- R2 バケットの CORS 設定を確認
- ブラウザのコンソールでエラーメッセージを確認
- `AllowedOrigins` に現在のドメインが含まれているか確認

### Presigned URL が無効と言われる場合

- R2 API トークンの権限を確認（Read & Write が必要）
- システム時刻が正確か確認（署名は時刻ベース）
- `.env` の設定値が正しいか確認

### アップロード後に DB 登録されない場合

- `/api/confirm-r2-upload.php` のレスポンスを確認
- `logs/r2_confirm_errors.log` を確認
- `community_artworks` テーブルのスキーマを確認

### 画像が表示されない場合

- R2 バケットが公開設定されているか確認
- `R2_PUBLIC_URL` が正しく設定されているか確認
- ブラウザで画像 URL に直接アクセスして確認

## セキュリティ上の注意

1. **API トークンの管理**
   - `.env` ファイルを Git にコミットしない
   - 本番環境では環境変数で管理

2. **投稿制限**
   - 現在は IP アドレスベースで 1日1回の制限
   - 必要に応じて追加の制限を実装

3. **ファイルサイズ制限**
   - 現在は 10MB まで
   - R2 側でも制限を設定可能

4. **CORS 設定**
   - 本番環境では `AllowedOrigins` を実際のドメインのみに制限
   - ワイルドカード（`*`）は使用しない

## コスト管理

Cloudflare R2 の料金:
- ストレージ: $0.015/GB/月（10GB まで無料）
- Class A 操作（PUT, POST）: $4.50/百万リクエスト
- Class B 操作（GET）: $0.36/百万リクエスト
- データ転送: 無料（Cloudflare 経由）

詳細: https://developers.cloudflare.com/r2/pricing/

## 既存データの移行

既存の `uploads/everyone-works/` 内の画像を R2 に移行する場合:

```bash
# Wrangler CLI をインストール
npm install -g wrangler

# 認証
wrangler login

# バッチアップロード
wrangler r2 object put maruttoart-artworks/community-artworks/migration/ \
  --file uploads/everyone-works/**/*.{png,jpg,jpeg}
```

移行後、DB の `image_path` と `webp_path` を更新するスクリプトが必要です。

## 参考リンク

- [Cloudflare R2 Documentation](https://developers.cloudflare.com/r2/)
- [AWS SDK for PHP (S3 Client)](https://docs.aws.amazon.com/sdk-for-php/v3/developer-guide/s3-presigned-url.html)
- [R2 CORS Configuration](https://developers.cloudflare.com/r2/buckets/cors/)

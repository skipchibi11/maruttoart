# R2 アップロード実装 - セットアップ手順

community_artworks のアップロードを Cloudflare R2（presigned URL 方式）に変更しました。

**特徴: SDK 不要** - AWS Signature Version 4 を PHP ネイティブで実装しているため、外部ライブラリやComposerは不要です。

## 実装内容

### 作成・修正したファイル

1. **config.php** - R2 設定を追加
2. **api/get-r2-presigned-url.php** - Presigned URL 生成 API（新規）
3. **api/confirm-r2-upload.php** - アップロード完了・DB登録 API（新規）
4. **compose/index.php** - クライアント側アップロード処理を R2 対応に変更
5. **composer.json** - AWS SDK for PHP を追加（新規）
6. **.env.example** - R2 設定項目を追加
7. **docs/R2_SETUP.md** - 詳細なセットアップガイド（新規）

### アップロードフロー

```
[ブラウザ] 
  ↓ (1) ファイル情報送信
[get-r2-presigned-url.php] 
  ↓ (2) Presigned URL 生成
[ブラウザ] 
  ↓ (3) PUT で直接アップロード
[Cloudflare R2] 
  ↓ (4) アップロード完了通知
[confirm-r2-upload.php] 
  ↓ (5) DB登録・post_limits更新
[完了]
```

## 次にやるndor/` ディレクトリが作成され、AWS SDK がインストールされます。

### 3. .env ファイルの設定

`.env.example` を参考に `.env` に以下を追加:

```env
R2_ACCOUNT_ID=your_account_id
R2_BUCKET=maruttoart-artworks
R2_ACCESS_KEY_ID=your_access_key
R2_SECRET_ACCESS_KEY=your_secret_key
R2_PUBLIC_URL=https://your-domain.com
```

### 4. Cloudflare R2 の設定

詳細は [docs/R2_SETUP.md](docs/R2_SETUP.md) を参照してください。

**必須手順:**
1. R2 バケットを作成
2. API トークンを作成（Read & Write 権限）
3. CORS ポリシーを設定（必須）
4. パブリックアクセスを有効化

### 5. 動作確認

1. `/compose/` にアクセス
2. 作品を作成して投稿
3. 以下を確認:
   - エラーが出ないこと
   - DB に `community_artworks` レコードが追加されること
   - `image_path` に R2 の URL が保存されていること
   -2画像が正しく表示されること

## トラブルシューティング

### エラー: "Call to undefined method"

→ `composer install` が実行されていません。上記手順2を実行してください。

### エラー: "R2設定が不完全です"

→ `.env` ファイルに R2 設定が追加されていません。上記手順3を実行してください。

### 3ラー: CORS エラー

→ R2 バケットの CORS 設定を確認してください。[docs/R2_SETUP.md](docs/R2_SETUP.md) の手順6を参照。

### エラー: "Presigned URL が無効"

→ 以下を確認:
- R2 API トークンの権限（Read & Write が必要）
- システム時刻が正確か
- `.env` の設定値が正しいか

### 画像が表示されない
URL に直接アクセスして確認

## 既存機能への影響

- **kids_artworks**: 影響なし（既存のローカルアップロードを継続）
- **admin/upload.php**: 影響なし（materials テーブルは既存方式）
- **api/upload-custom-artwork.php**1を実行してください。

### エラー: CORS エラー

→ R2 バケットの CORS 設定を確認してください。[docs/R2_SETUP.md](docs/R2_SETUP.md) の手順5
- 画像リサイズ・変換を R2 側で実行（Cloudflare Images 連携）
- WebP 自動変換（現在は PNG/JPEG のみ）
- 既存画像の R2 移行スクリプト

## 参考

- 詳細なセットアップガイド: [docs/R2_SETUP.md](docs/R2_SETUP.md)
- Cloudflare R2 公式ドキュメント: https://developers.cloudflare.com/r2/

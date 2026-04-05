# X (Twitter) 自動投稿機能のセットアップガイド

## 概要
このガイドでは、承認済みのコミュニティ作品をX (Twitter) に自動投稿する機能の設定方法を説明します。

## 前提条件
- PHPがインストールされていること（cURL拡張が有効）
- MariaDB/MySQLデータベースが稼働していること
- X Developer Accountを持っていること

## セットアップ手順

### 1. X API認証情報の取得

#### 2.1 Twitter Developer Portalへのアクセス
1. [Twitter Developer Portal](https://developer.twitter.com/en/portal/dashboard) にアクセス
2. X Developer Accountでログイン（未登録の場合は新規登録）

#### 2.2 アプリケーションの作成
1. "Projects & Apps" → "Overview" を選択
2. "Create App" または既存のアプリを選択
3. アプリ名を入力（例: "MaruttoArt Bot"）

#### 2.3 API権限の設定
1. アプリの設定画面で "Settings" タブを選択
2. "User authentication settings" の "Set up" をクリック
3. 以下を設定：
   - App permissions: **Read and Write** を選択
   - Type of App: **Web App, Automated App or Bot** を選択
   - Callback URI / Redirect URL: `http://localhost`（任意）
   - Website URL: あなたのサイトURL（例: `https://marutto.art`）

#### 2.4 APIキーとトークンの取得
1. "Keys and tokens" タブを選択
2. 以下の情報を控えておく：
   - **API Key** (Consumer Key)
   - **API Key Secret** (Consumer Secret)
   - **Access Token**
   - **Access Token Secret**
   - **Bearer Token** (オプション)

### 3. 環境変数の設定

`.env` ファイルを編集して、X APIの認証情報を追加します：

```bash
# .envファイルがない場合は.env.exampleからコピー
cp .env.example .env

# エディタで.envファイルを開く
nano .env
```

以下の行を追加または更新：

```env
# X (Twitter) API設定
X_API_KEY=your_api_key_here
X_API_SECRET=your_api_secret_here
X_ACCESS_TOKEN=your_access_token_here
X_ACCESS_TOKEN_SECRET=your_access_token_secret_here
X_BEARER_TOKEN=your_bearer_token_here
```

**セキュリティ注意事項:**
- `.env` ファイルは `.gitignore` に含めて、Gitにコミットしないこと
- APIキーは絶対に公開しないこと
- 定期的にトークンをローテーションすること

### 3. スクリプトの動作確認
2
#### 手動テスト実行
```bash
cd /path/to/maruttoart/cron
./post_to_x.sh
```

期待される出力：
```
[2026-04-05 10:00:00] 投稿する作品: 春の風景
[2026-04-05 10:00:01] 画像: WebP形式を使用
[2026-04-05 10:00:02] 画像アップロード成功: メディアID = 1234567890
[2026-04-05 10:00:03] ツイート投稿成功: https://x.com/i/status/1234567890123456789
```

#### エラー確認
```bash
# ログファイルを確認
tail -f logs/x_post.log
```

### 5. Cronジョブの設定
4
#### 推奨設定: 1日3回投稿（午前9時、午後3時、午後9時）

```bash
# crontabを編集
crontab -e
```

以下の行を追加：

```cron
# X (Twitter) への自動投稿（1日3回）
0 9,15,21 * * * /path/to/maruttoart/cron/post_to_x.sh
```

#### その他の設定例

**1日1回（午前9時）:**
```cron
0 9 * * * /path/to/maruttoart/cron/post_to_x.sh
```

**3時間ごと:**
```cron
0 */3 * * * /path/to/maruttoart/cron/post_to_x.sh
```

**毎日正午:**
```cron
0 12 * * * /path/to/maruttoart/cron/post_to_x.sh
```

#### Cronの確認
```bash
# 設定したcronジョブを確認
crontab -l

# Cronログを確認（システムによって場所が異なる）
tail -f /var/log/cron
# または
tail -f /var/log/syslog | grep CRON
```

### 6. ログの監視
5
定期的にログファイルを確認して、正常に動作しているか確認してください：

```bash
# リアルタイムログ監視
tail -f logs/x_post.log

# 最新20行を表示
tail -n 20 logs/x_post.log

# エラーのみ抽出
grep "エラー" logs/x_post.log
```

## トラブルシューティング

### よくあるエラーと対処法

#### 1. 認証エラー
**エラーメッセージ:**
```
エラー: X APIの認証情報が設定されていません。
```

**対処法:**
- `.env` ファイルに正しいAPIキーが設定されているか確認
- ファイルのパーミッションを確認（読み取り可能であること）

#### 2. 画像アップロードエラー（HTTP 401）
**エラーメッセージ:**
```
画像アップロードエラー: HTTP 401
```

**対処法:**
- APIキーが正しいか確認
- Access TokenとAccess Token Secretが正しいペアか確認
- X Developer Portalでアプリの権限が "Read and Write" になっているか確認

#### 3. ツイート投稿エラー（HTTP 403）
**エラーメッセージ:**
```
ツイート投稿エラー: HTTP 403
```

**対処法:**
- 重複したツイートを投稿していないか確認
- APIの利用制限に達していないか確認
- アプリが停止されていないか確認

#### 4. 画像ファイルが見つからない
**エラーメッセージ:**
```
エラー: 画像ファイルが見つかりません。
```

**対処法:**
- データベースのファイルパスが正しいか確認
- 実際にファイルが存在するか確認
- ファイルのパーミッションを確認

#### 5. 投稿可能な作品がない
**エラーメッセージ:**
```
投稿可能な作品が見つかりませんでした。
```

**対処法:**
- データベースに承認済みの作品（status = 'approved'）があるか確認
- 作品に画像ファイルが関連付けられているか確認

### ログレベルの調整

より詳細なログが必要な場合は、`post_to_x.php` の `logMessage` 関数を修正してください。

## 投稿内容のカスタマイズ

### ツイートテキストの変更

`post_to_x.php` の以下の部分を編集：

```php
// ツイート本文を作成
$tweetText = $artwork['title'] . "\n制作者: " . $artwork['pen_name'] . "\n\n#イラスト #illustration #maruttoart";
```

例：
```php
// URLを含める場合
$artworkUrl = SITE_URL . "/everyone-work.php?id=" . $artwork['id'];
$tweetText = $artwork['title'] . "\n制作者: " . $artwork['pen_name'] . "\n" . $artworkUrl . "\n\n#イラスト #illustration #maruttoart";
`hashtags = "\n\n#イラスト #illustration #maruttoart";
$tweetText = $artwork['title'] . "\n" . $artworkUrl . $hashtags

### ハッシュタグの追加

```php
$hashtags = "\n\n#イラスト #illustration #maruttoart #アート #創作";
$tweetText = $artwork['title'] . $hashtags;
```

## セキュリティのベストプラクティス

1. **APIキーの管理:**
   - `.env` ファイルを `.gitignore` に追加
   - サーバー上の `.env` ファイルのパーミッションを 600 に設定: `chmod 600 .env`

2. **定期的なローテーション:**
   - 3〜6ヶ月ごとにAPIキーを再生成
   - 古いキーを無効化

3. **アクセス制限:**
   - Cronスクリプトは必要最小限の権限で実行
   - Webサーバーから直接アクセスできないようにする（`.htaccess` で保護）

4. **監視:**
   - 異常な投稿パターンがないか定期的にチェック
   - ログファイルを定期的に確認

## まとめ

これで、X (Twitter) への自動投稿機能が設定されました！
データベーステーブル作成
- ✅ X API認証情報取得
- ✅ 環境変数設定
- ✅ 動作確認
- ✅ Cronジョブ設定
- ✅ ログ監視

何か問題が発生した場合は、ログファイルを確認するか、X Developer Portalのアプリケーション設定を見直してください。

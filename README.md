# maruttoart - 無料素材ダウンロードサイト

## 概要
ピュアPHP、MySQL、Apacheを使用した無料素材のダウンロードサイトです。
管理画面で素材をアップロードし、公式サイトでダウンロードできます。

## 技術スタック
- **言語**: ピュアPHP
- **データベース**: MySQL
- **Webサーバー**: Apache
- **開発環境**: Docker
- **フロントエンド**: Bootstrap 5

## URL構成

### 本番環境
- 公式サイト: `http://marutto.art/`
- 管理サイト: `http://marutto.art/admin`
- 画像格納場所: `http://marutto.art/uploads/yyyy/mm/[slug].png`

### ローカル開発環境
- 公式サイト: `http://localhost/`
- 管理サイト: `http://localhost/admin`
- 画像格納場所: `http://localhost/uploads/yyyy/mm/[slug].png`

## セットアップ

1. リポジトリをクローン:
```bash
git clone <repository-url>
cd maruttoart
```

2. Dockerコンテナを起動:
```bash
docker-compose up -d
```

3. データベースが自動的にセットアップされます。

4. ブラウザでアクセス:
   - 公式サイト: http://localhost
   - 管理画面: http://localhost/admin
   - phpMyAdmin: http://localhost:8080

## 管理者ログイン情報
- **メールアドレス**: 指定したアドレス
- **パスワード**: 指定したパスワード

## 機能

### 公式サイト
- 素材一覧表示（WebP形式のサムネイル）
- 検索機能
- 詳細ページ（画像、タイトル、説明、YouTube動画埋め込み）
- 素材ダウンロード

### 管理サイト
- 管理者ログイン
- 素材アップロード（PNG自動WebP変換）
- 素材管理（一覧、編集、削除）
- ダッシュボード

## データベース構造

### admins テーブル
- id (PRIMARY KEY)
- email
- password
- created_at

### materials テーブル
- id (PRIMARY KEY)
- title
- slug
- description
- youtube_url
- search_keywords_en
- search_keywords_jp
- image_path
- webp_path
- upload_date
- created_at
- updated_at

## ディレクトリ構造
```
/
├── admin/                 # 管理画面
│   ├── index.php         # ダッシュボード
│   ├── login.php         # ログイン画面
│   ├── upload.php        # アップロード画面
│   ├── delete.php        # 削除処理
│   └── logout.php        # ログアウト処理
├── detail/               # 詳細ページ
│   └── index.php
├── uploads/              # アップロード画像保存先
│   └── yyyy/mm/          # 年/月別ディレクトリ
├── database/
│   └── init.sql          # データベース初期化SQL
├── config.php            # 設定ファイル
├── index.php             # 公式サイトトップ
├── 404.php               # 404エラーページ
├── .htaccess             # URL書き換え設定
├── Dockerfile
└── docker-compose.yml
```

## 開発時の注意事項
- 画像アップロード時に自動でWebPに変換されます
- スラッグはURL用の識別子として使用されます
- 検索キーワードは日本語・英語両方に対応しています
- Bootstrap 5を使用して白を基調としたデザインです

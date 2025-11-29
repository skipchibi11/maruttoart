# データベースマイグレーション

このディレクトリには、maruttoartプロジェクトのデータベーススキーマとマイグレーションファイルが含まれています。

## ファイル一覧

### 初期セットアップ
- **`init.sql`** - データベースとユーザーの初期設定、基本テーブル作成

### 子供向けアトリエ機能
- **`kids_artworks_complete.sql`** - 子供向けアトリエ機能の統合マイグレーション（推奨）
  - kids_artworksテーブルの作成
  - ペンネーム機能の削除（NULL許可）
  - 必要なインデックスの作成

### レガシーファイル（非推奨）
- `kids_artworks.sql` - 初期のkids_artworksテーブル作成（`kids_artworks_complete.sql`に統合済み）
- `remove_pen_name_requirement.sql` - ペンネーム削除マイグレーション（`kids_artworks_complete.sql`に統合済み）

### その他の機能
- `community_artworks.sql` - みんなのアトリエ作品テーブル
- `password_reset_table.sql` - パスワードリセット機能
- `setup_tags_categories.sql` - タグとカテゴリー機能
- その他の拡張機能SQL

## 使用方法

### 1. 初期セットアップ（新規インストール）

```bash
# データベースとユーザーを作成
mysql -u root -p < database/init.sql

# 子供向けアトリエ機能を追加
mysql -u root -p maruttoart < database/kids_artworks_complete.sql

# その他の機能を必要に応じて追加
mysql -u root -p maruttoart < database/community_artworks.sql
mysql -u root -p maruttoart < database/setup_tags_categories.sql
```

### 2. 既存データベースへの追加

```bash
# 子供向けアトリエ機能を既存DBに追加
mysql -u root -p maruttoart < database/kids_artworks_complete.sql
```

### 3. Dockerでの使用

```bash
# Dockerコンテナ内で実行
docker-compose exec db mysql -u root -pmaruttopass maruttoart < /docker-entrypoint-initdb.d/kids_artworks_complete.sql
```

## テーブル構造

### kids_artworks（子供向けアトリエ作品）

```sql
CREATE TABLE kids_artworks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,              -- AIが生成したタイトル
    description TEXT,                         -- 作品説明（オプション）
    ai_story TEXT,                            -- AIが生成した物語
    image_path VARCHAR(512) NOT NULL,         -- 元画像パス
    webp_path VARCHAR(512),                   -- WebPサムネイル
    pen_name VARCHAR(100) NULL,               -- レガシー（使用しない）
    ip_address VARCHAR(45),                   -- IP制限用
    downloads INT DEFAULT 0,                  -- ダウンロード数
    is_featured BOOLEAN DEFAULT FALSE,        -- 注目作品
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

## 機能説明

### 子供向けアトリエ（kids.php）
- 子供でも簡単に使える作品作成ツール
- AIが自動的にタイトルと物語を生成
- ペンネーム機能は削除（シンプル化）
- IP制限による1日1回のアップロード制限

## マイグレーション履歴

| 日付 | ファイル | 変更内容 |
|------|---------|---------|
| 2024-08-XX | init.sql | 初期データベース作成 |
| 2024-08-XX | kids_artworks.sql | 子供向けアトリエ機能追加 |
| 2024-11-XX | remove_pen_name_requirement.sql | ペンネーム削除 |
| 2024-11-29 | kids_artworks_complete.sql | 子供向けアトリエ統合マイグレーション |

## トラブルシューティング

### エラー: Table already exists
```bash
# テーブルが既に存在する場合は、ALTER文のみが実行されます
# CREATE TABLE IF NOT EXISTS を使用しているため、エラーは発生しません
```

### ペンネームデータの削除
```sql
-- 既存のデフォルトペンネームをNULLに更新する場合
UPDATE kids_artworks SET pen_name = NULL WHERE pen_name = 'げんきな おともだち';
```

## 注意事項

- 本番環境で実行する前に、必ずバックアップを取得してください
- `kids_artworks.sql`と`remove_pen_name_requirement.sql`は統合されたため、新規セットアップでは`kids_artworks_complete.sql`のみを使用してください
- 既存のデータベースを更新する場合は、`kids_artworks_complete.sql`を実行すると自動的に適用されます

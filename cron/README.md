# 画像処理システム

## 概要
このシステムは、アップロードされた素材画像とコミュニティ作品の処理を自動化します：
1. 構造化データ（JSON-LD、OGP等）用の1200x1200px画像生成
2. 画像のベクトル化（類似検索用）
3. 類似画像の計算・管理
4. 素材ファイルの整理・統合
5. **コミュニティ作品のベクトル化と類似作品検出**
6. **子供の作品用タイトルとストーリーの自動生成**
7. **みんなのアトリエ作品用タイトルと説明の自動生成**

## 機能

### 構造化画像生成
- OpenAI Vision APIを使用した画像分析による適切な背景色の自動決定
- 元画像のアスペクト比を保持した1200x1200pxへのリサイズ
- ペールトーン（薄い色調）の背景色適用
- バッチ処理による複数画像の一括処理

### 画像ベクトル化
- OpenAI Vision APIによる画像内容の自動説明文生成
- Text Embedding APIによるベクトル数値の生成
- 未処理素材の1件ずつ処理（API制限対応）
- 類似画像検索の基盤データ作成

### 類似画像計算・管理
- コサイン類似度による画像類似度計算
- カテゴリ・タグが一致する素材のみを比較対象とする効率化
- 中間テーブルによる類似関係の管理
- 上位20件の類似画像を保存（閾値: 0.3以上）
- 進捗管理による安定した処理

### 素材ファイル整理
- 更新時に作成された異なる年月フォルダのファイルを、新規登録時の年月フォルダに統合
- 不要なファイルの自動削除
- 空のディレクトリの自動削除
- 1回の実行で1素材分を処理（安全性重視）

### コミュニティ作品のベクトル化
- 承認済みのコミュニティ作品の画像をOpenAI APIでベクトル化
- 作品タイトルを含めた画像説明文の自動生成
- 未処理作品を1件ずつ処理（API制限対応）
- 類似作品検索の基盤データ作成

### コミュニティ作品の類似度計算
- コサイン類似度による作品類似度計算
- 承認済み作品のみを比較対象とする
- 上位20件の類似作品を保存（閾値: 0.3以上）
- 進捗管理による安定した処理
- 循環処理により全作品の類似度を最新に保つ

### 子供の作品用ストーリー生成
- タイトルまたはストーリーがNULLの作品を自動検出
- OpenAI Vision APIによる画像分析とストーリー生成
- ひらがな中心の優しい表現（タイトル10文字以内、ストーリー100文字程度）
- 1回の実行で最大10件を処理
- API制限を考慮した待機時間の設定

### クロス類似度計算（コミュニティ作品 ⇔ 素材）
- コミュニティ作品と素材の間のベクトル類似度を計算
- 承認済みコミュニティ作品と全素材を比較
- 上位20件の類似素材を保存（閾値: 0.7以上）
- ビューで上位8件を取得可能
- 循環処理により定期的に再計算
- 相互表示：作品詳細に関連素材、素材詳細に関連作品

### ミニストーリー生成
- OpenAI GPT-4o-miniによる絵本風ミニストーリー自動生成
- 素材タイトルとカテゴリ情報から50〜100文字の短編作成
- 子供向けの優しい文体とポジティブな内容
- 未生成の素材を1件ずつ処理（API制限対応）
- 詳細ページでの表示によりコンテンツ充実化

## ファイル構成
```
cron/
├── .htaccess                                      # Webアクセス制限
├── generate_structured_images.php                 # 構造化画像生成スクリプト
├── generate_structured_images.sh                  # 構造化画像生成用シェルスクリプト
├── generate_image_embeddings.php                  # 素材画像ベクトル化スクリプト
├── generate_image_embeddings.sh                   # 素材画像ベクトル化用シェルスクリプト
├── calculate_similarities.php                     # 素材類似画像計算スクリプト
├── calculate_similarities.sh                      # 素材類似画像計算用シェルスクリプト
├── generate_community_artwork_embeddings.php      # コミュニティ作品ベクトル化スクリプト
├── generate_community_artwork_embeddings.sh       # コミュニティ作品ベクトル化用シェルスクリプト
├── calculate_community_artwork_similarities.php   # コミュニティ作品類似度計算スクリプト
├── calculate_community_artwork_similarities.sh    # コミュニティ作品類似度計算用シェルスクリプト
├── calculate_cross_similarities.php               # クロス類似度計算スクリプト（作品⇔素材）
├── calculate_cross_similarities.sh                # クロス類似度計算用シェルスクリプト
├── generate_mini_stories.php                      # ミニストーリー生成スクリプト
├── generate_mini_stories.sh                       # ミニストーリー生成用シェルスクリプト
├── cleanup_material_files.php                    # 素材ファイル整理スクリプト
├── cleanup_material_files.sh                     # 素材ファイル整理用シェルスクリプト
└── README.md                                      # このファイル
```

## セットアップ

### 1. データベースの更新
```sql
-- 構造化画像用カラム（database/add_structured_image_columns.sql）
ALTER TABLE materials 
ADD COLUMN structured_image_path VARCHAR(255) DEFAULT NULL,
ADD COLUMN structured_bg_color VARCHAR(7) DEFAULT NULL;

-- 画像ベクトル化用カラム（database/add_image_embedding.sql）
ALTER TABLE materials 
ADD COLUMN image_embedding TEXT DEFAULT NULL,
ADD COLUMN embedding_model VARCHAR(100) DEFAULT NULL,
ADD COLUMN embedding_created_at TIMESTAMP NULL DEFAULT NULL;

-- 類似画像管理用テーブル（database/add_material_similarities.sql）
CREATE TABLE material_similarities (
    material_id INT NOT NULL,
    similar_material_id INT NOT NULL,
    similarity_score DECIMAL(5,4) NOT NULL,
    calculation_method VARCHAR(50) NOT NULL DEFAULT 'cosine_similarity',
    calculated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ...
);

CREATE TABLE similarity_calculation_progress (
    material_id INT NOT NULL,
    status ENUM('pending', 'processing', 'completed', 'error'),
    processed_at TIMESTAMP NULL,
    ...
);

-- コミュニティ作品用テーブル（database/add_community_artwork_embedding.sql）
ALTER TABLE community_artworks
ADD COLUMN image_embedding TEXT DEFAULT NULL,
ADD COLUMN embedding_model VARCHAR(100) DEFAULT NULL,
ADD COLUMN embedding_created_at TIMESTAMP NULL DEFAULT NULL;

CREATE TABLE community_artwork_similarities (
    artwork_id INT NOT NULL,
    similar_artwork_id INT NOT NULL,
    similarity_score DECIMAL(5,4) NOT NULL,
    ...
);

CREATE TABLE community_artwork_similarity_progress (
    artwork_id INT NOT NULL,
    status ENUM('pending', 'processing', 'completed', 'error'),
    ...
);

-- クロス類似度用テーブル（database/add_cross_similarities.sql）
CREATE TABLE community_artwork_material_similarities (
    community_artwork_id INT NOT NULL,
    material_id INT NOT NULL,
    similarity_score DECIMAL(5,4) NOT NULL,
    calculated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (community_artwork_id, material_id),
    ...
);

CREATE TABLE cross_similarity_progress (
    community_artwork_id INT NOT NULL,
    status ENUM('pending', 'processing', 'completed', 'error'),
    processed_at TIMESTAMP NULL,
    ...
);

-- ビュー: 作品→素材の類似度取得（上位8件）
CREATE VIEW community_artwork_related_materials AS ...

-- ビュー: 素材→作品の類似度取得（上位8件）
CREATE VIEW material_related_community_artworks AS ...

-- ミニストーリー用カラム（database/add_mini_story.sql）
ALTER TABLE materials 
ADD COLUMN mini_story TEXT DEFAULT NULL,
ADD COLUMN mini_story_generated_at TIMESTAMP NULL DEFAULT NULL,
ADD COLUMN mini_story_model VARCHAR(100) DEFAULT NULL;
```

### 2. 必要な拡張機能の確認
- PHP GD拡張 (画像処理用)
- cURL拡張 (OpenAI API通信用)

### 3. 環境変数の設定
`.env`ファイルに以下が設定されていることを確認：
```
OPENAI_API_KEY=your_openai_api_key_here
```

## 使用方法

### 手動実行

#### 構造化画像生成（全ての未処理素材を処理）
```bash
cd /path/to/maruttoart/cron
php generate_structured_images.php
```

#### 類似画像計算（1件ずつ処理）
```bash
cd /path/to/maruttoart/cron
php calculate_similarities.php
```

#### 類似画像の取得（PHP関数）
```php
// 類似画像を5件取得（類似度0.5以上）
$similarMaterials = getSimilarMaterials($materialId, 5, 0.5);

// 類似画像があるかチェック
$hasSimilar = hasSimilarMaterials($materialId, 0.5);

// 計算進捗を確認
$progress = getSimilarityCalculationProgress();
```

#### 特定の素材IDのみ処理
```bash
cd /path/to/maruttoart/cron
php generate_structured_images.php 123
```

#### シェルスクリプト経由（ログ付き）
```bash
cd /path/to/maruttoart/cron
./generate_structured_images.sh
```

### cron設定例

#### 推奨: 15分ごとに実行（1件ずつ処理）
```bash
# crontabに追加 - OpenAI APIレート制限に配慮した効率的な処理
*/15 * * * * /path/to/maruttoart/cron/generate_structured_images.sh
```

#### 毎日午前2時に実行（バッチ処理）
```bash
# crontabに追加 - 特定の素材IDを指定する場合や手動実行向け
0 2 * * * /path/to/maruttoart/cron/generate_structured_images.sh
```

#### 6時間ごとに実行
```bash
# crontabに追加 - 中間的な頻度での処理
0 */6 * * * /path/to/maruttoart/cron/generate_structured_images.sh
```

## 処理方式

### 自動処理モード（推奨）
デフォルトでは**1件ずつ処理**するように最適化されています：
- 15分間隔での実行に最適
- OpenAI APIレート制限に配慮
- システム負荷を分散
- 未処理件数の進捗表示

### 処理の流れ
1. 未処理の素材を1件取得（作成日時の新しい順）
2. OpenAI APIで背景色を分析
3. 1200x1200px画像を生成
4. データベースを更新
5. 残り件数を表示

## 出力

### 生成される画像
- パス: `uploads/{year}/{month}/{slug}-structured.png`
- 例: `uploads/2024/08/peach-illustration-structured.png`
- サイズ: 1200x1200px (正方形)
- 形式: PNG (圧縮レベル6)
- 背景: OpenAI分析による適切なペールトーン
- 特徴: イラストに最適な無劣化圧縮で高品質を保持
- 保存場所: 他の素材ファイルと同じディレクトリに統一

### データベース更新
- `structured_image_path`: 生成画像の相対パス
- `structured_bg_color`: 使用された背景色のHEXコード

### ログ出力
- ファイル: `logs/structured_images.log`
- 内容: 処理結果、エラー情報、実行時間

## エラー対応

### よくあるエラー

#### OpenAI APIキーエラー
```
OpenAI APIキーが設定されていません
```
→ `.env`ファイルの`OPENAI_API_KEY`を確認

#### 画像ファイルエラー
```
画像ファイルが見つかりません
```
→ 元画像ファイルの存在とパスを確認

#### GD拡張エラー
```
GD拡張が必要です
```
→ PHP環境にGD拡張をインストール

### ログの確認
```bash
tail -f logs/structured_images.log
```

### Cron設定

#### crontabへの登録例
```bash
# 構造化画像生成（毎日深夜2時に実行）
0 2 * * * /path/to/maruttoart/cron/generate_structured_images.sh

# 素材画像ベクトル化（毎分実行、未処理を1件ずつ）
* * * * * /path/to/maruttoart/cron/generate_image_embeddings.sh

# 素材類似画像計算（5分おきに実行、未処理を1件ずつ）
*/5 * * * * /path/to/maruttoart/cron/calculate_similarities.sh

# コミュニティ作品ベクトル化（毎分実行、未処理を1件ずつ）
* * * * * /path/to/maruttoart/cron/generate_community_artwork_embeddings.sh

# コミュニティ作品類似度計算（5分おきに実行、未処理を1件ずつ）
*/5 * * * * /path/to/maruttoart/cron/calculate_community_artwork_similarities.sh

# クロス類似度計算（作品⇔素材）（5分おきに実行、未処理を1件ずつ）
*/5 * * * * /path/to/maruttoart/cron/calculate_cross_similarities.sh

# ミニストーリー生成（毎分実行、未処理を1件ずつ）
* * * * * /path/to/maruttoart/cron/generate_mini_stories.sh

# 素材ファイル整理（毎日深夜2時30分に実行）
30 2 * * * /path/to/maruttoart/cron/cleanup_material_files.sh
```

#### 設定手順
```bash
# crontabを編集
crontab -e

# 設定したcronジョブを確認
crontab -l

# cronログを確認
tail -f /var/log/cron
tail -f logs/structured_images.log
tail -f logs/image_embedding.log
tail -f logs/similarity_calculation.log
tail -f logs/community_artwork_embeddings.log
tail -f logs/community_artwork_similarity.log
tail -f logs/cross_similarity_calculation.log
tail -f logs/mini_story_generation.log
tail -f logs/cleanup_material_files.log
```

### 4. 素材ファイル整理の実行

#### 手動実行
```bash
cd /path/to/maruttoart/cron
./cleanup_material_files.sh
```

#### cron設定（1日1回、午前2時に実行）
```bash
0 2 * * * /path/to/maruttoart/cron/cleanup_material_files.sh
```

#### ログ確認
```bash
tail -f logs/cleanup_material_files.log
```

### 5. 子供の作品ストーリー生成の実行

#### 手動実行
```bash
cd /path/to/maruttoart/cron
./generate_kids_artwork_stories.sh
```

#### cron設定（5分ごとに実行）
```bash
*/5 * * * * /path/to/maruttoart/cron/generate_kids_artwork_stories.sh
```

#### ログ確認
```bash
tail -f logs/kids_story_generation.log
```

#### 特徴
- アップロード直後の作品は、タイトルとストーリーがNULLの状態で保存
- このスクリプトが定期的に実行され、NULLの作品を検出して生成
- 子供には「おはなしをつくっています」というメッセージが表示される
- 5分ごとに実行することで、ほぼリアルタイムでストーリーが生成される

### 7. みんなのアトリエ作品の説明生成
**スクリプト**: `generate_community_artwork_descriptions.php`, `generate_community_artwork_descriptions.sh`

#### 機能
- 説明が空の作品に対して、AIが自動的にタイトルと説明を生成
- OpenAI Vision APIで画像を分析
- 魅力的なタイトル（20文字以内）を生成
- 作品の特徴を伝える説明文（80〜150文字）を生成
- 「カスタム作品」などのデフォルトタイトルを具体的な内容に変更

#### 実行方法
```bash
cd /path/to/maruttoart/cron
./generate_community_artwork_descriptions.sh
```

#### cron設定（10分ごとに実行）
```bash
*/10 * * * * /path/to/maruttoart/cron/generate_community_artwork_descriptions.sh
```

#### ログ確認
```bash
tail -f logs/community_description_generation.log
```

#### 特徴
- 説明が空の作品を自動検出
- 1回の実行で最大10件を処理（API制限対応）
- WebP画像を優先的に使用（高速化）
- 現在のタイトルを考慮して、より良いタイトルを生成

## セキュリティ
- cronフォルダは`.htaccess`でWebアクセスを完全に制限
- OpenAI API Keyは環境変数で管理
- ロックファイルで重複実行を防止
- スクリプトはコマンドライン実行のみ許可
- APIキーは環境変数で安全に管理

## パフォーマンス
- 大量の画像処理時はメモリ使用量に注意
- OpenAI APIのレート制限に配慮
- 一度に処理する画像数を制限することを推奨
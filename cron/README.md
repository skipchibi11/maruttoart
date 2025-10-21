# 画像処理システム

## 概要
このシステムは、アップロードされた素材画像の処理を自動化します：
1. 構造化データ（JSON-LD、OGP等）用の1200x1200px画像生成
2. 画像のベクトル化（類似検索用）
3. 類似画像の計算・管理

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

## ファイル構成
```
cron/
├── .htaccess                        # Webアクセス制限
├── generate_structured_images.php   # 構造化画像生成スクリプト
├── generate_structured_images.sh    # 構造化画像生成用シェルスクリプト
├── generate_image_embeddings.php    # 画像ベクトル化スクリプト
├── generate_image_embeddings.sh     # 画像ベクトル化用シェルスクリプト
├── calculate_similarities.php       # 類似画像計算スクリプト
├── calculate_similarities.sh        # 類似画像計算用シェルスクリプト
└── README.md                        # このファイル
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

# 画像ベクトル化（毎分実行、未処理を1件ずつ）
* * * * * /path/to/maruttoart/cron/generate_image_embeddings.sh

# 類似画像計算（5分おきに実行、未処理を1件ずつ）
*/5 * * * * /path/to/maruttoart/cron/calculate_similarities.sh
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
```

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
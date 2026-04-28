<?php
require_once __DIR__ . '/../../config.php';
setPublicCache(3600, 7200);

$siteUrl = rtrim(SITE_URL, '/');
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>API ドキュメント | marutto.art</title>
<meta name="description" content="marutto.art の素材APIドキュメント。WordPress・Notionなどから無料イラスト素材を取得できます。">
<meta name="robots" content="noindex">
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
  body {
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
    font-size: 15px;
    line-height: 1.7;
    color: #333;
    background: #f8f9fa;
  }
  a { color: #0070f3; text-decoration: none; }
  a:hover { text-decoration: underline; }

  header {
    background: #fff;
    border-bottom: 1px solid #e5e7eb;
    padding: 1rem 1.5rem;
  }
  header .inner {
    max-width: 900px;
    margin: 0 auto;
    display: flex;
    align-items: center;
    gap: 1rem;
  }
  header .logo {
    font-size: 1.1rem;
    font-weight: 700;
    color: #111;
  }
  header .badge {
    font-size: 0.7rem;
    background: #0070f3;
    color: #fff;
    padding: 2px 8px;
    border-radius: 99px;
    letter-spacing: .05em;
  }

  main {
    max-width: 900px;
    margin: 2.5rem auto;
    padding: 0 1.5rem;
  }

  h1 { font-size: 1.6rem; margin-bottom: .4rem; }
  h2 {
    font-size: 1.15rem;
    font-weight: 700;
    margin: 2.5rem 0 .8rem;
    padding-bottom: .4rem;
    border-bottom: 2px solid #e5e7eb;
  }
  h3 { font-size: 1rem; font-weight: 600; margin: 1.5rem 0 .4rem; }

  p { margin-bottom: .8rem; }

  .lead { font-size: 1rem; color: #555; margin-bottom: 1.5rem; }

  /* エンドポイントカード */
  .endpoint {
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    margin-bottom: 1.5rem;
    overflow: hidden;
  }
  .endpoint-head {
    display: flex;
    align-items: center;
    gap: .75rem;
    padding: .75rem 1rem;
    background: #f9fafb;
    border-bottom: 1px solid #e5e7eb;
  }
  .method {
    font-family: monospace;
    font-size: .8rem;
    font-weight: 700;
    background: #16a34a;
    color: #fff;
    padding: 2px 8px;
    border-radius: 4px;
  }
  .path {
    font-family: monospace;
    font-size: .9rem;
    font-weight: 600;
    color: #111;
  }
  .endpoint-body { padding: 1rem; }
  .endpoint-body p { margin-bottom: .5rem; }

  /* パラメータテーブル */
  table {
    width: 100%;
    border-collapse: collapse;
    font-size: .875rem;
    margin: .75rem 0 1rem;
  }
  th {
    text-align: left;
    background: #f3f4f6;
    padding: .45rem .75rem;
    font-weight: 600;
    border: 1px solid #e5e7eb;
  }
  td {
    padding: .45rem .75rem;
    border: 1px solid #e5e7eb;
    vertical-align: top;
  }
  td code { font-family: monospace; font-size: .85em; }
  .optional { color: #888; font-size: .8em; }

  /* コードブロック */
  pre {
    background: #1e1e2e;
    color: #cdd6f4;
    border-radius: 8px;
    padding: 1rem 1.2rem;
    overflow-x: auto;
    font-family: "SFMono-Regular", Consolas, monospace;
    font-size: .82rem;
    line-height: 1.6;
    margin: .75rem 0 1rem;
  }
  code {
    font-family: "SFMono-Regular", Consolas, monospace;
    font-size: .88em;
    background: #f1f5f9;
    padding: 1px 5px;
    border-radius: 3px;
  }
  pre code { background: transparent; padding: 0; font-size: inherit; }

  /* レートリミット情報ボックス */
  .info-box {
    background: #eff6ff;
    border: 1px solid #bfdbfe;
    border-radius: 8px;
    padding: 1rem 1.2rem;
    margin-bottom: 1.5rem;
  }
  .info-box strong { color: #1d4ed8; }

  /* 言語タブ */
  .tab-group { margin: .75rem 0 1rem; }
  .tab-label {
    display: inline-block;
    font-size: .75rem;
    font-weight: 600;
    background: #374151;
    color: #9ca3af;
    padding: 3px 10px 3px;
    border-radius: 4px 4px 0 0;
    margin-bottom: -1px;
  }

  footer {
    text-align: center;
    padding: 2.5rem 1rem;
    color: #9ca3af;
    font-size: .85rem;
  }
  footer a { color: #9ca3af; }
</style>
</head>
<body>

<header>
  <div class="inner">
    <a class="logo" href="<?= h($siteUrl) ?>/">marutto.art</a>
    <span class="badge">API v1</span>
    <span style="margin-left:auto;font-size:.85rem;color:#888;">無料 · 登録不要 · 商用利用可</span>
  </div>
</header>

<main>

  <h1>素材 API ドキュメント</h1>
  <p class="lead">marutto.art のイラスト素材を、WordPressプラグイン・Notionテンプレート・Webアプリなどから直接取得できる REST API です。</p>

  <div class="info-box">
    <strong>ベースURL：</strong> <code><?= h($siteUrl) ?>/api/v1</code><br>
    <strong>レートリミット：</strong> <strong>1,000 リクエスト / 日</strong>（IPアドレス単位）。超過すると HTTP 429 が返ります。<br>
    <strong>認証：</strong> 不要（APIキーなしで利用可能）<br>
    <strong>フォーマット：</strong> JSON（UTF-8）<br>
    <strong>CORS：</strong> すべてのオリジンを許可
  </div>

  <!-- ===== レートリミットヘッダー ===== -->
  <h2>レートリミットヘッダー</h2>
  <p>すべてのレスポンスに以下のヘッダーが付与されます。</p>
  <table>
    <tr><th>ヘッダー</th><th>説明</th></tr>
    <tr><td><code>X-RateLimit-Limit</code></td><td>1日の上限リクエスト数</td></tr>
    <tr><td><code>X-RateLimit-Remaining</code></td><td>本日の残りリクエスト数</td></tr>
    <tr><td><code>X-RateLimit-Reset</code></td><td>リセット時刻（Unix timestamp）</td></tr>
  </table>

  <!-- ===== エンドポイント一覧 ===== -->
  <h2>エンドポイント</h2>

  <!-- materials list -->
  <div class="endpoint">
    <div class="endpoint-head">
      <span class="method">GET</span>
      <span class="path">/api/v1/materials</span>
    </div>
    <div class="endpoint-body">
      <p>素材の一覧をページネーション付きで返します。</p>
      <h3>クエリパラメータ</h3>
      <table>
        <tr><th>パラメータ</th><th>型</th><th>説明</th></tr>
        <tr><td><code>page</code> <span class="optional">任意</span></td><td>integer</td><td>ページ番号（デフォルト: 1）</td></tr>
        <tr><td><code>per_page</code> <span class="optional">任意</span></td><td>integer</td><td>1ページあたりの件数（デフォルト: 20, 最大: 100）</td></tr>
        <tr><td><code>category</code> <span class="optional">任意</span></td><td>string</td><td>カテゴリのスラッグで絞り込み</td></tr>
      </table>
      <h3>レスポンス例</h3>
      <pre><code>{
  "data": [
    {
      "id": 42,
      "slug": "peach-illustration",
      "title": "桃のイラスト",
      "description": "かわいい桃のイラスト素材です。",
      "category": { "title": "果物", "slug": "fruits" },
      "tags": [{ "id": 3, "name": "水彩", "slug": "watercolor" }],
      "images": {
        "original":    "https://example.r2.dev/items/item_xxx.png",
        "webp_small":  "https://example.r2.dev/items/item_xxx_small.webp",
        "webp_medium": "https://example.r2.dev/items/item_xxx_medium.webp",
        "svg":         null
      },
      "detail_url":  "<?= h($siteUrl) ?>/fruits/peach-illustration/",
      "upload_date": "2024-08-12",
      "created_at":  "2024-08-12 10:00:00"
    }
  ],
  "pagination": {
    "total": 250,
    "per_page": 20,
    "current_page": 1,
    "last_page": 13,
    "next_page_url": "<?= h($siteUrl) ?>/api/v1/materials?page=2&amp;per_page=20",
    "prev_page_url": null
  }
}</code></pre>
    </div>
  </div>

  <!-- material detail -->
  <div class="endpoint">
    <div class="endpoint-head">
      <span class="method">GET</span>
      <span class="path">/api/v1/materials/{slug}</span>
    </div>
    <div class="endpoint-body">
      <p>スラッグを指定して素材の詳細を取得します。</p>
      <h3>パスパラメータ</h3>
      <table>
        <tr><th>パラメータ</th><th>型</th><th>説明</th></tr>
        <tr><td><code>slug</code></td><td>string</td><td>素材のスラッグ（例: <code>peach-illustration</code>）</td></tr>
      </table>
      <h3>レスポンス例</h3>
      <pre><code>{
  "data": {
    "id": 42,
    "slug": "peach-illustration",
    "title": "桃のイラスト",
    ...
  }
}</code></pre>
      <p>素材が存在しない場合は HTTP <strong>404</strong> が返ります。</p>
    </div>
  </div>

  <!-- categories -->
  <div class="endpoint">
    <div class="endpoint-head">
      <span class="method">GET</span>
      <span class="path">/api/v1/categories</span>
    </div>
    <div class="endpoint-body">
      <p>すべてのカテゴリと素材数を返します。</p>
      <h3>レスポンス例</h3>
      <pre><code>{
  "data": [
    {
      "id": 1,
      "title": "果物",
      "slug": "fruits",
      "material_count": 48,
      "url": "<?= h($siteUrl) ?>/fruits/"
    }
  ]
}</code></pre>
    </div>
  </div>

  <!-- search -->
  <div class="endpoint">
    <div class="endpoint-head">
      <span class="method">GET</span>
      <span class="path">/api/v1/search</span>
    </div>
    <div class="endpoint-body">
      <p>タイトル・説明・キーワードを横断してフルテキスト検索します。</p>
      <h3>クエリパラメータ</h3>
      <table>
        <tr><th>パラメータ</th><th>型</th><th>説明</th></tr>
        <tr><td><code>q</code> <strong style="color:#dc2626">必須</strong></td><td>string</td><td>検索キーワード（最大100文字）</td></tr>
        <tr><td><code>page</code> <span class="optional">任意</span></td><td>integer</td><td>ページ番号（デフォルト: 1）</td></tr>
        <tr><td><code>per_page</code> <span class="optional">任意</span></td><td>integer</td><td>1ページあたりの件数（デフォルト: 20, 最大: 100）</td></tr>
      </table>
      <h3>レスポンス例</h3>
      <pre><code>{
  "query": "桃",
  "data": [ ... ],
  "pagination": { ... }
}</code></pre>
    </div>
  </div>

  <!-- ===== エラーレスポンス ===== -->
  <h2>エラーレスポンス</h2>
  <p>エラー時はHTTPステータスとともに以下のJSONを返します。</p>
  <pre><code>{
  "error": {
    "code": "not_found",
    "message": "指定されたslugsの素材が見つかりません。"
  }
}</code></pre>
  <table>
    <tr><th>HTTPステータス</th><th>code</th><th>説明</th></tr>
    <tr><td>400</td><td><code>missing_parameter</code> / <code>invalid_*</code></td><td>リクエストパラメータが不正</td></tr>
    <tr><td>404</td><td><code>not_found</code></td><td>リソースが見つからない</td></tr>
    <tr><td>405</td><td><code>method_not_allowed</code></td><td>GET以外のメソッドを使用</td></tr>
    <tr><td>429</td><td><code>rate_limit_exceeded</code></td><td>レートリミット超過</td></tr>
    <tr><td>500</td><td><code>server_error</code></td><td>サーバー内部エラー</td></tr>
  </table>

  <!-- ===== サンプルコード ===== -->
  <h2>サンプルコード</h2>

  <h3>JavaScript (fetch)</h3>
  <pre><code>// 素材一覧を取得
const res = await fetch('<?= h($siteUrl) ?>/api/v1/materials?per_page=20');
const { data, pagination } = await res.json();
console.log(data[0].title); // "桃のイラスト"

// キーワード検索
const search = await fetch('<?= h($siteUrl) ?>/api/v1/search?q=桃');
const result = await search.json();
console.log(result.query, result.data.length);</code></pre>

  <h3>PHP (WordPress / 一般)</h3>
  <pre><code>// wp_remote_get を使う例（WordPress）
$response = wp_remote_get( '<?= h($siteUrl) ?>/api/v1/materials?per_page=20' );
$body     = wp_remote_retrieve_body( $response );
$json     = json_decode( $body, true );
foreach ( $json['data'] as $item ) {
    echo esc_html( $item['title'] ) . '<br>';
}

// 標準PHPの場合
$json = json_decode( file_get_contents( '<?= h($siteUrl) ?>/api/v1/materials' ), true );
foreach ( $json['data'] as $item ) {
    echo htmlspecialchars( $item['title'] ) . PHP_EOL;
}</code></pre>

  <h3>Python</h3>
  <pre><code>import requests

r = requests.get('<?= h($siteUrl) ?>/api/v1/search', params={'q': '桃', 'per_page': 10})
r.raise_for_status()
for item in r.json()['data']:
    print(item['title'], item['images']['webp_small'])</code></pre>

  <!-- ===== ライセンス ===== -->
  <h2>ライセンス・利用条件</h2>
  <p>APIを通じて取得した素材は <a href="<?= h($siteUrl) ?>/terms-of-use/">利用規約</a> に基づき商用利用が可能です。素材の著作権はmarutto.artに帰属します。再配布・転売は禁止です。</p>

</main>

<footer>
  <a href="<?= h($siteUrl) ?>/">marutto.art</a> &mdash; 無料イラスト素材サイト
</footer>

</body>
</html>

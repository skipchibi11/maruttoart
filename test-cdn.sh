#!/bin/bash

# CDN キャッシュテストスクリプト
echo "=== CDN Cache Test ==="
echo "Testing: ${1:-https://marutto.art}"

URL="${1:-https://marutto.art}"

echo ""
echo "1. Basic Headers Test:"
curl -I "$URL" 2>/dev/null | grep -E "(Cache-Control|ETag|Last-Modified|X-Cache|CF-|Vary)"

echo ""
echo "2. CDN Detection:"
curl -I "$URL" 2>/dev/null | grep -i -E "(cf-ray|x-served-by|x-cache|fastly|cloudflare|x-amz)"

echo ""
echo "3. Cache Test Page:"
curl -s "$URL/cache-test.php" | jq '.cache_headers, .cdn_detection' 2>/dev/null || echo "cache-test.php not found or jq not installed"

echo ""
echo "4. CDN Diagnostic:"
curl -s "$URL/cdn-test.php" | jq '.cache_analysis, .cdn_detection' 2>/dev/null || echo "cdn-test.php not found or jq not installed"

echo ""
echo "=== Test Complete ==="

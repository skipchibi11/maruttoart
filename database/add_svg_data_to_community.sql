-- community_artworksテーブルにSVGデータカラムを追加
ALTER TABLE community_artworks 
ADD COLUMN svg_data LONGTEXT COMMENT 'SVGレイヤーデータ（JSON形式）' AFTER used_material_ids;

-- カレンダーアイテムテーブルに日付選定理由カラムを追加
ALTER TABLE calendar_items 
ADD COLUMN date_reason TEXT COMMENT 'AIによる日付選定理由' AFTER description;

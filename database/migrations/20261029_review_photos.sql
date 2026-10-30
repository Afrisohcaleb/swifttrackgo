ALTER TABLE reviews
    ADD COLUMN reviewer_image_path VARCHAR(255) NULL AFTER review_text;

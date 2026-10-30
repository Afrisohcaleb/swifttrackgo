ALTER TABLE reviews
    ADD COLUMN is_sample TINYINT(1) NOT NULL DEFAULT 0;

UPDATE reviews
SET is_sample = 1
WHERE reviewer_name IN (
    'Sarah Mitchell', 'David Okonkwo', 'Emma Richardson', 'Carlos Mendez',
    'Aisha Patel', 'James Thornton', 'Fatima Al-Hassan', 'Michael O\'Brien',
    'Lin Wei', 'Rachel Foster', 'Jean-Pierre Dubois', 'Grace Nakamura'
);

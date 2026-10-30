CREATE DATABASE IF NOT EXISTS swifttrackgo
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;
USE swifttrackgo;

CREATE TABLE IF NOT EXISTS users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    email VARCHAR(190) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin', 'staff') NOT NULL DEFAULT 'staff',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    failed_login_attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
    locked_until DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS customers (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(160) NOT NULL,
    email VARCHAR(190) NULL,
    telephone VARCHAR(40) NULL,
    address TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_customers_email (email),
    INDEX idx_customers_name (name)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS shipments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tracking_number VARCHAR(40) NOT NULL UNIQUE,
    sender_customer_id BIGINT UNSIGNED NOT NULL,
    recipient_customer_id BIGINT UNSIGNED NOT NULL,
    origin VARCHAR(190) NOT NULL,
    destination VARCHAR(190) NOT NULL,
    service_type VARCHAR(100) NOT NULL,
    current_status VARCHAR(40) NOT NULL,
    estimated_delivery_at DATE NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_shipments_status (current_status),
    INDEX idx_shipments_created (created_at),
    CONSTRAINT fk_shipments_sender FOREIGN KEY (sender_customer_id) REFERENCES customers(id),
    CONSTRAINT fk_shipments_recipient FOREIGN KEY (recipient_customer_id) REFERENCES customers(id),
    CONSTRAINT fk_shipments_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS tracking_events (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    shipment_id BIGINT UNSIGNED NOT NULL,
    status VARCHAR(40) NOT NULL,
    location VARCHAR(190) NOT NULL,
    description TEXT NOT NULL,
    event_at DATETIME NOT NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_tracking_events_shipment_time (shipment_id, event_at, id),
    INDEX idx_tracking_events_status (status),
    CONSTRAINT fk_tracking_events_shipment FOREIGN KEY (shipment_id) REFERENCES shipments(id) ON DELETE CASCADE,
    CONSTRAINT fk_tracking_events_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS services (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(100) NOT NULL UNIQUE,
    name VARCHAR(120) NOT NULL,
    description TEXT NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS site_settings (
    setting_key VARCHAR(100) PRIMARY KEY,
    setting_value TEXT NOT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Default live chat settings
INSERT IGNORE INTO site_settings (setting_key, setting_value) VALUES
    ('livechat_enabled', '1'),
    ('livechat_online', '0');

CREATE TABLE IF NOT EXISTS contact_messages (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(160) NOT NULL,
    email VARCHAR(190) NOT NULL,
    subject VARCHAR(190) NOT NULL,
    message TEXT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_contact_messages_created (created_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS audit_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NULL,
    action VARCHAR(100) NOT NULL,
    record_type VARCHAR(80) NULL,
    record_id BIGINT UNSIGNED NULL,
    ip_address VARCHAR(45) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_audit_logs_user_time (user_id, created_at),
    INDEX idx_audit_logs_record (record_type, record_id),
    CONSTRAINT fk_audit_logs_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Live chat conversations & messages
CREATE TABLE IF NOT EXISTS chat_conversations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    visitor_name VARCHAR(100) NOT NULL,
    visitor_session VARCHAR(64) NOT NULL,
    status ENUM('active', 'closed') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_chat_conversations_status (status),
    INDEX idx_chat_conversations_updated (updated_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS chat_messages (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    conversation_id BIGINT UNSIGNED NOT NULL,
    sender_type ENUM('visitor', 'agent') NOT NULL,
    message TEXT NOT NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_chat_messages_conversation (conversation_id, created_at),
    CONSTRAINT fk_chat_messages_conversation FOREIGN KEY (conversation_id) REFERENCES chat_conversations(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Customer reviews
CREATE TABLE IF NOT EXISTS reviews (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    reviewer_name VARCHAR(120) NOT NULL,
    reviewer_location VARCHAR(120) NOT NULL DEFAULT '',
    rating TINYINT UNSIGNED NOT NULL DEFAULT 5,
    review_text TEXT NOT NULL,
    reviewer_image_path VARCHAR(255) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    is_sample TINYINT(1) NOT NULL DEFAULT 0,
    sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Seed 12 customer reviews
INSERT IGNORE INTO reviews (id, reviewer_name, reviewer_location, rating, review_text, sort_order) VALUES
(1,  'Sarah Mitchell',    'New York, USA',       5, 'SwiftTrackGo made tracking my international shipment incredibly easy. I could see exactly where my package was at every step. Truly impressed with the level of detail in the tracking timeline.',         1),
(2,  'David Okonkwo',     'Lagos, Nigeria',      5, 'As a business owner who ships regularly, this platform has been a game-changer. The real-time updates save me countless phone calls to check on deliveries.',                                               2),
(3,  'Emma Richardson',   'London, UK',          5, 'I was nervous about sending important documents abroad, but the tracking updates put my mind at ease. I knew exactly when they arrived. Great service!',                                                  3),
(4,  'Carlos Mendez',     'Mexico City, Mexico', 4, 'Very reliable tracking system. The estimated delivery dates have been accurate every time I have used the service. Would definitely recommend to fellow business owners.',                                 4),
(5,  'Aisha Patel',       'Mumbai, India',       5, 'The customer support team is exceptional. When I had a query about my shipment, they responded within minutes through the live chat. Outstanding experience.',                                             5),
(6,  'James Thornton',    'Sydney, Australia',   5, 'I have tried several tracking platforms and SwiftTrackGo is by far the most user-friendly. Clean interface, accurate updates, and no unnecessary complexity.',                                             6),
(7,  'Fatima Al-Hassan',  'Dubai, UAE',          4, 'Shipping between countries used to be stressful. Now I just enter my tracking number and get a clear timeline of every milestone. Very well designed.',                                                     7),
(8,  'Michael O\'Brien',  'Dublin, Ireland',     5, 'Fantastic service from start to finish. The tracking page gives you all the information you need without any clutter. I recommend it to all my clients.',                                                   8),
(9,  'Lin Wei',           'Singapore',           5, 'The freight tracking feature is exactly what our warehouse team needed. We can monitor bulk shipments with the same ease as single parcels. Brilliant!',                                                    9),
(10, 'Rachel Foster',     'Toronto, Canada',     4, 'Simple, clean, and effective. I appreciate that I do not need to create an account just to track a package. Enter the number and you are good to go.',                                                     10),
(11, 'Jean-Pierre Dubois','Paris, France',       5, 'Excellent platform for tracking express deliveries. The status updates are timely and the interface is intuitive even for first-time users.',                                                              11),
(12, 'Grace Nakamura',    'Tokyo, Japan',        5, 'We integrated SwiftTrackGo into our e-commerce workflow and our customer complaints about delivery visibility dropped significantly. Highly recommend.',                                                   12);

UPDATE reviews SET is_sample = 1
WHERE reviewer_name IN (
    'Sarah Mitchell', 'David Okonkwo', 'Emma Richardson', 'Carlos Mendez',
    'Aisha Patel', 'James Thornton', 'Fatima Al-Hassan', 'Michael O\'Brien',
    'Lin Wei', 'Rachel Foster', 'Jean-Pierre Dubois', 'Grace Nakamura'
);
USE swifttrackgo;

INSERT IGNORE INTO services (slug, name, description, sort_order) VALUES
    ('domestic-delivery', 'Domestic Delivery', 'Dependable delivery across your local network.', 1),
    ('international-shipping', 'International Shipping', 'Ship across borders with clear progress updates.', 2),
    ('express-delivery', 'Express Delivery', 'A faster delivery option when timing matters.', 3),
    ('freight-services', 'Freight Services', 'Flexible support for larger and heavier shipments.', 4);

INSERT IGNORE INTO site_settings (setting_key, setting_value) VALUES
    ('company_name', 'SwiftTrackGo'),
    ('company_tagline', 'Shipping, without the guesswork'),
    ('company_email', ''),
    ('company_telephone', ''),
    ('company_address', ''),
    ('theme_primary', '#087f8c'),
    ('theme_secondary', '#122d3d'),
    ('theme_accent', '#f1a43c');

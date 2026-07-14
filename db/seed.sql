-- ViscosiGuide — optional demo seed data (food spots around Emmenbrücke / Viscosistrasse).
-- Load after schema.sql:  mysql < db/seed.sql   (or via bin/init-db.php --seed for SQLite)

INSERT INTO food_spots (name, description, category, lat, lng, address, price_level, status) VALUES
  ('Viscosi Kantine',       'Personalrestaurant direkt auf dem Areal. Wechselndes Mittagsmenü.', 'canteen',    47.0808, 8.2735, 'Viscosistrasse 4, 6032 Emmen',     1, 'active'),
  ('Pronto Pizza & Kebab',  'Schnell, günstig, grosse Portionen. Bang-for-the-Buck-Klassiker.', 'fastfood',   47.0791, 8.2762, 'Gerliswilstrasse 40, 6020 Emmenbrücke', 1, 'active'),
  ('Thai Orchidee',         'Frisches Thai-Curry und Wok-Gerichte über Mittag.',                 'asian',      47.0776, 8.2801, 'Rüeggisingerstrasse 20, 6020 Emmenbrücke', 2, 'active'),
  ('Ristorante Da Vinci',   'Italienisch mit Business-Lunch. Etwas gehobener.',                  'italian',    47.0759, 8.2843, 'Seetalstrasse 2, 6020 Emmenbrücke', 3, 'active'),
  ('Bäckerei Hug Beck',     'Sandwiches, Gipfeli und Kaffee für die schnelle Pause.',            'bakery',     47.0815, 8.2748, 'Sprengistrasse 6, 6020 Emmenbrücke', 1, 'active');

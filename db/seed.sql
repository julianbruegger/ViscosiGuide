-- ViscosiGuide — demo seed data: food spots around the Viscosi area (Viscosistrasse /
-- Emmenbrücke). Each spot carries an emoji "logo" and a location link (Google Maps).
-- Load after schema.sql:  mysql < db/seed.sql   (or via bin/init-db.php --seed for SQLite)
--
-- Note: coordinates are approximate and the Google Maps links point at the exact
-- lat/lng, so they resolve even without a curated place URL.

INSERT INTO food_spots (name, description, category, lat, lng, address, logo, location_url, price_level, status) VALUES
  ('Viscosi Kantine',       'Personalrestaurant direkt auf dem Areal. Wechselndes Mittagsmenü.',        'canteen',    47.0808, 8.2735, 'Viscosistrasse 4, 6032 Emmen',            '🍽️', 'https://www.google.com/maps/search/?api=1&query=47.0808,8.2735', 1, 'active'),
  ('Pronto Pizza & Kebab',  'Schnell, günstig, grosse Portionen. Bang-for-the-Buck-Klassiker.',        'fastfood',   47.0791, 8.2762, 'Gerliswilstrasse 40, 6020 Emmenbrücke',   '🥙', 'https://www.google.com/maps/search/?api=1&query=47.0791,8.2762', 1, 'active'),
  ('Thai Orchidee',         'Frisches Thai-Curry und Wok-Gerichte über Mittag.',                       'asian',      47.0776, 8.2801, 'Rüeggisingerstrasse 20, 6020 Emmenbrücke','🍜', 'https://www.google.com/maps/search/?api=1&query=47.0776,8.2801', 2, 'active'),
  ('Ristorante Da Vinci',   'Italienisch mit Business-Lunch. Etwas gehobener.',                        'italian',    47.0759, 8.2843, 'Seetalstrasse 2, 6020 Emmenbrücke',       '🍝', 'https://www.google.com/maps/search/?api=1&query=47.0759,8.2843', 3, 'active'),
  ('Bäckerei Hug Beck',     'Sandwiches, Gipfeli und Kaffee für die schnelle Pause.',                  'bakery',     47.0815, 8.2748, 'Sprengistrasse 6, 6020 Emmenbrücke',      '🥐', 'https://www.google.com/maps/search/?api=1&query=47.0815,8.2748', 1, 'active'),
  ('Emmen Center Food Court','Grosse Auswahl unter einem Dach — von Asia-Box bis Grillteller.',         'fastfood',   47.0834, 8.2717, 'Stauffacherstrasse 1, 6020 Emmenbrücke',  '🍔', 'https://www.google.com/maps/search/?api=1&query=47.0834,8.2717', 1, 'active'),
  ('Migros Restaurant',     'Klassiker fürs schnelle, faire Mittagessen. Salatbuffet inklusive.',      'canteen',    47.0837, 8.2721, 'Emmen Center, 6020 Emmenbrücke',          '🥗', 'https://www.google.com/maps/search/?api=1&query=47.0837,8.2721', 1, 'active'),
  ('Café Rondo',            'Gemütliches Quartier-Café mit hausgemachtem Kuchen und Lunch-Tellern.',    'cafe',       47.0782, 8.2779, 'Rüeggisingerstrasse 2, 6020 Emmenbrücke', '☕', 'https://www.google.com/maps/search/?api=1&query=47.0782,8.2779', 2, 'active'),
  ('Pizzeria Da Salvatore', 'Holzofen-Pizza und Pasta, familiär geführt. Take-away möglich.',          'italian',    47.0801, 8.2810, 'Gerliswilstrasse 78, 6020 Emmenbrücke',   '🍕', 'https://www.google.com/maps/search/?api=1&query=47.0801,8.2810', 2, 'active'),
  ('Sakura Sushi Bar',      'Frisches Sushi und Bowls. Mittags-Sets zum Mitnehmen.',                   'asian',      47.0768, 8.2822, 'Seetalstrasse 20, 6020 Emmenbrücke',      '🍣', 'https://www.google.com/maps/search/?api=1&query=47.0768,8.2822', 2, 'active'),
  ('Kebap House Emmen',     'Döner, Dürüm und Falafel bis spät. Studentenfreundliche Preise.',          'fastfood',   47.0795, 8.2751, 'Gerliswilstrasse 30, 6020 Emmenbrücke',   '🌯', 'https://www.google.com/maps/search/?api=1&query=47.0795,8.2751', 1, 'active'),
  ('Green Bowl Vegi',       'Vegetarisch & vegan: Bowls, Wraps und frische Säfte.',                    'vegetarian', 47.0787, 8.2793, 'Rüeggisingerstrasse 12, 6020 Emmenbrücke','🥦', 'https://www.google.com/maps/search/?api=1&query=47.0787,8.2793', 2, 'active'),
  ('Bierhaus Emmenbrücke',  'Feierabend-Bier, Burger und Flammkuchen. Grosse Terrasse.',               'bar',        47.0771, 8.2836, 'Seetalstrasse 8, 6020 Emmenbrücke',       '🍺', 'https://www.google.com/maps/search/?api=1&query=47.0771,8.2836', 2, 'active');

-- ViscosiGuide — demo seed data: REAL food spots around the Viscosi area
-- (Viscosistadt / Viscosipark and the neighbouring Emmenbrücke centre).
-- Load after schema.sql:  mysql < db/seed.sql   (or via bin/init-db.php --seed for SQLite)
--
-- Data notes:
--  * Names, addresses and websites are real establishments in/around Viscosistadt
--    Emmen (verified July 2026). Where a company website is known it is set, so the
--    brand logo is loaded automatically from its favicon.
--  * Coordinates are approximate (placed within the correct block); the location_url
--    is a Google-Maps search by name, so the "Standort öffnen" link resolves to the
--    exact place regardless of the pin.

INSERT INTO food_spots (name, description, category, lat, lng, address, logo, location_url, website, price_level, status) VALUES
  ('Nylon 7',                       'Die Kantine der IG Arbeit mitten in der Viscosistadt — frisch, günstig, Industrie-Charme. 3 Mittagsmenüs (eines vegi/vegan) + Salatbuffet.', 'canteen',    47.0803, 8.2743, 'Spinnereistrasse 1, 6020 Emmenbrücke',      '🏭', 'https://www.google.com/maps/search/?api=1&query=Nylon%207%20Viscosistadt%20Emmenbr%C3%BCcke',           'igarbeit.ch',                   1, 'active'),
  ('ChäsChalet Heubode',            'Käse-Spezialitäten und Fondue im Viscosipark Emmenweid.',                                        'restaurant', 47.0820, 8.2752, 'Webereistrasse 9, 6020 Emmenbrücke',        '🧀', 'https://www.google.com/maps/search/?api=1&query=Ch%C3%A4sChalet%20Heubode%20Viscosipark%20Emmenbr%C3%BCcke', NULL,                         3, 'active'),
  ('McDonald''s Emmen Center',      'Burger, Pommes & Co. im Emmen Center. 365 Tage geöffnet, grosse Terrasse.',                       'fastfood',   47.0840, 8.2730, 'Stauffacherstrasse 1, 6020 Emmenbrücke',    '🍟', 'https://www.google.com/maps/search/?api=1&query=McDonald%27s%20Emmen%20Center%20Emmenbr%C3%BCcke',      'mcdonalds.ch',                  1, 'active'),
  ('Manora Emmen Center',           'Self-Service-Restaurant im Emmen Center — frisch zubereitet, saisonal.',                          'canteen',    47.0836, 8.2724, 'Stauffacherstrasse 1, 6020 Emmenbrücke',    '🍽️', 'https://www.google.com/maps/search/?api=1&query=Manora%20Emmen%20Center%20Emmenbr%C3%BCcke',           'manor.ch',                      2, 'active'),
  ('TUK-TUK.asia',                  'Asiatische Wok-Küche im Emmen Center — Suppe, Salat, Wok-Gerichte frisch zubereitet.',            'asian',      47.0838, 8.2722, 'Stauffacherstrasse 1, 6020 Emmenbrücke',    '🥡', 'https://www.google.com/maps/search/?api=1&query=TUK-TUK.asia%20Emmen%20Center%20Emmenbr%C3%BCcke',      NULL,                            2, 'active'),
  ('BaBa Oriental',                 'Döner Kebab & orientalisches Soul Food im Emmen Center.',                                         'fastfood',   47.0835, 8.2728, 'Stauffacherstrasse 1, 6020 Emmenbrücke',    '🌯', 'https://www.google.com/maps/search/?api=1&query=BaBa%20Oriental%20Emmen%20Center%20Emmenbr%C3%BCcke',   NULL,                            1, 'active'),
  ('Kelly''s Restaurant & Steakhouse','Steak, Pizza und hausgemachte Pasta an der Gerliswilstrasse.',                                  'restaurant', 47.0808, 8.2758, 'Gerliswilstrasse 74, 6020 Emmenbrücke',     '🥩', 'https://www.google.com/maps/search/?api=1&query=Kelly%27s%20Restaurant%20Steakhouse%20Emmenbr%C3%BCcke','kellysrestaurant.ch',           3, 'active'),
  ('Tramhüsli Bistro & Bar',        'Bistro & Bar im denkmalgeschützten alten Tramhäuschen an der Gerliswilstrasse, grosse Terrasse.', 'bar',        47.0790, 8.2772, 'Gerliswilstrasse, 6020 Emmenbrücke',        '🚋', 'https://www.google.com/maps/search/?api=1&query=Tramh%C3%BCsli%20Bistro%20Bar%20Emmenbr%C3%BCcke',      'tramhuesli.ch',                 2, 'active'),
  ('Hans im Glück (Emmen 4Viertel)','Burgergrill mit grosser vegetarischer & veganer Auswahl im Quartier 4Viertel.',                  'restaurant', 47.0772, 8.2788, 'Gerliswilstrasse 13a, 6020 Emmen',          '🍔', 'https://www.google.com/maps/search/?api=1&query=Hans%20im%20Gl%C3%BCck%20Emmen%204Viertel',             'hansimglueck-burgergrill.ch',   2, 'active'),
  ('Bistro Limette',                'Frühstück, Kaffee & Kuchen, Salate und Tagesmenüs (Fleisch, Fisch, vegi) an der Gerliswilstrasse.','cafe',      47.0800, 8.2762, 'Gerliswilstrasse 63, 6020 Emmenbrücke',     '🍋', 'https://www.google.com/maps/search/?api=1&query=Bistro%20Limette%20Gerliswilstrasse%20Emmenbr%C3%BCcke', NULL,                           2, 'active');

-- Insert parent character album for Runa
INSERT INTO chv_albums (album_name, album_user_id, album_date, album_date_gmt, album_creation_ip)
VALUES ('Runa', 7, NOW(), NOW(), '127.0.0.1');

-- Get the newly inserted parent album ID and insert its child subalbums
SET @runa_id = LAST_INSERT_ID();

INSERT INTO chv_albums (album_name, album_user_id, album_parent_id, album_date, album_date_gmt, album_creation_ip) VALUES
('Portrait', 7, @runa_id, NOW(), NOW(), '127.0.0.1'),
('Square', 7, @runa_id, NOW(), NOW(), '127.0.0.1'),
('Secondary Square', 7, @runa_id, NOW(), NOW(), '127.0.0.1'),
('Rectangle/Banner', 7, @runa_id, NOW(), NOW(), '127.0.0.1'),
('Avatar URL', 7, @runa_id, NOW(), NOW(), '127.0.0.1');

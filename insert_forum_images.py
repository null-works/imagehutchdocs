import csv
import re

csv_path = r"c:\Users\kylem\repos\jcink_watcher\forum_row_imagehut_to_local_map.csv"
sql_lines = []

with open(csv_path, newline='', encoding='utf-8') as f:
    reader = csv.DictReader(f)
    for row in reader:
        original_url = row['original_url']
        local_filename = row['local_filename']

        # Extract name and extension
        base_name, ext = local_filename.rsplit('.', 1)
        
        # Extract width and height from name (e.g. 340x245)
        m = re.search(r'(\d+)x(\d+)', base_name)
        if m:
            w, h = m.groups()
        else:
            w, h = '340', '245'

        # Extract date from original_url
        parts = original_url.split('/')
        if len(parts) >= 8:
            date_str = f"{parts[4]}-{parts[5]}-{parts[6]} 12:00:00"
        else:
            date_str = "2026-02-21 12:00:00"

        # Construct single insert statement
        sql = f"""
        INSERT INTO chv_images 
        (image_name, image_extension, image_size, image_width, image_height, image_date, image_date_gmt, image_nsfw, image_user_id, image_album_id, image_uploader_ip, image_storage_mode, image_storage_id, image_checksum, image_original_filename, image_chain, image_thumb_size, image_medium_size, image_frame_size)
        VALUES 
        ('{base_name}', '{ext}', 50000, {w}, {h}, '{date_str}', '{date_str}', 0, 10, 233511, '127.0.0.1', 'datefolder', 1, '000', '{local_filename}', 7, 0, 0, 0);
        """
        sql_lines.append(sql.strip())

sql_lines.append("""
UPDATE chv_albums SET album_image_count = (SELECT COUNT(*) FROM chv_images WHERE image_album_id = 233511) WHERE album_id = 233511;
""")

output_sql = r"c:\Users\kylem\repos\imagehutchdocs\insert_forum_images.sql"
with open(output_sql, 'w', encoding='utf-8') as f:
    f.write('\n'.join(sql_lines))

print(f"SQL file recreated successfully: {output_sql}")

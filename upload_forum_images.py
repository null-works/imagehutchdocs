import csv
import subprocess
import os

csv_path = r"c:\Users\kylem\repos\jcink_watcher\forum_row_imagehut_to_local_map.csv"

with open(csv_path, newline='', encoding='utf-8') as f:
    reader = csv.DictReader(f)
    for row in reader:
        original_url = row['original_url']
        local_path = row['local_full_path']
        local_filename = row['local_filename']

        # Extract date from URL like: https://imagehut.ch/images/2026/03/12/announcements.png
        # The split gives ['https:', '', 'imagehut.ch', 'images', '2026', '03', '12', 'announcements.png']
        parts = original_url.split('/')
        if len(parts) >= 8:
            year = parts[4]
            month = parts[5]
            day = parts[6]
            dest = f"/var/lib/docker/volumes/chevereto_app/_data/images/{year}/{month}/{day}/{local_filename}"

            print(f"Uploading {local_path} to {dest}...")
            cmd = f'scp -o StrictHostKeyChecking=no "{local_path}" root@inklit.ch:"{dest}"'
            subprocess.run(cmd, shell=True, check=True)

print("All files successfully uploaded to their original target directories.")

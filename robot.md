# ImageHut Docs - Knowledge & Learnings (robot.md)

This document contains all technical discoveries, context, and system setup notes accumulated during the image restoration and database reconstruction task for ImageHut.

---

## 1. Environment & Infrastructure

### File Locations
- **Local Git Repository**: `c:\Users\kylem\repos\imagehutchdocs`
- **Server**: `root@inklit.ch`
- **Docker App Path**: `/var/lib/docker/volumes/chevereto_app/_data/`
- **Docker Volume Path for Database**: `/var/lib/docker/volumes/chevereto_database/_data/`

### Databases and Credentials
- **MariaDB Instance**: `chevereto-database-1`
- **Credentials**: 
  - **User**: `chevereto`
  - **Password**: `chevereto_secure_password_456`
  - **Database**: `chevereto`

### Custom Server Additions
- **Assign Images Applet (Offlined)**: Remotely served from `/var/lib/docker/volumes/chevereto_app/_data/assign_images.php` to assign unmapped admin images to character sub-albums.
- **Randomizer Routing Redirect (`i/index.php`)**: Deployed directly at `/var/lib/docker/volumes/chevereto_app/_data/i/index.php` to map old randomizer encoded IDs (`YzJ`, `YKd`, etc.) to correct sub-albums.

---

## 2. Technical Learnings

### Sub-album Hierarchy for Characters
Every character has a root/parent album with `album_parent_id = NULL`. Standard child sub-albums have `album_parent_id = [Parent ID]` and include:
- `Portrait`
- `Rectangle/Banner`
- `Secondary Square`
- `Square`
- `Tertiary Square`

### Randomizer String Mapping & Decoding
Old encoded IDs used for randomizer links map to specific characters and sub-albums:

| Old Encoded ID | Character / Field | Target Album ID |
|---|---|---|
| `AOk` | Sen Wu Portrait | 162937 |
| `xHT` | Alison Blaire Portrait | 131516 |
| `YzJ` | Izaiah Carter Portrait | 59571 |
| `YKd` | Izaiah Carter Square | 60511 |
| `csr` | Kimberly Parson Portrait | 61648 |
| `wSC` | Kimberly Parson Square | 45885 |
| `beZ` | Kimberly Parson Secondary Square | 200416 |
| `2gM` | Kimberly Parson Rectangle/Banner | 49123 |
| `AGp` | Sharon Davis Portrait | 162861 |
| `A2e` | Sharon Davis Square | 162200 |
| `gry` | Sharon Davis Secondary Square | 185781 |
| `A3v` | Sharon Davis Rectangle/Banner | 162800 |
| `zmN` | William Kaplan Portrait | 119024 |
| `xoK` | Logan Portrait | 134461 |
| `ujY` | Juliet Hawkins Portrait | 13593 |
| `UMS` | Katherine Murphy Portrait | 139620 |
| `oFb` | Nirav Chaudhari Portrait | 233420 |
| `zbG` | Tommy Shepherd Portrait | 118566 |

### Encoding/Decoding Setup
The database variable `crypt_salt` is configured as `751b13d3` and `id_padding` is `999`.

---

## 3. Git Management & Syncing
Always sync code changes back to the remote repository `https://github.com/null-works/imagehutchdocs.git` on the `main` branch to ensure work is backed up.

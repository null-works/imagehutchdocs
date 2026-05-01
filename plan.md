# ImageHut Migration & Restoration Plan

This document tracks our work for setting up and restoring the ImageHut infrastructure.

## Phase 1: Install Chevereto Free
1. Download the latest Chevereto Free release package from GitHub (`https://github.com/chevereto/chevereto/releases`).
2. Extract the package contents to the target website directory (`public_html` or equivalent).
3. Complete the setup process (via HTTP by visiting the website URL or via CLI).

## Phase 2: Upgrade Free to Pro
1. Navigate to the Chevereto `/dashboard`.
2. Click on the **License key** button.
3. Enter the valid Pro license key and click **Save changes**.
4. Follow the on-screen upgrade instructions to seamlessly upgrade the Free edition to Pro.

## Phase 3: Install Randomizer Module
Based on the provided Chevereto V4 integration by Rodolfo, we will implement the randomizer using native overrides to ensure upgrade compatibility.

1. **Add Route Overrides (`app/legacy/routes/overrides/i.php`):**
   Implement the random image fetch logic that decodes the album ID, queries the database for a random image, and redirects the user to the image URL.
   
2. **Add Modal UI (`content/legacy/themes/Peafowl/overrides/snippets/modal_random.php`):**
   Create the randomizer modal that provides users with the copyable randomizer link (`/randomizer/<encoded_id>.gif`).

3. **Add Button to Album View (`content/legacy/themes/Peafowl/overrides/views/album.php`):**
   Add the randomizer button next to the existing action buttons (like Share and Edit) in the album header.

4. **Update `.htaccess`:**
   Add a rewrite rule for the randomizer links:
   `RewriteRule randomizer/(.+)\.gif$ i/?album=$1 [R=301,L]`

## Phase 4: Database Restoration & Migration
Once the infrastructure is up and running, we will perform the database fragment restoration:
1. Extract the map of `imagehut.ch` URLs from the JCink crawler database.
2. Cross-reference the image links with the list of users to map them back to their character/player owners.
3. Validate the URLs against the map of fully intact R2 storage to ensure images are successfully mapped.
4. Inject these user-image associations back into the new Chevereto database to restore ownership.

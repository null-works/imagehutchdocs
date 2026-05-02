$sourceDir = "C:\Users\kylem\Downloads\TWAI BANNER IMAGES"
$existingBaseNames = @(
    "agents-of-shield-season-7_2560x1440_xtrafondos.com",
    "black-panther-2020_2560x1440_xtrafondos.com",
    "black-widow-fan-art-minimalist_2560x1440_xtrafondos.com",
    "captain-america-s-shield-with-city_2560x1440_xtrafondos.com",
    "captain-america-vs-winter-solider-in-marvel-rivals_2560x1440_xtrafondos.com",
    "cyberpunk-captain-america-fanart_2560x1440_xtrafondos.com",
    "fantastic-4-first-steps-herbie-2k-wallpaper-uhdpaper.com-7855g",
    "hulk-molten_2560x1440_xtrafondos.com",
    "iron-man-2020-art_2560x1440_xtrafondos.com",
    "magneto-2020_2560x1440_xtrafondos.com",
    "marvel-rivals-game_2560x1440_xtrafondos.com",
    "miles-morales-in-spider-man-into-the-spider-verse_2560x1440_xtrafondos.com",
    "moon-knight_2560x1440_xtrafondos.com",
    "mvp_1901",
    "rocket-in-marvel-rivals_2560x1440_xtrafondos.com",
    "shadows-of-the-avengers_2560x1440_xtrafondos.com",
    "spiderman-upside-down_2560x1440_xtrafondos.com",
    "star-lord-from-guardians-of-the-galaxy_2560x1440_xtrafondos.com",
    "thunder-thor_2560x1440_xtrafondos.com"
)

$apiKey = "c12f765938b3b996dcd03ffb71b2e33f662aae5b38186cdeb7e7f6214f90463d"
$albumId = "oKO"

Get-ChildItem $sourceDir -File | ForEach-Object {
    $baseName = $_.BaseName
    if ($existingBaseNames -contains $baseName) {
        Write-Host "Skipping duplicate: $baseName"
    } else {
        Write-Host "Uploading: $_.Name"
        $fileBytes = [System.IO.File]::ReadAllBytes($_.FullName)
        $base64 = [System.Convert]::ToBase64String($fileBytes)
        
        $body = @{
            key = $apiKey
            action = "upload"
            source = $base64
            album = $albumId
        }
        
        try {
            $response = Invoke-RestMethod -Uri "https://imagehut.ch/api/1/upload" -Method Post -Body $body -ErrorAction Stop
            Write-Host "Uploaded successfully!"
        } catch {
            Write-Host "Failed to upload: $_.Name. $_"
        }
    }
}

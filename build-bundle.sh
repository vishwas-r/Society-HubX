#!/usr/bin/env bash
# Bash script to build the Society GoVernX Webapp Bundle
# Downloads WordPress core, installs the plugin, adds setup.php, and compresses it.

set -e

# Resolve execution workspace path
WORKSPACE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ZIP_URL="https://wordpress.org/latest.zip"
ZIP_FILE="$WORKSPACE/wordpress-temp.zip"
DIST_DIR="$WORKSPACE/dist"
OUT_ZIP="$WORKSPACE/society-nestx-webapp.zip"

echo "=============================================="
echo "  SocietyNestX - Webapp Packager (Bash)  "
echo "=============================================="

# 1. Clean up old build files
if [ -f "$ZIP_FILE" ]; then
    echo "[1/7] Cleaning old temp zip..."
    rm -f "$ZIP_FILE"
fi
if [ -d "$DIST_DIR" ]; then
    echo "[1/7] Cleaning old dist folder..."
    rm -rf "$DIST_DIR"
fi
if [ -f "$OUT_ZIP" ]; then
    echo "[1/7] Cleaning old webapp bundle zip..."
    rm -f "$OUT_ZIP"
fi

# 2. Download WordPress Core
echo "[2/7] Downloading WordPress Core (latest.zip)..."
if command -v curl >/dev/null 2>&1; then
    curl -L -o "$ZIP_FILE" "$ZIP_URL"
elif command -v wget >/dev/null 2>&1; then
    wget -O "$ZIP_FILE" "$ZIP_URL"
else
    echo "Error: Neither curl nor wget is installed. Cannot download WordPress." >&2
    exit 1
fi

# 3. Extract WordPress Core
echo "[3/7] Extracting WordPress Core..."
if command -v unzip >/dev/null 2>&1; then
    unzip -q "$ZIP_FILE" -d "$WORKSPACE"
else
    echo "Error: unzip command not found. Cannot extract WordPress." >&2
    exit 1
fi

# Rename extracted folder to dist
mv "$WORKSPACE/wordpress" "$DIST_DIR"
sleep 1

# 4. Create plugin directory structure
echo "[4/7] Creating plugin folder structure..."
PLUGIN_DIR="$DIST_DIR/wp-content/plugins/society-nestx"
mkdir -p "$PLUGIN_DIR"

# 5. Copy plugin assets & files from src/
echo "[5/7] Copying plugin code & assets from src..."
cp -r "$WORKSPACE/src/"* "$PLUGIN_DIR/"

# 6. Copy setup.php to root
echo "[6/7] Copying setup.php wizard to root..."
cp "$WORKSPACE/setup.php" "$DIST_DIR/setup.php"

# 7. Package everything into webapp zip
echo "[7/7] Packaging into society-nestx-webapp.zip..."
if command -v zip >/dev/null 2>&1; then
    (cd "$DIST_DIR" && zip -rq "$OUT_ZIP" .)
else
    echo "Error: zip command not found. Cannot package files." >&2
    exit 1
fi

# 8. Clean up temp files
echo "Cleaning up temporary files..."
rm -f "$ZIP_FILE"
rm -rf "$DIST_DIR"

echo "=============================================="
echo "  Success! Webapp bundle created at:  "
echo "  $OUT_ZIP"
echo "=============================================="

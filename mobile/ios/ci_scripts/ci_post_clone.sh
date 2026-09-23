#!/bin/sh

# Fail this script if any subcommand fails
set -e

echo "=== Starting Xcode Cloud Post-Clone Script ==="

# Navigate to the Flutter project directory
if [ -n "$CI_PRIMARY_REPOSITORY_PATH" ]; then
    cd "$CI_PRIMARY_REPOSITORY_PATH/mobile"
else
    SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
    if [ -d "$SCRIPT_DIR/../../mobile" ]; then
        cd "$SCRIPT_DIR/../../mobile"
    elif [ -d "$SCRIPT_DIR/../mobile" ]; then
        cd "$SCRIPT_DIR/../mobile"
    else
        cd "$SCRIPT_DIR/.."
    fi
fi

# 1. Install Flutter SDK
echo "Installing Flutter SDK (stable)..."
git clone https://github.com/flutter/flutter.git --depth 1 -b stable $HOME/flutter
export PATH="$PATH:$HOME/flutter/bin"

flutter --version

# 2. Precache iOS engine artifacts
echo "Precaching iOS artifacts..."
flutter precache --ios

# 3. Fetch dependencies
echo "Running flutter pub get..."
flutter pub get

# 4. Install CocoaPods and generate iOS configurations
echo "Installing CocoaPods..."
HOMEBREW_NO_AUTO_UPDATE=1 brew install cocoapods

echo "Preparing iOS build configuration and Swift packages..."
flutter build ios --config-only --no-codesign

cd ios
echo "Running pod install..."
pod install

echo "=== Xcode Cloud Post-Clone Script Completed Successfully ==="

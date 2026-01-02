#!/bin/bash
# Install Couchbase PHP SDK
# Usage: ./install-sdk.sh

set -e

echo "Installing Couchbase PHP SDK..."

# Detect OS
if [ -f /etc/debian_version ]; then
    echo "Detected Debian/Ubuntu"
    
    # Install libcouchbase
    sudo apt-get update
    sudo apt-get install -y build-essential cmake libssl-dev
    
    # Add Couchbase repository
    wget -O - https://packages.couchbase.com/clients/c/repos/deb/couchbase.key | sudo apt-key add -
    echo "deb https://packages.couchbase.com/clients/c/repos/deb/ubuntu2004 focal focal/main" | sudo tee /etc/apt/sources.list.d/couchbase.list
    
    sudo apt-get update
    sudo apt-get install -y libcouchbase3 libcouchbase-dev libcouchbase3-tools
    
    # Install PHP extension via PECL
    sudo pecl install couchbase
    
    # Enable extension
    PHP_VERSION=$(php -v | head -1 | cut -d' ' -f2 | cut -d'.' -f1,2)
    echo "extension=couchbase.so" | sudo tee /etc/php/${PHP_VERSION}/mods-available/couchbase.ini
    sudo phpenmod couchbase
    
    # Restart PHP-FPM if running
    sudo systemctl restart php${PHP_VERSION}-fpm 2>/dev/null || true
    
elif [ -f /etc/redhat-release ]; then
    echo "Detected RHEL/CentOS"
    
    # Install libcouchbase
    sudo yum install -y epel-release
    sudo yum install -y libcouchbase3 libcouchbase-devel
    
    # Install PHP extension
    sudo pecl install couchbase
    
    echo "extension=couchbase.so" | sudo tee /etc/php.d/couchbase.ini
    
    sudo systemctl restart php-fpm 2>/dev/null || true
    
elif [[ "$OSTYPE" == "darwin"* ]]; then
    echo "Detected macOS"
    
    # Use Homebrew
    brew install libcouchbase
    pecl install couchbase
    
    # Add to php.ini
    PHP_INI=$(php --ini | grep "Loaded Configuration File" | cut -d':' -f2 | tr -d ' ')
    if ! grep -q "extension=couchbase.so" "$PHP_INI"; then
        echo "extension=couchbase.so" >> "$PHP_INI"
    fi
else
    echo "ERROR: Unsupported operating system"
    exit 1
fi

# Verify installation
echo ""
echo "Verifying installation..."
php -m | grep -i couchbase

if [ $? -eq 0 ]; then
    echo ""
    echo "SUCCESS: Couchbase PHP SDK installed successfully!"
    php -r "echo 'Couchbase SDK Version: ' . phpversion('couchbase') . PHP_EOL;"
else
    echo "ERROR: Couchbase extension not loaded"
    exit 1
fi

#!/bin/bash

# Test script for the delete operation name rule page

BASE_URL="http://localhost:7777"
COOKIES_FILE="/tmp/test_cookies.txt"

echo "Testing Delete Operation Name Rule Page"
echo "========================================"

# Step 1: Login to get a session
echo ""
echo "Step 1: Logging in..."
curl -s -c "$COOKIES_FILE" \
  -d "institution_id=1&username=admin&password=admin" \
  "$BASE_URL/site/login" > /dev/null

if [ $? -eq 0 ]; then
    echo "✓ Login successful (session saved)"
else
    echo "✗ Login failed"
    exit 1
fi

# Step 2: Navigate to the list page to find a valid ID
echo ""
echo "Step 2: Checking for existing operation name rules..."
RESPONSE=$(curl -s -b "$COOKIES_FILE" "$BASE_URL/OphTrOperationbooking/admin/viewOperationNameRules")

# Check if page loaded
if echo "$RESPONSE" | grep -q "Operation name rules"; then
    echo "✓ List page loaded successfully"
    
    # Try to extract an ID from the page
    ID=$(echo "$RESPONSE" | grep -o 'data-attr-id="[0-9]*"' | head -1 | grep -o '[0-9]*')
    
    if [ -z "$ID" ]; then
        echo "  No existing rules found, will try ID 1 for testing"
        ID=1
    else
        echo "  Found rule with ID: $ID"
    fi
else
    echo "✗ Failed to load list page"
    exit 1
fi

# Step 3: Test the delete page
echo ""
echo "Step 3: Testing delete page for ID: $ID..."
DELETE_RESPONSE=$(curl -s -b "$COOKIES_FILE" -w "\n%{http_code}" "$BASE_URL/OphTrOperationbooking/admin/deleteOperationNameRule/$ID")

# Extract HTTP status code
HTTP_CODE=$(echo "$DELETE_RESPONSE" | tail -n 1)
PAGE_CONTENT=$(echo "$DELETE_RESPONSE" | head -n -1)

echo "  HTTP Status Code: $HTTP_CODE"

if [ "$HTTP_CODE" = "200" ]; then
    echo "✓ Page loaded successfully (HTTP 200)"
    
    # Check for expected content
    if echo "$PAGE_CONTENT" | grep -q "Delete operation name rule"; then
        echo "✓ Delete confirmation heading found"
    else
        echo "! Warning: Delete confirmation heading not found"
    fi
    
    if echo "$PAGE_CONTENT" | grep -q "onr_deleteform"; then
        echo "✓ Delete form found"
    else
        echo "! Warning: Delete form not found"
    fi
    
    if echo "$PAGE_CONTENT" | grep -q "et_delete\|et_cancel"; then
        echo "✓ Delete/Cancel buttons found"
    else
        echo "! Warning: Delete/Cancel buttons not found"
    fi
else
    if [ "$HTTP_CODE" = "404" ]; then
        echo "✗ Page not found (HTTP 404) - This could indicate the action is not properly registered"
    elif [ "$HTTP_CODE" = "500" ]; then
        echo "✗ Internal Server Error (HTTP 500)"
        echo "  Response:"
        echo "$PAGE_CONTENT" | head -20
    else
        echo "✗ Unexpected HTTP status code: $HTTP_CODE"
    fi
fi

# Step 4: Test navigation to the delete page from the list
echo ""
echo "Step 4: Testing if list page links to delete page..."
if echo "$RESPONSE" | grep -q "/deleteOperationNameRule/"; then
    echo "✓ Delete links found in list page"
else
    echo "! Warning: Delete links not found in list page (might be expected if no rules exist)"
fi

# Cleanup
rm -f "$COOKIES_FILE"
echo ""
echo "Test complete!"

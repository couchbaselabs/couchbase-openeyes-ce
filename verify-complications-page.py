#!/usr/bin/env python3
"""
Verify the /procedure/complications page
"""
import urllib.request
import urllib.parse
import http.cookiejar
import sys
import re
import json

def test_complications_page():
    url_base = 'http://localhost:7777'
    
    # Create a cookie jar and opener
    cookie_jar = http.cookiejar.CookieJar()
    opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(cookie_jar))
    urllib.request.install_opener(opener)
    
    # Step 1: Try to access the page first without login
    print("=" * 60)
    print("STEP 1: Testing page access without authentication")
    print("=" * 60)
    try:
        response = opener.open(f'{url_base}/procedure/complications')
        body = response.read().decode('utf-8')
        if 'OpenEyes Main - Login' in body:
            print("✓ Page correctly redirects to login (unauthenticated request)")
        else:
            print("✗ Unexpected response (not redirected to login)")
    except urllib.error.HTTPError as e:
        if e.code == 302 or e.code == 200:
            print(f"✓ Expected redirection (status {e.code})")
        else:
            print(f"✗ Unexpected HTTP error: {e.code}")
    
    # Step 2: Login
    print("\n" + "=" * 60)
    print("STEP 2: Authenticating")
    print("=" * 60)
    login_url = f'{url_base}/site/login'
    login_data = urllib.parse.urlencode({
        'User[username]': 'admin',
        'User[password]': 'admin'
    }).encode('utf-8')
    
    try:
        response = opener.open(login_url, login_data)
        body = response.read().decode('utf-8')
        print(f"✓ Login request completed (status {response.status})")
        
        # Check cookies
        print("\nCookies after login:")
        for cookie in cookie_jar:
            print(f"  - {cookie.name}: {cookie.value[:20]}...")
    except Exception as e:
        print(f"✗ Login failed: {e}")
        return False
    
    # Step 3: Test /procedure/complications without ID
    print("\n" + "=" * 60)
    print("STEP 3: Testing /procedure/complications (no ID)")
    print("=" * 60)
    try:
        response = opener.open(f'{url_base}/procedure/complications')
        body = response.read().decode('utf-8')
        headers = dict(response.headers)
        
        print(f"HTTP Status: {response.status}")
        print(f"Content-Type: {headers.get('Content-Type', 'unknown')}")
        print(f"Response length: {len(body)} bytes")
        
        # Check what was returned
        if 'OpenEyes Main - Login' in body:
            print("✗ FAILED: Page returned login page (not authenticated)")
        elif response.status == 200:
            # Parse response based on content type
            if 'application/json' in headers.get('Content-Type', ''):
                try:
                    data = json.loads(body)
                    print(f"✓ SUCCESS: Returned valid JSON: {data}")
                except:
                    print(f"✗ FAILED: Invalid JSON response")
            else:
                print(f"✗ FAILED: Returned HTML instead of JSON")
                # Show first part of response
                if 'CHttpException' in body:
                    print("    Contains: CHttpException")
                if 'Missing required parameter' in body:
                    print("    Contains: Missing required parameter")
                if body.strip() == '[]':
                    print("✓ SUCCESS: Returned empty JSON array")
        else:
            print(f"✗ FAILED: Unexpected status {response.status}")
    except urllib.error.HTTPError as e:
        print(f"HTTP Error {e.code}: {e.reason}")
        body = e.read().decode('utf-8')
        print(f"Response: {body[:200]}")
    except Exception as e:
        print(f"Error: {e}")
        import traceback
        traceback.print_exc()
    
    # Step 4: Test with procedure ID
    print("\n" + "=" * 60)
    print("STEP 4: Testing /procedure/complications/1 (with ID)")
    print("=" * 60)
    try:
        response = opener.open(f'{url_base}/procedure/complications/1')
        body = response.read().decode('utf-8')
        headers = dict(response.headers)
        
        print(f"HTTP Status: {response.status}")
        print(f"Content-Type: {headers.get('Content-Type', 'unknown')}")
        print(f"Response length: {len(body)} bytes")
        
        if response.status == 200:
            # Parse response
            if 'application/json' in headers.get('Content-Type', '') or body.strip().startswith('['):
                try:
                    data = json.loads(body)
                    print(f"✓ SUCCESS: Valid JSON response")
                    if isinstance(data, list):
                        print(f"    Array with {len(data)} items")
                        if data:
                            print(f"    Sample: {data[0] if len(data) > 0 else 'empty'}")
                    else:
                        print(f"    Object: {data}")
                except:
                    print(f"✗ FAILED: Invalid JSON")
            else:
                print(f"✗ FAILED: Not JSON response")
        else:
            print(f"✗ FAILED: Status {response.status}")
    except Exception as e:
        print(f"Error: {e}")
    
    # Step 5: Test with query parameter
    print("\n" + "=" * 60)
    print("STEP 5: Testing /procedure/complications?id=1 (query param)")
    print("=" * 60)
    try:
        response = opener.open(f'{url_base}/procedure/complications?id=1')
        body = response.read().decode('utf-8')
        headers = dict(response.headers)
        
        print(f"HTTP Status: {response.status}")
        print(f"Response length: {len(body)} bytes")
        
        if body.strip().startswith('['):
            try:
                data = json.loads(body)
                print(f"✓ Query param works: JSON array with {len(data)} items")
            except:
                print(f"✗ Invalid JSON from query param")
        else:
            print(f"Response: {body[:100]}")
    except Exception as e:
        print(f"Error: {e}")

if __name__ == '__main__':
    test_complications_page()

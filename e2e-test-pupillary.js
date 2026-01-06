const { chromium } = require('playwright');

const TEST_NAME = 'E2E Test OphCiExamination_PupillaryAbnormalities_Abnormality 1767359433';
const PRIMARY_USER = 'droid_test_18';
const PRIMARY_PASS = 'Droid@Test123!';
const FALLBACK_USER = 'admin';
const FALLBACK_PASS = 'admin';

async function runTest() {
    const browser = await chromium.launch({ headless: true });
    const context = await browser.newContext();
    const page = await context.newPage();
    
    let issues = [];
    let loginSuccess = false;
    
    try {
        // Navigate to login page
        console.log('Navigating to http://localhost:7777...');
        await page.goto('http://localhost:7777', { timeout: 30000 });
        await page.waitForTimeout(2000);
        
        // Try primary credentials first
        console.log(`Attempting login with ${PRIMARY_USER}...`);
        try {
            await page.fill('input[name="LoginForm[username]"]', PRIMARY_USER, { timeout: 5000 });
            await page.fill('input[name="LoginForm[password]"]', PRIMARY_PASS);
            await page.click('button[type="submit"], input[type="submit"], button:has-text("Login"), #login-button', { timeout: 5000 });
            await page.waitForTimeout(3000);
            
            // Check if login succeeded
            const errorVisible = await page.locator('.alert-error, .error-message, .flash-error').isVisible().catch(() => false);
            if (errorVisible || await page.url().includes('login')) {
                console.log('Primary login failed, trying fallback...');
                throw new Error('Primary login failed');
            }
            loginSuccess = true;
            console.log('Primary login successful');
        } catch (e) {
            // Try fallback
            console.log(`Attempting fallback login with ${FALLBACK_USER}...`);
            await page.goto('http://localhost:7777', { timeout: 30000 });
            await page.waitForTimeout(2000);
            await page.fill('input[name="LoginForm[username]"]', FALLBACK_USER);
            await page.fill('input[name="LoginForm[password]"]', FALLBACK_PASS);
            await page.click('button[type="submit"], input[type="submit"], button:has-text("Login"), #login-button');
            await page.waitForTimeout(3000);
            
            const errorVisible2 = await page.locator('.alert-error, .error-message, .flash-error').isVisible().catch(() => false);
            if (!errorVisible2) {
                loginSuccess = true;
                console.log('Fallback login successful');
            }
        }
        
        if (!loginSuccess) {
            issues.push('LOGIN_FAILED');
            console.log('TEST RESULT: FAIL|LOGIN_FAILED');
            await browser.close();
            return;
        }
        
        // Handle institution selection if present
        console.log('Checking for institution selection...');
        await page.waitForTimeout(2000);
        const institutionDropdown = await page.locator('select[name="institution_id"], #institution-select, select:has-text("Institution")').first();
        if (await institutionDropdown.isVisible().catch(() => false)) {
            console.log('Selecting institution...');
            await institutionDropdown.selectOption({ label: 'OpenEyes Default Institution' }).catch(() => {});
            await page.click('button:has-text("Continue"), button:has-text("Select"), input[type="submit"]').catch(() => {});
            await page.waitForTimeout(2000);
        }
        
        // Handle site selection if present
        const siteDropdown = await page.locator('select[name="site_id"], #site-select').first();
        if (await siteDropdown.isVisible().catch(() => false)) {
            console.log('Selecting site...');
            await siteDropdown.selectOption({ index: 1 }).catch(() => {});
            await page.click('button:has-text("Continue"), button:has-text("Select"), input[type="submit"]').catch(() => {});
            await page.waitForTimeout(2000);
        }
        
        // Navigate to admin page
        console.log('Navigating to admin page...');
        await page.goto('http://localhost:7777/OphCiExamination/admin/PupillaryAbnormalities', { timeout: 30000 });
        await page.waitForTimeout(3000);
        
        // Take screenshot for debugging
        await page.screenshot({ path: '/Users/asahu/Desktop/untitled folder/openeyes/test-screenshot-1.png' });
        
        // Check if admin page loaded
        const pageContent = await page.content();
        const currentUrl = page.url();
        console.log('Current URL:', currentUrl);
        
        if (currentUrl.includes('login') || currentUrl.includes('Login')) {
            issues.push('LOGIN_FAILED');
            console.log('TEST RESULT: FAIL|LOGIN_FAILED');
            await browser.close();
            return;
        }
        
        // Look for Add button with various selectors
        console.log('Looking for Add button...');
        const addButton = await page.locator('a:has-text("Add"), button:has-text("Add"), a.button:has-text("Add"), .add-button, a[href*="add"], a[href*="create"], button:has-text("Create"), a:has-text("Create")').first();
        
        if (!await addButton.isVisible().catch(() => false)) {
            // Check for different admin page structure
            const adminContent = await page.locator('.admin, #admin, .content, main').first();
            if (!await adminContent.isVisible().catch(() => false)) {
                issues.push('NO_ADMIN_PAGE');
                console.log('TEST RESULT: FAIL|NO_ADMIN_PAGE');
                await browser.close();
                return;
            }
            issues.push('NO_ADD_BUTTON');
            console.log('TEST RESULT: FAIL|NO_ADD_BUTTON');
            await browser.close();
            return;
        }
        
        // Click Add button
        console.log('Clicking Add button...');
        await addButton.click();
        await page.waitForTimeout(2000);
        
        await page.screenshot({ path: '/Users/asahu/Desktop/untitled folder/openeyes/test-screenshot-2.png' });
        
        // Fill in the form
        console.log('Filling form with test data...');
        const nameInput = await page.locator('input[name*="name"], input[name*="Name"], input[type="text"]').first();
        if (await nameInput.isVisible().catch(() => false)) {
            await nameInput.fill(TEST_NAME);
        } else {
            // Try other input patterns
            await page.fill('input:visible', TEST_NAME).catch(() => {});
        }
        
        await page.waitForTimeout(1000);
        await page.screenshot({ path: '/Users/asahu/Desktop/untitled folder/openeyes/test-screenshot-3.png' });
        
        // Click Save button
        console.log('Clicking Save button...');
        const saveButton = await page.locator('button:has-text("Save"), input[type="submit"]:has-text("Save"), button[type="submit"], input.generic-admin-save, #et_save, .save-button').first();
        if (await saveButton.isVisible().catch(() => false)) {
            await saveButton.click();
        } else {
            await page.click('button[type="submit"], input[type="submit"]').catch(() => {});
        }
        
        await page.waitForTimeout(3000);
        await page.screenshot({ path: '/Users/asahu/Desktop/untitled folder/openeyes/test-screenshot-4.png' });
        
        // Refresh and verify
        console.log('Refreshing page...');
        await page.goto('http://localhost:7777/OphCiExamination/admin/PupillaryAbnormalities', { timeout: 30000 });
        await page.waitForTimeout(3000);
        
        await page.screenshot({ path: '/Users/asahu/Desktop/untitled folder/openeyes/test-screenshot-5.png' });
        
        // Check if record exists
        console.log('Verifying record...');
        const pageText = await page.content();
        if (!pageText.includes('1767359433')) {
            issues.push('RECORD_NOT_PERSISTED');
            console.log('Record not found in page');
        } else {
            console.log('Record found in page!');
        }
        
    } catch (error) {
        console.error('Error during test:', error.message);
        if (!loginSuccess) {
            issues.push('LOGIN_FAILED');
        } else {
            issues.push('RECORD_NOT_PERSISTED');
        }
    } finally {
        await browser.close();
    }
    
    // Output final result
    if (issues.length === 0) {
        console.log('UI_TEST_PASSED');
    } else {
        console.log('UI_TEST_ISSUES:', issues.join(','));
    }
}

runTest().catch(console.error);

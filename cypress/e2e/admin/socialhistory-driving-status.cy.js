describe('E2E Test: SocialHistoryDrivingStatus Admin CRUD', () => {
    const testRecordName = 'E2E Test SocialHistoryDrivingStatus 1767359433';

    it('Should create and persist a new SocialHistoryDrivingStatus record', () => {
        // Login with test account or fallback to admin
        cy.login('droid_test_24', 'Droid@Test123!')
            .then((response) => {
                // If login failed, try fallback
                if (response.status !== 200) {
                    return cy.login('admin', 'admin');
                }
                return response;
            })
            .then(() => {
                // Navigate to the admin page
                cy.visit('/OphCiExamination/admin/SocialHistoryDrivingStatuses');
                
                // Wait for page to load
                cy.get('body').should('be.visible');
                
                // Take screenshot for debugging
                cy.screenshot('admin-page-loaded');
                
                // Look for Add button - try various selectors
                cy.get('body').then(($body) => {
                    // Check if we have an Add/Create button
                    const addButtonSelectors = [
                        'button:contains("Add")',
                        'a:contains("Add")',
                        '.button:contains("Add")',
                        '#et_add',
                        '.add-button',
                        'button.add',
                        'a.button:contains("Add")',
                        '.generic-admin-add',
                        'button[type="submit"]:contains("Add")',
                    ];
                    
                    let found = false;
                    for (const sel of addButtonSelectors) {
                        if ($body.find(sel).length > 0) {
                            found = true;
                            cy.get(sel).first().click();
                            break;
                        }
                    }
                    
                    if (!found) {
                        // Try looking for any button that might add
                        cy.log('Looking for add button alternatives');
                        cy.get('button, a.button').then(($buttons) => {
                            cy.log('Found buttons: ' + $buttons.length);
                            $buttons.each((i, el) => {
                                cy.log('Button: ' + el.innerText);
                            });
                        });
                    }
                });
                
                // Wait for form to appear and fill it
                cy.get('input[name="name"], input[name*="[name]"], input#name', { timeout: 10000 })
                    .should('be.visible')
                    .clear()
                    .type(testRecordName);
                
                // Click Save button
                cy.get('button:contains("Save"), input[type="submit"]:contains("Save"), #et_save, button[type="submit"]')
                    .first()
                    .click();
                
                // Wait for save to complete
                cy.wait(1000);
                
                // Refresh the page
                cy.visit('/OphCiExamination/admin/SocialHistoryDrivingStatuses');
                
                // Verify the record appears in the list
                cy.contains(testRecordName).should('exist');
                
                cy.screenshot('record-created-verified');
            });
    });
});

/**
 * Tier 2: Admin CRUD Verification Tests
 * 
 * These tests verify that admin pages correctly:
 * 1. Allow creating new records
 * 2. Display created records in list views
 * 
 * This catches issues like the Amino Acid Change bug where
 * "creation success" appears but data doesn't show in the list.
 */

const adminPages = require('../../../scripts/admin-crud-pages.json').pages;

describe('Admin CRUD Verification', () => {
    beforeEach(() => {
        cy.login();
    });

    // Filter to pages that can be auto-tested (have createFields and no skipAutoCreate)
    const autoTestPages = adminPages.filter(p => p.createFields && !p.skipAutoCreate);

    autoTestPages.forEach((page) => {
        it(`CRUD: ${page.url} - creates and displays ${page.model}`, function() {
            const uniqueValue = `Test_${Date.now()}_${Math.random().toString(36).substr(2, 5)}`;
            const createData = { ...page.createFields };
            
            // Make the display field unique
            if (createData[page.displayField]) {
                createData[page.displayField] = uniqueValue;
            } else {
                // Find the first string field and make it unique
                const firstField = Object.keys(createData)[0];
                createData[firstField] = `${createData[firstField]}_${uniqueValue}`;
            }

            // Create via API using CypressHelper
            cy.createModels(page.model, [], createData).then((created) => {
                const listUrl = page.listUrl || page.url;

                // Visit list page
                cy.visit(listUrl);

                // Wait for page to load
                cy.get('body').should('be.visible');

                // Verify the created record appears in the list
                const searchValue = createData[page.displayField] || createData[Object.keys(createData)[0]];
                
                cy.get(page.listSelector || 'table tbody, .admin-list, [data-test="admin-list"]')
                    .should('exist')
                    .and('contain', searchValue.substring(0, 20)); // Check partial match
            });
        });
    });

    // Special test for Amino Acid Change (the specific issue mentioned)
    it('CRUD: Amino Acid Change - verifies dual-write consistency', function() {
        const uniqueChange = `AminoAcid_${Date.now()}`;

        // Create via UI to test full flow
        cy.visit('/Genetics/aminoAcidChangeAdmin/list');
        
        // Check if Add button exists
        cy.get('body').then($body => {
            if ($body.find('[data-test="add"], .add, a[href*="edit"]').length > 0) {
                // Click add/create
                cy.get('[data-test="add"], .add, a[href*="edit"]').first().click();
                
                // Fill form
                cy.get('input[name*="change"], input[name="change"]').clear().type(uniqueChange);
                
                // Submit
                cy.get('button[type="submit"], input[type="submit"], .save').first().click();
                
                // Should redirect to list with success message
                cy.url().should('include', '/list');
                
                // CRITICAL: Verify the new record appears in the table
                cy.get('table tbody, .admin-list').should('contain', uniqueChange);
            } else {
                // If no add button, create via API and verify list
                cy.createModels('PedigreeAminoAcidChangeType', [], { change: uniqueChange }).then(() => {
                    cy.reload();
                    cy.get('table tbody, .admin-list').should('contain', uniqueChange);
                });
            }
        });
    });
});

describe('Admin List Page Verification', () => {
    beforeEach(() => {
        cy.login();
    });

    // Test that list pages load without errors
    const listPages = [
        { url: '/Genetics/aminoAcidChangeAdmin/list', selector: 'table tbody, .admin-list' },
        { url: '/Genetics/gene/list', selector: 'table tbody, .admin-list' },
        { url: '/Genetics/baseChangeAdmin/list', selector: 'table tbody, .admin-list' },
        { url: '/admin/contactlabels', selector: 'table tbody, .admin-list' },
        { url: '/OphCiExamination/admin/risks', selector: 'table tbody, .admin-list' },
        { url: '/OphCiExamination/admin/socialHistoryDrivingStatus', selector: 'table tbody, .admin-list' },
        { url: '/OphCiExamination/admin/postOpComplications', selector: "[data-test='complications-select']" },
        { url: '/OphCoCorrespondence/admin/letterMacros', selector: 'table tbody, .admin-list' },
        { url: '/oeadmin/team/list', selector: 'table tbody, .admin-list' },
        { url: '/PatientTicketing/admin', selector: "[data-test='patient-ticketing-list'], table tbody, .admin-list" },
    ];

    listPages.forEach((p) => {
        it(`List loads: ${p.url}`, () => {
            cy.visit(p.url, { failOnStatusCode: false });
            
            // Should not show PHP errors
            cy.get('body').should('not.contain', 'Fatal error');
            cy.get('body').should('not.contain', 'Exception');
            cy.get('body').should('not.contain', 'Stack trace');
            
            // Should have some content
            cy.get(p.selector).should('exist');
        });
    });
});

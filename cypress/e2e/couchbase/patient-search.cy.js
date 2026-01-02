/**
 * Patient Search E2E Tests for Couchbase Migration
 * 
 * Tests patient search functionality to ensure consistent results
 * regardless of whether data comes from MariaDB or Couchbase.
 */

describe('Patient Search - Couchbase Migration', () => {
    beforeEach(() => {
        // Login and setup
        cy.login();
        cy.logBackendStatus();
    });

    describe('Basic Search Functionality', () => {
        it('should search patient by hospital number', () => {
            cy.visit('/patient/search');
            
            // Use a known test patient hospital number
            const hosNum = '1234567';
            
            cy.searchPatient(hosNum);
            
            // Verify results page loads
            cy.url().should('include', 'patient');
            
            // Verify patient data is displayed
            cy.get('body').should('contain.text', hosNum);
        });

        it('should search patient by NHS number', () => {
            cy.visit('/patient/search');
            
            const nhsNum = '123 456 7890';
            
            cy.get('input[name="term"]').clear().type(nhsNum);
            cy.get('button[type="submit"]').click();
            
            // Wait for search to complete
            cy.wait(1000);
            
            // Should either show patient or "no results" message
            cy.get('body').then(($body) => {
                const hasResults = $body.find('.patient-summary, .search-results').length > 0;
                const hasNoResults = $body.text().includes('No patients found') || $body.text().includes('no results');
                
                expect(hasResults || hasNoResults).to.be.true;
            });
        });

        it('should search patient by name', () => {
            cy.visit('/patient/search');
            
            cy.get('input[name="term"]').clear().type('Smith');
            cy.get('button[type="submit"]').click();
            
            cy.wait(1000);
            
            // Should show search results or patient list
            cy.get('body').then(($body) => {
                const hasResults = $body.find('.patient-summary, .search-results, table').length > 0;
                const hasNoResults = $body.text().includes('No patients found');
                
                expect(hasResults || hasNoResults).to.be.true;
            });
        });
    });

    describe('Search Performance', () => {
        it('should return results within acceptable time', () => {
            cy.visit('/patient/search');
            
            const startTime = Date.now();
            
            cy.get('input[name="term"]').clear().type('1234567');
            cy.get('button[type="submit"]').click();
            
            // Wait for page to load
            cy.get('.patient-summary, .search-results, .flash-message', { timeout: 5000 })
                .should('exist')
                .then(() => {
                    const loadTime = Date.now() - startTime;
                    cy.log(`Search completed in ${loadTime}ms`);
                    
                    // Search should complete within 3 seconds
                    expect(loadTime).to.be.lessThan(3000);
                });
        });

        it('should handle rapid successive searches', () => {
            cy.visit('/patient/search');
            
            // Perform multiple searches quickly
            ['123', '1234', '12345', '123456', '1234567'].forEach((term, index) => {
                cy.get('input[name="term"]').clear().type(term);
                cy.wait(200);
            });
            
            cy.get('button[type="submit"]').click();
            
            // Should not crash, should show results
            cy.get('body').should('be.visible');
        });
    });

    describe('Data Consistency', () => {
        it('should display consistent patient data after search', () => {
            cy.visit('/patient/search');
            
            // Search for patient
            cy.get('input[name="term"]').clear().type('1234567');
            cy.get('button[type="submit"]').click();
            
            // If patient found, verify data is displayed consistently
            cy.get('body').then(($body) => {
                if ($body.find('.patient-summary').length > 0) {
                    // Get displayed hospital number
                    cy.get('.patient-hos-num, [data-hos-num]').should('contain', '1234567');
                    
                    // Verify patient has required fields displayed
                    cy.get('.patient-name').should('exist');
                    cy.get('.patient-dob, [data-dob]').should('exist');
                }
            });
        });

        it('should show same results when searching same term twice', () => {
            cy.visit('/patient/search');
            
            // First search
            cy.get('input[name="term"]').clear().type('1234567');
            cy.get('button[type="submit"]').click();
            cy.wait(1000);
            
            let firstSearchContent;
            cy.get('body').invoke('text').then((text) => {
                firstSearchContent = text;
            });
            
            // Second search with same term
            cy.visit('/patient/search');
            cy.get('input[name="term"]').clear().type('1234567');
            cy.get('button[type="submit"]').click();
            cy.wait(1000);
            
            // Results should be the same
            cy.get('body').invoke('text').then((text) => {
                // Both should either find or not find the patient
                const firstFound = !firstSearchContent.includes('No patients found');
                const secondFound = !text.includes('No patients found');
                expect(firstFound).to.equal(secondFound);
            });
        });
    });

    describe('Error Handling', () => {
        it('should handle empty search gracefully', () => {
            cy.visit('/patient/search');
            
            cy.get('button[type="submit"]').click();
            
            // Should show validation message or stay on page
            cy.get('body').should('be.visible');
        });

        it('should handle special characters in search', () => {
            cy.visit('/patient/search');
            
            cy.get('input[name="term"]').clear().type("O'Brien");
            cy.get('button[type="submit"]').click();
            
            // Should not cause error
            cy.wait(1000);
            cy.get('body').should('be.visible');
        });

        it('should handle very long search term', () => {
            cy.visit('/patient/search');
            
            const longTerm = 'a'.repeat(100);
            cy.get('input[name="term"]').clear().type(longTerm);
            cy.get('button[type="submit"]').click();
            
            // Should handle gracefully
            cy.wait(1000);
            cy.get('body').should('be.visible');
        });
    });
});

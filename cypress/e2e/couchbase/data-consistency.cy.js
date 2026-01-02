/**
 * Data Consistency E2E Tests for Couchbase Migration
 * 
 * Tests that data displayed on pages matches the underlying database,
 * validating consistency during the MariaDB to Couchbase migration.
 */

describe('Data Consistency - Couchbase Migration', () => {
    beforeEach(() => {
        cy.login();
        cy.logBackendStatus();
    });

    describe('Patient Data Consistency', () => {
        it('should display correct patient details on view page', () => {
            cy.visit('/patient/search');
            cy.get('input[name="term"]').clear().type('test');
            cy.get('button[type="submit"]').click();
            
            cy.get('body').then(($body) => {
                if ($body.find('.patient-link, a[href*="/patient/view"]').length > 0) {
                    cy.get('.patient-link, a[href*="/patient/view"]').first().click();
                    
                    // Extract patient ID from URL or page
                    cy.url().then((url) => {
                        const patientIdMatch = url.match(/\/patient\/view\/(\d+)/);
                        if (patientIdMatch) {
                            const patientId = patientIdMatch[1];
                            
                            // Validate page data against backend
                            cy.validatePageDataAgainstBackend(patientId);
                        }
                    });
                }
            });
        });

        it('should maintain data consistency after page refresh', () => {
            cy.visit('/patient/search');
            cy.get('input[name="term"]').clear().type('test');
            cy.get('button[type="submit"]').click();
            
            cy.get('body').then(($body) => {
                if ($body.find('.patient-link, a[href*="/patient/view"]').length > 0) {
                    cy.get('.patient-link, a[href*="/patient/view"]').first().click();
                    
                    let originalData = {};
                    
                    // Capture original data
                    cy.extractPatientDataFromPage().then((data) => {
                        originalData = data;
                    });
                    
                    // Refresh page
                    cy.reload();
                    
                    // Verify data is the same
                    cy.extractPatientDataFromPage().then((newData) => {
                        Object.keys(originalData).forEach(key => {
                            if (originalData[key] && newData[key]) {
                                expect(newData[key]).to.equal(originalData[key]);
                            }
                        });
                    });
                }
            });
        });

        it('should show consistent data across different patient pages', () => {
            cy.visit('/patient/search');
            cy.get('input[name="term"]').clear().type('test');
            cy.get('button[type="submit"]').click();
            
            cy.get('body').then(($body) => {
                if ($body.find('.patient-link, a[href*="/patient/view"]').length > 0) {
                    let patientData = {};
                    
                    // Visit patient summary
                    cy.get('.patient-link, a[href*="/patient/view"]').first().click();
                    cy.extractPatientDataFromPage().then((data) => {
                        patientData = data;
                    });
                    
                    // If there's a link to patient episodes/events, verify data there too
                    cy.get('body').then(($patientBody) => {
                        if ($patientBody.find('.patient-sidebar .patient-info').length > 0) {
                            // Patient info in sidebar should match
                            cy.get('.patient-sidebar .patient-info').should('contain', patientData.hos_num);
                        }
                    });
                }
            });
        });
    });

    describe('Episode Data Consistency', () => {
        it('should display consistent episode data', () => {
            cy.visit('/patient/search');
            cy.get('input[name="term"]').clear().type('test');
            cy.get('button[type="submit"]').click();
            
            cy.get('body').then(($body) => {
                if ($body.find('.patient-link, a[href*="/patient/view"]').length > 0) {
                    cy.get('.patient-link, a[href*="/patient/view"]').first().click();
                    
                    cy.get('.patient-summary', { timeout: 10000 }).should('exist');
                    
                    // Count episodes shown
                    cy.get('.episode-item, .event-summary').then(($episodes) => {
                        const displayedCount = $episodes.length;
                        cy.log(`Displayed ${displayedCount} episodes/events`);
                        
                        // Each episode should have required data
                        $episodes.each((index, episode) => {
                            const $ep = Cypress.$(episode);
                            // Episode should have a date or other identifying info
                            expect($ep.text().length).to.be.greaterThan(0);
                        });
                    });
                }
            });
        });

        it('should maintain episode order consistency', () => {
            cy.visit('/patient/search');
            cy.get('input[name="term"]').clear().type('test');
            cy.get('button[type="submit"]').click();
            
            cy.get('body').then(($body) => {
                if ($body.find('.patient-link, a[href*="/patient/view"]').length > 0) {
                    cy.get('.patient-link, a[href*="/patient/view"]').first().click();
                    
                    let episodeOrder = [];
                    
                    // Get episode order
                    cy.get('.episode-item, .event-summary').each(($el) => {
                        episodeOrder.push($el.text().trim());
                    });
                    
                    // Refresh
                    cy.reload();
                    
                    // Verify same order
                    cy.get('.episode-item, .event-summary').each(($el, index) => {
                        expect($el.text().trim()).to.equal(episodeOrder[index]);
                    });
                }
            });
        });
    });

    describe('Search Result Consistency', () => {
        it('should show consistent search results for same query', () => {
            const searchTerm = 'test';
            let firstResultCount;
            
            // First search
            cy.visit('/patient/search');
            cy.get('input[name="term"]').clear().type(searchTerm);
            cy.get('button[type="submit"]').click();
            
            cy.wait(1000);
            
            cy.get('.patient-link, a[href*="/patient/view"], .search-result').then(($results) => {
                firstResultCount = $results.length;
            });
            
            // Second search
            cy.visit('/patient/search');
            cy.get('input[name="term"]').clear().type(searchTerm);
            cy.get('button[type="submit"]').click();
            
            cy.wait(1000);
            
            // Should have same result count
            cy.get('.patient-link, a[href*="/patient/view"], .search-result').should('have.length', firstResultCount);
        });

        it('should maintain result ordering', () => {
            cy.visit('/patient/search');
            cy.get('input[name="term"]').clear().type('smith');
            cy.get('button[type="submit"]').click();
            
            let resultOrder = [];
            
            cy.get('.patient-link, a[href*="/patient/view"]').each(($el) => {
                resultOrder.push($el.attr('href'));
            });
            
            // Refresh and search again
            cy.visit('/patient/search');
            cy.get('input[name="term"]').clear().type('smith');
            cy.get('button[type="submit"]').click();
            
            cy.get('.patient-link, a[href*="/patient/view"]').each(($el, index) => {
                expect($el.attr('href')).to.equal(resultOrder[index]);
            });
        });
    });

    describe('Backend Mode Verification', () => {
        it('should function correctly regardless of backend mode', () => {
            cy.getBackendMode().then((mode) => {
                cy.log(`Testing with backend: Couchbase=${mode.couchbaseEnabled}`);
                
                // Basic functionality should work
                cy.visit('/patient/search');
                cy.get('input[name="term"]').should('exist');
                cy.get('button[type="submit"]').should('exist');
                
                // Search should work
                cy.get('input[name="term"]').clear().type('test');
                cy.get('button[type="submit"]').click();
                
                // Page should load without errors
                cy.get('body').should('be.visible');
            });
        });

        it('should not show database errors to user', () => {
            cy.visit('/patient/search');
            cy.get('input[name="term"]').clear().type('test');
            cy.get('button[type="submit"]').click();
            
            // Page should not contain raw database errors
            cy.get('body').invoke('text').then((text) => {
                expect(text.toLowerCase()).not.to.include('sql error');
                expect(text.toLowerCase()).not.to.include('database error');
                expect(text.toLowerCase()).not.to.include('couchbase error');
                expect(text.toLowerCase()).not.to.include('n1ql error');
            });
        });
    });

    describe('Data Format Consistency', () => {
        it('should display dates in consistent format', () => {
            cy.visit('/patient/search');
            cy.get('input[name="term"]').clear().type('test');
            cy.get('button[type="submit"]').click();
            
            cy.get('body').then(($body) => {
                if ($body.find('.patient-link, a[href*="/patient/view"]').length > 0) {
                    cy.get('.patient-link, a[href*="/patient/view"]').first().click();
                    
                    // All dates should be in consistent format
                    cy.get('.date, [data-date]').each(($date) => {
                        const dateText = $date.text().trim();
                        // Date should not be raw database format
                        expect(dateText).not.to.match(/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/);
                    });
                }
            });
        });

        it('should display numbers in consistent format', () => {
            cy.visit('/patient/search');
            cy.get('input[name="term"]').clear().type('test');
            cy.get('button[type="submit"]').click();
            
            cy.get('body').then(($body) => {
                if ($body.find('.patient-link, a[href*="/patient/view"]').length > 0) {
                    cy.get('.patient-link, a[href*="/patient/view"]').first().click();
                    
                    // Hospital number should be formatted consistently
                    cy.get('.patient-hos-num, [data-hos-num]').each(($hosNum) => {
                        const hosNumText = $hosNum.text().trim();
                        // Should be numeric or properly formatted
                        expect(hosNumText).to.match(/^[\d\s-]+$/);
                    });
                }
            });
        });
    });
});

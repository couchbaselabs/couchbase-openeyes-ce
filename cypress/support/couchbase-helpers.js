/**
 * Couchbase Testing Helper Commands for Cypress
 * 
 * These commands provide utilities for testing Couchbase-related functionality
 * in the OpenEyes application during the migration period.
 */

// API endpoints for backend verification
const API_BASE = '/api/v1';

/**
 * Wait for backend to be ready (both MariaDB and Couchbase if enabled)
 */
Cypress.Commands.add('waitForBackend', () => {
    cy.request({
        url: '/site/health',
        failOnStatusCode: false,
        timeout: 30000,
    }).then((response) => {
        expect(response.status).to.be.oneOf([200, 404]);
    });
});

/**
 * Get patient from backend API and verify response
 */
Cypress.Commands.add('getPatientFromAPI', (patientId) => {
    return cy.request({
        url: `${API_BASE}/patient/${patientId}`,
        failOnStatusCode: false,
    }).then((response) => {
        if (response.status === 200) {
            return response.body;
        }
        return null;
    });
});

/**
 * Search patients via API
 */
Cypress.Commands.add('searchPatientsAPI', (searchParams) => {
    const queryString = new URLSearchParams(searchParams).toString();
    return cy.request({
        url: `${API_BASE}/patient/search?${queryString}`,
        failOnStatusCode: false,
    }).then((response) => {
        if (response.status === 200) {
            return response.body;
        }
        return { patients: [] };
    });
});

/**
 * Verify data consistency between page display and API
 */
Cypress.Commands.add('verifyDataConsistency', (pageData, apiData) => {
    Object.keys(pageData).forEach(key => {
        if (apiData[key] !== undefined) {
            expect(pageData[key]).to.equal(apiData[key], `Field ${key} should match`);
        }
    });
});

/**
 * Get current backend mode (MariaDB, Couchbase, or Dual)
 */
Cypress.Commands.add('getBackendMode', () => {
    return cy.request({
        url: '/couchbase/health/status',
        failOnStatusCode: false,
    }).then((response) => {
        if (response.status === 200 && response.body) {
            return {
                couchbaseEnabled: response.body.couchbase_enabled || false,
                dualWriteEnabled: response.body.dual_write_enabled || false,
                couchbaseReadEnabled: response.body.couchbase_read_enabled || false,
            };
        }
        return {
            couchbaseEnabled: false,
            dualWriteEnabled: false,
            couchbaseReadEnabled: false,
        };
    });
});

/**
 * Extract patient data from the current page
 */
Cypress.Commands.add('extractPatientDataFromPage', () => {
    const data = {};
    
    cy.get('[data-patient-id]').then(($el) => {
        if ($el.length) {
            data.id = $el.attr('data-patient-id');
        }
    });
    
    cy.get('.patient-hos-num, [data-hos-num]').then(($el) => {
        if ($el.length) {
            data.hos_num = $el.text().trim() || $el.attr('data-hos-num');
        }
    });
    
    cy.get('.patient-nhs-num, [data-nhs-num]').then(($el) => {
        if ($el.length) {
            data.nhs_num = $el.text().trim() || $el.attr('data-nhs-num');
        }
    });
    
    cy.get('.patient-name').then(($el) => {
        if ($el.length) {
            data.name = $el.text().trim();
        }
    });
    
    return cy.wrap(data);
});

/**
 * Navigate to patient view page
 */
Cypress.Commands.add('visitPatient', (patientId) => {
    cy.visit(`/patient/view/${patientId}`);
    cy.get('.patient-summary', { timeout: 10000 }).should('exist');
});

/**
 * Search for patient using search bar
 */
Cypress.Commands.add('searchPatient', (searchTerm) => {
    cy.get('#patient-search-field, input[name="term"]').clear().type(searchTerm);
    cy.get('#patient-search-btn, button[type="submit"]').click();
    cy.wait(500); // Allow search to complete
});

/**
 * Verify episode list is displayed correctly
 */
Cypress.Commands.add('verifyEpisodeList', (expectedCount) => {
    if (expectedCount !== undefined) {
        cy.get('.episode-item, .event-summary').should('have.length', expectedCount);
    } else {
        cy.get('.episode-item, .event-summary').should('exist');
    }
});

/**
 * Measure page load performance
 */
Cypress.Commands.add('measurePageLoad', (pageName) => {
    const startTime = performance.now();
    
    return cy.wrap(null).then(() => {
        return new Cypress.Promise((resolve) => {
            cy.window().then((win) => {
                const loadTime = performance.now() - startTime;
                const result = {
                    page: pageName,
                    loadTime: loadTime,
                    timestamp: new Date().toISOString(),
                };
                
                // Log for analysis
                cy.log(`Page Load Time for ${pageName}: ${loadTime.toFixed(2)}ms`);
                
                resolve(result);
            });
        });
    });
});

/**
 * Compare page data with database data (for consistency validation)
 */
Cypress.Commands.add('validatePageDataAgainstBackend', (patientId) => {
    let pageData = {};
    
    // Get data from page
    cy.extractPatientDataFromPage().then((data) => {
        pageData = data;
    });
    
    // Get data from API
    cy.getPatientFromAPI(patientId).then((apiData) => {
        if (apiData) {
            // Compare fields
            if (pageData.hos_num && apiData.hos_num) {
                expect(pageData.hos_num).to.include(apiData.hos_num);
            }
            if (pageData.nhs_num && apiData.nhs_num) {
                expect(pageData.nhs_num).to.include(apiData.nhs_num);
            }
        }
    });
});

/**
 * Log current database backend status
 */
Cypress.Commands.add('logBackendStatus', () => {
    cy.getBackendMode().then((mode) => {
        cy.log(`Backend Mode: Couchbase=${mode.couchbaseEnabled}, DualWrite=${mode.dualWriteEnabled}, CouchbaseRead=${mode.couchbaseReadEnabled}`);
    });
});

<?php
/**
 * Seed essential test data in Couchbase
 * Run this script to ensure tests have required reference data
 * 
 * Usage: php protected/scripts/couchbase/seed-test-data.php
 * 
 * This is a standalone script that doesn't require Yii framework
 */

class CouchbaseTestDataSeeder
{
    private $couchbaseHost;
    private $couchbaseUser;
    private $couchbasePassword;
    private $bucket = 'openeyes';
    
    public function __construct()
    {
        $this->couchbaseHost = getenv('COUCHBASE_HOST') ?: 'localhost';
        $this->couchbaseUser = getenv('COUCHBASE_USER') ?: 'Administrator';
        $this->couchbasePassword = getenv('COUCHBASE_PASSWORD') ?: 'password';
    }
    
    public function run()
    {
        echo "=== Couchbase Test Data Seeder ===\n\n";
        
        // Verify connection
        if (!$this->testConnection()) {
            echo "ERROR: Cannot connect to Couchbase\n";
            return false;
        }
        
        // Seed essential data
        $this->seedUserAuthenticationMethods();
        $this->seedDefaultInstitution();
        $this->seedDefaultInstitutionAuthentication();
        $this->seedDefaultUser();
        $this->seedDefaultUserAuthentication();
        $this->seedEssentialDisorders();
        
        echo "\n=== Seeding Complete ===\n";
        return true;
    }
    
    private function testConnection()
    {
        $result = $this->executeN1ql("SELECT 1 as test");
        return !empty($result);
    }
    
    private function seedUserAuthenticationMethods()
    {
        echo "Checking UserAuthenticationMethod records...\n";
        
        $methods = [
            ['code' => 'LOCAL', '_type' => 'user_authentication_method'],
            ['code' => 'LDAP', '_type' => 'user_authentication_method'],
            ['code' => 'SSO', '_type' => 'user_authentication_method'],
        ];
        
        foreach ($methods as $method) {
            $exists = $this->documentExists('admin', 'user_authentication_method', $method['code']);
            if (!$exists) {
                $this->upsertDocument('admin', 'user_authentication_method', $method['code'], $method);
                echo "  Created: {$method['code']}\n";
            } else {
                echo "  Exists: {$method['code']}\n";
            }
        }
    }
    
    private function seedDefaultInstitution()
    {
        echo "Checking default Institution...\n";
        
        $exists = $this->documentExists('core', 'institution', '1');
        if (!$exists) {
            $doc = [
                'id' => 1,
                'name' => 'Test Institution',
                'remote_id' => 'TEST',
                'short_name' => 'Test',
                'active' => true,
                '_type' => 'institution',
                '_mysql_id' => 1,
            ];
            $this->upsertDocument('core', 'institution', '1', $doc);
            echo "  Created default institution\n";
        } else {
            echo "  Default institution exists\n";
        }
    }
    
    private function seedDefaultInstitutionAuthentication()
    {
        echo "Checking InstitutionAuthentication...\n";
        
        $exists = $this->documentExists('admin', 'institution_authentication', '1');
        if (!$exists) {
            $doc = [
                'id' => 1,
                'institution_id' => 1,
                'user_authentication_method' => 'LOCAL',
                'active' => true,
                '_type' => 'institution_authentication',
                '_mysql_id' => 1,
            ];
            $this->upsertDocument('admin', 'institution_authentication', '1', $doc);
            echo "  Created default institution_authentication\n";
        } else {
            echo "  Default institution_authentication exists\n";
        }
    }
    
    private function seedDefaultUser()
    {
        echo "Checking default User...\n";
        
        $exists = $this->documentExists('admin', 'user', '1');
        if (!$exists) {
            $doc = [
                'id' => 1,
                'username' => 'admin',
                'first_name' => 'Admin',
                'last_name' => 'User',
                'email' => 'admin@test.com',
                'active' => true,
                'global_firm_rights' => true,
                '_type' => 'user',
                '_mysql_id' => 1,
            ];
            $this->upsertDocument('admin', 'user', '1', $doc);
            echo "  Created default user\n";
        } else {
            echo "  Default user exists\n";
        }
    }
    
    private function seedDefaultUserAuthentication()
    {
        echo "Checking UserAuthentication...\n";
        
        $exists = $this->documentExists('admin', 'user_authentication', '1');
        if (!$exists) {
            // Password hash for 'admin'
            $passwordHash = password_hash('admin', PASSWORD_DEFAULT);
            $doc = [
                'id' => 1,
                'user_id' => 1,
                'username' => 'admin',
                'password' => $passwordHash,
                'institution_authentication_id' => 1,
                'active' => true,
                '_type' => 'user_authentication',
                '_mysql_id' => 1,
            ];
            $this->upsertDocument('admin', 'user_authentication', '1', $doc);
            echo "  Created default user_authentication\n";
        } else {
            echo "  Default user_authentication exists\n";
        }
    }
    
    private function seedEssentialDisorders()
    {
        echo "Checking essential Disorders...\n";
        
        // Check if disorders exist
        $result = $this->executeN1ql(
            "SELECT COUNT(*) as cnt FROM `{$this->bucket}`.`reference`.`disorder` WHERE specialty_id IS NULL"
        );
        
        $count = isset($result[0]['cnt']) ? $result[0]['cnt'] : 0;
        
        if ($count < 5) {
            // Seed some test disorders
            $disorders = [
                ['id' => 9999001, 'term' => 'Test Disorder 1', 'fully_specified_name' => 'Test Disorder 1', 'active' => true],
                ['id' => 9999002, 'term' => 'Test Disorder 2', 'fully_specified_name' => 'Test Disorder 2', 'active' => true],
                ['id' => 9999003, 'term' => 'Test Disorder 3', 'fully_specified_name' => 'Test Disorder 3', 'active' => true],
                ['id' => 9999004, 'term' => 'Test Disorder 4', 'fully_specified_name' => 'Test Disorder 4', 'active' => true],
                ['id' => 9999005, 'term' => 'Test Disorder 5', 'fully_specified_name' => 'Test Disorder 5', 'active' => true],
            ];
            
            foreach ($disorders as $disorder) {
                $exists = $this->documentExists('reference', 'disorder', (string)$disorder['id']);
                if (!$exists) {
                    $disorder['_type'] = 'disorder';
                    $disorder['_mysql_id'] = $disorder['id'];
                    $this->upsertDocument('reference', 'disorder', (string)$disorder['id'], $disorder);
                    echo "  Created: {$disorder['term']}\n";
                }
            }
        } else {
            echo "  Disorders exist (count: $count)\n";
        }
    }
    
    private function documentExists($scope, $collection, $docId)
    {
        $key = "{$collection}::{$docId}";
        $n1ql = "SELECT RAW 1 FROM `{$this->bucket}`.`{$scope}`.`{$collection}` USE KEYS ['{$key}'] LIMIT 1";
        $result = $this->executeN1ql($n1ql);
        return !empty($result);
    }
    
    private function upsertDocument($scope, $collection, $docId, $doc)
    {
        $key = "{$collection}::{$docId}";
        $docJson = json_encode($doc);
        
        $n1ql = "UPSERT INTO `{$this->bucket}`.`{$scope}`.`{$collection}` (KEY, VALUE) VALUES ('{$key}', {$docJson})";
        return $this->executeN1ql($n1ql);
    }
    
    private function executeN1ql($query, $params = [])
    {
        $url = "http://{$this->couchbaseHost}:8093/query/service";
        
        $postData = ['statement' => $query];
        if (!empty($params)) {
            $postData['args'] = json_encode($params);
        }
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERPWD, "{$this->couchbaseUser}:{$this->couchbasePassword}");
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            echo "  N1QL Error (HTTP $httpCode): " . substr($response, 0, 200) . "\n";
            return [];
        }
        
        $data = json_decode($response, true);
        return isset($data['results']) ? $data['results'] : [];
    }
}

// Run the seeder
$seeder = new CouchbaseTestDataSeeder();
$seeder->run();

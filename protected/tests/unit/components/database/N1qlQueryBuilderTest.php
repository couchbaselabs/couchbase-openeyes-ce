<?php
/**
 * Unit tests for N1qlQueryBuilder
 */

class N1qlQueryBuilderTest extends CTestCase
{
    private $builder;
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->builder = new \OE\Database\N1qlQueryBuilder('openeyes');
    }
    
    public function testBasicSelect()
    {
        $query = $this->builder
            ->from('core', 'patient')
            ->build();
        
        $this->assertStringContainsString('SELECT META().id AS _id, *', $query);
        $this->assertStringContainsString('FROM `openeyes`.`core`.`patient`', $query);
    }
    
    public function testSelectSpecificColumns()
    {
        $query = $this->builder
            ->from('core', 'patient')
            ->select('hos_num, nhs_num, dob')
            ->build();
        
        $this->assertStringContainsString('META().id AS _id', $query);
        $this->assertStringContainsString('hos_num, nhs_num, dob', $query);
    }
    
    public function testSelectWithArray()
    {
        $query = $this->builder
            ->from('core', 'patient')
            ->select(['hos_num', 'nhs_num', 'dob'])
            ->build();
        
        $this->assertStringContainsString('hos_num, nhs_num, dob', $query);
    }
    
    public function testFromWithAlias()
    {
        $query = $this->builder
            ->from('core', 'patient', 'p')
            ->build();
        
        $this->assertStringContainsString('FROM `openeyes`.`core`.`patient` AS p', $query);
    }
    
    public function testWhereCondition()
    {
        $query = $this->builder
            ->from('core', 'patient')
            ->where('hos_num = $hosNum', ['hosNum' => '12345'])
            ->build();
        
        $this->assertStringContainsString('WHERE hos_num = $hosNum', $query);
        $this->assertEquals(['hosNum' => '12345'], $this->builder->getParams());
    }
    
    public function testMultipleWhereConditions()
    {
        $query = $this->builder
            ->from('core', 'patient')
            ->where('hos_num = $hosNum', ['hosNum' => '12345'])
            ->andWhere('active = $active', ['active' => true])
            ->build();
        
        $this->assertStringContainsString('WHERE hos_num = $hosNum AND active = $active', $query);
        $params = $this->builder->getParams();
        $this->assertEquals('12345', $params['hosNum']);
        $this->assertTrue($params['active']);
    }
    
    public function testOrWhere()
    {
        $query = $this->builder
            ->from('core', 'patient')
            ->where('hos_num = $hosNum', ['hosNum' => '12345'])
            ->orWhere('nhs_num = $nhsNum', ['nhsNum' => '9876543210'])
            ->build();
        
        $this->assertStringContainsString('WHERE hos_num = $hosNum OR nhs_num = $nhsNum', $query);
    }
    
    public function testWhereIn()
    {
        $query = $this->builder
            ->from('core', 'patient')
            ->whereIn('gender', ['M', 'F'])
            ->build();
        
        $this->assertStringContainsString('WHERE gender IN [$in0, $in1]', $query);
        $params = $this->builder->getParams();
        $this->assertEquals('M', $params['in0']);
        $this->assertEquals('F', $params['in1']);
    }
    
    public function testWhereInEmpty()
    {
        $query = $this->builder
            ->from('core', 'patient')
            ->whereIn('gender', [])
            ->build();
        
        $this->assertStringContainsString('WHERE 1 = 0', $query);
    }
    
    public function testWhereBetween()
    {
        $query = $this->builder
            ->from('core', 'patient')
            ->whereBetween('dob', '1980-01-01', '1989-12-31')
            ->build();
        
        $this->assertStringContainsString('WHERE dob BETWEEN $betweenStart AND $betweenEnd', $query);
        $params = $this->builder->getParams();
        $this->assertEquals('1980-01-01', $params['betweenStart']);
        $this->assertEquals('1989-12-31', $params['betweenEnd']);
    }
    
    public function testWhereLike()
    {
        $query = $this->builder
            ->from('core', 'patient')
            ->whereLike('contact.last_name', 'Smi%', 'name')
            ->build();
        
        $this->assertStringContainsString('WHERE contact.last_name LIKE $name', $query);
        $this->assertEquals('Smi%', $this->builder->getParams()['name']);
    }
    
    public function testWhereILike()
    {
        $query = $this->builder
            ->from('core', 'patient')
            ->whereILike('contact.last_name', 'smi%', 'name')
            ->build();
        
        $this->assertStringContainsString('LOWER(contact.last_name) LIKE LOWER($name)', $query);
        $this->assertEquals('smi%', $this->builder->getParams()['name']);
    }
    
    public function testWhereNull()
    {
        $query = $this->builder
            ->from('core', 'patient')
            ->whereNull('date_of_death')
            ->build();
        
        $this->assertStringContainsString('WHERE date_of_death IS NULL', $query);
    }
    
    public function testWhereNotNull()
    {
        $query = $this->builder
            ->from('core', 'patient')
            ->whereNotNull('nhs_num')
            ->build();
        
        $this->assertStringContainsString('WHERE nhs_num IS NOT NULL', $query);
    }
    
    public function testWhereAny()
    {
        $query = $this->builder
            ->from('clinical', 'examination')
            ->whereAny('elements.VisualAcuity.left_readings', 'r', 'r.value < $threshold', ['threshold' => 0.5])
            ->build();
        
        $this->assertStringContainsString('ANY r IN elements.VisualAcuity.left_readings SATISFIES r.value < $threshold END', $query);
        $this->assertEquals(0.5, $this->builder->getParams()['threshold']);
    }
    
    public function testJoin()
    {
        $query = $this->builder
            ->from('core', 'episode', 'e')
            ->join('core', 'patient', 'e.patient_id = META(p).id', 'p')
            ->select('e.*, p.hos_num')
            ->build();
        
        $this->assertStringContainsString('JOIN `openeyes`.`core`.`patient` AS p ON e.patient_id = META(p).id', $query);
    }
    
    public function testLeftJoin()
    {
        $query = $this->builder
            ->from('core', 'episode', 'e')
            ->leftJoin('core', 'patient', 'e.patient_id = META(p).id', 'p')
            ->build();
        
        $this->assertStringContainsString('LEFT JOIN `openeyes`.`core`.`patient` AS p ON e.patient_id = META(p).id', $query);
    }
    
    public function testNest()
    {
        $query = $this->builder
            ->from('core', 'patient', 'p')
            ->nest('core', 'episode', 'episodes', 'e.patient_id = META(p).id')
            ->build();
        
        $this->assertStringContainsString('NEST `openeyes`.`core`.`episode` AS episodes ON e.patient_id = META(p).id', $query);
    }
    
    public function testUnnest()
    {
        $query = $this->builder
            ->from('clinical', 'examination', 'e')
            ->unnest('e.elements.VisualAcuity.left_readings', 'r')
            ->select('e.event_id, r.*')
            ->build();
        
        $this->assertStringContainsString('UNNEST e.elements.VisualAcuity.left_readings AS r', $query);
    }
    
    public function testOrderBy()
    {
        $query = $this->builder
            ->from('core', 'patient')
            ->orderBy('contact.last_name')
            ->orderBy('contact.first_name', 'DESC')
            ->build();
        
        $this->assertStringContainsString('ORDER BY contact.last_name ASC, contact.first_name DESC', $query);
    }
    
    public function testOrderByInvalidDirection()
    {
        $query = $this->builder
            ->from('core', 'patient')
            ->orderBy('contact.last_name', 'INVALID')
            ->build();
        
        // Should default to ASC
        $this->assertStringContainsString('ORDER BY contact.last_name ASC', $query);
    }
    
    public function testGroupByString()
    {
        $query = $this->builder
            ->from('core', 'episode')
            ->select('firm_id, COUNT(*) as cnt')
            ->groupBy('firm_id')
            ->build();
        
        $this->assertStringContainsString('GROUP BY firm_id', $query);
    }
    
    public function testGroupByArray()
    {
        $query = $this->builder
            ->from('core', 'episode')
            ->select('firm_id, subspecialty_id, COUNT(*) as cnt')
            ->groupBy(['firm_id', 'subspecialty_id'])
            ->build();
        
        $this->assertStringContainsString('GROUP BY firm_id, subspecialty_id', $query);
    }
    
    public function testHaving()
    {
        $query = $this->builder
            ->from('core', 'episode')
            ->select('firm_id, COUNT(*) as cnt')
            ->groupBy('firm_id')
            ->having('COUNT(*) > $minCount', ['minCount' => 10])
            ->build();
        
        $this->assertStringContainsString('HAVING COUNT(*) > $minCount', $query);
        $this->assertEquals(10, $this->builder->getParams()['minCount']);
    }
    
    public function testLimit()
    {
        $query = $this->builder
            ->from('core', 'patient')
            ->limit(20)
            ->build();
        
        $this->assertStringContainsString('LIMIT 20', $query);
    }
    
    public function testOffset()
    {
        $query = $this->builder
            ->from('core', 'patient')
            ->offset(40)
            ->build();
        
        $this->assertStringContainsString('OFFSET 40', $query);
    }
    
    public function testLimitAndOffset()
    {
        $query = $this->builder
            ->from('core', 'patient')
            ->limit(20)
            ->offset(40)
            ->build();
        
        $this->assertStringContainsString('LIMIT 20', $query);
        $this->assertStringContainsString('OFFSET 40', $query);
    }
    
    public function testUseKeys()
    {
        $query = $this->builder
            ->from('core', 'patient')
            ->useKeys(['patient::1', 'patient::2'])
            ->build();
        
        $this->assertStringContainsString("USE KEYS ['patient::1', 'patient::2']", $query);
    }
    
    public function testUseKeysSingle()
    {
        $query = $this->builder
            ->from('core', 'patient')
            ->useKeys('patient::123')
            ->build();
        
        $this->assertStringContainsString("USE KEYS ['patient::123']", $query);
    }
    
    public function testWithoutMetaId()
    {
        $query = $this->builder
            ->from('core', 'patient')
            ->withoutMetaId()
            ->build();
        
        $this->assertStringNotContainsString('META().id', $query);
    }
    
    public function testAddSelect()
    {
        $query = $this->builder
            ->from('core', 'patient')
            ->select('hos_num')
            ->addSelect('nhs_num')
            ->addSelect(['dob', 'gender'])
            ->build();
        
        $this->assertStringContainsString('hos_num, nhs_num, dob, gender', $query);
    }
    
    public function testComplexQuery()
    {
        $query = $this->builder
            ->from('core', 'episode', 'e')
            ->join('core', 'patient', 'e.patient_id = META(p).id', 'p')
            ->select('e.*, p.hos_num, p.nhs_num')
            ->where('e.start_date >= $startDate', ['startDate' => '2024-01-01'])
            ->andWhere('e.subspecialty_id = $subspecialty', ['subspecialty' => 1])
            ->orderBy('e.start_date', 'DESC')
            ->limit(50)
            ->offset(0)
            ->build();
        
        $this->assertStringContainsString('FROM `openeyes`.`core`.`episode` AS e', $query);
        $this->assertStringContainsString('JOIN `openeyes`.`core`.`patient` AS p', $query);
        $this->assertStringContainsString('WHERE e.start_date >= $startDate AND e.subspecialty_id = $subspecialty', $query);
        $this->assertStringContainsString('ORDER BY e.start_date DESC', $query);
        $this->assertStringContainsString('LIMIT 50', $query);
        $this->assertStringContainsString('OFFSET 0', $query);
    }
    
    public function testReset()
    {
        $this->builder
            ->from('core', 'patient')
            ->where('id = $id', ['id' => 1])
            ->orderBy('last_name')
            ->limit(10);
        
        $this->builder->reset();
        
        $this->assertEmpty($this->builder->getParams());
        
        // Build a new query to verify reset worked
        $query = $this->builder
            ->from('core', 'episode')
            ->build();
        
        $this->assertStringContainsString('FROM `openeyes`.`core`.`episode`', $query);
        $this->assertStringNotContainsString('patient', $query);
        $this->assertStringNotContainsString('WHERE', $query);
    }
    
    public function testToString()
    {
        $query = $this->builder
            ->from('core', 'patient')
            ->where('hos_num = $hosNum', ['hosNum' => '12345'])
            ->build();
        
        $this->assertEquals($query, (string)$this->builder);
    }
    
    public function testGetParams()
    {
        $this->builder
            ->from('core', 'patient')
            ->where('hos_num = $hosNum', ['hosNum' => '12345'])
            ->andWhere('active = $active', ['active' => true]);
        
        $params = $this->builder->getParams();
        
        $this->assertIsArray($params);
        $this->assertArrayHasKey('hosNum', $params);
        $this->assertArrayHasKey('active', $params);
        $this->assertEquals('12345', $params['hosNum']);
        $this->assertTrue($params['active']);
    }
}

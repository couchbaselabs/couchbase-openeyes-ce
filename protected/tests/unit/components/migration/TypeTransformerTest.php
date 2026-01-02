<?php
/**
 * Unit tests for TypeTransformer
 */

use OE\Migration\TypeTransformer;

class TypeTransformerTest extends CTestCase
{
    /**
     * @test
     */
    public function testTransformInteger()
    {
        $this->assertSame(123, TypeTransformer::transform('123', 'int(11)'));
        $this->assertSame(0, TypeTransformer::transform('0', 'int'));
        $this->assertSame(-5, TypeTransformer::transform('-5', 'int(11) unsigned'));
        $this->assertNull(TypeTransformer::transform(null, 'int'));
    }
    
    /**
     * @test
     */
    public function testTransformBigint()
    {
        $this->assertSame(9999999999, TypeTransformer::transform('9999999999', 'bigint(20)'));
    }
    
    /**
     * @test
     */
    public function testTransformFloat()
    {
        $this->assertSame(123.45, TypeTransformer::transform('123.45', 'decimal(10,2)'));
        $this->assertSame(0.0, TypeTransformer::transform('0', 'float'));
        $this->assertSame(99.99, TypeTransformer::transform('99.99', 'double'));
        $this->assertNull(TypeTransformer::transform(null, 'float'));
    }
    
    /**
     * @test
     */
    public function testTransformBoolean()
    {
        $this->assertTrue(TypeTransformer::transform('1', 'tinyint(1)'));
        $this->assertTrue(TypeTransformer::transform(1, 'tinyint(1)'));
        $this->assertFalse(TypeTransformer::transform('0', 'tinyint(1)'));
        $this->assertFalse(TypeTransformer::transform(0, 'tinyint(1)'));
        $this->assertNull(TypeTransformer::transform(null, 'tinyint(1)'));
    }
    
    /**
     * @test
     */
    public function testTransformDate()
    {
        $this->assertSame('2024-01-15', TypeTransformer::transform('2024-01-15', 'date'));
        $this->assertNull(TypeTransformer::transform('0000-00-00', 'date'));
        $this->assertNull(TypeTransformer::transform('', 'date'));
        $this->assertNull(TypeTransformer::transform(null, 'date'));
    }
    
    /**
     * @test
     */
    public function testTransformDatetime()
    {
        $result = TypeTransformer::transform('2024-01-15 10:30:00', 'datetime');
        $this->assertStringContainsString('2024-01-15', $result);
        $this->assertStringContainsString('10:30:00', $result);
        
        $this->assertNull(TypeTransformer::transform('0000-00-00 00:00:00', 'datetime'));
        $this->assertNull(TypeTransformer::transform('', 'datetime'));
        $this->assertNull(TypeTransformer::transform(null, 'datetime'));
    }
    
    /**
     * @test
     */
    public function testTransformTimestamp()
    {
        $result = TypeTransformer::transform('2024-01-15 10:30:00', 'timestamp');
        $this->assertStringContainsString('2024-01-15', $result);
    }
    
    /**
     * @test
     */
    public function testTransformTime()
    {
        $this->assertSame('10:30:00', TypeTransformer::transform('10:30:00', 'time'));
        $this->assertNull(TypeTransformer::transform(null, 'time'));
    }
    
    /**
     * @test
     */
    public function testTransformJson()
    {
        $json = '{"key": "value", "number": 123}';
        $result = TypeTransformer::transform($json, 'json');
        $this->assertIsArray($result);
        $this->assertSame('value', $result['key']);
        $this->assertSame(123, $result['number']);
        
        // Already an array
        $array = ['foo' => 'bar'];
        $this->assertSame($array, TypeTransformer::transform($array, 'json'));
        
        // Invalid JSON
        $this->assertNull(TypeTransformer::transform('not valid json', 'json'));
        $this->assertNull(TypeTransformer::transform(null, 'json'));
    }
    
    /**
     * @test
     */
    public function testTransformString()
    {
        $this->assertSame('hello', TypeTransformer::transform('hello', 'varchar(255)'));
        $this->assertSame('123', TypeTransformer::transform(123, 'varchar(10)'));
        $this->assertSame('', TypeTransformer::transform('', 'text'));
        $this->assertNull(TypeTransformer::transform(null, 'text'));
    }
    
    /**
     * @test
     */
    public function testTransformEnum()
    {
        $this->assertSame('active', TypeTransformer::transform('active', "enum('active','inactive')"));
    }
    
    /**
     * @test
     */
    public function testTransformMediumText()
    {
        $longText = str_repeat('a', 10000);
        $this->assertSame($longText, TypeTransformer::transform($longText, 'mediumtext'));
    }
    
    /**
     * @test
     */
    public function testNullHandling()
    {
        $this->assertNull(TypeTransformer::transform(null, 'int'));
        $this->assertNull(TypeTransformer::transform(null, 'varchar(255)'));
        $this->assertNull(TypeTransformer::transform(null, 'date'));
        $this->assertNull(TypeTransformer::transform(null, 'datetime'));
        $this->assertNull(TypeTransformer::transform(null, 'tinyint(1)'));
        $this->assertNull(TypeTransformer::transform(null, 'json'));
    }
}

<?php
/**
 * Unit tests for DataValidationCommand
 */

class DataValidationTest extends CTestCase
{
    /**
     * @test
     */
    public function testValuesMatchWithIntegers()
    {
        $cmd = new DataValidationCommand('test', null);
        $reflection = new ReflectionClass($cmd);
        $method = $reflection->getMethod('valuesMatch');
        $method->setAccessible(true);
        
        // Numeric string and int should match
        $this->assertTrue($method->invoke($cmd, '123', 123));
        $this->assertTrue($method->invoke($cmd, 123, '123'));
        $this->assertTrue($method->invoke($cmd, 123, 123));
        $this->assertTrue($method->invoke($cmd, '123.0', 123));
    }
    
    /**
     * @test
     */
    public function testValuesMatchWithFloats()
    {
        $cmd = new DataValidationCommand('test', null);
        $reflection = new ReflectionClass($cmd);
        $method = $reflection->getMethod('valuesMatch');
        $method->setAccessible(true);
        
        $this->assertTrue($method->invoke($cmd, '123.45', 123.45));
        $this->assertTrue($method->invoke($cmd, 123.45, '123.45'));
    }
    
    /**
     * @test
     */
    public function testValuesMatchWithNull()
    {
        $cmd = new DataValidationCommand('test', null);
        $reflection = new ReflectionClass($cmd);
        $method = $reflection->getMethod('valuesMatch');
        $method->setAccessible(true);
        
        $this->assertTrue($method->invoke($cmd, null, null));
        $this->assertFalse($method->invoke($cmd, null, 0));
        $this->assertFalse($method->invoke($cmd, 0, null));
        $this->assertFalse($method->invoke($cmd, null, ''));
    }
    
    /**
     * @test
     */
    public function testValuesMatchWithBooleans()
    {
        $cmd = new DataValidationCommand('test', null);
        $reflection = new ReflectionClass($cmd);
        $method = $reflection->getMethod('valuesMatch');
        $method->setAccessible(true);
        
        // MySQL stores booleans as 0/1
        $this->assertTrue($method->invoke($cmd, '1', true));
        $this->assertTrue($method->invoke($cmd, '0', false));
        $this->assertTrue($method->invoke($cmd, 1, true));
        $this->assertTrue($method->invoke($cmd, 0, false));
    }
    
    /**
     * @test
     */
    public function testValuesMatchWithStrings()
    {
        $cmd = new DataValidationCommand('test', null);
        $reflection = new ReflectionClass($cmd);
        $method = $reflection->getMethod('valuesMatch');
        $method->setAccessible(true);
        
        $this->assertTrue($method->invoke($cmd, 'hello', 'hello'));
        $this->assertFalse($method->invoke($cmd, 'hello', 'Hello'));
        $this->assertFalse($method->invoke($cmd, 'hello', 'world'));
    }
    
    /**
     * @test
     */
    public function testCommandHelp()
    {
        $cmd = new DataValidationCommand('test', null);
        $help = $cmd->getHelp();
        
        $this->assertStringContainsString('datavalidation', $help);
        $this->assertStringContainsString('run', $help);
        $this->assertStringContainsString('counts', $help);
        $this->assertStringContainsString('sample', $help);
    }
}

<?php
namespace Jivoo\Data\Query\Builders;

class SelectionBaseTest extends \Jivoo\TestCase
{
    protected $dataSource;
    
    protected function setUp()
    {
        $this->dataSource = $this->getMockBuilder('Jivoo\Data\RecordSource')->getMock();
    }
    
    protected function getInstance()
    {
        return $this->getMockForAbstractClass(
            'Jivoo\Data\Query\Builders\SelectionBase',
            [$this->dataSource]
        );
    }

    public function testPredicate()
    {
        $record = [];
        $selection = $this->getInstance();
        
        $this->assertNull($selection->getPredicate());
        
        $selection = $selection->where('true');
        $this->assertInstanceOf('Jivoo\Data\Query\Expression', $selection->getPredicate());
        $this->assertTrue($selection->getPredicate()->__invoke($record));
        
        $selection = $selection->and('false');
        $this->assertFalse($selection->getPredicate()->__invoke($record));
        
        $selection = $selection->or('true');
        $this->assertTrue($selection->getPredicate()->__invoke($record));
        
        $selection = $this->getInstance();
        $selection = $selection->or('%b', true);
        $this->assertTrue($selection->getPredicate()->__invoke($record));
    }
    
    public function testOrderBy()
    {
        $selection = $this->getInstance();
        
        $selection = $selection->orderBy('foo')->orderByDescending('bar');
        $this->assertEquals([['foo', false], ['bar', true]], $selection->getOrdering());
        
        $selection = $selection->reverseOrder();
        $this->assertEquals([['foo', true], ['bar', false]], $selection->getOrdering());
    }
    
    public function testToSelection()
    {
        $record = [];
        
        $selection = $this->getInstance()
            ->where('%b', true)
            ->orderBy('foo')
            ->orderByDescending('bar')
            ->limit(10);
        
        $copy = $selection->toSelection();
        $this->assertInstanceOf('Jivoo\Data\Query\Builders\SelectionBuilder', $copy);
        $this->assertTrue($copy->getPredicate()->__invoke($record));
        $this->assertEquals([['foo', false], ['bar', true]], $copy->getOrdering());
        $this->assertEquals(10, $copy->getLimit());
    }
}

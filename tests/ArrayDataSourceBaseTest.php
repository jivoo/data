<?php
namespace Jivoo\Data;

use Jivoo\Data\Query\Builders\DeleteSelectionBuilder;
use Jivoo\Data\Query\Builders\UpdateSelectionBuilder;
use Jivoo\Data\Query\Builders\ReadSelectionBuilder;
use Jivoo\Data\Query\E;

class ArrayDataSourceBaseTest extends \Jivoo\TestCase
{
    
    public function testRead()
    {
        $data = [
            new ArrayRecord(['id' => 1, 'name' => 'foo', 'group' => 'admin']),
            new ArrayRecord(['id' => 2, 'name' => 'bar', 'group' => 'user']),
            new ArrayRecord(['id' => 3, 'name' => 'foobar', 'group' => 'user']),
            new ArrayRecord(['id' => 4, 'name' => 'foo', 'group' => 'admin']),
            new ArrayRecord(['id' => 5, 'name' => 'baz', 'group' => 'user']),
            new ArrayRecord(['id' => 6, 'name' => 'foobaz', 'group' => 'user'])
        ];
        
        // Select all
        $source = new ArrayDataSource($data);
        $selection = new ReadSelectionBuilder($source);
        
        $this->assertEquals(6, count($selection->toArray()));
        $this->assertEquals($data, $selection->toArray());
        
        // Select with a predicate
        $source = new ArrayDataSource($data);
        $selection = new ReadSelectionBuilder($source);
        $selection = $selection->where('group = %s', 'user');
        
        $this->assertEquals(4, count($selection->toArray()));
        $this->assertNotContains($data[0], $selection->toArray());
        $this->assertContains($data[1], $selection->toArray());
        
        // Select with groups
        $source = new ArrayDataSource($data);
        $selection = new ReadSelectionBuilder($source);
        $selection = $selection->groupBy(['group']);
        
        $this->assertEquals(2, count($selection->toArray()));
        
        $source = new ArrayDataSource($data);
        $selection = new ReadSelectionBuilder($source);
        $selection = $selection->groupBy(['group', 'name'], E::e('name in %s()', ['foo', 'bar', 'foobar']));
        
        $this->assertEquals(3, count($selection->toArray()));
        
        // Select with limit
        $source = new ArrayDataSource($data);
        $selection = new ReadSelectionBuilder($source);
        $selection = $selection->limit(1);
        
        $this->assertEquals(1, count($selection->toArray()));
        $this->assertContains($data[0], $selection->toArray());
        
        // Select with projection
        $source = new ArrayDataSource($data);
        $selection = new ReadSelectionBuilder($source);
        $projection = $selection->select(['n' => 'name']);

        $this->assertEquals(6, iterator_count($projection));
        foreach ($projection as $record) {
            $this->assertEquals(['n'], array_keys($record->getData()));
        }
        
    }
    
    public function testUpdate()
    {
        $data = [
            new ArrayRecord(['id' => 1, 'name' => 'foo']),
            new ArrayRecord(['id' => 2, 'name' => 'bar']),
            new ArrayRecord(['id' => 3, 'name' => 'foobar'])
        ];
        
        // Update all
        $source = new ArrayDataSource($data);
        $selection = new UpdateSelectionBuilder($source);
        $selection->set('name', 'baz')->update();
        
        foreach ($source->getData() as $record) {
            $this->assertEquals('baz', $record->name);
        }
    }

    public function testDelete()
    {
        $data = [
            new ArrayRecord(['id' => 1, 'name' => 'foo']),
            new ArrayRecord(['id' => 2, 'name' => 'bar']),
            new ArrayRecord(['id' => 3, 'name' => 'foobar'])
        ];
        
        // Delete all
        $source = new ArrayDataSource($data);
        $selection = new DeleteSelectionBuilder($source);
        $selection->delete();
        
        $this->assertEmpty($source->getData());

        // Delete with a predicate
        $source = new ArrayDataSource($data);
        $selection = new DeleteSelectionBuilder($source);
        $selection->where('id <= %i', 2)->delete();

        $this->assertEquals(1, count($source->getData()));
        $this->assertArrayHasKey(2, $source->getData());
        $this->assertEquals([2 => $data[2]], $source->getData());
        
        // Delete with a limit
        $source = new ArrayDataSource($data);
        $selection = new DeleteSelectionBuilder($source);
        $selection->limit(1)->delete();
        
        $this->assertEquals(2, count($source->getData()));
        $this->assertEquals([1 => $data[1], 2 => $data[2]], $source->getData());
        
        // Delete sorted with a limit
        $source = new ArrayDataSource($data);
        $selection = new DeleteSelectionBuilder($source);
        $selection->orderByDescending('id')->limit(1)->delete();
        
        $this->assertEquals(2, count($source->getData()));
        $this->assertEquals([0 => $data[0], 1 => $data[1]], $source->getData());
    }
    
    public function testSort()
    {
        $data = [
            new ArrayRecord(['a' => 4, 'b' => 'b']),
            new ArrayRecord(['a' => 6, 'b' => 'be']),
            new ArrayRecord(['a' => 1, 'b' => 'a']),
            new ArrayRecord(['a' => -4, 'b' => 'c']),
            new ArrayRecord(['a' => 3, 'b' => 'd']),
            new ArrayRecord(['a' => 3, 'b' => 'd'])
        ];

        $expected1 = [
            new ArrayRecord(['a' => -4, 'b' => 'c']),
            new ArrayRecord(['a' => 1, 'b' => 'a']),
            new ArrayRecord(['a' => 3, 'b' => 'd']),
            new ArrayRecord(['a' => 3, 'b' => 'd']),
            new ArrayRecord(['a' => 4, 'b' => 'b']),
            new ArrayRecord(['a' => 6, 'b' => 'be'])
        ];

        $expected2 = [
            new ArrayRecord(['a' => 6, 'b' => 'be']),
            new ArrayRecord(['a' => 4, 'b' => 'b']),
            new ArrayRecord(['a' => 3, 'b' => 'd']),
            new ArrayRecord(['a' => 3, 'b' => 'd']),
            new ArrayRecord(['a' => 1, 'b' => 'a']),
            new ArrayRecord(['a' => -4, 'b' => 'c'])
        ];

        $expected3 = [
            new ArrayRecord(['a' => 1, 'b' => 'a']),
            new ArrayRecord(['a' => 4, 'b' => 'b']),
            new ArrayRecord(['a' => 6, 'b' => 'be']),
            new ArrayRecord(['a' => -4, 'b' => 'c']),
            new ArrayRecord(['a' => 3, 'b' => 'd']),
            new ArrayRecord(['a' => 3, 'b' => 'd'])
        ];

        $expected4 = [
            new ArrayRecord(['a' => 3, 'b' => 'd']),
            new ArrayRecord(['a' => 3, 'b' => 'd']),
            new ArrayRecord(['a' => -4, 'b' => 'c']),
            new ArrayRecord(['a' => 6, 'b' => 'be']),
            new ArrayRecord(['a' => 4, 'b' => 'b']),
            new ArrayRecord(['a' => 1, 'b' => 'a'])
        ];
        
        $this->assertEquals($expected1, ArrayDataSource::sort($data, 'a', false, false));
        $this->assertEquals($expected2, ArrayDataSource::sort($data, 'a', true, false));
        $this->assertEquals($expected3, ArrayDataSource::sort($data, 'b', false, false));
        $this->assertEquals($expected4, ArrayDataSource::sort($data, 'b', true, false));
        
        // maintain key association
        
        $data = [
            0 => new ArrayRecord(['a' => 4]),
            1 => new ArrayRecord(['a' => 6]),
            2 => new ArrayRecord(['a' => 1])
        ];
        
        $expected = [
            2 => new ArrayRecord(['a' => 1]),
            0 => new ArrayRecord(['a' => 4]),
            1 => new ArrayRecord(['a' => 6])
        ];
        
        $this->assertEquals($expected, ArrayDataSource::sort($data, 'a', false, true));
    }

    public function testSorAll()
    {
        $data = [
            new ArrayRecord(['a' => 4, 'b' => 'b']),
            new ArrayRecord(['a' => 6, 'b' => 'be']),
            new ArrayRecord(['a' => 1, 'b' => 'a']),
            new ArrayRecord(['a' => 1, 'b' => 'c']),
            new ArrayRecord(['a' => 3, 'b' => 'd']),
            new ArrayRecord(['a' => 3, 'b' => 'd'])
        ];

        $expected1 = [
            new ArrayRecord(['a' => 1, 'b' => 'a']),
            new ArrayRecord(['a' => 1, 'b' => 'c']),
            new ArrayRecord(['a' => 3, 'b' => 'd']),
            new ArrayRecord(['a' => 3, 'b' => 'd']),
            new ArrayRecord(['a' => 4, 'b' => 'b']),
            new ArrayRecord(['a' => 6, 'b' => 'be'])
        ];

        $expected2 = [
            new ArrayRecord(['a' => 1, 'b' => 'c']),
            new ArrayRecord(['a' => 1, 'b' => 'a']),
            new ArrayRecord(['a' => 3, 'b' => 'd']),
            new ArrayRecord(['a' => 3, 'b' => 'd']),
            new ArrayRecord(['a' => 4, 'b' => 'b']),
            new ArrayRecord(['a' => 6, 'b' => 'be'])
        ];

        $expected3 = [
            new ArrayRecord(['a' => 6, 'b' => 'be']),
            new ArrayRecord(['a' => 4, 'b' => 'b']),
            new ArrayRecord(['a' => 3, 'b' => 'd']),
            new ArrayRecord(['a' => 3, 'b' => 'd']),
            new ArrayRecord(['a' => 1, 'b' => 'a']),
            new ArrayRecord(['a' => 1, 'b' => 'c'])
        ];

        $expected4 = [
            new ArrayRecord(['a' => 6, 'b' => 'be']),
            new ArrayRecord(['a' => 4, 'b' => 'b']),
            new ArrayRecord(['a' => 3, 'b' => 'd']),
            new ArrayRecord(['a' => 3, 'b' => 'd']),
            new ArrayRecord(['a' => 1, 'b' => 'c']),
            new ArrayRecord(['a' => 1, 'b' => 'a'])
        ];
        
        $this->assertEquals($expected1, ArrayDataSource::sortAll($data, [['a', false], ['b', false]], false));
        $this->assertEquals($expected2, ArrayDataSource::sortAll($data, [['a', false], ['b', true]], false));
        $this->assertEquals($expected3, ArrayDataSource::sortAll($data, [['a', true], ['b', false]], false));
        $this->assertEquals($expected4, ArrayDataSource::sortAll($data, [['a', true], ['b', true]], false));
        
        // maintain key association
        
        $data = [
            0 => new ArrayRecord(['a' => 4, 'b' => 'd']),
            1 => new ArrayRecord(['a' => 1, 'b' => 'e']),
            2 => new ArrayRecord(['a' => 1, 'b' => 'a'])
        ];
        
        $expected = [
            2 => new ArrayRecord(['a' => 1, 'b' => 'a']),
            1 => new ArrayRecord(['a' => 1, 'b' => 'e']),
            0 => new ArrayRecord(['a' => 4, 'b' => 'd'])
        ];

        $this->assertEquals($expected, ArrayDataSource::sortAll($data, [['a', false], ['b', false]], true));
    }
}

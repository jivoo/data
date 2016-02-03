<?php
namespace Jivoo\Data;

class ArrayDataSourceTest extends \Jivoo\TestCase
{

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
        
        $this->assertEquals($expected1, ArrayDataSource::sort($data, 'a'));
        $this->assertEquals($expected2, ArrayDataSource::sort($data, 'a', true));
        $this->assertEquals($expected3, ArrayDataSource::sort($data, 'b'));
        $this->assertEquals($expected4, ArrayDataSource::sort($data, 'b', true));
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
        
        $this->assertEquals($expected1, ArrayDataSource::sortAll($data, [['a', false], ['b', false]]));
        $this->assertEquals($expected2, ArrayDataSource::sortAll($data, [['a', false], ['b', true]]));
        $this->assertEquals($expected3, ArrayDataSource::sortAll($data, [['a', true], ['b', false]]));
        $this->assertEquals($expected4, ArrayDataSource::sortAll($data, [['a', true], ['b', true]]));
    }
}
<?php

namespace Jivoo\Data\Database\Common;

use Jivoo\Data\DataType;
use Jivoo\Json;

class MysqlTypeAdapterTest extends SqlTestBase
{

    public function testEncode()
    {
        $db = $this->getDb();
        $adapter = new MysqlTypeAdapter($db);

        $this->assertSame('NULL', $adapter->encode(DataType::integer(), null));
        $this->assertSame(15, $adapter->encode(DataType::integer(), '15'));
        $this->assertSame(33.0, $adapter->encode(DataType::float(), 33));
        $this->assertSame(1, $adapter->encode(DataType::boolean(), true));
        $this->assertSame(0, $adapter->encode(DataType::boolean(), false));
        $this->assertRegExp('/^"\d{4}-\d{2}-\d{2}"$/', $adapter->encode(DataType::date(), time()));
        $this->assertRegExp(
            '/^"\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}"$/',
            $adapter->encode(DataType::dateTime(), time())
        );
        
        $this->assertSame('"foo bar baz"', $adapter->encode(DataType::string(), 'foo bar baz'));
        $this->assertSame('"[]"', $adapter->encode(DataType::object(), []));
    }
    
    public function testDecode()
    {
        $db = $this->getDb();
        $adapter = new MysqlTypeAdapter($db);
        
        $this->assertNull($adapter->decode(DataType::integer(), null));
        $this->assertSame(15, $adapter->decode(DataType::integer(), '15'));
        $this->assertSame(33.0, $adapter->decode(DataType::float(), 33));
        $this->assertSame(true, $adapter->decode(DataType::boolean(), 1));
        $this->assertSame(false, $adapter->decode(DataType::boolean(), 0));
        $time = time();
        $encoded = $adapter->encode(DataType::dateTime(), $time);
        $this->assertTrue(preg_match('/^"(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})"$/', $encoded, $matches) === 1);
        $this->assertEquals($time, $adapter->decode(DataType::dateTime(), $matches[1]));
        $this->assertSame('foo bar baz', $adapter->decode(DataType::string(), 'foo bar baz'));
        $this->assertSame([], $adapter->decode(DataType::object(), '[]'));
    }
}

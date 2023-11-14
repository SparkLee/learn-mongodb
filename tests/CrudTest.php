<?php

use MongoDB\BSON\ObjectId;
use MongoDB\Client;
use MongoDB\Collection;
use MongoDB\Database;
use MongoDB\Driver\Cursor;
use MongoDB\Model\BSONDocument;
use PHPUnit\Framework\TestCase;

/**
 * @see https://www.mongodb.com/docs/php-library/current/tutorial/crud
 */
class CrudTest extends TestCase
{
    private Client $client;
    private Database $db;
    private Collection $collection;

    protected function setUp(): void
    {
        $this->client = new Client('mongodb://127.0.0.1:27017');
        $this->db = $this->client->test;
        // $this->db=$this->client->selectDatabase('test');
        $this->collection = $this->db->users;
    }

    public function testCommand()
    {
        $cursor = $this->db->command(['ping' => 1]);
        self::assertInstanceOf(Cursor::class, $cursor);
    }

    public function testInsertOne()
    {
        // 不指定 _id，由 mongodb 自动生成
        $insertOneResult = $this->collection->insertOne([
            'username' => 'admin',
            'email' => 'admin@example.com',
            'name' => 'Admin User',
        ]);
        self::assertSame(1, $insertOneResult->getInsertedCount());
        self::assertInstanceOf(ObjectId::class, $insertOneResult->getInsertedId());
        // var_dump($insertOneResult->getInsertedId());

        // 指定 _id
        $this->collection->deleteOne(['_id' => 666]);
        $insertOneResult2 = $this->collection->insertOne([
            '_id' => 666,
            'username' => 'admin'
        ]);
        self::assertSame(1, $insertOneResult2->getInsertedCount());
        self::assertSame(666, $insertOneResult2->getInsertedId());
    }

    public function testInsertMany()
    {
        $insertManyResult = $this->collection->insertMany([
            ['username' => 'admin', 'email' => 'admin@example.com', 'name' => 'Admin User'],
            ['username' => 'user', 'email' => 'user@example.com', 'name' => 'User User'],
        ]);
        self::assertSame(2, $insertManyResult->getInsertedCount());
        self::assertinstanceof(ObjectId::class, $insertManyResult->getInsertedIds()[0]);
        self::assertinstanceof(ObjectId::class, $insertManyResult->getInsertedIds()[1]);
        // var_dump($insertManyResult->getInsertedIds());
    }

    public function testFindOne()
    {
        $this->collection->deleteMany(['username' => 'admin']);
        $this->collection->insertOne([
            'username' => 'admin',
            'email' => 'admin@example.com',
            'name' => 'Admin User',
        ]);

        $findOneResult = $this->collection->findOne(['username' => 'admin']);
        self::assertInstanceOf(BSONDocument::class, $findOneResult);
        self::assertInstanceOf(ObjectId::class, $findOneResult['_id']);
        self::assertSame('admin', $findOneResult['username']);
        self::assertSame('admin@example.com', $findOneResult->email);
        self::assertSame('Admin User', $findOneResult->name);

        self::assertNull($this->collection->findOne(['username' => '__non_exists__']));
    }

    public function testFind()
    {
        $this->collection->drop();
        $this->collection->insertMany([
            ['username' => 'admin', 'email' => 'admin@example.com', 'age' => 18],
            ['username' => 'user', 'email' => 'user@example.com', 'age' => 18],
            ['username' => 'man', 'email' => 'man@example.com', 'age' => 35],
        ]);

        $cursor = $this->collection->find(['age' => 18]);

        // MongoDB\Driver\Exception\LogicException: Cursors cannot rewind after starting iteration
        // self::assertCount(2, $cursor);

        // MongoDB\Driver\Exception\LogicException: Cursors cannot rewind after starting iteration
        // self::assertSame('admin', $cursor->toArray()[0]->username);

        foreach ($cursor as $document) {
            self::assertSame(18, $document['age']);
            self::assertTrue(in_array($document->username, ['admin', 'user', 'man']));
        }
    }

    /**
     * By default, queries in MongoDB return all fields in matching documents.
     * To limit the amount of data that MongoDB sends to applications, you can
     * include a projection document in the query operation.
     */
    public function testQueryProjection()
    {
        $this->collection->drop();
        $this->collection->insertMany([
            ['username' => 'admin', 'email' => 'admin@example.com', 'age' => 18],
            ['username' => 'user', 'email' => 'user@example.com', 'age' => 18],
            ['username' => 'man', 'email' => 'man@example.com', 'age' => 18],
        ]);

        $cursor = $this->collection->find(
            ['age' => 18],
            [
                // 待返回的字段（类似 sql 中的 fields）
                'projection' => [
                    'username' => 1,
                    'age' => 1,
                ],
                // 排序（类似 sql 中的 order by）
                'sort' => ['username' => -1],
                // 待返回的第一条记录的偏移索引（类似 sql 中的 offset）
                'skip' => 2,
                // 最大返回记录数（类似 sql 中的 limit）
                'limit' => 2,
            ]
        );
        // var_dump($cursor->toArray());
        self::assertCount(1, $cursor->toArray());
    }

    public function testRegularExpressions()
    {
        $this->collection->drop();
        $this->collection->insertMany([
            ['username' => 'admin', 'email' => 'foo-admin@example.com', 'age' => 18],
            ['username' => '(man)1', 'email' => 'foo-@baidu.com', 'age' => 18],
            ['username' => '(man)2', 'email' => 'man@example.com', 'age' => 18],
        ]);

        $cursor = $this->collection->find([
            // 查询 email 以 'foo-' 开头的文档
            'email' => new \MongoDB\BSON\Regex('^foo-', 'i'),
        ]);
        // var_dump($cursor->toArray());
        self::assertCount(2, $cursor->toArray());

        $cursor2 = $this->collection->find([
            'email' => ['$regex' => '^foo-', '$options' => 'i'],
        ]);
        // var_dump($cursor2->toArray());
        self::assertCount(2, $cursor2->toArray());

        $cursor3 = $this->collection->find([
            // 'username' => new \MongoDB\BSON\Regex('^\(man\)', 'i'),
            // 使用 preg_quote() 做转义
            'username' => new \MongoDB\BSON\Regex('^' . preg_quote('(man)'), 'i'),
        ]);
        // var_dump($cursor3->toArray());
        self::assertCount(2, $cursor3->toArray());
    }

    public function testComplexQueriesWithAggregation()
    {
        $this->collection->drop();
        $this->collection->insertMany([
            ['username' => 'admin', 'email' => 'foo-admin@example.com', 'age' => 18],
            ['username' => '(man)1', 'email' => 'foo-@baidu.com', 'age' => 18],
            ['username' => '(man)2', 'email' => 'man@example.com', 'age' => 18],
            ['username' => '(man)3', 'email' => 'man@example.com', 'age' => 35],
        ]);

        $cursor = $this->collection->aggregate([
            ['$group' => ['_id' => '$age', 'user_count' => ['$sum' => 1]]],
            ['$sort' => ['user_count' => -1]],
            ['$limit' => 2],
        ]);
        $arr = $cursor->toArray();
        self::assertSame(18, $arr[0]['_id']);
        self::assertSame(3, $arr[0]['user_count']);
        self::assertSame(35, $arr[1]->_id);
        self::assertSame(1, $arr[1]->user_count);
    }

    public function testUpdateOne()
    {
        $this->collection->drop();
        $this->collection->insertMany([
            ['username' => 'admin', 'email' => 'foo-admin@example.com', 'age' => 10],
            ['username' => 'user', 'email' => 'man@example.com', 'age' => 30],
        ]);

        $updateResult = $this->collection->updateOne(
            ['username' => 'admin'],
            ['$set' => ['age' => 11]],
        );
        // var_dump($updateResult);
        self::assertSame(1, $updateResult->getMatchedCount());
        self::assertSame(1, $updateResult->getModifiedCount());

        $updateResult = $this->collection->updateOne(
            ['username' => 'admin'],
            ['$set' => ['age' => 11]],
        );
        // var_dump($updateResult);
        self::assertSame(1, $updateResult->getMatchedCount());
        self::assertSame(0, $updateResult->getModifiedCount()); // 若新旧值相同，则不更新
    }

    public function testUpdateMany()
    {
        $this->collection->drop();
        $this->collection->insertMany([
            ['username' => 'u1', 'email' => 'foo1@example.com', 'age' => 18],
            ['username' => 'u2', 'email' => 'foo2@baidu.com', 'age' => 18],
            ['username' => 'u3', 'age' => 18],
        ]);

        $updateResult = $this->collection->updateMany(
            ['age' => 18],
            ['$set' => ['email' => 'foo1@example.com']],
        );
        self::assertSame(3, $updateResult->getMatchedCount());
        self::assertSame(2, $updateResult->getModifiedCount()); // 若新旧值相同，则不更新
    }

    /**
     * Replacement operations are similar to update operations, but instead of updating a document
     * to include new fields or new field values, a replacement operation replaces the entire document
     * with a new document, but retains the original document’s _id value.
     *
     * @see https://www.mongodb.com/docs/php-library/current/tutorial/crud/#replace-documents
     */
    public function testReplace()
    {
        $this->collection->drop();
        $this->collection->insertMany([
            ['username' => 'u1', 'email' => 'foo1@example.com', 'age' => 18],
            ['username' => 'u2', 'email' => 'foo2@baidu.com', 'age' => 18],
        ]);

        $updateResult = $this->collection->replaceOne(
            ['age' => 18],
            ['name' => 'foo']
        );
        self::assertSame(1, $updateResult->getMatchedCount()); // 仅匹配到1条，而非2条
        self::assertSame(1, $updateResult->getModifiedCount());
    }

    public function testUpsert()
    {
        $this->collection->drop();
        $this->collection->insertMany([
            ['username' => 'admin', 'email' => 'admin@example.com', 'age' => 10],
        ]);

        $updateResult = $this->collection->updateOne(
            ['username' => 'admin'],
            ['$set' => ['age' => 18]],
            ['upsert' => true],
        );
        self::assertSame(1, $updateResult->getMatchedCount());
        self::assertSame(1, $updateResult->getModifiedCount());
        // 记录存在则直接更新，不会新增记录
        self::assertSame(0, $updateResult->getUpsertedCount());
        self::assertSame(null, $updateResult->getUpsertedId());

        $updateResult = $this->collection->updateOne(
            ['username' => 'sparklee'],
            ['$set' => ['name' => '李威', 'age' => 18]],
            ['upsert' => true],
        );
        self::assertSame(0, $updateResult->getMatchedCount());
        self::assertSame(0, $updateResult->getModifiedCount());
        // 记录不存在则新增记录
        self::assertSame(1, $updateResult->getUpsertedCount());
        self::assertInstanceOf(ObjectId::class, $updateResult->getUpsertedId());
    }

    public function testDeleteOne()
    {
        $this->collection->drop();
        $this->collection->insertMany([
            ['username' => 'admin', 'email' => 'admin@example.com', 'age' => 10],
        ]);

        $deleteResult = $this->collection->deleteOne(['username' => 'admin']);
        self::assertSame(1, $deleteResult->getDeletedCount());

        $deleteResult = $this->collection->deleteOne(['username' => 'admin']);
        self::assertSame(0, $deleteResult->getDeletedCount());
    }

    public function testDeleteMany()
    {
        $this->collection->drop();
        $this->collection->insertMany([
            ['username' => 'admin', 'email' => 'admin@example.com', 'age' => 10],
            ['username' => 'sparklee', 'email' => 'sparklee@example.com', 'age' => 10],
        ]);

        $deleteResult = $this->collection->deleteMany(['age' => 10]);
        self::assertSame(2, $deleteResult->getDeletedCount());
    }
}
